<?php

namespace App\Jobs;

use App\Models\Asset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use App\Exceptions\PipelineCancelledException;
use App\Jobs\AiTagAsset;
use App\Jobs\ApplyTaxonomy;

/**
 * ProcessAssetPipeline — orchestrates the full processing chain for an uploaded asset.
 *
 * Pipeline stages (in order):
 *   1. hashing        — verify SHA-256 integrity
 *   2. previewing     — generate thumbnail + medium preview
 *   3. tagging        — AI classification (Phase 6)
 *   4. classifying    — taxonomy rule matching (Phase 6)
 *   5. indexing        — search index update (Phase 6)
 *   6. done           — mark asset as fully processed
 *
 * Stages 3-5 are stubs that will be implemented in later phases.
 * For now, this job handles hashing + preview generation and marks the asset done.
 */
class ProcessAssetPipeline implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 600; // 10 minutes — generous to avoid mid-job kills

    public function __construct(
        public Asset $asset
    ) {}

    public function handle(): void
    {
        $asset = $this->asset;

        try {
            // ── Stage 1: Hashing (verify integrity) ─────────────────
            $this->updateStatus('hashing');
            $this->verifyHash($asset);

            // ── Stage 2: Preview Generation ─────────────────────────
            $this->updateStatus('previewing');
            GeneratePreview::dispatchSync($asset);

            // ── Stage 3: AI Tagging (non-blocking — fallback on failure) ──
            $this->updateStatus('tagging');
            try {
                AiTagAsset::dispatchSync($asset);
            } catch (\Throwable $e) {
                Log::warning("ProcessAssetPipeline: AI tagging failed, continuing with fallback", [
                    'asset' => $asset->id,
                    'error' => $e->getMessage(),
                ]);
                // AiTagAsset::failed() already applies fallback tags,
                // but call it explicitly in case dispatchSync didn't trigger it.
                $asset->refresh();
                if ($asset->tags()->count() === 0) {
                    (new AiTagAsset($asset))->failed($e);
                }
            }

            // ── Stage 4: Taxonomy Classification ────────────────────
            $this->updateStatus('classifying');
            ApplyTaxonomy::dispatchSync($asset);

            // ── Stage 5: Indexing (Phase 7 — Meilisearch) ────────────
            $this->updateStatus('indexing');
            // Meilisearch full-text search indexing — deferred to Phase 7
            Log::info("ProcessAssetPipeline: Indexing skipped (Phase 7)", ['asset' => $asset->id]);

            // ── Stage 6: Move to production storage ─────────────────
            $this->moveToProduction($asset);

            // ── Done ────────────────────────────────────────────────
            $asset->update([
                'pipeline_status' => 'done',
                'review_status'   => 'pending',
            ]);

            activity()
                ->performedOn($asset)
                ->log("Pipeline completed for: {$asset->original_filename}");

            Log::info("ProcessAssetPipeline: Completed", ['asset' => $asset->id]);

        } catch (PipelineCancelledException $e) {
            // User cancelled — exit gracefully, don't retry
            Log::info("ProcessAssetPipeline: Cancelled by user", ['asset' => $asset->id]);
        } catch (\Throwable $e) {
            $asset->update(['pipeline_status' => 'failed']);

            activity()
                ->performedOn($asset)
                ->withProperties(['error' => $e->getMessage()])
                ->log("Pipeline failed for: {$asset->original_filename}");

            Log::error("ProcessAssetPipeline: Failed", [
                'asset' => $asset->id,
                'error' => $e->getMessage(),
            ]);

            throw $e; // Re-throw for retry
        }
    }

    /**
     * Verify file integrity by re-computing SHA-256 and comparing with stored hash.
     */
    private function verifyHash(Asset $asset): void
    {
        $disk = Storage::disk($asset->storage_disk);

        if (!$disk->exists($asset->storage_path)) {
            throw new \RuntimeException("File not found on disk [{$asset->storage_disk}]: {$asset->storage_path}");
        }

        $filePath = $disk->path($asset->storage_path);
        $computedHash = hash_file('sha256', $filePath);

        if ($computedHash !== $asset->sha256_hash) {
            throw new \RuntimeException(
                "SHA-256 mismatch for asset {$asset->id}: expected {$asset->sha256_hash}, got {$computedHash}"
            );
        }

        Log::info("ProcessAssetPipeline: Hash verified", ['asset' => $asset->id]);
    }

    /**
     * Move file from staging to production assets disk.
     */
    private function moveToProduction(Asset $asset): void
    {
        $stagingDisk = config('gam.storage.staging_disk', 'staging');
        $assetsDisk  = config('gam.storage.assets_disk', 'assets');

        // Skip if already on production disk
        if ($asset->storage_disk === $assetsDisk) {
            return;
        }

        $sourcePath = $asset->storage_path;
        $destPath   = 'processed/' . now()->format('Y/m/d') . '/' . basename($sourcePath);

        // Copy from staging → assets
        $contents = Storage::disk($stagingDisk)->get($sourcePath);
        Storage::disk($assetsDisk)->put($destPath, $contents);

        // Delete from staging
        Storage::disk($stagingDisk)->delete($sourcePath);

        // Update asset record
        $asset->update([
            'storage_disk' => $assetsDisk,
            'storage_path' => $destPath,
        ]);

        Log::info("ProcessAssetPipeline: Moved to production", [
            'asset' => $asset->id,
            'from'  => "{$stagingDisk}:{$sourcePath}",
            'to'    => "{$assetsDisk}:{$destPath}",
        ]);
    }

    /**
     * Update pipeline status on the asset.
     */
    private function updateStatus(string $status): void
    {
        $this->asset->refresh();
        if ($this->asset->pipeline_status === 'cancelled') {
            throw new PipelineCancelledException("Pipeline cancelled for asset {$this->asset->id}");
        }
        $this->asset->update(['pipeline_status' => $status]);
    }

    /**
     * Handle a failed job (all retries exhausted).
     */
    public function failed(\Throwable $exception): void
    {
        $this->asset->update(['pipeline_status' => 'failed']);

        Log::error("ProcessAssetPipeline: All retries exhausted", [
            'asset' => $this->asset->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
