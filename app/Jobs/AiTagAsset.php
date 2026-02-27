<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Services\TaxonomyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * AiTagAsset — sends the asset to DeepSeek with the GAM controlled taxonomy
 * and extracts tags, group classification, confidence scores, and a description.
 *
 * Per REQ-10: Returns structured JSON with controlled vocabulary tags,
 * group classification from the 7 visual groups or 8 doc groups,
 * confidence scores, and description.
 *
 * The system prompt is built dynamically by TaxonomyService from the
 * taxonomy_terms DB table (loaded from the GAM XLSX spreadsheet).
 *
 * Cost: ~$0.001 per call (DeepSeek).
 */
class AiTagAsset implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;        // Retry once on transient failures
    public int $timeout = 120;     // 2 minutes — generous for API call
    public int $backoff = 5;

    public function __construct(
        public Asset $asset
    ) {}

    public function handle(): void
    {
        $asset = $this->asset;
        $taxonomyService = app(TaxonomyService::class);

        if (empty(config('openai.api_key'))) {
            Log::warning("AiTagAsset: No OpenAI API key configured — using fallback tagging", [
                'asset' => $asset->id,
            ]);
            $this->applyFallbackTags($asset);
            return;
        }

        try {
            $imageData = $this->getImageData($asset);
            $ext = strtolower($asset->file_extension ?? '');

            $result = $this->callDeepSeek($asset, $imageData, $taxonomyService, $ext);

            if (!$result) {
                Log::warning("AiTagAsset: Invalid AI response — routing to review", ['asset' => $asset->id]);
                $this->applyFallbackTags($asset);
                return;
            }

            $this->applyAiResults($asset, $result, $taxonomyService, $ext);

            $group = $result['primary_group'] ?? $result['doc_group'] ?? 'unknown';
            Log::info("AiTagAsset: Completed", [
                'asset' => $asset->id,
                'group' => $group,
                'tags'  => count($result['tags'] ?? []),
            ]);

        } catch (\Throwable $e) {
            Log::error("AiTagAsset: Failed", [
                'asset' => $asset->id,
                'error' => $e->getMessage(),
            ]);

            // Always apply fallback on any error (timeout, network, parsing)
            // instead of retrying and blocking the pipeline for minutes
            $this->applyFallbackTags($asset);
            return;
        }
    }

    /**
     * Get base64-encoded image data for the AI model.
     * Prefers preview image; falls back to original for small images.
     */
    private function getImageData(Asset $asset): ?string
    {
        // Try the generated preview first
        if ($asset->preview_path) {
            $previewsDisk = config('gam.storage.previews_disk', 'previews');
            if (Storage::disk($previewsDisk)->exists($asset->preview_path)) {
                $contents = Storage::disk($previewsDisk)->get($asset->preview_path);
                return base64_encode($contents);
            }
        }

        // Fall back to original file if it's an image and small enough (< 20MB)
        if (str_starts_with($asset->mime_type ?? '', 'image/') && $asset->file_size < 20 * 1024 * 1024) {
            $disk = Storage::disk($asset->storage_disk);
            if ($disk->exists($asset->storage_path)) {
                $contents = $disk->get($asset->storage_path);
                return base64_encode($contents);
            }
        }

        return null;
    }

    /**
     * Call the AI model with the taxonomy-grounded prompt.
     * Tries vision (image) first, falls back to text-only with filename/metadata.
     */
    private function callDeepSeek(Asset $asset, ?string $base64Image, TaxonomyService $taxonomyService, string $ext): ?array
    {
        $threshold = config('gam.ai.confidence_threshold', 0.70);
        $model = config('gam.ai.model', env('OPENAI_MODEL', 'deepseek/deepseek-chat'));
        $systemPrompt = $taxonomyService->buildSystemPrompt($ext, $threshold);

        // Try vision-capable request first if we have image data
        if ($base64Image) {
            try {
                // Detect the actual mime for the data URI (default jpeg)
                $dataMime = $asset->mime_type ?? 'image/jpeg';
                if (!str_starts_with($dataMime, 'image/')) {
                    $dataMime = 'image/jpeg';
                }

                // Use direct HTTP request for vision to pass OpenRouter-specific
                // provider hints that force selection of vision-capable endpoints
                $baseUrl = rtrim(config('openai.base_uri', 'https://openrouter.ai/api/v1'), '/');
                $apiKey = config('openai.api_key');
                $timeout = (int) config('openai.request_timeout', 60);

                $httpResponse = \Illuminate\Support\Facades\Http::timeout($timeout)
                    ->withHeaders([
                        'Authorization' => "Bearer {$apiKey}",
                        'Content-Type'  => 'application/json',
                        'HTTP-Referer'  => config('app.url', 'http://localhost'),
                        'X-Title'       => 'GAM Asset Manager',
                    ])
                    ->post("{$baseUrl}/chat/completions", [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            [
                                'role' => 'user',
                                'content' => [
                                    [
                                        'type' => 'image_url',
                                        'image_url' => [
                                            'url' => "data:{$dataMime};base64,{$base64Image}",
                                        ],
                                    ],
                                    [
                                        'type' => 'text',
                                        'text' => "Look at this image carefully. Describe what you see, then classify it.\nFilename: \"{$asset->original_filename}\" (.{$ext}, {$asset->mime_type}).\nReturn 8-15 tags from the taxonomy. Focus on the VISUAL CONTENT of the image — subject, setting, mood, colors, composition. JSON only.",
                                    ],
                                ],
                            ],
                        ],
                        'max_tokens' => 800,
                        'temperature' => 0.1,
                        // OpenRouter: only use providers that support image input
                        'provider' => [
                            'require_parameters' => true,
                        ],
                    ]);

                if (!$httpResponse->successful()) {
                    throw new \RuntimeException("Vision API error {$httpResponse->status()}: " . substr($httpResponse->body(), 0, 300));
                }

                $data = $httpResponse->json();
                $content = $data['choices'][0]['message']['content'] ?? '';
                return $this->parseRawContent($content);
            } catch (\Throwable $e) {
                Log::info("AiTagAsset: Vision request failed, trying text-only", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Text-only fallback: send filename + metadata for classification
        $filename = $asset->original_filename ?? 'unknown';
        $mime = $asset->mime_type ?? 'unknown';
        $size = $asset->file_size ?? 0;
        $sizeHuman = number_format($size / 1024, 0) . ' KB';

        Log::info("AiTagAsset: Using text-only classification (no image data)", ['asset' => $asset->id]);

        $response = OpenAI::chat()->create([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => $systemPrompt],
                [
                    'role' => 'user',
                    'content' => "No image available. Classify this asset based on its metadata only:\nFilename: \"{$filename}\"\nExtension: .{$ext}\nMIME: {$mime}\nSize: {$sizeHuman}\nReturn 8-15 tags from the taxonomy. Set needs_review=true since no visual data is available. JSON only.",
                ],
            ],
            'max_tokens' => 800,
            'temperature' => 0.1,
        ]);

        return $this->parseResponse($response);
    }

    /**
     * Parse the AI response JSON from the OpenAI PHP client response object.
     */
    private function parseResponse($response): ?array
    {
        $content = $response->choices[0]->message->content ?? '';
        return $this->parseRawContent($content);
    }

    /**
     * Parse raw AI response content string into structured data.
     */
    private function parseRawContent(string $content): ?array
    {

        // Strip thinking model tags (e.g. Qwen3 <think>...</think>)
        $content = preg_replace('/<think>.*?<\/think>/s', '', $content);

        // Strip potential markdown wrapping
        $content = preg_replace('/^```(?:json)?\s*/m', '', $content);
        $content = preg_replace('/\s*```\s*$/m', '', $content);
        $content = trim($content);

        $parsed = json_decode($content, true);

        if (!$parsed) {
            Log::warning("AiTagAsset: Could not parse AI response", ['content' => substr($content, 0, 500)]);
            return null;
        }

        // Accept both old format (group) and new format (primary_group/doc_group)
        $hasGroup = isset($parsed['primary_group']) || isset($parsed['doc_group']) || isset($parsed['group']);
        $hasTags = isset($parsed['tags']);

        if (!$hasGroup || !$hasTags) {
            Log::warning("AiTagAsset: AI response missing required fields", ['content' => substr($content, 0, 500)]);
            return null;
        }

        return $parsed;
    }

    /**
     * Apply AI tagging results to the asset.
     * Validates tags against the controlled vocabulary and normalizes via synonyms.
     */
    private function applyAiResults(Asset $asset, array $result, TaxonomyService $taxonomyService, string $ext): void
    {
        $threshold = config('gam.ai.confidence_threshold', 0.70) * 100;
        $needsReview = $result['needs_review'] ?? false;
        $unknownTerms = [];

        // Determine group field based on asset type
        $isDoc = in_array($ext, ['pdf', 'doc', 'docx', 'pptx', 'xlsx', 'xls', 'txt', 'csv', 'rtf']);
        $groupField = $isDoc ? 'doc_group' : 'primary_group';
        $assignedGroup = $result[$groupField] ?? $result['group'] ?? null;

        // Store tags — validate each against controlled vocabulary
        foreach ($result['tags'] ?? [] as $tagData) {
            // Handle both new format {"term", "facet", "confidence"} and old format {"tag", "confidence"}
            $tagLabel = $tagData['term'] ?? $tagData['tag'] ?? null;
            $facet = $tagData['facet'] ?? null;
            $confidenceRaw = $tagData['confidence'] ?? 0;
            // Normalize to 0-1 scale: prompt asks for 0-100 but some models return 0-1
            $confidence = $confidenceRaw > 1 ? $confidenceRaw / 100 : (float) $confidenceRaw;

            if (!$tagLabel) continue;

            $tagLower = strtolower(trim($tagLabel));

            // Try synonym normalization first
            $normalized = $taxonomyService->normalizeTag($tagLower);
            if ($normalized) {
                $tagLower = strtolower($normalized);
                $tagLabel = $normalized;
            }

            // Check if the term is in the controlled vocabulary
            $isControlled = $taxonomyService->isControlledTerm($tagLabel);

            if (!$isControlled) {
                // Try fuzzy match
                $closest = $taxonomyService->findClosestTerm($tagLabel);
                if ($closest && $closest[2] >= 0.80) {
                    $tagLabel = $closest[0]; // Use the closest controlled term
                    $isControlled = true;
                } else {
                    $unknownTerms[] = $tagLabel;
                }
            }

            $asset->tags()->create([
                'tag'           => strtolower(trim($tagLabel)),
                'confidence'    => round($confidence, 2),
                'auto_approved' => $isControlled && $confidenceRaw >= $threshold,
                'is_manual'     => false,
            ]);
        }

        // Validate group against taxonomy
        $validGroups = $taxonomyService->getValidGroupCodes($ext);
        if ($assignedGroup && !in_array($assignedGroup, $validGroups)) {
            Log::warning("AiTagAsset: AI returned invalid group '{$assignedGroup}', routing to review", [
                'asset' => $asset->id,
                'valid_groups' => $validGroups,
            ]);
            $needsReview = true;
            // Try to map common mismatches
            $assignedGroup = $this->resolveGroupCode($assignedGroup, $validGroups) ?? ($isDoc ? 'DOC-OPS' : 'SPEC');
        }

        $updateData = [
            'group_classification' => $assignedGroup,
            'group_confidence'     => isset($result['group_confidence'])
                ? round($result['group_confidence'] / 100, 2)
                : null,
        ];

        if ($result['description'] ?? null) {
            $updateData['description'] = $result['description'];
        }

        // Route to review if needed
        if ($needsReview || ($result['group_confidence'] ?? 100) < 80 || !empty($unknownTerms)) {
            $updateData['review_status'] = 'pending';
            if (!empty($unknownTerms)) {
                $updateData['review_reason'] = 'Unknown terms: ' . implode(', ', array_slice($unknownTerms, 0, 10));
            }
        }

        $asset->update($updateData);

        // Store raw AI response for traceability (in activity log)
        activity()
            ->performedOn($asset)
            ->withProperties([
                'group'         => $assignedGroup ?? 'unknown',
                'confidence'    => $result['group_confidence'] ?? 0,
                'tag_count'     => count($result['tags'] ?? []),
                'unknown_terms' => $unknownTerms,
                'raw_response'  => $result,
            ])
            ->log("AI tagged: {$asset->original_filename} → {$assignedGroup}");
    }

    /**
     * Attempt to resolve a group code that doesn't match the valid list.
     */
    private function resolveGroupCode(?string $group, array $validGroups): ?string
    {
        if (!$group) return null;

        $groupUpper = strtoupper($group);

        // Direct match
        if (in_array($groupUpper, $validGroups)) return $groupUpper;

        // Common AI mismatches (label → code)
        $labelToCode = [
            'FOOD'       => 'FOOD',
            'FOOD GROUP' => 'FOOD',
            'MEDIA'      => 'MEDIA',
            'MEDIA GROUP'=> 'MEDIA',
            'BUSINESS'   => 'GENBUS',
            'GEN BUSINESS'=> 'GENBUS',
            'GENERAL BUSINESS' => 'GENBUS',
            'LOCATION'   => 'GEO',
            'GEOGRAPHY'  => 'GEO',
            'NATURE'     => 'NATURE',
            'LIFESTYLE'  => 'LIFE',
            'SPECIALTY'  => 'SPEC',
            'CONCEPTS'   => 'SPEC',
            // Doc groups
            'CLIENT'     => 'DOC-CLIENT',
            'MARKETING'  => 'DOC-MKT',
            'WEB'        => 'DOC-WEB',
            'DATA'       => 'DOC-DATA',
            'PRODUCT'    => 'DOC-PROD',
            'OPERATIONS' => 'DOC-OPS',
            'LEGAL'      => 'DOC-LEGAL',
        ];

        return $labelToCode[$groupUpper] ?? null;
    }

    /**
     * Fallback tagging when DeepSeek is unavailable.
     * Uses file extension and MIME type to assign a basic group + tags.
     * Group codes match the taxonomy: FOOD, MEDIA, GENBUS, GEO, NATURE, LIFE, SPEC.
     */
    private function applyFallbackTags(Asset $asset): void
    {
        $ext = strtolower($asset->file_extension ?? '');
        $mime = $asset->mime_type ?? '';

        // Extension → taxonomy group code mapping
        $groupMap = [
            'jpg' => 'MEDIA', 'jpeg' => 'MEDIA', 'jfif' => 'MEDIA', 'png' => 'MEDIA', 'gif' => 'MEDIA',
            'webp' => 'MEDIA', 'svg' => 'SPEC', 'bmp' => 'MEDIA', 'tiff' => 'MEDIA', 'tif' => 'MEDIA',
            'psd' => 'MEDIA', 'ai' => 'SPEC', 'eps' => 'SPEC',
            'mp4' => 'MEDIA', 'mov' => 'MEDIA', 'avi' => 'MEDIA', 'mkv' => 'MEDIA',
            'pdf' => 'DOC-OPS', 'doc' => 'DOC-OPS', 'docx' => 'DOC-OPS',
            'xls' => 'DOC-DATA', 'xlsx' => 'DOC-DATA', 'csv' => 'DOC-DATA',
            'pptx' => 'DOC-MKT', 'txt' => 'DOC-OPS', 'rtf' => 'DOC-OPS',
        ];

        // Extension → basic tags
        $tagMap = [
            'jpg' => ['photograph', 'image', 'jpeg'], 'jpeg' => ['photograph', 'image', 'jpeg'],
            'png' => ['image', 'graphic', 'png'], 'gif' => ['image', 'animated', 'gif'],
            'webp' => ['image', 'web', 'webp'], 'svg' => ['vector', 'graphic', 'scalable'],
            'psd' => ['photoshop', 'design', 'layered'], 'ai' => ['illustrator', 'vector', 'design'],
            'eps' => ['vector', 'print', 'encapsulated'], 'tiff' => ['image', 'print', 'high-quality'],
            'tif' => ['image', 'print', 'high-quality'],
            'mp4' => ['video', 'media', 'clip'], 'mov' => ['video', 'media', 'quicktime'],
            'pdf' => ['document', 'portable', 'pdf'], 'doc' => ['document', 'word', 'text'],
            'docx' => ['document', 'word', 'text'],
            'xls' => ['spreadsheet', 'data', 'excel'], 'xlsx' => ['spreadsheet', 'data', 'excel'],
        ];

        $group = $groupMap[$ext] ?? 'SPEC';
        $tags = $tagMap[$ext] ?? ['unclassified'];

        // Add MIME-based tag
        if (str_starts_with($mime, 'image/')) $tags[] = 'raster-image';
        if (str_starts_with($mime, 'video/')) $tags[] = 'video-content';
        if (str_starts_with($mime, 'application/')) $tags[] = 'document';

        $tags = array_unique($tags);

        // Store tags with low confidence (signals fallback)
        foreach ($tags as $tag) {
            $asset->tags()->create([
                'tag'           => $tag,
                'confidence'    => 0.45,
                'auto_approved' => false,
                'is_manual'     => false,
            ]);
        }

        $asset->update([
            'group_classification' => $asset->group_classification ?: $group,
            'group_confidence'     => 0.30,
            'review_status'        => 'pending',
        ]);

        activity()
            ->performedOn($asset)
            ->log("Fallback tagging applied (no AI key): {$asset->original_filename} → {$group}");
    }

    public function failed(\Throwable $exception): void
    {
        Log::error("AiTagAsset: All retries exhausted", [
            'asset' => $this->asset->id,
            'error' => $exception->getMessage(),
        ]);

        // Apply fallback tags on permanent failure
        $this->applyFallbackTags($this->asset);
    }
}
