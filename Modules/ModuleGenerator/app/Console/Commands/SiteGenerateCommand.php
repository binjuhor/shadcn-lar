<?php

namespace Modules\ModuleGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class SiteGenerateCommand extends Command
{
    protected $signature = 'site:generate
        {name : Project name (will be used as directory name)}
        {--modules= : Comma-separated module names to include}
        {--output= : Output directory (default: parent of current project)}
        {--dry-run : Preview files without creating them}';

    protected $description = 'Generate a new Laravel+React project with selected modules from this monorepo';

    protected array $selectedModules = [];

    protected string $outputPath = '';

    protected array $excludedPaths = [
        'node_modules',
        'vendor',
        '.git',
        '.idea',
        '.vscode',
        'storage/logs',
        'storage/framework/cache/data',
        'storage/framework/sessions',
        'storage/framework/views',
        'bootstrap/cache',
        '.env',
        '.phpunit.cache',
        '.claude',
        'docs',
    ];

    protected array $baseFiles = [
        'app',
        'bootstrap',
        'config',
        'database',
        'public',
        'resources',
        'routes',
        'storage',
        'tests',
        'artisan',
        'composer.json',
        'package.json',
        'tsconfig.json',
        'vite.config.js',
        'tailwind.config.js',
        'postcss.config.js',
        '.env.example',
        'phpunit.xml',
        'README.md',
        '.gitignore',
        '.editorconfig',
        'modules_statuses.json',
    ];

    public function __construct(
        protected Filesystem $files
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        if (! preg_match('/^[A-Za-z][a-zA-Z0-9_-]*$/', $name)) {
            $this->error('Project name must start with a letter and contain only letters, numbers, hyphens, or underscores');

            return 1;
        }

        $modulesOption = $this->option('modules');
        if (empty($modulesOption)) {
            $this->error('You must specify at least one module with --modules option');
            $this->line('');
            $this->line('Available modules:');
            $this->listAvailableModules();

            return 1;
        }

        $this->selectedModules = array_map('trim', explode(',', $modulesOption));

        if (! $this->validateModules()) {
            return 1;
        }

        $outputDir = $this->option('output') ?: dirname(base_path());
        $this->outputPath = rtrim($outputDir, '/').'/'.$name;

        if ($this->files->isDirectory($this->outputPath) && ! $this->option('dry-run')) {
            $this->error("Directory already exists: {$this->outputPath}");

            return 1;
        }

        if ($this->option('dry-run')) {
            $this->previewGeneration();

            return 0;
        }

        $this->generate();

        return 0;
    }

    protected function listAvailableModules(): void
    {
        $modulesPath = base_path('Modules');
        $modules = $this->files->directories($modulesPath);

        foreach ($modules as $module) {
            $moduleName = basename($module);
            if ($moduleName === 'ModuleGenerator') {
                continue;
            }
            $this->line("  - {$moduleName}");
        }
    }

    protected function validateModules(): bool
    {
        $modulesPath = base_path('Modules');
        $invalid = [];

        foreach ($this->selectedModules as $module) {
            $modulePath = "{$modulesPath}/{$module}";
            if (! $this->files->isDirectory($modulePath)) {
                $invalid[] = $module;
            }
        }

        if (! empty($invalid)) {
            $this->error('Invalid module(s): '.implode(', ', $invalid));
            $this->line('');
            $this->line('Available modules:');
            $this->listAvailableModules();

            return false;
        }

        return true;
    }

    protected function previewGeneration(): void
    {
        $this->info('Dry run - project structure that would be created:');
        $this->line('');
        $this->line("Output path: <fg=cyan>{$this->outputPath}</>");
        $this->line('');

        $this->line('<fg=yellow>Base files:</>');
        foreach ($this->baseFiles as $file) {
            $this->line("  <fg=green>COPY</> {$file}");
        }

        $this->line('');
        $this->line('<fg=yellow>Selected modules:</>');
        foreach ($this->selectedModules as $module) {
            $this->line("  <fg=green>COPY</> Modules/{$module}/");
        }

        $this->line('');
        $this->line('<fg=yellow>Configuration updates:</>');
        $this->line('  <fg=blue>MODIFY</> composer.json (remove unused module autoloads)');
        $this->line('  <fg=blue>MODIFY</> tsconfig.json (remove unused module aliases)');
        $this->line('  <fg=blue>MODIFY</> vite.config.js (remove unused module aliases)');
        $this->line('  <fg=blue>MODIFY</> modules_statuses.json (only selected modules)');

        $this->line('');
        $this->line('<fg=red>Excluded:</>');
        foreach ($this->excludedPaths as $path) {
            $this->line("  <fg=gray>SKIP</> {$path}");
        }
    }

    protected function generate(): void
    {
        $this->info("Generating project: {$this->argument('name')}");
        $this->line("Output: {$this->outputPath}");
        $this->line('');

        $this->files->ensureDirectoryExists($this->outputPath);

        $this->copyBaseProject();
        $this->copyModules();
        $this->updateComposerJson();
        $this->updateTsConfig();
        $this->updateViteConfig();
        $this->updateModulesStatuses();
        $this->createStorageStructure();

        $this->line('');
        $this->info('Project generated successfully!');
        $this->line('');
        $this->line('<fg=yellow>Next steps:</>');
        $this->line("  1. cd {$this->outputPath}");
        $this->line('  2. composer install');
        $this->line('  3. yarn install');
        $this->line('  4. cp .env.example .env');
        $this->line('  5. php artisan key:generate');
        $this->line('  6. Configure database in .env');
        $this->line('  7. php artisan migrate');
        $this->line('  8. yarn dev');
    }

    protected function copyBaseProject(): void
    {
        $this->line('<fg=cyan>Copying base project files...</>');

        foreach ($this->baseFiles as $file) {
            $sourcePath = base_path($file);
            $targetPath = "{$this->outputPath}/{$file}";

            if (! $this->files->exists($sourcePath)) {
                continue;
            }

            if ($this->files->isDirectory($sourcePath)) {
                $this->copyDirectoryFiltered($sourcePath, $targetPath);
            } else {
                $this->files->ensureDirectoryExists(dirname($targetPath));
                $this->files->copy($sourcePath, $targetPath);
            }

            $this->line("  <fg=green>COPY</> {$file}");
        }

        $this->files->ensureDirectoryExists("{$this->outputPath}/Modules");
        $this->files->put("{$this->outputPath}/Modules/.gitkeep", '');
    }

    protected function copyDirectoryFiltered(string $source, string $target): void
    {
        $this->files->ensureDirectoryExists($target);

        $items = $this->files->allFiles($source, true);

        foreach ($items as $item) {
            $relativePath = $item->getRelativePathname();

            if ($this->shouldExclude($relativePath)) {
                continue;
            }

            $targetFile = "{$target}/{$relativePath}";
            $this->files->ensureDirectoryExists(dirname($targetFile));
            $this->files->copy($item->getPathname(), $targetFile);
        }

        $directories = $this->files->directories($source);
        foreach ($directories as $dir) {
            $dirName = basename($dir);
            $relativeDirPath = str_replace(base_path().'/', '', $dir);

            if ($this->shouldExclude($relativeDirPath) || $this->shouldExclude($dirName)) {
                continue;
            }

            $targetDir = "{$target}/{$dirName}";
            if (! $this->files->isDirectory($targetDir)) {
                $this->copyDirectoryFiltered($dir, $targetDir);
            }
        }
    }

    protected function shouldExclude(string $path): bool
    {
        foreach ($this->excludedPaths as $excluded) {
            if (str_starts_with($path, $excluded) || str_contains($path, "/{$excluded}/") || $path === $excluded) {
                return true;
            }
        }

        return false;
    }

    protected function copyModules(): void
    {
        $this->line('<fg=cyan>Copying selected modules...</>');

        foreach ($this->selectedModules as $module) {
            $sourcePath = base_path("Modules/{$module}");
            $targetPath = "{$this->outputPath}/Modules/{$module}";

            $this->copyDirectoryFiltered($sourcePath, $targetPath);
            $this->line("  <fg=green>COPY</> Modules/{$module}");
        }
    }

    protected function updateComposerJson(): void
    {
        $this->line('<fg=cyan>Updating composer.json...</>');

        $composerPath = "{$this->outputPath}/composer.json";
        $composer = json_decode($this->files->get($composerPath), true);

        $composer['name'] = 'laravel/'.Str::kebab($this->argument('name'));
        $composer['description'] = 'Generated from shadcn-admin monorepo';

        $newAutoload = [];
        foreach ($composer['autoload']['psr-4'] ?? [] as $namespace => $path) {
            if (str_starts_with($namespace, 'Modules\\')) {
                $moduleName = explode('\\', $namespace)[1];
                if (in_array($moduleName, $this->selectedModules)) {
                    $newAutoload[$namespace] = $path;
                }
            } else {
                $newAutoload[$namespace] = $path;
            }
        }
        $composer['autoload']['psr-4'] = $newAutoload;

        $newAutoloadDev = [];
        foreach ($composer['autoload-dev']['psr-4'] ?? [] as $namespace => $path) {
            if (str_starts_with($namespace, 'Modules\\')) {
                $moduleName = explode('\\', $namespace)[1];
                if (in_array($moduleName, $this->selectedModules)) {
                    $newAutoloadDev[$namespace] = $path;
                }
            } else {
                $newAutoloadDev[$namespace] = $path;
            }
        }
        $composer['autoload-dev']['psr-4'] = $newAutoloadDev;

        $this->files->put(
            $composerPath,
            json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        $this->line('  <fg=blue>MODIFY</> composer.json');
    }

    protected function updateTsConfig(): void
    {
        $this->line('<fg=cyan>Updating tsconfig.json...</>');

        $tsconfigPath = "{$this->outputPath}/tsconfig.json";
        $tsconfig = json_decode($this->files->get($tsconfigPath), true);

        $newPaths = [];
        foreach ($tsconfig['compilerOptions']['paths'] ?? [] as $alias => $paths) {
            if (str_starts_with($alias, '@modules/')) {
                $moduleName = str_replace(['@modules/', '/*'], '', $alias);
                if (in_array($moduleName, $this->selectedModules)) {
                    $newPaths[$alias] = $paths;
                }
            } else {
                $newPaths[$alias] = $paths;
            }
        }
        $tsconfig['compilerOptions']['paths'] = $newPaths;

        $this->files->put(
            $tsconfigPath,
            json_encode($tsconfig, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
        );

        $this->line('  <fg=blue>MODIFY</> tsconfig.json');
    }

    protected function updateViteConfig(): void
    {
        $this->line('<fg=cyan>Updating vite.config.js...</>');

        $vitePath = "{$this->outputPath}/vite.config.js";
        $content = $this->files->get($vitePath);

        $lines = explode("\n", $content);
        $newLines = [];

        foreach ($lines as $line) {
            $shouldKeep = true;

            if (preg_match("/'@modules\/(\w+)':/", $line, $matches)) {
                $moduleName = $matches[1];
                if (! in_array($moduleName, $this->selectedModules)) {
                    $shouldKeep = false;
                }
            }

            if ($shouldKeep) {
                $newLines[] = $line;
            }
        }

        $this->files->put($vitePath, implode("\n", $newLines));
        $this->line('  <fg=blue>MODIFY</> vite.config.js');
    }

    protected function updateModulesStatuses(): void
    {
        $this->line('<fg=cyan>Updating modules_statuses.json...</>');

        $statusPath = "{$this->outputPath}/modules_statuses.json";

        $statuses = [];
        foreach ($this->selectedModules as $module) {
            $statuses[$module] = true;
        }

        $this->files->put(
            $statusPath,
            json_encode($statuses, JSON_PRETTY_PRINT)."\n"
        );

        $this->line('  <fg=blue>MODIFY</> modules_statuses.json');
    }

    protected function createStorageStructure(): void
    {
        $storageDirs = [
            'storage/app/public',
            'storage/framework/cache/data',
            'storage/framework/sessions',
            'storage/framework/testing',
            'storage/framework/views',
            'storage/logs',
            'bootstrap/cache',
        ];

        foreach ($storageDirs as $dir) {
            $this->files->ensureDirectoryExists("{$this->outputPath}/{$dir}");
            $this->files->put("{$this->outputPath}/{$dir}/.gitignore", "*\n!.gitignore\n");
        }
    }
}
