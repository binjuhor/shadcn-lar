# Phase 4: Testing & Documentation

**Parent:** [plan.md](./plan.md)
**Dependencies:** Phases 1-3 complete

## Overview

| Field | Value |
|-------|-------|
| Date | 2024-12-26 |
| Description | Test command and create documentation |
| Priority | Medium |
| Implementation | Pending |
| Review | Pending |

## Key Insights

- Need Feature test for command execution
- Documentation should include usage examples
- Consider adding stub publish command for customization

## Requirements

1. Feature test for ModuleScaffoldCommand
2. Usage documentation
3. Stub customization guide

## Related Code Files

- `tests/Feature/ModuleScaffoldCommandTest.php` (create)
- `docs/module-scaffold.md` (create, if user requests)

## Implementation Steps

### Step 1: Feature Test

**tests/Feature/ModuleScaffoldCommandTest.php:**
```php
<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ModuleScaffoldCommandTest extends TestCase
{
    protected function tearDown(): void
    {
        // Clean up test module
        if (File::isDirectory(base_path('Modules/TestModule'))) {
            File::deleteDirectory(base_path('Modules/TestModule'));
        }
        parent::tearDown();
    }

    public function test_creates_module_structure(): void
    {
        $this->artisan('module:scaffold', ['name' => 'TestModule'])
            ->expectsOutput('Module TestModule created successfully!')
            ->assertExitCode(0);

        $this->assertDirectoryExists(base_path('Modules/TestModule'));
        $this->assertFileExists(base_path('Modules/TestModule/module.json'));
        $this->assertFileExists(base_path('Modules/TestModule/app/Providers/TestModuleServiceProvider.php'));
        $this->assertFileExists(base_path('Modules/TestModule/resources/js/pages/index.tsx'));
    }

    public function test_creates_crud_structure(): void
    {
        $this->artisan('module:scaffold', [
            'name' => 'TestModule',
            '--with-crud' => true,
            '--entity' => 'Item',
        ])->assertExitCode(0);

        $this->assertFileExists(base_path('Modules/TestModule/app/Models/Item.php'));
        $this->assertFileExists(base_path('Modules/TestModule/app/Http/Controllers/ItemController.php'));
        $this->assertFileExists(base_path('Modules/TestModule/app/Policies/ItemPolicy.php'));
    }

    public function test_dry_run_does_not_create_files(): void
    {
        $this->artisan('module:scaffold', [
            'name' => 'TestModule',
            '--dry-run' => true,
        ])->assertExitCode(0);

        $this->assertDirectoryDoesNotExist(base_path('Modules/TestModule'));
    }

    public function test_rejects_invalid_module_name(): void
    {
        $this->artisan('module:scaffold', ['name' => 'invalid-name'])
            ->expectsOutput('Module name must be PascalCase')
            ->assertExitCode(1);
    }

    public function test_rejects_existing_module(): void
    {
        File::makeDirectory(base_path('Modules/TestModule'), 0755, true);

        $this->artisan('module:scaffold', ['name' => 'TestModule'])
            ->expectsOutput('Module TestModule already exists')
            ->assertExitCode(1);
    }
}
```

### Step 2: Usage Examples

```bash
# Basic module creation
php artisan module:scaffold Inventory

# With CRUD for default entity (Inventory -> singular "Inventory")
php artisan module:scaffold Inventory --with-crud

# With CRUD for specific entity
php artisan module:scaffold Warehouse --with-crud --entity=Product

# Preview without creating files
php artisan module:scaffold Inventory --dry-run
```

### Step 3: Post-Creation Checklist

After running the command, user must:

1. **Run migrations:**
   ```bash
   php artisan migrate
   ```

2. **Add to sidebar navigation:**
   Edit `resources/js/components/layout/data/sidebar-data.ts`

3. **Clear caches (if needed):**
   ```bash
   php artisan optimize:clear
   ```

4. **Build frontend:**
   ```bash
   yarn build
   ```

## Todo List

- [ ] Create ModuleScaffoldCommandTest.php
- [ ] Test basic module creation
- [ ] Test --with-crud option
- [ ] Test --dry-run option
- [ ] Test error cases
- [ ] Add command help text
- [ ] Create inline documentation

## Success Criteria

- [ ] All tests pass
- [ ] Command includes --help documentation
- [ ] User can generate and use new module

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Test isolation issues | Medium | Low | Clean up in tearDown |
| Flaky filesystem tests | Low | Medium | Use temp directories |

## Security Considerations

- Tests should not expose sensitive paths
- Clean up test artifacts

## Next Steps

After Phase 4:
- Command ready for use
- Consider publishing stubs for customization
- Monitor usage and gather feedback
