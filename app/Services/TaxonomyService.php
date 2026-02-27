<?php

namespace App\Services;

use App\Models\TaxonomyTerm;
use App\Models\TaxonomyRule;
use Illuminate\Support\Facades\Cache;

/**
 * TaxonomyService — builds DeepSeek prompt context from the controlled vocabulary.
 *
 * Key methods:
 *   buildVisualPromptContext()  → for images/videos — uses 7 primary groups + keywords
 *   buildDocumentPromptContext() → for documents — uses 8 doc groups + doc keywords
 *   normalizeTag()              → maps a raw tag to its canonical controlled term
 *   isControlledTerm()          → checks if a tag exists in the vocabulary
 *
 * The prompt context is cached for 24 hours and invalidated when taxonomy is re-seeded.
 */
class TaxonomyService
{
    /**
     * Build prompt context for VISUAL assets (images, videos, vectors).
     * Includes the 7 primary groups with their full keyword hierarchies.
     */
    public function buildVisualPromptContext(): string
    {
        return Cache::remember('taxonomy:prompt:visual', 86400, function () {
            $groups = TaxonomyTerm::getPrimaryGroups();
            $lines = ["TAXONOMY — VISUAL ASSET GROUPS (choose exactly one primary_group):\n"];

            foreach ($groups as $group) {
                $code = $group['group_code'];
                $label = $group['term_label'];
                $desc = $group['description'];
                $lines[] = "## {$code} — {$label}";
                $lines[] = "   {$desc}";

                // Projects for this group
                $projects = TaxonomyTerm::active()
                    ->ofType('project')
                    ->forGroup($code)
                    ->orderBy('sort_order')
                    ->get();

                if ($projects->isNotEmpty()) {
                    $projList = $projects->pluck('term_label')->implode(', ');
                    $lines[] = "   Projects: {$projList}";
                }

                // Keywords grouped by category
                $categories = TaxonomyTerm::active()
                    ->ofType('keyword_category')
                    ->forGroup($code)
                    ->orderBy('sort_order')
                    ->get();

                foreach ($categories as $cat) {
                    $catCode = $cat['term_code'];
                    $catLabel = $cat['term_label'];

                    $keywords = TaxonomyTerm::active()
                        ->ofType('keyword')
                        ->forGroup($code)
                        ->where('parent_code', $catCode)
                        ->orderBy('sort_order')
                        ->pluck('term_label')
                        ->toArray();

                    // Also pick up leaf terms with this facet
                    $leafTerms = TaxonomyTerm::active()
                        ->ofType('keyword')
                        ->forGroup($code)
                        ->where('facet', $catLabel)
                        ->whereNull('parent_code')
                        ->orderBy('sort_order')
                        ->pluck('term_label')
                        ->toArray();

                    $allTerms = array_unique(array_merge($keywords, $leafTerms));

                    if (! empty($allTerms)) {
                        $termList = implode(', ', array_slice($allTerms, 0, 30)); // cap for prompt size
                        $lines[] = "   {$catLabel}: {$termList}";
                    }
                }

                $lines[] = '';
            }

            return implode("\n", $lines);
        });
    }

    /**
     * Build prompt context for DOCUMENT assets (PDF, DOCX, XLSX, etc.).
     * Includes the 8 doc groups with their facets and controlled keywords.
     */
    public function buildDocumentPromptContext(): string
    {
        return Cache::remember('taxonomy:prompt:document', 86400, function () {
            $docGroups = TaxonomyTerm::getDocGroups();
            $lines = ["TAXONOMY — DOCUMENT GROUPS (choose exactly one doc_group):\n"];

            foreach ($docGroups as $dg) {
                $code = $dg['group_code'];
                $label = $dg['term_label'];
                $desc = $dg['description'];
                $extra = $dg['extra'] ?? [];

                $lines[] = "## {$code} — {$label}";
                $lines[] = "   {$desc}";

                if (! empty($extra['file_types'])) {
                    $lines[] = "   Typical files: {$extra['file_types']}";
                }
                if (! empty($extra['access_level'])) {
                    $lines[] = "   Access: {$extra['access_level']}";
                }
                if (! empty($extra['ai_processing'])) {
                    $lines[] = "   AI processing: {$extra['ai_processing']}";
                }

                // Doc keywords grouped by facet
                $docKeywords = TaxonomyTerm::active()
                    ->ofType('doc_keyword')
                    ->forGroup($code)
                    ->orderBy('sort_order')
                    ->get()
                    ->groupBy('facet');

                foreach ($docKeywords as $facet => $terms) {
                    $termLabels = $terms->pluck('term_label')->implode(', ');
                    $lines[] = "   {$facet}: {$termLabels}";
                }

                $lines[] = '';
            }

            return implode("\n", $lines);
        });
    }

    /**
     * Build a combined prompt context for geo terms (used alongside visual/doc context).
     */
    public function buildGeoContext(): string
    {
        return Cache::remember('taxonomy:prompt:geo', 86400, function () {
            $regions = TaxonomyTerm::active()
                ->ofType('geo_state')
                ->orderBy('sort_order')
                ->get()
                ->groupBy(fn ($t) => $t->extra['region_primary'] ?? 'Other');

            $lines = ["GEO REGIONS (include if location is visible/relevant):"];

            foreach ($regions as $region => $states) {
                $stateList = $states->pluck('term_code')->implode(', ');
                $lines[] = "  {$region}: {$stateList}";
            }

            return implode("\n", $lines);
        });
    }

    /**
     * Build a compact taxonomy context — flattened keywords per group on one line each.
     * Cuts prompt size by ~60% vs the verbose version.
     */
    public function buildCompactVisualContext(): string
    {
        return Cache::remember('taxonomy:prompt:visual:compact', 86400, function () {
            $groups = TaxonomyTerm::getPrimaryGroups();
            $lines = [];

            foreach ($groups as $group) {
                $code = $group['group_code'];
                $label = $group['term_label'];

                // Gather ALL keywords for this group (flatten categories)
                $keywords = TaxonomyTerm::active()
                    ->whereIn('term_type', ['keyword', 'viz_keyword'])
                    ->forGroup($code)
                    ->orderBy('sort_order')
                    ->pluck('term_label')
                    ->unique()
                    ->take(40)
                    ->implode(', ');

                $lines[] = "{$code} ({$label}): {$keywords}";
            }

            return implode("\n", $lines);
        });
    }

    /**
     * Build a compact document taxonomy context.
     */
    public function buildCompactDocContext(): string
    {
        return Cache::remember('taxonomy:prompt:doc:compact', 86400, function () {
            $docGroups = TaxonomyTerm::getDocGroups();
            $lines = [];

            foreach ($docGroups as $dg) {
                $code = $dg['group_code'];
                $label = $dg['term_label'];

                $keywords = TaxonomyTerm::active()
                    ->whereIn('term_type', ['doc_keyword', 'keyword'])
                    ->forGroup($code)
                    ->orderBy('sort_order')
                    ->pluck('term_label')
                    ->unique()
                    ->take(30)
                    ->implode(', ');

                $lines[] = "{$code} ({$label}): {$keywords}";
            }

            return implode("\n", $lines);
        });
    }

    /**
     * Build the full system prompt for the AI model based on asset type.
     * Uses compact taxonomy format for fast inference.
     */
    public function buildSystemPrompt(string $assetType, float $confidenceThreshold = 0.70): string
    {
        $isDoc = in_array($assetType, ['pdf', 'doc', 'docx', 'pptx', 'xlsx', 'xls', 'txt', 'csv', 'rtf']);

        if ($isDoc) {
            $taxonomyContext = $this->buildCompactDocContext();
            $primaryField = 'doc_group';
            $groupCodes = collect(TaxonomyTerm::getDocGroups())->pluck('group_code')->implode(', ');
        } else {
            $taxonomyContext = $this->buildCompactVisualContext();
            $primaryField = 'primary_group';
            $groupCodes = collect(TaxonomyTerm::getPrimaryGroups())->pluck('group_code')->implode(', ');
        }

        $thresholdPct = (int) ($confidenceThreshold * 100);

        $prompt = <<<PROMPT
You are a digital asset classifier for a media company. When an image is provided, LOOK AT IT CAREFULLY and base your classification on what you SEE in the image — not just the filename.

Classify the asset using ONLY terms from the taxonomy below.

GROUPS (pick one {$primaryField}): {$groupCodes}

TAXONOMY (Group: allowed tags):
{$taxonomyContext}

Return ONLY valid JSON (no markdown, no fences):
{{"{$primaryField}":"CODE","group_confidence":90,"tags":[{{"term":"Tag","facet":"Category","confidence":85}}],"description":"One sentence","needs_review":false}}

Rules:
- Pick 8-15 tags from the taxonomy above. Use exact term labels. Be comprehensive — tag content, style, mood, subject, setting.
- The primary_group MUST match what the image actually shows (e.g. a lake photo → NATURE, food → FOOD, office → GENBUS).
- Set needs_review=true if uncertain (confidence < {$thresholdPct}).
- group_confidence and tag confidence are 0-100.
- description: 1-2 factual sentences describing what is VISIBLE in the asset.
PROMPT;

        return $prompt;
    }

    /**
     * Build the full system prompt for DeepSeek based on asset type.
     * (Legacy verbose version — kept for reference, not used in production.)
     */
    public function buildSystemPromptVerbose(string $assetType, float $confidenceThreshold = 0.70): string
    {
        $isDoc = in_array($assetType, ['pdf', 'doc', 'docx', 'pptx', 'xlsx', 'xls', 'txt', 'csv', 'rtf']);

        if ($isDoc) {
            $taxonomyContext = $this->buildDocumentPromptContext();
            $primaryField = 'doc_group';
            $groupInstruction = 'Choose exactly one doc_group from the DOCUMENT GROUPS taxonomy.';
        } else {
            $taxonomyContext = $this->buildVisualPromptContext();
            $primaryField = 'primary_group';
            $groupInstruction = 'Choose exactly one primary_group code from the VISUAL ASSET GROUPS taxonomy (FOOD, MEDIA, GENBUS, GEO, NATURE, LIFE, or SPEC).';
        }

        $geoContext = $this->buildGeoContext();
        $thresholdPct = (int) ($confidenceThreshold * 100);

        $prompt = <<<PROMPT
You are an expert digital asset classifier for a Global Asset Management (GAM) system.
Your job is to classify and tag assets using ONLY the controlled vocabulary provided below.

{$taxonomyContext}

{$geoContext}

RESPONSE FORMAT — return ONLY valid JSON, no markdown, no code fences:
{
  "{$primaryField}": "GROUP_CODE",
  "group_confidence": 92,
  "tags": [
    {"term": "Controlled Term Label", "facet": "Category Name", "confidence": 95}
  ],
  "geo_region": null,
  "geo_state": null,
  "description": "1-2 sentence description of the asset content",
  "needs_review": false
}

RULES:
1. {$groupInstruction}
2. Return 5-15 tags. Each tag MUST be a term_label from the taxonomy above.
3. Each tag must include the facet (category) it belongs to and a confidence score 0-100.
4. If a term is NOT in the taxonomy, do NOT include it. Only use controlled terms.
5. Set needs_review=true if any tag confidence < {$thresholdPct} or group classification is uncertain.
6. If you detect a US location, set geo_region and/or geo_state (USPS code).
7. group_confidence is 0-100 indicating how certain you are about the group classification.
8. description should be factual, 1-2 sentences.
9. Return ONLY valid JSON.
PROMPT;

        return $prompt;
    }

    /**
     * Normalize a raw tag using synonym rules → canonical controlled term.
     */
    public function normalizeTag(string $rawTag): ?string
    {
        $lower = strtolower(trim($rawTag));

        $rules = $this->getSynonymMap();

        return $rules[$lower] ?? null;
    }

    /**
     * Check if a term label exists in the controlled vocabulary.
     */
    public function isControlledTerm(string $label): bool
    {
        $allLabels = TaxonomyTerm::getAllTermLabels();

        return in_array(strtolower(trim($label)), $allLabels);
    }

    /**
     * Find the closest matching controlled term for a raw tag (fuzzy).
     * Returns [term_label, group_code, similarity_score] or null.
     */
    public function findClosestTerm(string $rawTag): ?array
    {
        $lower = strtolower(trim($rawTag));
        $allTerms = TaxonomyTerm::active()
            ->whereIn('term_type', ['keyword', 'doc_keyword', 'viz_keyword'])
            ->get(['term_label', 'group_code']);

        $bestMatch = null;
        $bestScore = 0;

        foreach ($allTerms as $term) {
            $termLower = strtolower($term->term_label);

            // Exact match
            if ($termLower === $lower) {
                return [$term->term_label, $term->group_code, 1.0];
            }

            // Contains match
            if (str_contains($termLower, $lower) || str_contains($lower, $termLower)) {
                $score = similar_text($lower, $termLower) / max(strlen($lower), strlen($termLower));
                if ($score > $bestScore) {
                    $bestScore = $score;
                    $bestMatch = [$term->term_label, $term->group_code, round($score, 2)];
                }
            }

            // Similarity check
            similar_text($lower, $termLower, $pct);
            $score = $pct / 100;
            if ($score > 0.75 && $score > $bestScore) {
                $bestScore = $score;
                $bestMatch = [$term->term_label, $term->group_code, round($score, 2)];
            }
        }

        return $bestMatch;
    }

    /**
     * Get the valid group codes for a given asset type.
     */
    public function getValidGroupCodes(string $assetType): array
    {
        $isDoc = in_array($assetType, ['pdf', 'doc', 'docx', 'pptx', 'xlsx', 'xls', 'txt', 'csv', 'rtf']);

        if ($isDoc) {
            return collect(TaxonomyTerm::getDocGroups())
                ->pluck('group_code')
                ->toArray();
        }

        return collect(TaxonomyTerm::getPrimaryGroups())
            ->pluck('group_code')
            ->toArray();
    }

    /**
     * Expand a search query using synonym rules.
     *
     * Given a search term, returns an array of terms to search for:
     *  - the original (lowercased/trimmed)
     *  - the canonical term (if the query is a known synonym)
     *  - all raw synonyms that map to the same canonical term
     *
     * This enables users to search "burger" and also find assets tagged "hamburger", etc.
     */
    public function expandSearchTerms(string $query): array
    {
        $lower = strtolower(trim($query));
        if ($lower === '') {
            return [];
        }

        $terms = [$lower];

        $synonymMap = $this->getSynonymMap();          // raw_term → canonical_term
        $reverseMap = $this->getReverseSynonymMap();    // canonical_term → [raw_terms…]

        // If query matches a raw synonym, add its canonical term
        if (isset($synonymMap[$lower])) {
            $canonical = strtolower($synonymMap[$lower]);
            if (!in_array($canonical, $terms)) {
                $terms[] = $canonical;
            }
            // Also add sibling synonyms that map to the same canonical
            foreach ($reverseMap[$canonical] ?? [] as $sibling) {
                if (!in_array($sibling, $terms)) {
                    $terms[] = $sibling;
                }
            }
        }

        // If query IS a canonical term, add all its raw synonyms
        if (isset($reverseMap[$lower])) {
            foreach ($reverseMap[$lower] as $rawSynonym) {
                if (!in_array($rawSynonym, $terms)) {
                    $terms[] = $rawSynonym;
                }
            }
        }

        return $terms;
    }

    /**
     * Get the cached synonym map (raw_term → canonical_term).
     */
    private function getSynonymMap(): array
    {
        return Cache::remember('taxonomy:synonym_map', 86400, function () {
            return TaxonomyRule::where('is_active', true)
                ->get()
                ->mapWithKeys(fn ($r) => [strtolower($r->raw_term) => $r->canonical_term])
                ->toArray();
        });
    }

    /**
     * Get the cached reverse synonym map (canonical_term → [raw_terms…]).
     */
    private function getReverseSynonymMap(): array
    {
        return Cache::remember('taxonomy:reverse_synonym_map', 86400, function () {
            $map = [];
            foreach (TaxonomyRule::where('is_active', true)->get() as $rule) {
                $canonical = strtolower($rule->canonical_term);
                $map[$canonical][] = strtolower($rule->raw_term);
            }
            return $map;
        });
    }
}
