<?php

namespace App\Jobs;

use App\Models\Asset;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * GeneratePreview — creates thumbnail and medium-size preview for all asset types.
 *
 * Per REQ-02 / REQ-09: Routes different file formats to the appropriate tool:
 *   - JPG/PNG/GIF/WebP/BMP  → PHP GD (built-in, always available)
 *   - PDF                    → Poppler pdftoppm (or Ghostscript fallback)
 *   - PSD                    → ImageMagick convert
 *   - SVG                    → Inkscape headless (or direct serve)
 *   - AI/EPS                 → Ghostscript or Inkscape
 *   - MP4/MOV                → FFmpeg thumbnail extraction
 *   - DOCX/XLSX              → LibreOffice headless → PDF → Poppler
 *   - TIFF                   → ImageMagick (or GD if single-layer)
 *
 * If a CLI tool is not installed, the job marks preview_status as 'unsupported'
 * and continues — it does NOT block the rest of the pipeline.
 */
class GeneratePreview implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    /** Extension → handler method mapping */
    private const FORMAT_HANDLERS = [
        // GD-native images
        'jpg'  => 'handleGdImage',
        'jpeg' => 'handleGdImage',
        'jfif' => 'handleGdImage',
        'png'  => 'handleGdImage',
        'gif'  => 'handleGdImage',
        'webp' => 'handleGdImage',
        'bmp'  => 'handleGdImage',
        // CLI-tool formats
        'pdf'  => 'handlePdf',
        'psd'  => 'handleImageMagick',
        'tiff' => 'handleTiff',
        'tif'  => 'handleTiff',
        'svg'  => 'handleSvg',
        'ai'   => 'handleVectorAi',
        'eps'  => 'handleVectorEps',
        'mp4'  => 'handleVideo',
        'mov'  => 'handleVideo',
        'avi'  => 'handleVideo',
        'mkv'  => 'handleVideo',
        'doc'  => 'handleOffice',
        'docx' => 'handleOffice',
        'xls'  => 'handleOffice',
        'xlsx' => 'handleOffice',
    ];

    public function __construct(
        public Asset $asset
    ) {}

    public function handle(): void
    {
        $asset = $this->asset;
        $ext = strtolower($asset->file_extension ?? '');

        $handler = self::FORMAT_HANDLERS[$ext] ?? null;

        if (!$handler) {
            $asset->update(['preview_status' => 'unsupported']);
            Log::info("GeneratePreview: No handler for extension '{$ext}'", ['asset' => $asset->id]);
            return;
        }

        try {
            $sourceDisk = Storage::disk($asset->storage_disk);
            $sourcePath = $sourceDisk->path($asset->storage_path);

            if (!file_exists($sourcePath)) {
                throw new \RuntimeException("Source file not found: {$sourcePath}");
            }

            $previewsDisk = config('gam.storage.previews_disk', 'previews');
            $baseName = Str::slug(pathinfo($asset->original_filename, PATHINFO_FILENAME)) . '-' . $asset->id;
            $dateDir = now()->format('Y/m/d');

            $result = $this->$handler($sourcePath, $previewsDisk, $dateDir, $baseName);

            if ($result) {
                $asset->update([
                    'thumbnail_path' => $result['thumbnail'],
                    'preview_path'   => $result['preview'],
                    'preview_status' => 'done',
                ]);

                Log::info("GeneratePreview: Done ({$handler})", [
                    'asset' => $asset->id,
                    'ext'   => $ext,
                ]);
            } else {
                $asset->update(['preview_status' => 'unsupported']);
                Log::info("GeneratePreview: Handler returned null — tool not available", [
                    'asset'   => $asset->id,
                    'handler' => $handler,
                ]);
            }

        } catch (\Throwable $e) {
            $asset->update(['preview_status' => 'failed']);
            Log::error("GeneratePreview: Failed", [
                'asset' => $asset->id,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    // ════════════════════════════════════════════════════════════════
    //  FORMAT HANDLERS
    // ════════════════════════════════════════════════════════════════

    /**
     * Handle GD-native image formats (JPG, PNG, GIF, WebP, BMP).
     */
    private function handleGdImage(string $source, string $disk, string $dir, string $base): array
    {
        $thumbSize = config('gam.pipeline.preview_thumbnail_size', 300);
        $mediumWidth = config('gam.pipeline.preview_medium_size', 1200);

        $thumbPath = "{$dir}/{$base}-thumb.jpg";
        $previewPath = "{$dir}/{$base}-preview.jpg";

        $this->gdResize($source, $thumbSize, $thumbSize, $disk, $thumbPath);
        $this->gdResize($source, $mediumWidth, null, $disk, $previewPath);

        return ['thumbnail' => $thumbPath, 'preview' => $previewPath];
    }

    /**
     * Handle PDF files using Poppler (pdftoppm) or Ghostscript.
     */
    private function handlePdf(string $source, string $disk, string $dir, string $base): ?array
    {
        $tempPng = tempnam(sys_get_temp_dir(), 'gam_pdf_') . '.png';

        // Try pdftoppm (Poppler) first
        if ($this->commandExists('pdftoppm')) {
            $tempBase = Str::beforeLast($tempPng, '.png');
            $result = Process::timeout(30)->run([
                'pdftoppm', '-png', '-r', '150', '-singlefile', $source, $tempBase,
            ]);
            $tempPng = $tempBase . '.png';

            if ($result->successful() && file_exists($tempPng)) {
                return $this->generateFromPng($tempPng, $disk, $dir, $base);
            }
        }

        // Try Ghostscript
        if ($this->commandExists('gs') || $this->commandExists('gswin64c')) {
            $gsCmd = $this->commandExists('gswin64c') ? 'gswin64c' : 'gs';
            $result = Process::timeout(30)->run([
                $gsCmd, '-dNOPAUSE', '-dBATCH', '-dFirstPage=1', '-dLastPage=1',
                '-sDEVICE=png16m', '-r150', "-sOutputFile={$tempPng}", $source,
            ]);

            if ($result->successful() && file_exists($tempPng)) {
                return $this->generateFromPng($tempPng, $disk, $dir, $base);
            }
        }

        // Try ImageMagick convert
        if ($this->commandExists('magick') || $this->commandExists('convert')) {
            $cmd = $this->commandExists('magick') ? 'magick' : 'convert';
            $result = Process::timeout(30)->run([
                $cmd, "{$source}[0]", '-density', '150', '-flatten', $tempPng,
            ]);

            if ($result->successful() && file_exists($tempPng)) {
                return $this->generateFromPng($tempPng, $disk, $dir, $base);
            }
        }

        @unlink($tempPng);
        return null;
    }

    /**
     * Handle PSD files via ImageMagick.
     */
    private function handleImageMagick(string $source, string $disk, string $dir, string $base): ?array
    {
        $tempPng = tempnam(sys_get_temp_dir(), 'gam_psd_') . '.png';

        $cmd = $this->commandExists('magick') ? 'magick' : ($this->commandExists('convert') ? 'convert' : null);
        if (!$cmd) return null;

        $result = Process::timeout(30)->run([
            $cmd, "{$source}[0]", '-flatten', $tempPng,
        ]);

        if ($result->successful() && file_exists($tempPng)) {
            return $this->generateFromPng($tempPng, $disk, $dir, $base);
        }

        @unlink($tempPng);
        return null;
    }

    /**
     * Handle TIFF files — try ImageMagick first, fall back to GD.
     */
    private function handleTiff(string $source, string $disk, string $dir, string $base): ?array
    {
        $cmd = $this->commandExists('magick') ? 'magick' : ($this->commandExists('convert') ? 'convert' : null);
        if ($cmd) {
            $tempPng = tempnam(sys_get_temp_dir(), 'gam_tiff_') . '.png';
            $result = Process::timeout(30)->run([
                $cmd, "{$source}[0]", '-flatten', $tempPng,
            ]);

            if ($result->successful() && file_exists($tempPng)) {
                return $this->generateFromPng($tempPng, $disk, $dir, $base);
            }
            @unlink($tempPng);
        }

        return null;
    }

    /**
     * Handle SVG files — copy as-is for browser rendering + generate PNG fallback.
     */
    private function handleSvg(string $source, string $disk, string $dir, string $base): ?array
    {
        // SVGs can be served directly to browsers; store the SVG as "preview"
        $svgPath = "{$dir}/{$base}-preview.svg";
        Storage::disk($disk)->put($svgPath, file_get_contents($source));

        // Try Inkscape for PNG thumbnail
        if ($this->commandExists('inkscape')) {
            $tempPng = tempnam(sys_get_temp_dir(), 'gam_svg_') . '.png';
            $result = Process::timeout(30)->run([
                'inkscape', '--export-type=png', '--export-width=300',
                "--export-filename={$tempPng}", $source,
            ]);

            if ($result->successful() && file_exists($tempPng)) {
                $thumbPath = "{$dir}/{$base}-thumb.png";
                Storage::disk($disk)->put($thumbPath, file_get_contents($tempPng));
                @unlink($tempPng);
                return ['thumbnail' => $thumbPath, 'preview' => $svgPath];
            }
            @unlink($tempPng);
        }

        // Try ImageMagick as fallback
        $cmd = $this->commandExists('magick') ? 'magick' : ($this->commandExists('convert') ? 'convert' : null);
        if ($cmd) {
            $tempPng = tempnam(sys_get_temp_dir(), 'gam_svg_') . '.png';
            $result = Process::timeout(30)->run([
                $cmd, '-density', '150', '-resize', '300x300', $source, $tempPng,
            ]);

            if ($result->successful() && file_exists($tempPng)) {
                $thumbPath = "{$dir}/{$base}-thumb.png";
                Storage::disk($disk)->put($thumbPath, file_get_contents($tempPng));
                @unlink($tempPng);
                return ['thumbnail' => $thumbPath, 'preview' => $svgPath];
            }
            @unlink($tempPng);
        }

        // Serve SVG as both preview and thumb (browser can render it)
        return ['thumbnail' => $svgPath, 'preview' => $svgPath];
    }

    /**
     * Handle Adobe Illustrator (.AI) files — Inkscape or Ghostscript.
     */
    private function handleVectorAi(string $source, string $disk, string $dir, string $base): ?array
    {
        if ($this->commandExists('inkscape')) {
            $tempPng = tempnam(sys_get_temp_dir(), 'gam_ai_') . '.png';
            $result = Process::timeout(60)->run([
                'inkscape', '--export-type=png', '--export-width=1200',
                "--export-filename={$tempPng}", $source,
            ]);

            if ($result->successful() && file_exists($tempPng)) {
                return $this->generateFromPng($tempPng, $disk, $dir, $base);
            }
            @unlink($tempPng);
        }

        return $this->handlePdf($source, $disk, $dir, $base);
    }

    /**
     * Handle EPS files — Ghostscript preferred.
     */
    private function handleVectorEps(string $source, string $disk, string $dir, string $base): ?array
    {
        $tempPng = tempnam(sys_get_temp_dir(), 'gam_eps_') . '.png';

        $gsCmd = $this->commandExists('gswin64c') ? 'gswin64c'
               : ($this->commandExists('gs') ? 'gs' : null);

        if ($gsCmd) {
            $result = Process::timeout(30)->run([
                $gsCmd, '-dNOPAUSE', '-dBATCH', '-sDEVICE=png16m',
                '-r150', "-sOutputFile={$tempPng}", $source,
            ]);

            if ($result->successful() && file_exists($tempPng)) {
                return $this->generateFromPng($tempPng, $disk, $dir, $base);
            }
        }

        if ($this->commandExists('inkscape')) {
            $result = Process::timeout(60)->run([
                'inkscape', '--export-type=png', '--export-width=1200',
                "--export-filename={$tempPng}", $source,
            ]);

            if ($result->successful() && file_exists($tempPng)) {
                return $this->generateFromPng($tempPng, $disk, $dir, $base);
            }
        }

        @unlink($tempPng);
        return null;
    }

    /**
     * Handle video files — FFmpeg thumbnail extraction.
     */
    private function handleVideo(string $source, string $disk, string $dir, string $base): ?array
    {
        if (!$this->commandExists('ffmpeg')) return null;

        $tempPng = tempnam(sys_get_temp_dir(), 'gam_vid_') . '.png';

        $result = Process::timeout(30)->run([
            'ffmpeg', '-y', '-i', $source,
            '-ss', '00:00:01', '-frames:v', '1',
            '-vf', 'scale=1200:-1',
            $tempPng,
        ]);

        if ($result->successful() && file_exists($tempPng)) {
            return $this->generateFromPng($tempPng, $disk, $dir, $base);
        }

        @unlink($tempPng);
        return null;
    }

    /**
     * Handle Office documents (DOCX, XLSX) — LibreOffice headless → PDF → PNG.
     */
    private function handleOffice(string $source, string $disk, string $dir, string $base): ?array
    {
        $loCmd = $this->findLibreOffice();
        if (!$loCmd) return null;

        $tempDir = sys_get_temp_dir() . '/gam_office_' . uniqid();
        @mkdir($tempDir, 0755, true);

        $result = Process::timeout(60)->run([
            $loCmd, '--headless', '--convert-to', 'pdf',
            '--outdir', $tempDir, $source,
        ]);

        if (!$result->successful()) {
            $this->cleanDir($tempDir);
            return null;
        }

        $pdfs = glob($tempDir . '/*.pdf');
        if (empty($pdfs)) {
            $this->cleanDir($tempDir);
            return null;
        }

        $pdfResult = $this->handlePdf($pdfs[0], $disk, $dir, $base);
        $this->cleanDir($tempDir);
        return $pdfResult;
    }

    // ════════════════════════════════════════════════════════════════
    //  UTILITY METHODS
    // ════════════════════════════════════════════════════════════════

    /**
     * Generate thumbnail + preview from an intermediate PNG file.
     */
    private function generateFromPng(string $pngPath, string $disk, string $dir, string $base): array
    {
        $thumbSize = config('gam.pipeline.preview_thumbnail_size', 300);
        $mediumWidth = config('gam.pipeline.preview_medium_size', 1200);

        $thumbPath = "{$dir}/{$base}-thumb.jpg";
        $previewPath = "{$dir}/{$base}-preview.jpg";

        $this->gdResize($pngPath, $thumbSize, $thumbSize, $disk, $thumbPath);
        $this->gdResize($pngPath, $mediumWidth, null, $disk, $previewPath);

        @unlink($pngPath);

        return ['thumbnail' => $thumbPath, 'preview' => $previewPath];
    }

    /**
     * Resize an image using GD and save as JPEG to the target disk.
     */
    private function gdResize(string $sourcePath, int $maxWidth, ?int $maxHeight, string $disk, string $destPath): void
    {
        $imageInfo = @getimagesize($sourcePath);
        if (!$imageInfo) {
            throw new \RuntimeException("Cannot read image dimensions: {$sourcePath}");
        }

        [$origWidth, $origHeight, $imageType] = $imageInfo;

        $ratio = $origWidth / max($origHeight, 1);

        if ($maxHeight === null) {
            $newWidth = min($origWidth, $maxWidth);
            $newHeight = (int) round($newWidth / max($ratio, 0.01));
        } else {
            if ($origWidth / max($maxWidth, 1) > $origHeight / max($maxHeight, 1)) {
                $newWidth = min($origWidth, $maxWidth);
                $newHeight = (int) round($newWidth / max($ratio, 0.01));
            } else {
                $newHeight = min($origHeight, $maxHeight);
                $newWidth = (int) round($newHeight * $ratio);
            }
        }

        $newWidth = max($newWidth, 1);
        $newHeight = max($newHeight, 1);

        $sourceImage = match ($imageType) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($sourcePath),
            IMAGETYPE_PNG  => @imagecreatefrompng($sourcePath),
            IMAGETYPE_GIF  => @imagecreatefromgif($sourcePath),
            IMAGETYPE_WEBP => @imagecreatefromwebp($sourcePath),
            IMAGETYPE_BMP  => @imagecreatefrombmp($sourcePath),
            default        => false,
        };

        if (!$sourceImage) {
            throw new \RuntimeException("Failed to create GD resource (type: {$imageType}): {$sourcePath}");
        }

        $resized = imagecreatetruecolor($newWidth, $newHeight);
        $white = imagecolorallocate($resized, 255, 255, 255);
        imagefill($resized, 0, 0, $white);

        imagecopyresampled($resized, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $origWidth, $origHeight);

        $tempFile = tempnam(sys_get_temp_dir(), 'gam_resize_');
        imagejpeg($resized, $tempFile, 85);

        imagedestroy($sourceImage);
        imagedestroy($resized);

        Storage::disk($disk)->put($destPath, file_get_contents($tempFile));
        @unlink($tempFile);
    }

    /**
     * Check if a CLI command exists on this system.
     */
    private function commandExists(string $command): bool
    {
        $check = PHP_OS_FAMILY === 'Windows'
            ? "where {$command} 2>NUL"
            : "which {$command} 2>/dev/null";

        $result = @shell_exec($check);
        return !empty(trim($result ?? ''));
    }

    /**
     * Find LibreOffice executable path.
     */
    private function findLibreOffice(): ?string
    {
        if ($this->commandExists('libreoffice')) return 'libreoffice';
        if ($this->commandExists('soffice')) return 'soffice';

        $windowsPaths = [
            'C:\\Program Files\\LibreOffice\\program\\soffice.exe',
            'C:\\Program Files (x86)\\LibreOffice\\program\\soffice.exe',
        ];
        foreach ($windowsPaths as $path) {
            if (file_exists($path)) return $path;
        }

        return null;
    }

    /**
     * Clean up a temporary directory.
     */
    private function cleanDir(string $dir): void
    {
        if (!is_dir($dir)) return;
        $files = glob($dir . '/*');
        foreach ($files as $file) @unlink($file);
        @rmdir($dir);
    }

    public function failed(\Throwable $exception): void
    {
        $this->asset->update(['preview_status' => 'failed']);
        Log::error("GeneratePreview: All retries exhausted", [
            'asset' => $this->asset->id,
            'error' => $exception->getMessage(),
        ]);
    }
}
