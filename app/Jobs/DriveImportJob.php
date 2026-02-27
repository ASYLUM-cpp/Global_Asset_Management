<?php

namespace App\Jobs;

use App\Models\Asset;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * DriveImportJob — downloads a single file from Google Drive,
 * stores it in the staging disk, creates an Asset record,
 * and dispatches the standard ProcessAssetPipeline.
 */
class DriveImportJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries   = 2;
    public int $timeout = 300; // 5 minutes per file

    public function __construct(
        public User   $user,
        public string $fileId,
        public string $accessToken,
        public ?string $refreshToken = null,
    ) {}

    public function handle(): void
    {
        Log::info("DriveImportJob: starting", ['fileId' => $this->fileId, 'user' => $this->user->id]);

        // 1. Get file metadata from Google Drive
        $meta = $this->getFileMeta();
        if (!$meta) {
            Log::error("DriveImportJob: could not fetch metadata", ['fileId' => $this->fileId]);
            return;
        }

        $name     = $meta['name'];
        $mimeType = $meta['mimeType'];
        $size     = (int) ($meta['size'] ?? 0);

        // Skip Google Docs native formats (they have no downloadable binary)
        if (str_starts_with($mimeType, 'application/vnd.google-apps.')) {
            // Export Google Docs as PDF
            $exportMime = match ($mimeType) {
                'application/vnd.google-apps.document'     => 'application/pdf',
                'application/vnd.google-apps.spreadsheet'  => 'application/pdf',
                'application/vnd.google-apps.presentation' => 'application/pdf',
                default => null,
            };

            if (!$exportMime) {
                Log::info("DriveImportJob: skipping unsupported Google type", ['mime' => $mimeType, 'name' => $name]);
                return;
            }

            $content = $this->exportGoogleFile($exportMime);
            $name    = pathinfo($name, PATHINFO_FILENAME) . '.pdf';
            $mimeType = $exportMime;
        } else {
            // 2. Download the binary file content
            $content = $this->downloadFile();
        }

        if ($content === null) {
            Log::error("DriveImportJob: download failed", ['fileId' => $this->fileId, 'name' => $name]);
            return;
        }

        $size = strlen($content);

        // 3. Store in staging
        $stagingDisk = config('gam.storage.staging_disk', 'local');
        $extension   = strtolower(pathinfo($name, PATHINFO_EXTENSION)) ?: 'bin';
        $storagePath = 'uploads/' . now()->format('Y/m/d') . '/' . uniqid('gdrive_') . '.' . $extension;

        Storage::disk($stagingDisk)->put($storagePath, $content);
        unset($content); // free memory

        $hash = hash_file('sha256', Storage::disk($stagingDisk)->path($storagePath));

        // 4. Dedup check
        $existing = Asset::where('sha256_hash', $hash)->first();
        if ($existing) {
            Log::info("DriveImportJob: duplicate detected, skipping", [
                'fileId'   => $this->fileId,
                'existing' => $existing->id,
            ]);
            Storage::disk($stagingDisk)->delete($storagePath);
            return;
        }

        // 5. Create Asset record
        $asset = Asset::create([
            'original_filename'    => $name,
            'original_path'        => $storagePath,
            'file_extension'       => $extension,
            'file_size'            => $size,
            'mime_type'            => $mimeType,
            'sha256_hash'          => $hash,
            'upload_source'        => 'google_drive',
            'uploader_ip'          => '0.0.0.0',
            'ingested_at'          => now(),
            'pipeline_status'      => 'queued',
            'preview_status'       => 'pending',
            'review_status'        => 'pending',
            'is_master'            => true,
            'storage_disk'         => $stagingDisk,
            'storage_path'         => $storagePath,
            'uploaded_by'          => $this->user->id,
        ]);

        // 6. Create initial version
        $asset->versions()->create([
            'version_number' => 1,
            'file_path'      => $storagePath,
            'file_size'      => $size,
            'sha256_hash'    => $hash,
            'uploaded_by'    => $this->user->id,
            'change_notes'   => 'Imported from Google Drive',
        ]);

        activity()
            ->causedBy($this->user)
            ->performedOn($asset)
            ->log("Imported from Google Drive: {$name}");

        // 7. Dispatch pipeline
        ProcessAssetPipeline::dispatch($asset);

        Log::info("DriveImportJob: completed", ['fileId' => $this->fileId, 'asset' => $asset->id]);
    }

    // ── Google Drive API helpers ────────────────────────────────

    private function getFileMeta(): ?array
    {
        $resp = Http::withToken($this->accessToken)
            ->timeout(15)
            ->get("https://www.googleapis.com/drive/v3/files/{$this->fileId}", [
                'fields' => 'id,name,mimeType,size,modifiedTime',
            ]);

        return $resp->successful() ? $resp->json() : null;
    }

    private function downloadFile(): ?string
    {
        $resp = Http::withToken($this->accessToken)
            ->timeout($this->timeout)
            ->get("https://www.googleapis.com/drive/v3/files/{$this->fileId}", [
                'alt' => 'media',
            ]);

        return $resp->successful() ? $resp->body() : null;
    }

    private function exportGoogleFile(string $exportMime): ?string
    {
        $resp = Http::withToken($this->accessToken)
            ->timeout($this->timeout)
            ->get("https://www.googleapis.com/drive/v3/files/{$this->fileId}/export", [
                'mimeType' => $exportMime,
            ]);

        return $resp->successful() ? $resp->body() : null;
    }
}
