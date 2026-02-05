<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class CleanModuleStatusesCommand extends Command
{
    protected $signature = 'modules:clean-statuses
                            {--dry-run : Show what would be removed without making changes}';

    protected $description = 'Remove orphaned entries from modules_statuses.json for modules that no longer exist';

    public function handle(): int
    {
        $statusFile = base_path('modules_statuses.json');

        if (! file_exists($statusFile)) {
            $this->info('modules_statuses.json does not exist. Nothing to clean.');

            return Command::SUCCESS;
        }

        $statuses = json_decode(file_get_contents($statusFile), true);

        if (! is_array($statuses)) {
            $this->error('Invalid modules_statuses.json format.');

            return Command::FAILURE;
        }

        $modulesPath = base_path('Modules');
        $orphaned = [];
        $valid = [];

        foreach ($statuses as $moduleName => $enabled) {
            $moduleDir = $modulesPath.'/'.$moduleName;

            if (is_dir($moduleDir) && file_exists($moduleDir.'/module.json')) {
                $valid[$moduleName] = $enabled;
            } else {
                $orphaned[] = $moduleName;
            }
        }

        if (empty($orphaned)) {
            $this->info('No orphaned module entries found. modules_statuses.json is clean.');

            return Command::SUCCESS;
        }

        $this->warn('Found '.count($orphaned).' orphaned module(s):');
        foreach ($orphaned as $name) {
            $this->line("  - {$name}");
        }

        if ($this->option('dry-run')) {
            $this->info('Dry run mode - no changes made.');

            return Command::SUCCESS;
        }

        file_put_contents(
            $statusFile,
            json_encode($valid, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)."\n"
        );

        $this->info('Removed '.count($orphaned).' orphaned entries from modules_statuses.json');

        return Command::SUCCESS;
    }
}
