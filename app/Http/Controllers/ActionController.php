<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessAssetPipeline;
use App\Models\Asset;
use App\Models\AssetTag;
use App\Models\Collection;
use App\Models\TaxonomyRule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Spatie\Activitylog\Models\Activity;

class ActionController extends Controller
{
    // ══════════════════════════════════════════════════════════════
    //  UPLOAD  (REQ-04: Multi-Source File Upload)
    // ══════════════════════════════════════════════════════════════

    /**
     * Store uploaded file(s) — browser drag-and-drop / file picker.
     * POST /upload
     */
    public function uploadStore(Request $request)
    {
        $request->validate([
            'files'        => 'required|array|min:1',
            'files.*'      => 'required|file|max:512000', // 500 MB per file
            'collection'   => 'nullable|exists:collections,id',
            'group'        => ['nullable', Rule::in(array_keys(config('gam.groups')))],
            'description'  => 'nullable|string|max:1000',
        ]);

        $user    = $request->user();
        $results = [];

        foreach ($request->file('files') as $file) {
            $hash      = hash_file('sha256', $file->getRealPath());
            $extension = strtolower($file->getClientOriginalExtension());

            // Dedup check (REQ-07)
            $existing = Asset::where('sha256_hash', $hash)->first();
            if ($existing) {
                $results[] = [
                    'name'      => $file->getClientOriginalName(),
                    'status'    => 'duplicate',
                    'assetId'   => $existing->id,
                    'message'   => "Duplicate file — already exists as \"{$existing->original_filename}\".",
                    'existing'  => [
                        'id'       => $existing->id,
                        'filename' => $existing->original_filename,
                        'group'    => $existing->group_classification,
                        'link'     => "/preview/{$existing->id}",
                    ],
                ];
                continue;
            }

            // Store to staging disk
            $storagePath = $file->store(
                'uploads/' . now()->format('Y/m/d'),
                config('gam.storage.staging_disk', 'local')
            );

            $asset = Asset::create([
                'original_filename'  => $file->getClientOriginalName(),
                'original_path'      => $storagePath,
                'file_extension'     => $extension,
                'file_size'          => $file->getSize(),
                'mime_type'          => $file->getMimeType(),
                'sha256_hash'        => $hash,
                'upload_source'      => 'browser',
                'uploader_ip'        => $request->ip(),
                'ingested_at'        => now(),
                'group_classification' => $request->input('group'),
                'description'        => $request->input('description'),
                'pipeline_status'    => 'queued',
                'preview_status'     => 'pending',
                'review_status'      => 'pending',
                'is_master'          => true,
                'storage_disk'       => config('gam.storage.staging_disk', 'local'),
                'storage_path'       => $storagePath,
                'uploaded_by'        => $user->id,
            ]);

            // Attach to collection if specified (REQ-17)
            if ($request->filled('collection')) {
                $asset->collections()->attach($request->input('collection'));
            }

            // Create initial version record (REQ-16)
            $asset->versions()->create([
                'version_number' => 1,
                'file_path'      => $storagePath,
                'file_size'      => $file->getSize(),
                'sha256_hash'    => $hash,
                'uploaded_by'    => $user->id,
                'change_notes'   => 'Initial upload',
            ]);

            activity()
                ->causedBy($user)
                ->performedOn($asset)
                ->log("Uploaded file: {$asset->original_filename}");

            // Dispatch processing pipeline (REQ-05: 7-stage pipeline)
            ProcessAssetPipeline::dispatch($asset);

            $results[] = [
                'name'    => $file->getClientOriginalName(),
                'status'  => 'queued',
                'assetId' => $asset->id,
            ];
        }

        return redirect()->back()->with('success', count($results) . ' file(s) uploaded successfully.');
    }

    // ══════════════════════════════════════════════════════════════
    //  AJAX SINGLE-FILE UPLOAD  (per-file progress via XHR)
    // ══════════════════════════════════════════════════════════════

    /**
     * Upload a single file via AJAX (supports per-file progress tracking).
     * POST /upload/single
     *
     * Returns JSON: { id, name, status, size, sizeFormatted }
     */
    public function uploadSingle(Request $request)
    {
        $request->validate([
            'file'         => 'required|file|max:512000', // 500 MB
            'collection'   => 'nullable|exists:collections,id',
            'group'        => ['nullable', Rule::in(array_keys(config('gam.groups')))],
            'description'  => 'nullable|string|max:1000',
            'tags'         => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $file = $request->file('file');
        $hash = hash_file('sha256', $file->getRealPath());
        $extension = strtolower($file->getClientOriginalExtension());

        // Dedup check
        $existing = Asset::where('sha256_hash', $hash)->first();
        if ($existing) {
            // Clean up the uploaded staging file since we're rejecting it
            if (Storage::disk(config('gam.storage.staging_disk', 'staging'))->exists($file->hashName())) {
                Storage::disk(config('gam.storage.staging_disk', 'staging'))->delete($file->hashName());
            }

            return response()->json([
                'id'          => $existing->id,
                'name'        => $file->getClientOriginalName(),
                'status'      => 'duplicate',
                'message'     => 'Duplicate file — SHA-256 match found.',
                'existing'    => [
                    'id'        => $existing->id,
                    'filename'  => $existing->original_filename,
                    'group'     => $existing->group_classification,
                    'uploaded'  => $existing->ingested_at?->format('M d, Y'),
                    'link'      => "/preview/{$existing->id}",
                ],
            ], 409);
        }

        $storagePath = $file->store(
            'uploads/' . now()->format('Y/m/d'),
            config('gam.storage.staging_disk', 'staging')
        );

        $asset = Asset::create([
            'original_filename'    => $file->getClientOriginalName(),
            'original_path'        => $storagePath,
            'file_extension'       => $extension,
            'file_size'            => $file->getSize(),
            'mime_type'            => $file->getMimeType(),
            'sha256_hash'          => $hash,
            'upload_source'        => 'browser',
            'uploader_ip'          => $request->ip(),
            'ingested_at'          => now(),
            'group_classification' => $request->input('group'),
            'description'          => $request->input('description'),
            'pipeline_status'      => 'queued',
            'preview_status'       => 'pending',
            'review_status'        => 'pending',
            'is_master'            => true,
            'storage_disk'         => config('gam.storage.staging_disk', 'staging'),
            'storage_path'         => $storagePath,
            'uploaded_by'          => $user->id,
        ]);

        // Attach to collection
        if ($request->filled('collection')) {
            $asset->collections()->attach($request->input('collection'));
        }

        // Create initial version
        $asset->versions()->create([
            'version_number' => 1,
            'file_path'      => $storagePath,
            'file_size'      => $file->getSize(),
            'sha256_hash'    => $hash,
            'uploaded_by'    => $user->id,
            'change_notes'   => 'Initial upload',
        ]);

        // Apply manual tags
        if ($request->filled('tags')) {
            $tagNames = array_map('trim', explode(',', $request->input('tags')));
            foreach ($tagNames as $tagName) {
                if (empty($tagName)) continue;
                $asset->tags()->create([
                    'tag'           => $tagName,
                    'source'        => 'manual',
                    'confidence'    => 1.00,
                    'auto_approved' => true,
                ]);
            }
        }

        activity()
            ->causedBy($user)
            ->performedOn($asset)
            ->log("Uploaded file: {$asset->original_filename}");

        // Dispatch pipeline
        ProcessAssetPipeline::dispatch($asset);

        return response()->json([
            'id'            => $asset->id,
            'name'          => $asset->original_filename,
            'status'        => 'queued',
            'size'          => $asset->file_size,
            'sizeFormatted' => $asset->file_size_formatted,
            'extension'     => $asset->file_extension,
            'mime'          => $asset->mime_type,
        ], 201);
    }

    /**
     * Check the processing status of an asset — includes AI metadata when available.
     * Also auto-recovers assets stuck in a processing stage for > 10 minutes.
     * GET /upload/status/{id}
     */
    public function uploadStatus(string $id)
    {
        $asset = Asset::with(['tags' => fn ($q) => $q->orderByDesc('confidence')])->findOrFail($id);

        // ── Stale-job recovery: if stuck in a processing stage for > 10 min, mark failed ──
        $processingStages = ['queued', 'hashing', 'previewing', 'tagging', 'classifying', 'indexing'];
        if (in_array($asset->pipeline_status, $processingStages) && $asset->updated_at->diffInMinutes(now()) > 10) {
            $asset->update(['pipeline_status' => 'failed']);
            $asset->refresh();
        }

        $data = [
            'id'              => $asset->id,
            'name'            => $asset->original_filename,
            'pipeline_status' => $asset->pipeline_status,
            'preview_status'  => $asset->preview_status,
            'review_status'   => $asset->review_status,
            'preview_path'    => $asset->preview_path,
            'thumbnail_path'  => $asset->thumbnail_path,
        ];

        // Include AI metadata once tagging stage has completed
        $postTagStages = ['classifying', 'indexing', 'done', 'failed'];
        if (in_array($asset->pipeline_status, $postTagStages) || $asset->tags->isNotEmpty()) {
            $data['ai_metadata'] = [
                'group'            => $asset->group_classification,
                'group_confidence' => $asset->group_confidence ? round((float) $asset->group_confidence, 2) : null,
                'description'      => $asset->description,
                'tags'             => $asset->tags->map(fn ($t) => [
                    'id'         => $t->id,
                    'tag'        => $t->tag,
                    'confidence' => round((float) $t->confidence, 2),
                    'source'     => $t->source,
                    'facet'      => $t->facet ?? null,
                ])->values()->toArray(),
            ];
        }

        return response()->json($data);
    }

    /**
     * Save user-edited metadata for an asset (after AI auto-fill).
     * PATCH /upload/{id}/metadata
     */
    public function updateAssetMetadata(string $id, Request $request)
    {
        $request->validate([
            'group'       => ['nullable', 'string', 'max:50'],
            'description' => ['nullable', 'string', 'max:2000'],
            'tags'        => ['nullable', 'array'],
            'tags.*'      => ['string', 'max:200'],
        ]);

        $asset = Asset::findOrFail($id);

        // Update group & description
        $asset->update(array_filter([
            'group_classification' => $request->input('group'),
            'description'          => $request->input('description'),
        ], fn ($v) => $v !== null));

        // Sync tags if provided
        if ($request->has('tags')) {
            // Keep existing AI tags, add any new manual ones
            $existingTags = $asset->tags()->pluck('tag')->map(fn ($t) => strtolower($t))->toArray();
            foreach ($request->input('tags', []) as $tagName) {
                $tagName = trim($tagName);
                if (empty($tagName)) continue;
                if (in_array(strtolower($tagName), $existingTags)) continue;
                $asset->tags()->create([
                    'tag'           => $tagName,
                    'source'        => 'manual',
                    'confidence'    => 1.00,
                    'auto_approved' => true,
                ]);
            }
            // Remove tags not in the new list
            $newTagsLower = array_map(fn ($t) => strtolower(trim($t)), $request->input('tags', []));
            $asset->tags()->get()->each(function ($tag) use ($newTagsLower) {
                if (!in_array(strtolower($tag->tag), $newTagsLower)) {
                    $tag->delete();
                }
            });
        }

        activity()
            ->causedBy($request->user())
            ->performedOn($asset)
            ->log('Updated metadata via upload page');

        return response()->json(['status' => 'ok', 'message' => 'Metadata saved.']);
    }

    /**
     * Retry a failed or stuck upload — re-dispatches the processing pipeline.
     * POST /upload/{id}/retry
     */
    public function retryUpload(string $id, Request $request)
    {
        $asset = Asset::findOrFail($id);

        // Only allow retry on failed or stuck assets
        $retryable = ['failed', 'queued', 'hashing', 'previewing', 'tagging', 'classifying', 'indexing'];
        if (!in_array($asset->pipeline_status, $retryable)) {
            return response()->json([
                'status'  => 'error',
                'message' => "Cannot retry — asset is in '{$asset->pipeline_status}' state.",
            ], 409);
        }

        // Reset pipeline status and re-dispatch
        $asset->update(['pipeline_status' => 'queued']);

        ProcessAssetPipeline::dispatch($asset);

        activity()
            ->causedBy($request->user())
            ->performedOn($asset)
            ->log("Retried pipeline for: {$asset->original_filename}");

        return response()->json(['status' => 'ok', 'message' => 'Pipeline re-queued.']);
    }

    /**
     * Cancel an upload / pipeline at any stage.
     * DELETE /upload/{id}/cancel
     *
     * Stages handled:
     *  - queued/hashing/previewing/tagging/classifying/indexing: set pipeline_status=cancelled, clean up files
     *  - done: asset already finished — returns 409
     *  - cancelled: already cancelled — returns 200 idempotent
     */
    public function cancelUpload(string $id, Request $request)
    {
        $asset = Asset::findOrFail($id);

        // Already cancelled — idempotent
        if ($asset->pipeline_status === 'cancelled') {
            return response()->json(['status' => 'ok', 'message' => 'Already cancelled.']);
        }

        // Completed — can't cancel
        if ($asset->pipeline_status === 'done') {
            return response()->json(['status' => 'error', 'message' => 'Asset already finished processing.'], 409);
        }

        // Mark as cancelled
        $asset->update(['pipeline_status' => 'cancelled', 'review_status' => 'rejected']);

        // Clean up stored files
        $stagingDisk  = config('gam.storage.staging_disk', 'staging');
        $previewsDisk = config('gam.storage.previews_disk', 'previews');
        $assetsDisk   = config('gam.storage.assets_disk', 'assets');

        if ($asset->storage_path && $asset->storage_disk) {
            Storage::disk($asset->storage_disk)->delete($asset->storage_path);
        }
        if ($asset->preview_path) {
            Storage::disk($previewsDisk)->delete($asset->preview_path);
        }
        if ($asset->thumbnail_path) {
            Storage::disk($previewsDisk)->delete($asset->thumbnail_path);
        }

        // Remove tags
        $asset->tags()->delete();

        // Soft-delete the asset record
        $asset->delete();

        activity()
            ->causedBy($request->user())
            ->performedOn($asset)
            ->log("Cancelled upload: {$asset->original_filename}");

        return response()->json(['status' => 'ok', 'message' => 'Upload cancelled and cleaned up.']);
    }

    // ══════════════════════════════════════════════════════════════
    //  REVIEW ACTIONS  (REQ-15: Review Queue)
    // ══════════════════════════════════════════════════════════════

    /**
     * Approve an asset.
     * POST /review/{id}/approve
     */
    public function reviewApprove(string $id, Request $request)
    {
        $asset = Asset::findOrFail($id);
        $asset->update([
            'review_status' => 'approved',
            'reviewed_by'   => $request->user()->id,
            'reviewed_at'   => now(),
        ]);

        activity()
            ->causedBy($request->user())
            ->performedOn($asset)
            ->log("Approved asset: {$asset->original_filename}");

        return redirect()->back()->with('success', "Asset '{$asset->original_filename}' approved.");
    }

    /**
     * Reject an asset.
     * POST /review/{id}/reject
     */
    public function reviewReject(string $id, Request $request)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        $asset = Asset::findOrFail($id);
        $asset->update([
            'review_status' => 'rejected',
            'reviewed_by'   => $request->user()->id,
            'reviewed_at'   => now(),
            'review_reason' => $request->input('reason', 'Rejected by reviewer'),
        ]);

        activity()
            ->causedBy($request->user())
            ->performedOn($asset)
            ->log("Rejected asset: {$asset->original_filename}");

        return redirect()->back()->with('success', "Asset '{$asset->original_filename}' rejected.");
    }

    /**
     * Override group classification during review.
     * POST /review/{id}/override
     */
    public function reviewOverride(string $id, Request $request)
    {
        $request->validate([
            'group' => ['required', Rule::in(array_keys(config('gam.groups')))],
        ]);

        $asset = Asset::findOrFail($id);
        $oldGroup = $asset->group_classification;

        $asset->update([
            'group_classification' => $request->input('group'),
            'group_confidence'     => 1.00, // manual override = 100% confidence
            'review_status'        => 'approved',
            'reviewed_by'          => $request->user()->id,
            'reviewed_at'          => now(),
        ]);

        activity()
            ->causedBy($request->user())
            ->performedOn($asset)
            ->withProperties(['old_group' => $oldGroup, 'new_group' => $request->input('group')])
            ->log("Overrode classification: {$oldGroup} → {$request->input('group')}");

        return redirect()->back()->with('success', "Classification changed to '{$request->input('group')}'.");
    }

    /**
     * Flag asset for re-review.
     * POST /review/{id}/flag
     */
    public function reviewFlag(string $id, Request $request)
    {
        $request->validate(['reason' => 'nullable|string|max:500']);

        $asset = Asset::findOrFail($id);
        $asset->update([
            'review_status' => 'pending',
            'review_reason' => $request->input('reason', 'Flagged for re-review'),
            'reviewed_by'   => null,
            'reviewed_at'   => null,
        ]);

        activity()
            ->causedBy($request->user())
            ->performedOn($asset)
            ->log("Flagged asset for review: {$asset->original_filename}");

        return redirect()->back()->with('success', "Asset flagged for review.");
    }

    // ══════════════════════════════════════════════════════════════
    //  TAG MANAGEMENT  (REQ-15: ADD TAG / REMOVE TAG)
    // ══════════════════════════════════════════════════════════════

    /**
     * Add a tag to an asset.
     * POST /assets/{id}/tags
     */
    public function addTag(string $id, Request $request)
    {
        $request->validate([
            'tag' => 'required|string|max:100',
        ]);

        $asset = Asset::findOrFail($id);

        // Normalize via taxonomy rules (REQ-13)
        $rawTag       = trim($request->input('tag'));
        $canonicalTag = TaxonomyRule::normalize($rawTag) ?? $rawTag;

        // Prevent duplicate tags
        $exists = AssetTag::where('asset_id', $asset->id)
            ->where('tag', $canonicalTag)
            ->exists();

        if ($exists) {
            return redirect()->back()->with('info', "Tag '{$canonicalTag}' already exists on this asset.");
        }

        AssetTag::create([
            'asset_id'      => $asset->id,
            'tag'           => $canonicalTag,
            'confidence'    => 1.00,
            'auto_approved' => false,
            'is_manual'     => true,
            'added_by'      => $request->user()->id,
        ]);

        activity()
            ->causedBy($request->user())
            ->performedOn($asset)
            ->log("Added tag '{$canonicalTag}' to {$asset->original_filename}");

        return redirect()->back()->with('success', "Tag '{$canonicalTag}' added.");
    }

    /**
     * Remove a tag from an asset.
     * DELETE /assets/{id}/tags/{tagId}
     */
    public function removeTag(string $id, string $tagId, Request $request)
    {
        $asset = Asset::findOrFail($id);
        $tag   = AssetTag::where('asset_id', $asset->id)->findOrFail($tagId);

        $tagName = $tag->tag;
        $tag->delete();

        activity()
            ->causedBy($request->user())
            ->performedOn($asset)
            ->log("Removed tag '{$tagName}' from {$asset->original_filename}");

        return redirect()->back()->with('success', "Tag '{$tagName}' removed.");
    }

    // ══════════════════════════════════════════════════════════════
    //  TAXONOMY MANAGEMENT  (REQ-13: Normalization Rules)
    // ══════════════════════════════════════════════════════════════

    /**
     * Create a taxonomy rule.
     * POST /taxonomy
     */
    public function taxonomyStore(Request $request)
    {
        $request->validate([
            'raw_term'       => 'required|string|max:255',
            'canonical_term' => 'required|string|max:255',
            'group_hint'     => ['required', Rule::in(array_keys(config('gam.groups')))],
        ]);

        $rule = TaxonomyRule::create([
            'raw_term'       => strtolower(trim($request->input('raw_term'))),
            'canonical_term' => trim($request->input('canonical_term')),
            'group_hint'     => $request->input('group_hint'),
            'is_active'      => true,
        ]);

        TaxonomyRule::clearCache();

        activity()->causedBy($request->user())->log("Created taxonomy rule: {$rule->raw_term} → {$rule->canonical_term}");

        return redirect()->back()->with('success', "Taxonomy rule created.");
    }

    /**
     * Update a taxonomy rule.
     * PUT /taxonomy/{id}
     */
    public function taxonomyUpdate(string $id, Request $request)
    {
        $rule = TaxonomyRule::findOrFail($id);

        $request->validate([
            'raw_term'       => 'sometimes|string|max:255',
            'canonical_term' => 'sometimes|string|max:255',
            'group_hint'     => ['sometimes', Rule::in(array_keys(config('gam.groups')))],
            'is_active'      => 'sometimes|boolean',
        ]);

        $rule->update($request->only('raw_term', 'canonical_term', 'group_hint', 'is_active'));
        TaxonomyRule::clearCache();

        activity()->causedBy($request->user())->log("Updated taxonomy rule #{$rule->id}");

        return redirect()->back()->with('success', "Taxonomy rule updated.");
    }

    /**
     * Delete a taxonomy rule.
     * DELETE /taxonomy/{id}
     */
    public function taxonomyDestroy(string $id, Request $request)
    {
        $rule = TaxonomyRule::findOrFail($id);
        $rule->delete();
        TaxonomyRule::clearCache();

        activity()->causedBy($request->user())->log("Deleted taxonomy rule: {$rule->raw_term}");

        return redirect()->back()->with('success', "Taxonomy rule deleted.");
    }

    /**
     * Import taxonomy rules from CSV.
     * POST /taxonomy/import
     */
    public function taxonomyImport(Request $request)
    {
        $request->validate([
            'csv' => 'required|file|mimes:csv,txt|max:2048',
        ]);

        $file    = $request->file('csv');
        $handle  = fopen($file->getRealPath(), 'r');
        $header  = fgetcsv($handle);
        $count   = 0;
        $skipped = 0;

        while (($row = fgetcsv($handle)) !== false) {
            if (count($row) < 3) {
                $skipped++;
                continue;
            }

            $rawTerm   = strtolower(trim($row[0]));
            $canonical = trim($row[1]);
            $group     = trim($row[2]);

            if (!array_key_exists($group, config('gam.groups'))) {
                $skipped++;
                continue;
            }

            TaxonomyRule::updateOrCreate(
                ['raw_term' => $rawTerm],
                [
                    'canonical_term' => $canonical,
                    'group_hint'     => $group,
                    'is_active'      => true,
                ]
            );
            $count++;
        }

        fclose($handle);
        TaxonomyRule::clearCache();

        activity()->causedBy($request->user())->log("Imported {$count} taxonomy rules from CSV (skipped {$skipped})");

        return redirect()->back()->with('success', "{$count} rules imported, {$skipped} skipped.");
    }

    // ══════════════════════════════════════════════════════════════
    //  COLLECTION MANAGEMENT  (REQ-17)
    // ══════════════════════════════════════════════════════════════

    /**
     * Create a collection.
     * POST /collections
     */
    public function collectionStore(Request $request)
    {
        $request->validate([
            'name'         => 'required|string|max:255',
            'description'  => 'nullable|string|max:1000',
            'access_level' => ['required', Rule::in(['public', 'private', 'role-based'])],
        ]);

        $collection = Collection::create([
            'name'         => $request->input('name'),
            'description'  => $request->input('description'),
            'access_level' => $request->input('access_level'),
            'created_by'   => $request->user()->id,
        ]);

        activity()->causedBy($request->user())->log("Created collection: {$collection->name}");

        return redirect()->back()->with('success', "Collection '{$collection->name}' created.");
    }

    /**
     * Update a collection.
     * PUT /collections/{id}
     */
    public function collectionUpdate(string $id, Request $request)
    {
        $collection = Collection::findOrFail($id);

        $request->validate([
            'name'         => 'sometimes|string|max:255',
            'description'  => 'nullable|string|max:1000',
            'access_level' => ['sometimes', Rule::in(['public', 'private', 'role-based'])],
        ]);

        $collection->update($request->only('name', 'description', 'access_level'));

        activity()->causedBy($request->user())->log("Updated collection: {$collection->name}");

        return redirect()->back()->with('success', "Collection updated.");
    }

    /**
     * Delete a collection.
     * DELETE /collections/{id}
     */
    public function collectionDestroy(string $id, Request $request)
    {
        $collection = Collection::findOrFail($id);
        $name       = $collection->name;

        $collection->assets()->detach();
        $collection->delete();

        activity()->causedBy($request->user())->log("Deleted collection: {$name}");

        return redirect()->back()->with('success', "Collection '{$name}' deleted.");
    }

    /**
     * Add assets to a collection.
     * POST /collections/{id}/assets
     */
    public function collectionAddAssets(string $id, Request $request)
    {
        $request->validate([
            'asset_ids'   => 'required|array|min:1',
            'asset_ids.*' => 'exists:assets,id',
        ]);

        $collection = Collection::findOrFail($id);
        $collection->assets()->syncWithoutDetaching($request->input('asset_ids'));

        $count = count($request->input('asset_ids'));
        activity()->causedBy($request->user())->log("Added {$count} asset(s) to collection: {$collection->name}");

        return redirect()->back()->with('success', "{$count} asset(s) added to '{$collection->name}'.");
    }

    /**
     * Remove an asset from a collection.
     * DELETE /collections/{id}/assets/{assetId}
     */
    public function collectionRemoveAsset(string $id, string $assetId, Request $request)
    {
        $collection = Collection::findOrFail($id);
        $collection->assets()->detach($assetId);

        activity()->causedBy($request->user())->log("Removed asset #{$assetId} from collection: {$collection->name}");

        return redirect()->back()->with('success', "Asset removed from '{$collection->name}'.");
    }

    /**
     * Set role-based permissions for a collection.
     * POST /collections/{id}/permissions
     */
    public function collectionPermissions(string $id, Request $request)
    {
        $request->validate([
            'roles'   => 'required|array',
            'roles.*' => 'string|max:100',
        ]);

        $collection = Collection::findOrFail($id);

        // Replace all existing role permissions
        DB::table('collection_permissions')->where('collection_id', $collection->id)->delete();

        $roles = $request->input('roles', []);
        foreach ($roles as $roleName) {
            DB::table('collection_permissions')->insert([
                'collection_id' => $collection->id,
                'role_name'     => $roleName,
                'created_at'    => now(),
                'updated_at'    => now(),
            ]);
        }

        // Auto-set access_level to role-based if roles provided, public if empty
        $collection->update([
            'access_level' => count($roles) > 0 ? 'role-based' : 'public',
        ]);

        activity()->causedBy($request->user())->log("Updated permissions for collection: {$collection->name} — roles: " . implode(', ', $roles));

        return redirect()->back()->with('success', "Permissions updated for '{$collection->name}'.");
    }

    // ══════════════════════════════════════════════════════════════
    //  ASSET ACTIONS  (REQ-16, REQ-19, REQ-20)
    // ══════════════════════════════════════════════════════════════

    /**
     * Download an asset file.
     * GET /assets/{id}/download
     */
    public function downloadAsset(string $id, Request $request)
    {
        $asset = Asset::findOrFail($id);

        if (!$asset->isAccessibleBy($request->user())) {
            abort(403, 'You do not have permission to download this asset.');
        }

        activity()
            ->causedBy($request->user())
            ->performedOn($asset)
            ->log("Downloaded file: {$asset->original_filename}");

        $disk = $asset->storage_disk ?? 'local';
        $path = $asset->storage_path;

        if (!$path || !Storage::disk($disk)->exists($path)) {
            return redirect()->back()->with('error', 'File not found on storage.');
        }

        return Storage::disk($disk)->download($path, $asset->original_filename);
    }

    /**
     * Serve a preview image for an asset.
     * GET /serve/preview/{id}
     */
    public function servePreview(string $id, Request $request)
    {
        $asset = Asset::findOrFail($id);

        if (!$asset->isAccessibleBy($request->user())) {
            abort(403, 'You do not have permission to view this asset.');
        }

        $previewsDisk = config('gam.storage.previews_disk', 'previews');

        if (!$asset->preview_path || !Storage::disk($previewsDisk)->exists($asset->preview_path)) {
            abort(404, 'Preview not available.');
        }

        $contentType = $this->detectPreviewContentType($asset->preview_path);

        return response(Storage::disk($previewsDisk)->get($asset->preview_path))
            ->header('Content-Type', $contentType)
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * Serve a thumbnail image for an asset.
     * GET /serve/thumbnail/{id}
     */
    public function serveThumbnail(string $id, Request $request)
    {
        $asset = Asset::findOrFail($id);

        if (!$asset->isAccessibleBy($request->user())) {
            abort(403, 'You do not have permission to view this asset.');
        }

        $previewsDisk = config('gam.storage.previews_disk', 'previews');

        if (!$asset->thumbnail_path || !Storage::disk($previewsDisk)->exists($asset->thumbnail_path)) {
            abort(404, 'Thumbnail not available.');
        }

        $contentType = $this->detectPreviewContentType($asset->thumbnail_path);

        return response(Storage::disk($previewsDisk)->get($asset->thumbnail_path))
            ->header('Content-Type', $contentType)
            ->header('Cache-Control', 'public, max-age=86400');
    }

    /**
     * Detect content type from preview file extension.
     */
    private function detectPreviewContentType(string $path): string
    {
        $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return match ($ext) {
            'svg'  => 'image/svg+xml',
            'png'  => 'image/png',
            'webp' => 'image/webp',
            'gif'  => 'image/gif',
            default => 'image/jpeg',
        };
    }

    /**
     * Delete an asset (soft-delete).
     * DELETE /assets/{id}
     */
    public function deleteAsset(string $id, Request $request)
    {
        $asset = Asset::findOrFail($id);
        $name  = $asset->original_filename;

        activity()
            ->causedBy($request->user())
            ->performedOn($asset)
            ->log("Deleted asset: {$name}");

        $asset->delete(); // soft-delete via SoftDeletes trait

        return redirect()->back()->with('success', "Asset '{$name}' deleted.");
    }

    /**
     * Bulk-delete assets (admin only, soft-delete each individually).
     * POST /assets/bulk-delete
     */
    public function bulkDeleteAssets(Request $request)
    {
        if (!$request->user()->hasRole('Admin')) {
            return response()->json(['message' => 'Unauthorized — admin only.'], 403);
        }

        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'required',
        ]);

        $deleted = [];
        foreach ($request->ids as $id) {
            $asset = Asset::find($id);
            if ($asset) {
                activity()
                    ->causedBy($request->user())
                    ->performedOn($asset)
                    ->log("Bulk-deleted asset: {$asset->original_filename}");
                $asset->delete();
                $deleted[] = $id;
            }
        }

        return response()->json(['status' => 'ok', 'deleted' => $deleted, 'count' => count($deleted)]);
    }

    /**
     * Bulk-delete collections (admin only).
     * POST /collections/bulk-delete
     */
    public function bulkDeleteCollections(Request $request)
    {
        if (!$request->user()->hasRole('Admin')) {
            return response()->json(['message' => 'Unauthorized — admin only.'], 403);
        }

        $request->validate([
            'ids'   => 'required|array|min:1',
            'ids.*' => 'required',
        ]);

        $deleted = [];
        foreach ($request->ids as $id) {
            $col = Collection::find($id);
            if ($col) {
                activity()
                    ->causedBy($request->user())
                    ->performedOn($col)
                    ->log("Bulk-deleted collection: {$col->name}");
                $col->delete();
                $deleted[] = $id;
            }
        }

        return response()->json(['status' => 'ok', 'deleted' => $deleted, 'count' => count($deleted)]);
    }

    // ══════════════════════════════════════════════════════════════
    //  SEARCH  (REQ-22: Full-Text Search)
    // ══════════════════════════════════════════════════════════════

    /**
     * Search across assets.
     * GET /search
     */
    public function search(Request $request)
    {
        $request->validate([
            'q'         => 'nullable|string|min:1|max:200',
            'group'     => ['nullable', Rule::in(array_keys(config('gam.groups')))],
            'extension' => 'nullable|string|max:10',
            'status'    => ['nullable', Rule::in(['pending', 'approved', 'rejected'])],
            'sort'      => ['nullable', Rule::in(['relevance', 'newest', 'oldest', 'largest', 'smallest'])],
        ]);

        $query = $request->input('q', '');
        $user  = $request->user();

        // Expand search terms using taxonomy synonym rules for normalization
        $expandedTerms = [];
        if ($query) {
            $taxonomyService = app(\App\Services\TaxonomyService::class);
            $expandedTerms = $taxonomyService->expandSearchTerms($query);
        }

        $base = Asset::forUser($user)
            ->when($query, function ($q) use ($query, $expandedTerms) {
                $q->where(function ($qq) use ($query, $expandedTerms) {
                    $qq->where('original_filename', 'like', "%{$query}%")
                       ->orWhere('description', 'like', "%{$query}%")
                       ->orWhere('group_classification', 'like', "%{$query}%");
                    // Search tags with all expanded synonym terms
                    foreach ($expandedTerms as $term) {
                        $qq->orWhereHas('tags', fn ($tq) => $tq->where('tag', 'like', "%{$term}%"));
                    }
                });
            })
            ->when($request->filled('group'), fn ($q) => $q->where('group_classification', $request->input('group')))
            ->when($request->filled('extension'), fn ($q) => $q->where('file_extension', $request->input('extension')))
            ->when($request->filled('status'), fn ($q) => $q->where('review_status', $request->input('status')));

        // Faceted counts (on unfiltered search results)
        $facetBase = Asset::forUser($user)
            ->when($query, function ($q) use ($query, $expandedTerms) {
                $q->where(function ($qq) use ($query, $expandedTerms) {
                    $qq->where('original_filename', 'like', "%{$query}%")
                       ->orWhere('description', 'like', "%{$query}%")
                       ->orWhere('group_classification', 'like', "%{$query}%");
                    foreach ($expandedTerms as $term) {
                        $qq->orWhereHas('tags', fn ($tq) => $tq->where('tag', 'like', "%{$term}%"));
                    }
                });
            });
        $extensionCounts = (clone $facetBase)->selectRaw('file_extension, count(*) as cnt')
            ->groupBy('file_extension')->pluck('cnt', 'file_extension')->toArray();
        $statusCounts = (clone $facetBase)->selectRaw('review_status, count(*) as cnt')
            ->groupBy('review_status')->pluck('cnt', 'review_status')->toArray();

        $assets = (clone $base)
            ->when($request->input('sort') === 'newest', fn ($q) => $q->latest('ingested_at'))
            ->when($request->input('sort') === 'oldest', fn ($q) => $q->oldest('ingested_at'))
            ->when($request->input('sort') === 'largest', fn ($q) => $q->orderByDesc('file_size'))
            ->when($request->input('sort') === 'smallest', fn ($q) => $q->orderBy('file_size'))
            ->when(!$request->filled('sort') || $request->input('sort') === 'relevance', fn ($q) => $q->latest())
            ->paginate(24);

        $results = $assets->through(fn ($a) => [
            'id'        => $a->id,
            'name'      => $a->original_filename,
            'extension' => $a->file_extension,
            'size'      => $a->file_size_formatted,
            'group'     => $a->group_classification,
            'status'    => $a->review_status,
            'thumbnail' => $a->thumbnail_path,
            'uploaded'  => $a->ingested_at?->diffForHumans(),
        ]);

        // Return as Inertia or JSON depending on request type
        if ($request->wantsJson()) {
            return response()->json($results);
        }

        $popularTags = \App\Models\Tag::selectRaw('tag, count(*) as cnt')
            ->groupBy('tag')->orderByDesc('cnt')->limit(20)->pluck('cnt', 'tag')->toArray();

        return \Inertia\Inertia::render('Search', [
            'assets'          => $results,
            'totalCount'      => $results->total(),
            'filters'         => array_merge($request->only('group', 'extension', 'status', 'sort'), ['search' => $query]),
            'groups'          => array_keys(config('gam.groups')),
            'extensions'      => Asset::forUser($user)->distinct()->pluck('file_extension')->filter()->sort()->values(),
            'extensionCounts' => $extensionCounts,
            'statusCounts'    => $statusCounts,
            'popularTags'     => $popularTags,
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  SETTINGS  (REQ-27: Settings Page)
    // ══════════════════════════════════════════════════════════════

    /**
     * Save settings.
     * POST /settings
     */
    public function settingsUpdate(Request $request)
    {
        $request->validate([
            'ai_threshold'   => 'sometimes|numeric|min:0|max:1',
            'ai_model'       => 'sometimes|string|max:100',
            'concurrent_uploads' => 'sometimes|integer|min:1|max:20',
        ]);

        // In production this would persist to DB or .env — for now we log intent
        $changes = $request->only('ai_threshold', 'ai_model', 'concurrent_uploads');

        activity()
            ->causedBy($request->user())
            ->withProperties($changes)
            ->log('Updated system settings');

        return redirect()->back()->with('success', 'Settings saved.');
    }

    // ══════════════════════════════════════════════════════════════
    //  PERMISSIONS / RBAC  (REQ-18: Role-Based Access Control)
    // ══════════════════════════════════════════════════════════════

    /**
     * Assign a role to a user.
     * PUT /permissions/users/{id}/role
     */
    public function assignRole(string $id, Request $request)
    {
        $request->validate([
            'role' => ['required', Rule::in(['Admin', 'Food Team', 'Media Team', 'Marketing Team'])],
        ]);

        $user = User::findOrFail($id);
        $user->syncRoles([$request->input('role')]);

        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->log("Assigned role '{$request->input('role')}' to {$user->name}");

        return redirect()->back()->with('success', "Role '{$request->input('role')}' assigned to {$user->name}.");
    }

    /**
     * Create a new user (admin only).
     * POST /permissions/users
     */
    public function createUser(Request $request)
    {
        $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'role'     => ['required', Rule::in(['Admin', 'Food Team', 'Media Team', 'Marketing Team'])],
        ]);

        $user = User::create([
            'name'     => $request->input('name'),
            'email'    => $request->input('email'),
            'password' => $request->input('password'),
        ]);

        $user->assignRole($request->input('role'));

        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->log("Created user '{$user->name}' with role '{$request->input('role')}'");

        return redirect()->back()->with('success', "User '{$user->name}' created successfully.");
    }

    /**
     * Invite a user by email (alias for create with generated password).
     * POST /permissions/invite
     */
    public function inviteUser(Request $request)
    {
        $request->validate([
            'email' => 'required|email|max:255|unique:users,email',
            'role'  => ['required', Rule::in(['Admin', 'Food Team', 'Media Team', 'Marketing Team'])],
        ]);

        $name = explode('@', $request->input('email'))[0];
        $tempPassword = \Illuminate\Support\Str::random(12);

        $user = User::create([
            'name'     => $name,
            'email'    => $request->input('email'),
            'password' => $tempPassword,
        ]);

        $user->assignRole($request->input('role'));

        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->log("Invited user '{$user->email}' with role '{$request->input('role')}'");

        return redirect()->back()->with('success', "Invitation sent to {$user->email}.");
    }

    /**
     * Update a user's name/email (admin only).
     * PATCH /permissions/users/{id}
     */
    public function updateUser(string $id, Request $request)
    {
        $user = User::findOrFail($id);

        $request->validate([
            'name'  => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($request->only('name', 'email'));

        activity()
            ->causedBy($request->user())
            ->performedOn($user)
            ->log("Updated user '{$user->name}' details");

        return redirect()->back()->with('success', "User '{$user->name}' updated.");
    }

    /**
     * Delete a user (admin only).
     * DELETE /permissions/users/{id}
     */
    public function deleteUser(string $id, Request $request)
    {
        $user = User::findOrFail($id);
        $userName = $user->name;

        // Prevent self-deletion
        if ($user->id === $request->user()->id) {
            return redirect()->back()->with('error', 'You cannot delete your own account.');
        }

        $user->delete();

        activity()
            ->causedBy($request->user())
            ->log("Deleted user '{$userName}'");

        return redirect()->back()->with('success', "User '{$userName}' deleted.");
    }

    // ══════════════════════════════════════════════════════════════
    //  PROFILE  (REQ-26: Profile Page)
    // ══════════════════════════════════════════════════════════════

    /**
     * Update user profile.
     * PUT /profile
     */
    public function profileUpdate(Request $request)
    {
        $user = $request->user();

        $request->validate([
            'name'  => 'sometimes|string|max:255',
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
        ]);

        $user->update($request->only('name', 'email'));

        activity()->causedBy($user)->log('Updated profile');

        return redirect()->back()->with('success', 'Profile updated.');
    }

    /**
     * Change password.
     * PUT /profile/password
     */
    public function profilePassword(Request $request)
    {
        $request->validate([
            'current_password' => 'required|current_password',
            'password'         => 'required|string|min:8|confirmed',
        ]);

        $request->user()->update([
            'password' => $request->input('password'), // auto-hashed via User cast
        ]);

        activity()->causedBy($request->user())->log('Changed password');

        return redirect()->back()->with('success', 'Password changed.');
    }

    // ══════════════════════════════════════════════════════════════
    //  AUDIT LOG EXPORT  (REQ-25: CSV export of audit log)
    // ══════════════════════════════════════════════════════════════

    /**
     * Export audit log as CSV.
     * GET /audit-log/export
     */
    public function auditLogExport(Request $request)
    {
        $query = Activity::with('causer');

        if ($request->filled('action')) {
            $query->where('description', 'like', '%' . $request->input('action') . '%');
        }
        if ($request->filled('user_id')) {
            $query->where('causer_id', $request->input('user_id'));
        }
        if ($request->filled('log_name')) {
            $query->where('log_name', $request->input('log_name'));
        }
        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->input('date_from'));
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->input('date_to'));
        }

        $activities = $query->latest()->get();

        $csv = "ID,Date,User,Action,Log Name,Subject Type,Subject ID\n";
        foreach ($activities as $a) {
            $csv .= implode(',', [
                $a->id,
                '"' . $a->created_at->format('Y-m-d H:i:s') . '"',
                '"' . str_replace('"', '""', $a->causer?->name ?? 'System') . '"',
                '"' . str_replace('"', '""', $a->description) . '"',
                '"' . ($a->log_name ?? '') . '"',
                '"' . ($a->subject_type ? class_basename($a->subject_type) : '') . '"',
                $a->subject_id ?? '',
            ]) . "\n";
        }

        activity()->causedBy($request->user())->log('Exported audit log as CSV');

        return response($csv, 200, [
            'Content-Type'        => 'text/csv',
            'Content-Disposition' => 'attachment; filename="audit-log-' . now()->format('Y-m-d') . '.csv"',
        ]);
    }

    // ══════════════════════════════════════════════════════════════
    //  VERSION MANAGEMENT  (REQ-16: Version History & Rollback)
    // ══════════════════════════════════════════════════════════════

    /**
     * Upload a new version of an asset.
     * POST /assets/{id}/versions
     */
    public function uploadVersion(string $id, Request $request)
    {
        $request->validate([
            'file'         => 'required|file|max:512000',
            'change_notes' => 'nullable|string|max:500',
        ]);

        $asset = Asset::findOrFail($id);
        $user  = $request->user();
        $file  = $request->file('file');
        $hash  = hash_file('sha256', $file->getRealPath());

        $storagePath = $file->store(
            'uploads/' . now()->format('Y/m/d'),
            config('gam.storage.staging_disk', 'staging')
        );

        // Create new version record
        $latestVersion = $asset->versions()->max('version_number') ?? 0;
        $newVersion = $asset->versions()->create([
            'version_number' => $latestVersion + 1,
            'file_path'      => $storagePath,
            'file_size'      => $file->getSize(),
            'sha256_hash'    => $hash,
            'uploaded_by'    => $user->id,
            'change_notes'   => $request->input('change_notes', 'New version uploaded'),
        ]);

        // Update the asset to point to the new file
        $asset->update([
            'original_path'  => $storagePath,
            'file_extension' => strtolower($file->getClientOriginalExtension()),
            'file_size'      => $file->getSize(),
            'mime_type'      => $file->getMimeType(),
            'sha256_hash'    => $hash,
            'storage_path'   => $storagePath,
            'pipeline_status'=> 'queued',
            'preview_status' => 'pending',
        ]);

        activity()
            ->causedBy($user)
            ->performedOn($asset)
            ->log("Uploaded version {$newVersion->version_number} of {$asset->original_filename}");

        ProcessAssetPipeline::dispatch($asset);

        return redirect()->back()->with('success', "Version {$newVersion->version_number} uploaded.");
    }

    /**
     * Restore a previous version of an asset.
     * PATCH /assets/{id}/versions/{versionId}/restore
     */
    public function restoreVersion(string $id, string $versionId, Request $request)
    {
        $asset   = Asset::findOrFail($id);
        $version = $asset->versions()->findOrFail($versionId);
        $user    = $request->user();

        // Create a new version entry for the rollback
        $latestVersion = $asset->versions()->max('version_number') ?? 0;
        $asset->versions()->create([
            'version_number' => $latestVersion + 1,
            'file_path'      => $version->file_path,
            'file_size'      => $version->file_size,
            'sha256_hash'    => $version->sha256_hash,
            'uploaded_by'    => $user->id,
            'change_notes'   => "Rolled back to version {$version->version_number}",
        ]);

        // Update the asset to point to the restored version's file
        $asset->update([
            'file_size'      => $version->file_size,
            'sha256_hash'    => $version->sha256_hash,
            'storage_path'   => $version->file_path,
            'original_path'  => $version->file_path,
            'pipeline_status'=> 'queued',
            'preview_status' => 'pending',
        ]);

        activity()
            ->causedBy($user)
            ->performedOn($asset)
            ->log("Rolled back to version {$version->version_number}");

        ProcessAssetPipeline::dispatch($asset);

        return redirect()->back()->with('success', "Restored to version {$version->version_number}.");
    }

    // ══════════════════════════════════════════════════════════════
    //  API TOKEN MANAGEMENT  (REQ-01: Sanctum Bearer Tokens)
    // ══════════════════════════════════════════════════════════════

    /**
     * Create a new personal-access token.
     * POST /settings/tokens
     */
    public function createToken(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:100',
            'abilities'   => 'nullable|array',
            'abilities.*' => 'string|max:40',
        ]);

        $user = $request->user();
        $abilities = $request->input('abilities', ['*']);
        $token = $user->createToken($request->input('name'), $abilities);

        activity()
            ->causedBy($user)
            ->log("Created API token: {$request->input('name')}");

        return back()->with('flash', [
            'token'     => $token->plainTextToken,
            'tokenName' => $request->input('name'),
            'message'   => "Token created. Copy it now — it will not be shown again.",
        ]);
    }

    /**
     * Revoke (delete) a personal-access token.
     * DELETE /settings/tokens/{id}
     */
    public function revokeToken(string $id, Request $request)
    {
        $user = $request->user();
        $token = $user->tokens()->findOrFail($id);
        $tokenName = $token->name;

        $token->delete();

        activity()
            ->causedBy($user)
            ->log("Revoked API token: {$tokenName}");

        return back()->with('success', "Token '{$tokenName}' revoked.");
    }

    // ══════════════════════════════════════════════════════════════
    //  BACKUP MANAGEMENT  (REQ-16: Disaster Recovery)
    // ══════════════════════════════════════════════════════════════

    /**
     * List backup files in storage/app/backups.
     * GET /settings/backups
     */
    public function listBackups()
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $files = collect($disk->files('backups'))
            ->filter(fn ($f) => str_ends_with($f, '.zip') || str_ends_with($f, '.sql') || str_ends_with($f, '.gz'))
            ->sortByDesc(fn ($f) => $disk->lastModified($f))
            ->values()
            ->map(fn ($f) => [
                'filename'  => basename($f),
                'path'      => $f,
                'size'      => $disk->size($f),
                'sizeHuman' => $this->formatBytes($disk->size($f)),
                'date'      => \Carbon\Carbon::createFromTimestamp($disk->lastModified($f))->toIso8601String(),
                'dateHuman' => \Carbon\Carbon::createFromTimestamp($disk->lastModified($f))->diffForHumans(),
                'type'      => str_contains(basename($f), 'db') ? 'Database' : (str_contains(basename($f), 'full') ? 'Full' : 'Files'),
            ]);

        return response()->json(['backups' => $files]);
    }

    /**
     * Trigger an on-demand backup.
     * POST /settings/backups
     */
    public function runBackup(Request $request)
    {
        $user = $request->user();

        try {
            \Illuminate\Support\Facades\Artisan::call('gam:backup');

            activity()
                ->causedBy($user)
                ->log('Triggered manual backup');

            return back()->with('success', 'Backup started successfully.');
        } catch (\Throwable $e) {
            return back()->with('error', 'Backup failed: ' . $e->getMessage());
        }
    }

    /**
     * Delete a backup file.
     * DELETE /settings/backups/{filename}
     */
    public function deleteBackup(string $filename, Request $request)
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $path = 'backups/' . basename($filename);

        if (!$disk->exists($path)) {
            return back()->with('error', 'Backup not found.');
        }

        $disk->delete($path);

        activity()
            ->causedBy($request->user())
            ->log("Deleted backup: {$filename}");

        return back()->with('success', "Backup '{$filename}' deleted.");
    }

    // ══════════════════════════════════════════════════════════════
    //  MASTER REPLACEMENT  (REQ-03: Replace with New Version)
    // ══════════════════════════════════════════════════════════════

    /**
     * Replace existing asset with file from a duplicate upload.
     * POST /assets/{id}/replace
     * Accepts a file upload which becomes the new version.
     */
    public function replaceWithVersion(string $id, Request $request)
    {
        $request->validate([
            'file' => 'required|file|max:512000',
        ]);

        $asset = Asset::findOrFail($id);
        $user  = $request->user();
        $file  = $request->file('file');

        // Store the new file
        $path = $file->store('assets', 'local');
        $hash = hash_file('sha256', $file->getRealPath());
        $size = $file->getSize();

        // Bump version
        $latestVersion = $asset->versions()->max('version_number') ?? 0;
        $asset->versions()->create([
            'version_number' => $latestVersion + 1,
            'file_path'      => $path,
            'file_size'      => $size,
            'sha256_hash'    => $hash,
            'uploaded_by'    => $user->id,
            'change_notes'   => 'Replaced via duplicate detection (master replacement)',
        ]);

        // Point asset to new file
        $asset->update([
            'storage_path'    => $path,
            'original_path'   => $path,
            'file_size'       => $size,
            'sha256_hash'     => $hash,
            'pipeline_status' => 'queued',
            'preview_status'  => 'pending',
        ]);

        activity()
            ->causedBy($user)
            ->performedOn($asset)
            ->log("Replaced master with new version (v" . ($latestVersion + 1) . ")");

        ProcessAssetPipeline::dispatch($asset);

        return response()->json([
            'message' => "Asset replaced. New version v" . ($latestVersion + 1) . " queued for processing.",
            'id'      => $asset->id,
        ]);
    }

    // ── Helpers ──────────────────────────────────────────────────

    private function formatBytes(int $bytes): string
    {
        if ($bytes === 0) return '0 B';
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = floor(log($bytes, 1024));
        return round($bytes / pow(1024, $i), 1) . ' ' . $units[$i];
    }

    // ── Trilium Notes (REQ-25) ──────────────────────────────────

    /**
     * Create a new note in Trilium.
     * POST /notes
     */
    public function createNote(Request $request)
    {
        $request->validate([
            'content' => 'required|string|max:5000',
            'tag'     => 'nullable|string|max:100',
        ]);

        $triliumUrl = config('services.trilium.url');

        // Find the GAM Knowledge Base parent note
        $kbNoteId = null;
        try {
            $rootResp = Http::timeout(5)->get($triliumUrl . '/etapi/notes/root');
            if ($rootResp->successful()) {
                foreach ($rootResp->json('childNoteIds', []) as $childId) {
                    if (str_starts_with($childId, '_')) continue;
                    $childResp = Http::timeout(3)->get($triliumUrl . '/etapi/notes/' . $childId);
                    if ($childResp->successful() && ($childResp->json('title') === 'GAM Knowledge Base')) {
                        $kbNoteId = $childId;
                        break;
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Trilium: cannot find KB parent: ' . $e->getMessage());
        }

        if (!$kbNoteId) {
            return back()->with('error', 'Cannot connect to Trilium. Please try again.');
        }

        // Determine where to place the new note
        $parentNoteId = $kbNoteId; // default: directly under GAM KB
        $tag = $request->input('tag');

        if ($tag) {
            try {
                $kbResp = Http::timeout(3)->get($triliumUrl . '/etapi/notes/' . $kbNoteId);
                if ($kbResp->successful()) {
                    $kbChildren = $kbResp->json('childNoteIds', []);
                    $tagLower = mb_strtolower($tag);

                    // Search for an existing note whose title CONTAINS the tag keyword
                    // e.g. tag "Onboarding" matches "New Team Member Onboarding"
                    foreach ($kbChildren as $catId) {
                        $catResp = Http::timeout(3)->get($triliumUrl . '/etapi/notes/' . $catId);
                        if (!$catResp->successful()) continue;
                        $catTitle = $catResp->json('title', '');

                        if (str_contains(mb_strtolower($catTitle), $tagLower)) {
                            $parentNoteId = $catId;
                            break;
                        }
                    }
                }
            } catch (\Throwable $e) {
                \Log::warning('Trilium: category lookup failed: ' . $e->getMessage());
            }
        }

        $user = $request->user();
        $title = mb_substr(strip_tags($request->content), 0, 60);
        $html = '<p><strong>' . e($user->name) . ':</strong> ' . nl2br(e($request->content)) . '</p>';

        try {
            $resp = Http::timeout(5)
                ->post($triliumUrl . '/etapi/create-note', [
                    'parentNoteId' => $parentNoteId,
                    'title'        => $title,
                    'type'         => 'text',
                    'content'      => $html,
                ]);

            if (!$resp->successful()) {
                return back()->with('error', 'Failed to create note in Trilium.');
            }

            // Store the tag as a Trilium label attribute so it survives regardless of title
            if ($tag) {
                $newNote = $resp->json('note', $resp->json());
                $newNoteId = $newNote['noteId'] ?? null;
                if ($newNoteId) {
                    try {
                        Http::timeout(3)->post($triliumUrl . '/etapi/attributes', [
                            'noteId' => $newNoteId,
                            'type'   => 'label',
                            'name'   => 'gamTag',
                            'value'  => $tag,
                        ]);
                    } catch (\Throwable $e) {
                        \Log::warning('Trilium: gamTag attribute failed: ' . $e->getMessage());
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::error('Trilium create note error: ' . $e->getMessage());
            return back()->with('error', 'Trilium connection failed.');
        }

        // Clear the tree cache so the new note appears
        Cache::forget('gam.trilium.tree');

        return back()->with('success', 'Note created.');
    }

    /**
     * Create a page in BookStack via API.
     * POST /documents
     */
    public function createDocument(Request $request)
    {
        $request->validate([
            'title'      => 'required|string|max:255',
            'book_id'    => 'required_without:chapter_id|nullable|integer',
            'chapter_id' => 'nullable|integer',
            'content'    => 'required|string|max:50000',
            'tags'       => 'nullable|array',
            'tags.*.name'  => 'required_with:tags|string|max:100',
            'tags.*.value' => 'nullable|string|max:250',
        ]);

        $bs = config('services.bookstack');
        $authHeader = 'Token ' . $bs['token_id'] . ':' . $bs['token_secret'];

        try {
            $html = '<p>' . nl2br(e($request->content)) . '</p>';

            $payload = [
                'name' => $request->title,
                'html' => $html,
            ];

            // Place in chapter or directly in book
            if ($request->chapter_id) {
                $payload['chapter_id'] = (int) $request->chapter_id;
            } else {
                $payload['book_id'] = (int) $request->book_id;
            }

            // Tags
            if ($request->tags && count($request->tags) > 0) {
                $payload['tags'] = collect($request->tags)->map(fn ($t) => [
                    'name'  => $t['name'],
                    'value' => $t['value'] ?? '',
                ])->values()->toArray();
            }

            $resp = Http::timeout(10)
                ->withHeaders(['Authorization' => $authHeader])
                ->post($bs['url'] . '/api/pages', $payload);

            if (!$resp->successful()) {
                \Log::error('BookStack create page failed: ' . $resp->body());
                return back()->with('error', 'Failed to create page in BookStack.');
            }
        } catch (\Throwable $e) {
            \Log::error('BookStack create page error: ' . $e->getMessage());
            return back()->with('error', 'BookStack connection failed.');
        }

        Cache::forget('gam.bookstack.hierarchy');
        return back()->with('success', 'Page created successfully.');
    }

    /**
     * Create a book in BookStack via API.
     * POST /documents/books
     */
    public function createBook(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'tags'        => 'nullable|array',
            'tags.*.name'  => 'required_with:tags|string|max:100',
            'tags.*.value' => 'nullable|string|max:250',
        ]);

        $bs = config('services.bookstack');
        $authHeader = 'Token ' . $bs['token_id'] . ':' . $bs['token_secret'];

        try {
            $payload = [
                'name'        => $request->name,
                'description' => $request->description ?? '',
            ];

            if ($request->tags && count($request->tags) > 0) {
                $payload['tags'] = collect($request->tags)->map(fn ($t) => [
                    'name'  => $t['name'],
                    'value' => $t['value'] ?? '',
                ])->values()->toArray();
            }

            $resp = Http::timeout(10)
                ->withHeaders(['Authorization' => $authHeader])
                ->post($bs['url'] . '/api/books', $payload);

            if (!$resp->successful()) {
                \Log::error('BookStack create book failed: ' . $resp->body());
                return back()->with('error', 'Failed to create book in BookStack.');
            }
        } catch (\Throwable $e) {
            \Log::error('BookStack create book error: ' . $e->getMessage());
            return back()->with('error', 'BookStack connection failed.');
        }

        Cache::forget('gam.bookstack.hierarchy');
        return back()->with('success', 'Book created successfully.');
    }

    /**
     * Create a chapter in BookStack via API.
     * POST /documents/chapters
     */
    public function createChapter(Request $request)
    {
        $request->validate([
            'name'        => 'required|string|max:255',
            'book_id'     => 'required|integer',
            'description' => 'nullable|string|max:1000',
            'tags'        => 'nullable|array',
            'tags.*.name'  => 'required_with:tags|string|max:100',
            'tags.*.value' => 'nullable|string|max:250',
        ]);

        $bs = config('services.bookstack');
        $authHeader = 'Token ' . $bs['token_id'] . ':' . $bs['token_secret'];

        try {
            $payload = [
                'book_id'     => (int) $request->book_id,
                'name'        => $request->name,
                'description' => $request->description ?? '',
            ];

            if ($request->tags && count($request->tags) > 0) {
                $payload['tags'] = collect($request->tags)->map(fn ($t) => [
                    'name'  => $t['name'],
                    'value' => $t['value'] ?? '',
                ])->values()->toArray();
            }

            $resp = Http::timeout(10)
                ->withHeaders(['Authorization' => $authHeader])
                ->post($bs['url'] . '/api/chapters', $payload);

            if (!$resp->successful()) {
                \Log::error('BookStack create chapter failed: ' . $resp->body());
                return back()->with('error', 'Failed to create chapter in BookStack.');
            }
        } catch (\Throwable $e) {
            \Log::error('BookStack create chapter error: ' . $e->getMessage());
            return back()->with('error', 'BookStack connection failed.');
        }

        Cache::forget('gam.bookstack.hierarchy');
        return back()->with('success', 'Chapter created successfully.');
    }

    /**
     * Like a note (no-op placeholder — Trilium has no likes, just acknowledges).
     * POST /notes/{id}/like
     */
    public function likeNote(string $id)
    {
        $userId = auth()->id();
        $exists = \Illuminate\Support\Facades\DB::table('note_likes')
            ->where('user_id', $userId)
            ->where('note_id', $id)
            ->exists();

        if ($exists) {
            \Illuminate\Support\Facades\DB::table('note_likes')
                ->where('user_id', $userId)
                ->where('note_id', $id)
                ->delete();
        } else {
            \Illuminate\Support\Facades\DB::table('note_likes')->insert([
                'user_id'    => $userId,
                'note_id'    => $id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return back();
    }

    /**
     * Fetch a single BookStack page's HTML content for inline viewing.
     */
    public function documentContent(int $id)
    {
        try {
            $bs = config('services.bookstack');
            $authHeader = 'Token ' . $bs['token_id'] . ':' . $bs['token_secret'];

            $resp = Http::timeout(5)
                ->withHeaders(['Authorization' => $authHeader])
                ->get($bs['url'] . '/api/pages/' . $id);

            if ($resp->successful()) {
                $page = $resp->json();
                return response()->json([
                    'title'   => $page['name'] ?? '',
                    'html'    => $page['html'] ?? '<p>' . e($page['markdown'] ?? 'No content') . '</p>',
                    'updated' => $page['updated_at'] ?? null,
                ]);
            }

            return response()->json(['error' => 'Page not found'], 404);
        } catch (\Throwable $e) {
            \Log::warning('BookStack page fetch error: ' . $e->getMessage());
            return response()->json(['error' => 'Could not load document'], 500);
        }
    }

    /**
     * Reply to a Trilium note by creating a child note.
     */
    public function replyToNote(Request $request, string $id)
    {
        $request->validate(['content' => 'required|string|max:2000']);

        try {
            $triliumUrl = config('services.trilium.url');

            $resp = Http::timeout(10)
                ->withHeaders(['Content-Type' => 'application/json'])
                ->post($triliumUrl . '/etapi/create-note', [
                    'parentNoteId' => $id,
                    'title'        => 'Reply by ' . (auth()->user()->name ?? 'User'),
                    'type'         => 'text',
                    'content'      => $request->input('content'),
                ]);

            if ($resp->successful()) {
                return back();
            }

            return back()->withErrors(['message' => 'Failed to post reply']);
        } catch (\Throwable $e) {
            \Log::warning('Trilium reply error: ' . $e->getMessage());
            return back()->withErrors(['message' => 'Trilium service unavailable']);
        }
    }

    // ══════════════════════════════════════════════════════════════
    //  DOCUMENTS — CACHE, LINKING, GRAPH (REQ-24 enhanced)
    // ══════════════════════════════════════════════════════════════

    public function documentsRefreshCache()
    {
        Cache::forget('gam.bookstack.hierarchy');
        return back()->with('success', 'Document tree refreshed.');
    }

    public function documentPageAssets(int $pageId)
    {
        $assets = DB::table('bookstack_links')
            ->join('assets', 'bookstack_links.asset_id', '=', 'assets.id')
            ->where('bookstack_links.bookstack_page_id', $pageId)
            ->where('assets.review_status', 'approved')
            ->select(
                'assets.id',
                'assets.original_filename',
                'assets.file_extension',
                'assets.group_classification',
                'assets.thumbnail_path',
                'assets.file_size',
                'bookstack_links.id as link_id',
                'bookstack_links.page_title'
            )
            ->get();

        return response()->json($assets);
    }

    public function documentGraph(Request $request)
    {
        $user = $request->user();
        $allowedGroups = $user->hasRole('Admin')
            ? array_keys(config('gam.groups', []))
            : ($user->allowed_groups ?? []);

        $links = DB::table('bookstack_links')
            ->join('assets', 'bookstack_links.asset_id', '=', 'assets.id')
            ->where('assets.review_status', 'approved')
            ->when(!$user->hasRole('Admin'), function ($q) use ($allowedGroups) {
                $q->whereIn('assets.group_classification', $allowedGroups);
            })
            ->select(
                'bookstack_links.bookstack_page_id',
                'bookstack_links.page_title',
                'bookstack_links.page_url',
                'assets.id as asset_id',
                'assets.original_filename',
                'assets.group_classification'
            )
            ->get();

        $nodes = [];
        $edges = [];
        $seenPages = [];
        $seenAssets = [];

        $groupColors = [
            'FOOD' => '#22c55e', 'MEDIA' => '#a855f7', 'GENBUS' => '#3b82f6',
            'GEO' => '#14b8a6', 'NATURE' => '#10b981', 'LIFE' => '#ec4899',
            'SPEC' => '#f97316',
        ];

        foreach ($links as $link) {
            $pageKey = 'page-' . $link->bookstack_page_id;
            if (!isset($seenPages[$pageKey])) {
                $nodes[] = [
                    'id' => $pageKey,
                    'type' => 'default',
                    'position' => ['x' => rand(0, 600), 'y' => rand(0, 400)],
                    'data' => [
                        'label' => $link->page_title,
                        'nodeType' => 'page',
                        'pageId' => $link->bookstack_page_id,
                        'url' => $link->page_url,
                    ],
                    'style' => ['background' => '#6366F1', 'color' => '#fff', 'borderRadius' => '8px', 'padding' => '10px'],
                ];
                $seenPages[$pageKey] = true;
            }

            $assetKey = 'asset-' . $link->asset_id;
            if (!isset($seenAssets[$assetKey])) {
                $color = $groupColors[$link->group_classification] ?? '#64748b';
                $nodes[] = [
                    'id' => $assetKey,
                    'type' => 'default',
                    'position' => ['x' => rand(0, 600), 'y' => rand(0, 400)],
                    'data' => [
                        'label' => $link->original_filename,
                        'nodeType' => 'asset',
                        'assetId' => $link->asset_id,
                        'group' => $link->group_classification,
                    ],
                    'style' => ['background' => $color, 'color' => '#fff', 'borderRadius' => '8px', 'padding' => '10px'],
                ];
                $seenAssets[$assetKey] = true;
            }

            $edges[] = [
                'id' => 'e-' . $link->bookstack_page_id . '-' . $link->asset_id,
                'source' => $pageKey,
                'target' => $assetKey,
                'label' => 'linked_to',
                'animated' => true,
            ];
        }

        return response()->json(['nodes' => $nodes, 'edges' => $edges]);
    }

    public function linkAssetToDocument(Request $request)
    {
        if (!$request->user()->hasRole('Admin')) abort(403);

        $request->validate([
            'asset_id' => 'required|integer|exists:assets,id',
            'bookstack_page_id' => 'required|integer',
            'page_title' => 'required|string|max:255',
        ]);

        DB::table('bookstack_links')->insertOrIgnore([
            'asset_id' => $request->asset_id,
            'bookstack_page_id' => $request->bookstack_page_id,
            'page_title' => $request->page_title,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        activity()->causedBy($request->user())->log('Linked asset #' . $request->asset_id . ' to BookStack page #' . $request->bookstack_page_id);

        return back()->with('success', 'Asset linked to document.');
    }

    public function unlinkAssetFromDocument(Request $request, int $id)
    {
        if (!$request->user()->hasRole('Admin')) abort(403);

        DB::table('bookstack_links')->where('id', $id)->delete();
        activity()->causedBy($request->user())->log('Unlinked bookstack_link #' . $id);

        return back()->with('success', 'Asset unlinked from document.');
    }

    // ══════════════════════════════════════════════════════════════
    //  NOTES — CACHE, LINKING, KNOWLEDGE GRAPH (REQ-25 enhanced)
    // ══════════════════════════════════════════════════════════════

    public function notesRefreshCache()
    {
        Cache::forget('gam.trilium.tree');
        return back()->with('success', 'Note tree refreshed.');
    }

    public function noteAssets(string $noteId)
    {
        $assets = DB::table('trilium_links')
            ->join('assets', 'trilium_links.asset_id', '=', 'assets.id')
            ->where('trilium_links.trilium_note_id', $noteId)
            ->where('assets.review_status', 'approved')
            ->select(
                'assets.id',
                'assets.original_filename',
                'assets.file_extension',
                'assets.group_classification',
                'assets.thumbnail_path',
                'assets.file_size',
                'trilium_links.id as link_id'
            )
            ->get();

        return response()->json($assets);
    }

    public function knowledgeGraph(Request $request)
    {
        $user = $request->user();
        $allowedGroups = $user->hasRole('Admin')
            ? array_keys(config('gam.groups', []))
            : ($user->allowed_groups ?? []);

        $data = Cache::remember('gam.knowledge.graph', 120, function () use ($user, $allowedGroups) {
            $nodes = [];
            $edges = [];
            $seenAssets = [];

            $groupColors = [
                'FOOD' => '#22c55e', 'MEDIA' => '#a855f7', 'GENBUS' => '#3b82f6',
                'GEO' => '#14b8a6', 'NATURE' => '#10b981', 'LIFE' => '#ec4899',
                'SPEC' => '#f97316',
            ];

            // Trilium links
            $triliumLinks = DB::table('trilium_links')
                ->join('assets', 'trilium_links.asset_id', '=', 'assets.id')
                ->where('assets.review_status', 'approved')
                ->when(!$user->hasRole('Admin'), function ($q) use ($allowedGroups) {
                    $q->whereIn('assets.group_classification', $allowedGroups);
                })
                ->select(
                    'trilium_links.trilium_note_id as note_id',
                    'trilium_links.note_title',
                    'assets.id as asset_id',
                    'assets.original_filename',
                    'assets.group_classification'
                )
                ->get();

            $seenNotes = [];
            foreach ($triliumLinks as $link) {
                $noteKey = 'note-' . $link->note_id;
                if (!isset($seenNotes[$noteKey])) {
                    $nodes[] = [
                        'id' => $noteKey,
                        'type' => 'default',
                        'position' => ['x' => rand(0, 800), 'y' => rand(0, 600)],
                        'data' => [
                            'label' => $link->note_title,
                            'nodeType' => 'note',
                            'noteId' => $link->note_id,
                        ],
                        'style' => ['background' => '#f59e0b', 'color' => '#fff', 'borderRadius' => '50%', 'padding' => '10px', 'width' => '80px', 'height' => '80px'],
                    ];
                    $seenNotes[$noteKey] = true;
                }

                $assetKey = 'asset-' . $link->asset_id;
                if (!isset($seenAssets[$assetKey])) {
                    $color = $groupColors[$link->group_classification] ?? '#64748b';
                    $nodes[] = [
                        'id' => $assetKey,
                        'type' => 'default',
                        'position' => ['x' => rand(0, 800), 'y' => rand(0, 600)],
                        'data' => [
                            'label' => $link->original_filename,
                            'nodeType' => 'asset',
                            'assetId' => $link->asset_id,
                            'group' => $link->group_classification,
                        ],
                        'style' => ['background' => $color, 'color' => '#fff', 'borderRadius' => '8px', 'padding' => '10px'],
                    ];
                    $seenAssets[$assetKey] = true;
                }

                $edges[] = [
                    'id' => 'te-' . $link->note_id . '-' . $link->asset_id,
                    'source' => $noteKey,
                    'target' => $assetKey,
                    'label' => 'linked_to',
                    'animated' => true,
                ];
            }

            // BookStack links
            $bsLinks = DB::table('bookstack_links')
                ->join('assets', 'bookstack_links.asset_id', '=', 'assets.id')
                ->where('assets.review_status', 'approved')
                ->when(!$user->hasRole('Admin'), function ($q) use ($allowedGroups) {
                    $q->whereIn('assets.group_classification', $allowedGroups);
                })
                ->select(
                    'bookstack_links.bookstack_page_id',
                    'bookstack_links.page_title',
                    'assets.id as asset_id',
                    'assets.original_filename',
                    'assets.group_classification'
                )
                ->get();

            $seenPages = [];
            foreach ($bsLinks as $link) {
                $pageKey = 'page-' . $link->bookstack_page_id;
                if (!isset($seenPages[$pageKey])) {
                    $nodes[] = [
                        'id' => $pageKey,
                        'type' => 'default',
                        'position' => ['x' => rand(0, 800), 'y' => rand(0, 600)],
                        'data' => [
                            'label' => $link->page_title,
                            'nodeType' => 'page',
                            'pageId' => $link->bookstack_page_id,
                        ],
                        'style' => ['background' => '#6366F1', 'color' => '#fff', 'borderRadius' => '8px', 'padding' => '10px'],
                    ];
                    $seenPages[$pageKey] = true;
                }

                $assetKey = 'asset-' . $link->asset_id;
                if (!isset($seenAssets[$assetKey])) {
                    $color = $groupColors[$link->group_classification] ?? '#64748b';
                    $nodes[] = [
                        'id' => $assetKey,
                        'type' => 'default',
                        'position' => ['x' => rand(0, 800), 'y' => rand(0, 600)],
                        'data' => [
                            'label' => $link->original_filename,
                            'nodeType' => 'asset',
                            'assetId' => $link->asset_id,
                            'group' => $link->group_classification,
                        ],
                        'style' => ['background' => $color, 'color' => '#fff', 'borderRadius' => '8px', 'padding' => '10px'],
                    ];
                    $seenAssets[$assetKey] = true;
                }

                $edges[] = [
                    'id' => 'be-' . $link->bookstack_page_id . '-' . $link->asset_id,
                    'source' => $pageKey,
                    'target' => $assetKey,
                    'label' => 'linked_to',
                    'animated' => true,
                ];
            }

            return ['nodes' => $nodes, 'edges' => $edges];
        });

        return response()->json($data);
    }

    public function linkAssetToNote(Request $request)
    {
        if (!$request->user()->hasRole('Admin')) abort(403);

        $request->validate([
            'asset_id' => 'required|integer|exists:assets,id',
            'trilium_note_id' => 'required|string|max:50',
            'note_title' => 'required|string|max:255',
        ]);

        DB::table('trilium_links')->insertOrIgnore([
            'asset_id' => $request->asset_id,
            'trilium_note_id' => $request->trilium_note_id,
            'note_title' => $request->note_title,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        activity()->causedBy($request->user())->log('Linked asset #' . $request->asset_id . ' to Trilium note ' . $request->trilium_note_id);

        return back()->with('success', 'Asset linked to note.');
    }

    public function unlinkAssetFromNote(Request $request, int $id)
    {
        if (!$request->user()->hasRole('Admin')) abort(403);

        DB::table('trilium_links')->where('id', $id)->delete();
        activity()->causedBy($request->user())->log('Unlinked trilium_link #' . $id);

        return back()->with('success', 'Asset unlinked from note.');
    }
}
