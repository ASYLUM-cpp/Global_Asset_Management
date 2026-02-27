<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Jobs\ProcessAssetPipeline;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * REQ-05: API-Based File Import
 *
 * External systems authenticate via Sanctum Bearer token
 * and push files into the GAM pipeline.
 */
class ApiController extends Controller
{
    /**
     * POST /api/assets/import
     *
     * Accepts a file upload via multipart/form-data.
     * Returns 202 Accepted with a staging_id to poll status.
     */
    public function import(Request $request)
    {
        $request->validate([
            'file'        => 'required|file|max:512000', // 500 MB
            'description' => 'nullable|string|max:2000',
            'group'       => 'nullable|string|max:50',
            'tags'        => 'nullable|array',
            'tags.*'      => 'string|max:200',
            'collection'  => 'nullable|string|max:100',
        ]);

        $file = $request->file('file');
        $user = $request->user();

        // Hash for dedup
        $hash = hash_file('sha256', $file->getRealPath());

        $existing = Asset::where('sha256_hash', $hash)->first();
        if ($existing) {
            return response()->json([
                'error'   => 'duplicate',
                'message' => "Duplicate file detected. Already exists as: {$existing->original_filename}",
                'asset'   => [
                    'id'       => $existing->id,
                    'filename' => $existing->original_filename,
                    'group'    => $existing->group_classification,
                    'preview'  => "/preview/{$existing->id}",
                ],
            ], 409);
        }

        // Store to staging
        $stagingPath = $file->store('staging', 'local');
        $extension   = strtolower($file->getClientOriginalExtension());
        $mime        = $file->getClientMimeType();
        $size        = $file->getSize();

        // Create asset record
        $asset = Asset::create([
            'id'                  => (string) Str::uuid(),
            'original_filename'   => $file->getClientOriginalName(),
            'file_extension'      => $extension,
            'mime_type'           => $mime,
            'file_size'           => $size,
            'sha256_hash'         => $hash,
            'storage_path'        => $stagingPath,
            'pipeline_status'     => 'queued',
            'review_status'       => 'none',
            'description'         => $request->input('description'),
            'group_classification' => $request->input('group'),
            'uploaded_by'         => $user->id,
            'ingested_at'         => now(),
        ]);

        // Create v1
        $asset->versions()->create([
            'version'      => 1,
            'file_path'    => $stagingPath,
            'file_size'    => $size,
            'change_notes' => 'Initial upload via API',
            'uploaded_by'  => $user->id,
        ]);

        // Tags
        foreach ($request->input('tags', []) as $tagName) {
            $tagName = trim($tagName);
            if (empty($tagName)) continue;
            $asset->tags()->create([
                'tag'           => $tagName,
                'source'        => 'manual',
                'confidence'    => 1.00,
                'auto_approved' => true,
            ]);
        }

        // Dispatch pipeline
        ProcessAssetPipeline::dispatch($asset);

        activity()
            ->causedBy($user)
            ->performedOn($asset)
            ->log("Imported asset via API: {$asset->original_filename}");

        return response()->json([
            'message'    => 'Asset accepted for processing.',
            'staging_id' => $asset->id,
            'status'     => 'queued',
        ], 202);
    }

    /**
     * GET /api/assets/status/{id}
     *
     * Poll the pipeline status of an imported asset.
     */
    public function status(string $id)
    {
        $asset = Asset::findOrFail($id);

        return response()->json([
            'id'              => $asset->id,
            'filename'        => $asset->original_filename,
            'pipeline_status' => $asset->pipeline_status,
            'review_status'   => $asset->review_status,
            'group'           => $asset->group_classification,
            'preview_ready'   => $asset->preview_status === 'done',
            'tags'            => $asset->tags()->pluck('tag')->toArray(),
            'created_at'      => $asset->ingested_at?->toIso8601String(),
        ]);
    }

    /**
     * GET /api/assets/{id}
     *
     * Full asset detail for external consumption.
     */
    public function show(string $id)
    {
        $asset = Asset::with(['tags', 'versions', 'uploader'])->findOrFail($id);

        return response()->json([
            'id'              => $asset->id,
            'filename'        => $asset->original_filename,
            'extension'       => $asset->file_extension,
            'mime'            => $asset->mime_type,
            'size'            => $asset->file_size,
            'size_formatted'  => $asset->file_size_formatted,
            'hash'            => $asset->sha256_hash,
            'group'           => $asset->group_classification,
            'description'     => $asset->description,
            'pipeline_status' => $asset->pipeline_status,
            'review_status'   => $asset->review_status,
            'preview_ready'   => $asset->preview_status === 'done',
            'uploaded_by'     => $asset->uploader?->name,
            'created_at'      => $asset->ingested_at?->toIso8601String(),
            'tags'            => $asset->tags->map(fn ($t) => [
                'tag'        => $t->tag,
                'source'     => $t->source,
                'confidence' => $t->confidence,
            ]),
            'versions'        => $asset->versions->map(fn ($v) => [
                'version'     => $v->version,
                'size'        => $v->file_size,
                'notes'       => $v->change_notes,
                'created_at'  => $v->created_at?->toIso8601String(),
            ]),
            'download_url'    => "/assets/{$asset->id}/download",
        ]);
    }

    /**
     * GET /api/assets
     *
     * List assets (paginated, role-scoped).
     */
    public function index(Request $request)
    {
        $user = $request->user();

        $assets = Asset::forUser($user)
            ->when($request->filled('group'), fn ($q) => $q->where('group_classification', $request->input('group')))
            ->when($request->filled('status'), fn ($q) => $q->where('review_status', $request->input('status')))
            ->when($request->filled('extension'), fn ($q) => $q->where('file_extension', $request->input('extension')))
            ->latest('ingested_at')
            ->paginate($request->input('per_page', 24));

        return response()->json($assets->through(fn ($a) => [
            'id'        => $a->id,
            'filename'  => $a->original_filename,
            'extension' => $a->file_extension,
            'size'      => $a->file_size_formatted,
            'group'     => $a->group_classification,
            'status'    => $a->review_status,
            'pipeline'  => $a->pipeline_status,
            'uploaded'  => $a->ingested_at?->toIso8601String(),
        ]));
    }

    /**
     * GET /api/user
     *
     * Return authenticated user info.
     */
    public function user(Request $request)
    {
        $user = $request->user();

        return response()->json([
            'id'    => $user->id,
            'name'  => $user->name,
            'email' => $user->email,
            'role'  => $user->primary_role,
            'token_name' => $user->currentAccessToken()?->name,
        ]);
    }
}
