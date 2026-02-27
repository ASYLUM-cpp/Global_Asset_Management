<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Models\TaxonomyRule;
use App\Services\TaxonomyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * ApplyTaxonomy — post-AI normalization and validation.
 *
 * 1. Normalizes AI-generated tags via synonym rules (945 mappings from GAM XLSX).
 * 2. Validates every tag against the controlled vocabulary (taxonomy_terms table).
 * 3. Flags uncontrolled terms for steward review.
 * 4. Validates/corrects group classification using tag-based voting + taxonomy_terms.
 * 5. Deduplicates tags after normalization.
 *
 * Per REQ-13: Synonym-based normalization (raw_term → canonical_term, cached 24h).
 * Per REQ-12: Group must be one of the 7 visual groups or 8 doc groups.
 */
class ApplyTaxonomy implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 30;

    public function __construct(
        public Asset $asset
    ) {}

    public function handle(): void
    {
        $asset = $this->asset;
        $taxonomyService = app(TaxonomyService::class);

        try {
            $rules = $this->getTaxonomyRules();

            // Phase 1: Normalize tags via synonyms
            $this->normalizeTags($asset, $rules);

            // Phase 2: Validate tags against controlled vocabulary
            $this->validateTags($asset, $taxonomyService);

            // Phase 3: Validate/correct group classification
            $this->validateGroupClassification($asset, $rules, $taxonomyService);

            // Phase 4: Deduplicate tags
            $this->deduplicateTags($asset);

            Log::info("ApplyTaxonomy: Completed", [
                'asset' => $asset->id,
                'group' => $asset->group_classification,
            ]);

        } catch (\Throwable $e) {
            Log::error("ApplyTaxonomy: Failed", [
                'asset' => $asset->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Get all taxonomy rules, cached for 24 hours.
     */
    private function getTaxonomyRules(): array
    {
        return Cache::remember('taxonomy_rules_all', 86400, function () {
            return TaxonomyRule::where('is_active', true)
                ->get()
                ->mapWithKeys(fn ($rule) => [
                    strtolower($rule->raw_term) => [
                        'canonical' => $rule->canonical_term,
                        'group'     => $rule->group_hint,
                    ],
                ])
                ->toArray();
        });
    }

    /**
     * Normalize tag names using synonym rules.
     */
    private function normalizeTags(Asset $asset, array $rules): void
    {
        $tags = $asset->tags()->get();
        $normalized = 0;

        foreach ($tags as $tag) {
            $lower = strtolower($tag->tag);

            if (isset($rules[$lower])) {
                $canonical = $rules[$lower]['canonical'];
                if ($canonical && strtolower($canonical) !== $lower) {
                    $tag->update(['tag' => strtolower($canonical)]);
                    $normalized++;
                }
            }
        }

        if ($normalized > 0) {
            Log::info("ApplyTaxonomy: Normalized {$normalized} tags", ['asset' => $asset->id]);
        }
    }

    /**
     * Validate each tag against the controlled vocabulary.
     * Mark uncontrolled tags as not auto_approved and flag for review.
     */
    private function validateTags(Asset $asset, TaxonomyService $taxonomyService): void
    {
        $tags = $asset->tags()->get();
        $uncontrolled = [];

        foreach ($tags as $tag) {
            $isControlled = $taxonomyService->isControlledTerm($tag->tag);

            if (!$isControlled) {
                // Try fuzzy matching
                $closest = $taxonomyService->findClosestTerm($tag->tag);

                if ($closest && $closest[2] >= 0.85) {
                    // Auto-correct to closest controlled term
                    $tag->update(['tag' => strtolower($closest[0])]);
                    Log::info("ApplyTaxonomy: Fuzzy-corrected tag", [
                        'asset' => $asset->id,
                        'from'  => $tag->tag,
                        'to'    => $closest[0],
                        'score' => $closest[2],
                    ]);
                } else {
                    // Mark as not approved — unknown term
                    $tag->update(['auto_approved' => false]);
                    $uncontrolled[] = $tag->tag;
                }
            }
        }

        if (!empty($uncontrolled)) {
            Log::info("ApplyTaxonomy: {$asset->id} has uncontrolled terms", [
                'terms' => array_slice($uncontrolled, 0, 10),
            ]);

            // Flag for review if many uncontrolled terms
            if (count($uncontrolled) >= 3) {
                $asset->update([
                    'review_status' => 'pending',
                    'review_reason' => 'Multiple uncontrolled terms: ' . implode(', ', array_slice($uncontrolled, 0, 5)),
                ]);
            }
        }
    }

    /**
     * Remove duplicate tags (keep highest confidence).
     */
    private function deduplicateTags(Asset $asset): void
    {
        $tags = $asset->tags()->orderByDesc('confidence')->get();
        $seen = [];

        foreach ($tags as $tag) {
            $key = strtolower($tag->tag);
            if (isset($seen[$key])) {
                $tag->delete();
            } else {
                $seen[$key] = true;
            }
        }
    }

    /**
     * Validate or correct group classification.
     * Uses tag-based voting against taxonomy_rules group_hint values.
     */
    private function validateGroupClassification(Asset $asset, array $rules, TaxonomyService $taxonomyService): void
    {
        $ext = strtolower($asset->file_extension ?? '');
        $validGroups = $taxonomyService->getValidGroupCodes($ext);
        $currentGroup = $asset->group_classification;

        // If current group is valid and confidence is high, keep it
        if (in_array($currentGroup, $validGroups) && ($asset->group_confidence ?? 0) >= 0.80) {
            return;
        }

        // Count group hints from taxonomy rules for this asset's tags
        $groupVotes = [];
        $tags = $asset->tags()->pluck('tag')->toArray();

        foreach ($tags as $tagName) {
            $lower = strtolower($tagName);
            if (isset($rules[$lower]) && $rules[$lower]['group']) {
                $group = $rules[$lower]['group'];
                $groupVotes[$group] = ($groupVotes[$group] ?? 0) + 1;
            }
        }

        if (!empty($groupVotes)) {
            arsort($groupVotes);
            $topGroup = array_key_first($groupVotes);
            $topVotes = $groupVotes[$topGroup];
            $totalVotes = array_sum($groupVotes);

            $voteConfidence = $totalVotes > 0 ? round($topVotes / $totalVotes, 2) : 0;

            if ($voteConfidence >= 0.50 && in_array($topGroup, $validGroups)) {
                if (!$currentGroup || ($asset->group_confidence ?? 0) < 0.60) {
                    $asset->update([
                        'group_classification' => $topGroup,
                        'group_confidence'     => max($asset->group_confidence ?? 0, $voteConfidence),
                    ]);

                    Log::info("ApplyTaxonomy: Group corrected via voting", [
                        'asset' => $asset->id,
                        'from'  => $currentGroup,
                        'to'    => $topGroup,
                        'votes' => $groupVotes,
                    ]);
                }
            }
        }

        // Ensure group is always valid
        $asset->refresh();
        if (!in_array($asset->group_classification, $validGroups)) {
            $isDoc = in_array($ext, ['pdf', 'doc', 'docx', 'pptx', 'xlsx', 'xls', 'txt', 'csv', 'rtf']);
            $defaultGroup = $isDoc ? 'DOC-OPS' : 'SPEC';

            $asset->update([
                'group_classification' => $defaultGroup,
                'group_confidence'     => 0.20,
                'review_status'        => 'pending',
            ]);
        }
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("ApplyTaxonomy: All retries exhausted", [
            'asset' => $this->asset->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
