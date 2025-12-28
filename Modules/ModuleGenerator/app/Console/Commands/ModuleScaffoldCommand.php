<?php

namespace Modules\ModuleGenerator\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ModuleScaffoldCommand extends Command
{
    protected $signature = 'module:scaffold
        {name : Module name in PascalCase (e.g., Inventory)}
        {--with-crud : Generate CRUD scaffolding with model, migration, policy, controller}
        {--entity= : Entity name for CRUD (defaults to singular module name)}
        {--dry-run : Preview files without creating them}';

    protected $description = 'Create a new module with React/TypeScript frontend scaffolding';

    protected array $variables = [];

    public function __construct(
        protected Filesystem $files
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        if (! preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name)) {
            $this->error('Module name must be PascalCase (e.g., Inventory, ProductCatalog)');

            return 1;
        }

        $modulePath = base_path("Modules/{$name}");

        if ($this->files->isDirectory($modulePath) && ! $this->option('dry-run')) {
            $this->error("Module {$name} already exists at {$modulePath}");

            return 1;
        }

        $this->variables = $this->buildVariables($name);

        if ($this->option('dry-run')) {
            $this->previewFiles();

            return 0;
        }

        $this->createModule();

        if ($this->option('with-crud')) {
            $this->createCrud();
        }

        $this->info("Module {$name} created successfully!");
        $this->newLine();
        $this->line('<fg=yellow>Next steps:</>');
        $this->line('  1. Run: <fg=green>composer dump-autoload</>');
        $this->line('  2. Run: <fg=green>php artisan module:enable '.$name.'</>');
        $this->line('  3. Run migrations: <fg=green>php artisan migrate</>');
        $this->line('  4. Add to sidebar in <fg=cyan>resources/js/components/layout/data/sidebar-data.ts</>');

        return 0;
    }

    protected function getStubsPath(): string
    {
        return module_path('ModuleGenerator', 'stubs');
    }

    protected function buildVariables(string $name): array
    {
        $entityName = $this->option('entity') ?: Str::singular($name);

        return [
            '{{moduleName}}' => $name,
            '{{moduleNameLower}}' => Str::lower($name),
            '{{moduleNameKebab}}' => Str::kebab($name),
            '{{moduleNameSnake}}' => Str::snake($name),
            '{{entityName}}' => Str::studly($entityName),
            '{{entityNameLower}}' => Str::lower($entityName),
            '{{entityNameCamel}}' => Str::camel($entityName),
            '{{entityNamePlural}}' => Str::plural(Str::studly($entityName)),
            '{{entityNamePluralLower}}' => Str::lower(Str::plural($entityName)),
            '{{entityNamePluralCamel}}' => Str::camel(Str::plural($entityName)),
            '{{tableNamePlural}}' => Str::snake(Str::plural($entityName)),
            '{{date}}' => now()->format('Y_m_d'),
            '{{timestamp}}' => now()->format('Y_m_d_His'),
        ];
    }

    protected function previewFiles(): void
    {
        $this->info('Dry run - files that would be created:');
        $this->newLine();

        $files = $this->getFilesToCreate();

        foreach ($files as $file) {
            $this->line("  <fg=green>CREATE</> {$file}");
        }

        if ($this->option('with-crud')) {
            $this->newLine();
            $this->line('<fg=yellow>CRUD files (--with-crud):</>');
            foreach ($this->getCrudFilesToCreate() as $file) {
                $this->line("  <fg=green>CREATE</> {$file}");
            }
        }
    }

    protected function getFilesToCreate(): array
    {
        $name = $this->variables['{{moduleName}}'];
        $nameLower = $this->variables['{{moduleNameLower}}'];

        return [
            "Modules/{$name}/module.json",
            "Modules/{$name}/composer.json",
            "Modules/{$name}/package.json",
            "Modules/{$name}/vite.config.js",
            "Modules/{$name}/config/config.php",
            "Modules/{$name}/routes/web.php",
            "Modules/{$name}/routes/api.php",
            "Modules/{$name}/app/Providers/{$name}ServiceProvider.php",
            "Modules/{$name}/app/Providers/RouteServiceProvider.php",
            "Modules/{$name}/app/Providers/EventServiceProvider.php",
            "Modules/{$name}/app/Http/Controllers/{$name}Controller.php",
            "Modules/{$name}/database/seeders/{$name}DatabaseSeeder.php",
            "Modules/{$name}/resources/js/pages/index.tsx",
            "Modules/{$name}/resources/js/types/{$nameLower}.ts",
        ];
    }

    protected function getCrudFilesToCreate(): array
    {
        $name = $this->variables['{{moduleName}}'];
        $entity = $this->variables['{{entityName}}'];
        $entityPluralLower = $this->variables['{{entityNamePluralLower}}'];
        $timestamp = $this->variables['{{timestamp}}'];
        $tableName = $this->variables['{{tableNamePlural}}'];

        return [
            "Modules/{$name}/app/Models/{$entity}.php",
            "Modules/{$name}/app/Policies/{$entity}Policy.php",
            "Modules/{$name}/app/Http/Controllers/{$entity}Controller.php",
            "Modules/{$name}/database/migrations/{$timestamp}_create_{$tableName}_table.php",
            "Modules/{$name}/resources/js/pages/{$entityPluralLower}/index.tsx",
            "Modules/{$name}/resources/js/pages/{$entityPluralLower}/create.tsx",
            "Modules/{$name}/resources/js/pages/{$entityPluralLower}/edit.tsx",
            "Modules/{$name}/resources/js/pages/{$entityPluralLower}/components/{$this->variables['{{entityNameLower}}']}-form.tsx",
        ];
    }

    protected function createModule(): void
    {
        $name = $this->variables['{{moduleName}}'];
        $basePath = base_path("Modules/{$name}");

        $this->createDirectories($basePath);

        $this->createFromStub('module.json.stub', "{$basePath}/module.json");
        $this->createFromStub('composer.json.stub', "{$basePath}/composer.json");
        $this->createFromStub('package.json.stub', "{$basePath}/package.json");
        $this->createFromStub('vite.config.stub', "{$basePath}/vite.config.js");
        $this->createFromStub('config.stub', "{$basePath}/config/config.php");
        $this->createFromStub('web-routes.stub', "{$basePath}/routes/web.php");
        $this->createFromStub('api-routes.stub', "{$basePath}/routes/api.php");
        $this->createFromStub('service-provider.stub', "{$basePath}/app/Providers/{$name}ServiceProvider.php");
        $this->createFromStub('route-service-provider.stub', "{$basePath}/app/Providers/RouteServiceProvider.php");
        $this->createFromStub('event-service-provider.stub', "{$basePath}/app/Providers/EventServiceProvider.php");
        $this->createFromStub('controller.stub', "{$basePath}/app/Http/Controllers/{$name}Controller.php");
        $this->createFromStub('database-seeder.stub', "{$basePath}/database/seeders/{$name}DatabaseSeeder.php");
        $this->createFromStub('resources/js/pages/index.stub', "{$basePath}/resources/js/pages/index.tsx");
        $this->createFromStub('resources/js/types/module.stub', "{$basePath}/resources/js/types/{$this->variables['{{moduleNameLower}}']}.ts");

        $this->createGitkeepFiles($basePath);
        $this->updateRootComposerJson();

        $this->line("Created module at <fg=cyan>{$basePath}</>");
    }

    protected function createCrud(): void
    {
        $name = $this->variables['{{moduleName}}'];
        $entity = $this->variables['{{entityName}}'];
        $entityLower = $this->variables['{{entityNameLower}}'];
        $entityPluralLower = $this->variables['{{entityNamePluralLower}}'];
        $tableName = $this->variables['{{tableNamePlural}}'];
        $timestamp = $this->variables['{{timestamp}}'];

        $basePath = base_path("Modules/{$name}");

        $this->files->ensureDirectoryExists("{$basePath}/resources/js/pages/{$entityPluralLower}/components");

        $this->createFromStub('crud/model.stub', "{$basePath}/app/Models/{$entity}.php");
        $this->createFromStub('crud/policy.stub', "{$basePath}/app/Policies/{$entity}Policy.php");
        $this->createFromStub('crud/controller.stub', "{$basePath}/app/Http/Controllers/{$entity}Controller.php");
        $this->createFromStub('crud/migration.stub', "{$basePath}/database/migrations/{$timestamp}_create_{$tableName}_table.php");
        $this->createFromStub('crud/pages/index.stub', "{$basePath}/resources/js/pages/{$entityPluralLower}/index.tsx");
        $this->createFromStub('crud/pages/create.stub', "{$basePath}/resources/js/pages/{$entityPluralLower}/create.tsx");
        $this->createFromStub('crud/pages/edit.stub', "{$basePath}/resources/js/pages/{$entityPluralLower}/edit.tsx");
        $this->createFromStub('crud/pages/form.stub', "{$basePath}/resources/js/pages/{$entityPluralLower}/components/{$entityLower}-form.tsx");

        $this->updateRoutesForCrud($basePath);
        $this->updateServiceProviderForCrud($basePath);

        $this->line("Created CRUD for <fg=cyan>{$entity}</>");
    }

    protected function createDirectories(string $basePath): void
    {
        $directories = [
            'app/Http/Controllers',
            'app/Http/Requests',
            'app/Models',
            'app/Policies',
            'app/Providers',
            'app/Services',
            'config',
            'database/factories',
            'database/migrations',
            'database/seeders',
            'resources/js/pages',
            'resources/js/types',
            'resources/views',
            'routes',
            'tests/Feature',
            'tests/Unit',
        ];

        foreach ($directories as $dir) {
            $this->files->ensureDirectoryExists("{$basePath}/{$dir}");
        }
    }

    protected function createGitkeepFiles(string $basePath): void
    {
        $emptyDirs = [
            'app/Http/Requests',
            'app/Models',
            'app/Policies',
            'app/Services',
            'database/factories',
            'database/migrations',
            'resources/views',
            'tests/Feature',
            'tests/Unit',
        ];

        foreach ($emptyDirs as $dir) {
            $this->files->put("{$basePath}/{$dir}/.gitkeep", '');
        }
    }

    protected function createFromStub(string $stubName, string $targetPath): void
    {
        $stubPath = $this->getStubsPath()."/{$stubName}";

        if (! $this->files->exists($stubPath)) {
            $this->warn("Stub not found: {$stubName}");

            return;
        }

        $content = $this->files->get($stubPath);
        $content = str_replace(array_keys($this->variables), array_values($this->variables), $content);

        $this->files->ensureDirectoryExists(dirname($targetPath));
        $this->files->put($targetPath, $content);
    }

    protected function updateRoutesForCrud(string $basePath): void
    {
        $routesPath = "{$basePath}/routes/web.php";
        $entity = $this->variables['{{entityName}}'];
        $entityPluralLower = $this->variables['{{entityNamePluralLower}}'];

        $content = $this->files->get($routesPath);

        $import = "use Modules\\{$this->variables['{{moduleName}}']}\\Http\\Controllers\\{$entity}Controller;";
        $route = "        Route::resource('{$entityPluralLower}', {$entity}Controller::class);";

        if (! str_contains($content, $import)) {
            $content = preg_replace(
                '/(use Modules\\\\.*?Controller;)/',
                "$1\n{$import}",
                $content,
                1
            );
        }

        if (! str_contains($content, $route)) {
            $content = preg_replace(
                '/(Route::get.*?index.*?;)/',
                "$1\n\n{$route}",
                $content,
                1
            );
        }

        $this->files->put($routesPath, $content);
    }

    protected function updateServiceProviderForCrud(string $basePath): void
    {
        $providerPath = "{$basePath}/app/Providers/{$this->variables['{{moduleName}}']}ServiceProvider.php";
        $entity = $this->variables['{{entityName}}'];
        $moduleName = $this->variables['{{moduleName}}'];

        $content = $this->files->get($providerPath);

        $modelImport = "use Modules\\{$moduleName}\\Models\\{$entity};";
        $policyImport = "use Modules\\{$moduleName}\\Policies\\{$entity}Policy;";
        $gateImport = 'use Illuminate\\Support\\Facades\\Gate;';

        if (! str_contains($content, $modelImport)) {
            $content = preg_replace(
                '/(namespace Modules\\\\.*?;)/',
                "$1\n\n{$gateImport}\n{$modelImport}\n{$policyImport}",
                $content,
                1
            );
        }

        if (! str_contains($content, 'registerPolicies')) {
            $bootMethod = <<<'PHP'

    protected function registerPolicies(): void
    {
        Gate::policy({{entityName}}::class, {{entityName}}Policy::class);
    }
PHP;
            $bootMethod = str_replace('{{entityName}}', $entity, $bootMethod);

            $content = preg_replace(
                '/(public function boot\(\): void\s*\{)/',
                "$1\n        \$this->registerPolicies();",
                $content,
                1
            );

            $content = preg_replace(
                '/(public function register\(\): void)/',
                "{$bootMethod}\n\n    $1",
                $content,
                1
            );
        }

        $this->files->put($providerPath, $content);
    }

    protected function updateRootComposerJson(): void
    {
        $composerPath = base_path('composer.json');
        $moduleName = $this->variables['{{moduleName}}'];

        $composer = json_decode($this->files->get($composerPath), true);

        $namespace = "Modules\\{$moduleName}\\";
        $path = "Modules/{$moduleName}/app/";

        if (! isset($composer['autoload']['psr-4'][$namespace])) {
            $composer['autoload']['psr-4'][$namespace] = $path;
            $composer['autoload']['psr-4']["Modules\\{$moduleName}\\Database\\Factories\\"] = "Modules/{$moduleName}/database/factories/";
            $composer['autoload']['psr-4']["Modules\\{$moduleName}\\Database\\Seeders\\"] = "Modules/{$moduleName}/database/seeders/";

            $this->files->put(
                $composerPath,
                json_encode($composer, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)."\n"
            );

            $this->line('Updated <fg=cyan>composer.json</> with module autoload');
        }
    }
}
