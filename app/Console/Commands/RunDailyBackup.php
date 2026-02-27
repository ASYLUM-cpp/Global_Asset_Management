<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * REQ-29: Daily backup of database and uploaded assets.
 *
 * Usage:
 *   php artisan gam:backup              # Run interactively
 *   php artisan gam:backup --db-only    # Only backup database
 *   php artisan gam:backup --files-only # Only backup uploads
 *
 * Schedule (in routes/console.php):
 *   Schedule::command('gam:backup')->dailyAt('02:00');
 */
class RunDailyBackup extends Command
{
    protected $signature   = 'gam:backup
        {--db-only    : Only backup the database}
        {--files-only : Only backup uploaded files}';

    protected $description = 'Run daily backup of database and uploaded assets';

    public function handle(): int
    {
        $timestamp = now()->format('Y-m-d_His');
        $backupDir = 'backups/' . now()->format('Y/m/d');
        $disk      = Storage::disk(config('gam.storage.backup_disk', 'local'));

        $this->info("Starting GAM backup [{$timestamp}]");
        $parts = [];

        // ── Database backup ──
        if (! $this->option('files-only')) {
            $this->info('  → Dumping database...');
            $dbPath = "{$backupDir}/db-{$timestamp}.sql";

            $host     = config('database.connections.mysql.host', '127.0.0.1');
            $port     = config('database.connections.mysql.port', '3306');
            $database = config('database.connections.mysql.database');
            $username = config('database.connections.mysql.username');
            $password = config('database.connections.mysql.password');

            $tmpFile = storage_path("app/{$dbPath}");
            $dir = dirname($tmpFile);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            $cmd = sprintf(
                'mysqldump -h %s -P %s -u %s -p%s %s > %s 2>&1',
                escapeshellarg($host),
                escapeshellarg($port),
                escapeshellarg($username),
                escapeshellarg($password),
                escapeshellarg($database),
                escapeshellarg($tmpFile),
            );

            exec($cmd, $output, $exitCode);

            if ($exitCode === 0 && file_exists($tmpFile)) {
                $size = filesize($tmpFile);
                $this->info("  ✓ Database dump: {$this->humanSize($size)}");
                $parts[] = "DB ({$this->humanSize($size)})";
            } else {
                $this->error('  ✗ Database dump failed: ' . implode("\n", $output));
            }
        }

        // ── File backup (tar.gz of uploads directory) ──
        if (! $this->option('db-only')) {
            $this->info('  → Archiving uploads...');
            $uploadsPath = storage_path('app/uploads');

            if (is_dir($uploadsPath)) {
                $archivePath = storage_path("app/{$backupDir}/uploads-{$timestamp}.tar.gz");
                $archiveDir  = dirname($archivePath);
                if (! is_dir($archiveDir)) {
                    mkdir($archiveDir, 0755, true);
                }

                $cmd = sprintf(
                    'tar -czf %s -C %s .',
                    escapeshellarg($archivePath),
                    escapeshellarg($uploadsPath),
                );

                exec($cmd, $output, $exitCode);

                if ($exitCode === 0 && file_exists($archivePath)) {
                    $size = filesize($archivePath);
                    $this->info("  ✓ Uploads archive: {$this->humanSize($size)}");
                    $parts[] = "Files ({$this->humanSize($size)})";
                } else {
                    $this->error('  ✗ Archive failed: ' . implode("\n", $output));
                }
            } else {
                $this->warn('  ⚠ No uploads directory found, skipping file backup.');
            }
        }

        // ── Cleanup old backups (keep last 7 days) ──
        $this->info('  → Cleaning old backups (keeping 7 days)...');
        $retentionDays = (int) config('gam.backup_retention_days', 7);
        $threshold     = now()->subDays($retentionDays);

        $dirs = Storage::disk('local')->directories('backups');
        $deleted = 0;
        foreach ($dirs as $yearDir) {
            foreach (Storage::disk('local')->directories($yearDir) as $monthDir) {
                foreach (Storage::disk('local')->directories($monthDir) as $dayDir) {
                    // Parse date from path like backups/2025/07/10
                    $parts2 = explode('/', str_replace('\\', '/', $dayDir));
                    if (count($parts2) >= 4) {
                        $dateStr = $parts2[1] . '-' . $parts2[2] . '-' . $parts2[3];
                        try {
                            $dirDate = \Carbon\Carbon::parse($dateStr);
                            if ($dirDate->lt($threshold)) {
                                Storage::disk('local')->deleteDirectory($dayDir);
                                $deleted++;
                            }
                        } catch (\Throwable) {
                            // skip unparseable directories
                        }
                    }
                }
            }
        }

        if ($deleted > 0) {
            $this->info("  ✓ Removed {$deleted} old backup(s).");
        }

        // ── Log the backup ──
        activity()->log('Daily backup completed: ' . implode(', ', $parts));

        $this->newLine();
        $this->info("✅ Backup complete.");

        return self::SUCCESS;
    }

    private function humanSize(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;
        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }
        return round($bytes, 1) . ' ' . $units[$i];
    }
}
