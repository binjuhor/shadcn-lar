# Phase 1: Core Command & Stubs

**Parent:** [plan.md](./plan.md)
**Dependencies:** nwidart/laravel-modules v12.0

## Overview

| Field | Value |
|-------|-------|
| Date | 2024-12-26 |
| Description | Create artisan command and base stub files |
| Priority | High |
| Implementation | Pending |
| Review | Pending |

## Key Insights

- nwidart/laravel-modules provides `module:make` for base structure
- Need to extend with React/TS/Inertia scaffolding
- ServiceProvider must follow Finance/Invoice pattern for Inertia page resolution
- Stub variables: `{{moduleName}}`, `{{moduleNameLower}}`, `{{moduleNameKebab}}`

## Requirements

1. Create `ModuleScaffoldCommand.php` in `app/Console/Commands/`
2. Implement stub loading and variable replacement
3. Support `--dry-run` for preview mode
4. Generate complete module structure with frontend

## Architecture

### Command Signature
```php
protected $signature = 'module:scaffold
    {name : Module name in PascalCase}
    {--with-crud : Generate CRUD scaffolding}
    {--entity= : Entity name for CRUD (defaults to singular module name)}
    {--dry-run : Preview files without creating}';
```

### Stub Variables
| Variable | Example |
|----------|---------|
| `{{moduleName}}` | Finance |
| `{{moduleNameLower}}` | finance |
| `{{moduleNameKebab}}` | finance |
| `{{entityName}}` | Account |
| `{{entityNameLower}}` | account |
| `{{entityNamePlural}}` | Accounts |
| `{{entityNamePluralLower}}` | accounts |
| `{{date}}` | 2024_12_26 |
| `{{timestamp}}` | 2024_12_26_100000 |

## Related Code Files

- `app/Console/Commands/ModuleScaffoldCommand.php` (create)
- `stubs/module-scaffold/*.stub` (create)
- `Modules/Finance/app/Providers/FinanceServiceProvider.php` (reference)
- `Modules/Finance/app/Providers/RouteServiceProvider.php` (reference)

## Implementation Steps

### Step 1: Create Command Class
```php
// app/Console/Commands/ModuleScaffoldCommand.php
<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class ModuleScaffoldCommand extends Command
{
    protected $signature = 'module:scaffold
        {name : Module name in PascalCase}
        {--with-crud : Generate CRUD scaffolding}
        {--entity= : Entity name for CRUD}
        {--dry-run : Preview without creating files}';

    protected $description = 'Create a new module with React/TypeScript frontend scaffolding';

    public function __construct(
        protected Filesystem $files
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $name = $this->argument('name');

        if (!preg_match('/^[A-Z][a-zA-Z0-9]*$/', $name)) {
            $this->error('Module name must be PascalCase');
            return 1;
        }

        $vars = $this->buildVariables($name);

        if ($this->option('dry-run')) {
            $this->previewFiles($vars);
            return 0;
        }

        $this->createModule($vars);

        if ($this->option('with-crud')) {
            $this->createCrud($vars);
        }

        $this->info("Module {$name} created successfully!");
        $this->newLine();
        $this->line("Next steps:");
        $this->line("  1. Run migrations: php artisan migrate");
        $this->line("  2. Add to sidebar navigation manually");

        return 0;
    }
}
```

### Step 2: Create Base Stubs

**service-provider.stub:**
```php
<?php

namespace Modules\{{moduleName}}\Providers;

use Illuminate\Support\Facades\Blade;
use Illuminate\Support\ServiceProvider;
use Nwidart\Modules\Traits\PathNamespace;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

class {{moduleName}}ServiceProvider extends ServiceProvider
{
    use PathNamespace;

    protected string $name = '{{moduleName}}';
    protected string $nameLower = '{{moduleNameLower}}';

    public function boot(): void
    {
        $this->registerCommands();
        $this->registerCommandSchedules();
        $this->registerTranslations();
        $this->registerConfig();
        $this->registerViews();
        $this->loadMigrationsFrom(module_path($this->name, 'database/migrations'));
    }

    public function register(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(RouteServiceProvider::class);
    }

    // ... other methods from Finance pattern
}
```

### Step 3: Create Module Structure
Directory structure to generate:
```
Modules/{{moduleName}}/
├── app/
│   ├── Http/Controllers/{{moduleName}}Controller.php
│   ├── Models/
│   ├── Policies/
│   ├── Providers/
│   │   ├── {{moduleName}}ServiceProvider.php
│   │   ├── RouteServiceProvider.php
│   │   └── EventServiceProvider.php
│   └── Services/
├── config/config.php
├── database/
│   ├── factories/
│   ├── migrations/
│   └── seeders/{{moduleName}}DatabaseSeeder.php
├── resources/
│   ├── js/
│   │   ├── pages/index.tsx
│   │   └── types/{{moduleNameLower}}.ts
│   └── views/
├── routes/
│   ├── web.php
│   └── api.php
├── tests/Feature/
├── tests/Unit/
├── composer.json
├── module.json
├── package.json
└── vite.config.js
```

## Todo List

- [ ] Create ModuleScaffoldCommand class
- [ ] Implement variable substitution logic
- [ ] Create service-provider.stub
- [ ] Create route-service-provider.stub
- [ ] Create event-service-provider.stub
- [ ] Create web-routes.stub
- [ ] Create api-routes.stub
- [ ] Create config.stub
- [ ] Create controller.stub
- [ ] Create database-seeder.stub
- [ ] Create vite.config.stub
- [ ] Create package.json.stub
- [ ] Create composer.json.stub
- [ ] Create module.json.stub
- [ ] Implement --dry-run preview
- [ ] Test basic module generation

## Success Criteria

- [ ] Command registered and visible in `artisan list`
- [ ] Running `module:scaffold Test` creates Modules/Test/ structure
- [ ] All stub variables replaced correctly
- [ ] ServiceProvider registered automatically
- [ ] --dry-run shows file tree without creating

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Stub syntax conflicts | Low | Medium | Use `{{` prefix to avoid Blade conflicts |
| File permission issues | Low | High | Check permissions before write |
| Module name collision | Medium | High | Check if module exists before creating |

## Security Considerations

- Validate module name to prevent path traversal
- Sanitize all stub variables
- No user input directly in file paths without validation

## Next Steps

After Phase 1:
- Proceed to Phase 2 for React/TypeScript templates
- Test generated module structure
- Verify Inertia page resolution works
