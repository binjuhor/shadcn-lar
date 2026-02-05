<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class MigrateMediaToR2Command extends Command
{
    protected $signature = 'media:migrate-to-r2
                            {--dry-run : Show what would be migrated without doing it}
                            {--force : Skip confirmation prompt}
                            {--type= : Migrate only "media" or "avatars" (default: both)}';

    protected $description = 'Migrate existing files from public disk to R2 storage';

    private int $migrated = 0;

    private int $skipped = 0;

    private int $failed = 0;

    /** @var array<int, string> */
    private array $failures = [];

    public function handle(): int
    {
        $dryRun = $this->option('dry-run');
        $force = $this->option('force');
        $type = $this->option('type');

        if ($type && ! in_array($type, ['media', 'avatars'])) {
            $this->error('Invalid type. Use "media" or "avatars".');

            return Command::FAILURE;
        }

        $this->info('R2 Media Migration');
        $this->info('==================');
        $this->newLine();

        if ($dryRun) {
            $this->warn('DRY RUN — no files will be copied or records updated.');
            $this->newLine();
        }

        if (! $dryRun && ! $force && ! $this->confirm('This will copy files from public disk to R2 and update database records. Continue?')) {
            $this->info('Operation cancelled.');

            return Command::SUCCESS;
        }

        if (! $type || $type === 'media') {
            $this->migrateMediaLibrary($dryRun);
        }

        if (! $type || $type === 'avatars') {
            $this->migrateAvatars($dryRun);
        }

        $this->printSummary();

        return $this->failed > 0 ? Command::FAILURE : Command::SUCCESS;
    }

    private function migrateMediaLibrary(bool $dryRun): void
    {
        $this->info('Migrating Spatie Media Library files...');
        $this->newLine();

        $records = DB::table('media')
            ->where('disk', 'public')
            ->get();

        if ($records->isEmpty()) {
            $this->info('  No media records on public disk.');
            $this->newLine();

            return;
        }

        $this->info("  Found {$records->count()} record(s) on public disk.");
        $this->newLine();

        foreach ($records as $record) {
            $path = "{$record->id}/{$record->file_name}";

            $this->line("  Processing media #{$record->id}: {$record->file_name}");

            if ($this->fileExistsOnR2($path)) {
                $this->line('    <fg=yellow>⊘</> Already exists on R2, skipping file copy.');

                if (! $dryRun) {
                    $this->updateMediaDisk($record);
                }

                $this->skipped++;

                continue;
            }

            if (! Storage::disk('public')->exists($path)) {
                $this->line('    <fg=red>✗</> Source file missing on public disk.');
                $this->failed++;
                $this->failures[] = "media #{$record->id}: {$path} (source missing)";

                continue;
            }

            if ($dryRun) {
                $this->line('    <fg=blue>→</> Would copy to R2.');
                $this->migrated++;

                continue;
            }

            try {
                $stream = Storage::disk('public')->readStream($path);
                Storage::disk('r2')->writeStream($path, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                $this->updateMediaDisk($record);
                $this->line('    <fg=green>✓</> Copied and updated.');
                $this->migrated++;
            } catch (\Exception $e) {
                $this->line("    <fg=red>✗</> Failed: {$e->getMessage()}");
                $this->failed++;
                $this->failures[] = "media #{$record->id}: {$path} ({$e->getMessage()})";
            }
        }

        $this->newLine();
    }

    private function updateMediaDisk(object $record): void
    {
        $update = ['disk' => 'r2'];

        if ($record->conversions_disk === 'public') {
            $update['conversions_disk'] = 'r2';
        }

        DB::table('media')
            ->where('id', $record->id)
            ->update($update);
    }

    private function migrateAvatars(bool $dryRun): void
    {
        $this->info('Migrating user avatars...');
        $this->newLine();

        $users = User::whereNotNull('avatar_path')
            ->where('avatar_path', '!=', '')
            ->get(['id', 'avatar_path']);

        if ($users->isEmpty()) {
            $this->info('  No users with avatars.');
            $this->newLine();

            return;
        }

        $this->info("  Found {$users->count()} user(s) with avatars.");
        $this->newLine();

        foreach ($users as $user) {
            $path = $user->avatar_path;

            $this->line("  Processing user #{$user->id}: {$path}");

            if ($this->fileExistsOnR2($path)) {
                $this->line('    <fg=yellow>⊘</> Already exists on R2, skipping.');
                $this->skipped++;

                continue;
            }

            if (! Storage::disk('public')->exists($path)) {
                $this->line('    <fg=red>✗</> Source file missing on public disk.');
                $this->failed++;
                $this->failures[] = "user #{$user->id}: {$path} (source missing)";

                continue;
            }

            if ($dryRun) {
                $this->line('    <fg=blue>→</> Would copy to R2.');
                $this->migrated++;

                continue;
            }

            try {
                $stream = Storage::disk('public')->readStream($path);
                Storage::disk('r2')->writeStream($path, $stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }

                $this->line('    <fg=green>✓</> Copied.');
                $this->migrated++;
            } catch (\Exception $e) {
                $this->line("    <fg=red>✗</> Failed: {$e->getMessage()}");
                $this->failed++;
                $this->failures[] = "user #{$user->id}: {$path} ({$e->getMessage()})";
            }
        }

        $this->newLine();
    }

    private function fileExistsOnR2(string $path): bool
    {
        try {
            return Storage::disk('r2')->exists($path);
        } catch (\Exception) {
            return false;
        }
    }

    private function printSummary(): void
    {
        $this->info('Summary');
        $this->info('-------');
        $this->line("  Migrated: {$this->migrated}");
        $this->line("  Skipped:  {$this->skipped}");
        $this->line("  Failed:   {$this->failed}");

        if (! empty($this->failures)) {
            $this->newLine();
            $this->warn('Failed items:');

            foreach ($this->failures as $failure) {
                $this->line("  - {$failure}");
            }
        }

        $this->newLine();

        if ($this->failed === 0) {
            $this->info('All done.');
        } else {
            $this->warn("Completed with {$this->failed} failure(s). Re-run to retry failed items.");
        }
    }
}
