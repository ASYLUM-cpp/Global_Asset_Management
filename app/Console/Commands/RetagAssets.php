<?php

namespace App\Console\Commands;

use App\Jobs\AiTagAsset;
use App\Models\Asset;
use Illuminate\Console\Command;

class RetagAssets extends Command
{
    protected $signature = 'gam:retag
                            {--id= : Re-tag a specific asset by ID}
                            {--fallback : Only re-tag assets that have fallback tags}
                            {--all : Re-tag ALL assets}
                            {--dry-run : Show what would be re-tagged without actually doing it}';

    protected $description = 'Re-tag assets with AI using the current model. Replaces old/fallback tags.';

    /**
     * Fallback tag patterns — these are generic tags applied when AI tagging fails.
     */
    private const FALLBACK_TAGS = [
        'photograph', 'image', 'raster-image', 'graphic', 'document', 'portable',
        'jpg', 'jpeg', 'png', 'gif', 'bmp', 'svg', 'webp', 'pdf', 'doc', 'docx',
        'xls', 'xlsx', 'pptx', 'txt', 'csv',
    ];

    public function handle(): int
    {
        if ($this->option('id')) {
            $assets = Asset::where('id', $this->option('id'))->get();
        } elseif ($this->option('all')) {
            $assets = Asset::where('pipeline_status', 'done')->get();
        } elseif ($this->option('fallback')) {
            $assets = $this->findFallbackAssets();
        } else {
            $this->error('Specify --id=N, --fallback, or --all');
            return 1;
        }

        if ($assets->isEmpty()) {
            $this->info('No assets to re-tag.');
            return 0;
        }

        $this->info("Found {$assets->count()} asset(s) to re-tag.");
        $this->newLine();

        foreach ($assets as $asset) {
            $currentTags = $asset->tags()->pluck('tag')->toArray();
            $this->line("  #{$asset->id}: {$asset->original_filename}");
            $this->line("    Current tags: " . implode(', ', $currentTags));

            if ($this->option('dry-run')) {
                $this->line('    → Would re-tag (dry-run)');
                continue;
            }

            // Delete old tags
            $asset->tags()->delete();

            // Dispatch AI tagging synchronously
            try {
                $job = new AiTagAsset($asset);
                $job->handle();
                
                $newTags = $asset->fresh()->tags()->pluck('tag')->toArray();
                $this->info("    → New tags ({" . count($newTags) . "}): " . implode(', ', $newTags));
            } catch (\Throwable $e) {
                $this->error("    → Failed: {$e->getMessage()}");
            }

            $this->newLine();
        }

        $this->info('Done!');
        return 0;
    }

    /**
     * Find assets whose tags look like fallback (all tags are generic extension/type-based).
     */
    private function findFallbackAssets()
    {
        return Asset::where('pipeline_status', 'done')
            ->with('tags')
            ->get()
            ->filter(function (Asset $asset) {
                $tags = $asset->tags->pluck('tag')->map(fn ($t) => strtolower($t))->toArray();

                if (empty($tags)) {
                    return true; // No tags → should re-tag
                }

                // If ALL tags are fallback patterns, it needs re-tagging
                $nonFallback = array_filter($tags, fn ($t) => !in_array($t, self::FALLBACK_TAGS));
                return empty($nonFallback);
            });
    }
}
