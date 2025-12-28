# Phase 3: CRUD Generator

**Parent:** [plan.md](./plan.md)
**Dependencies:** Phase 1, Phase 2 complete

## Overview

| Field | Value |
|-------|-------|
| Date | 2024-12-26 |
| Description | Implement --with-crud option for full CRUD scaffolding |
| Priority | Medium |
| Implementation | Pending |
| Review | Pending |

## Key Insights

- CRUD = Model + Migration + Policy + Controller + Frontend pages
- Entity name defaults to singular module name
- Follow AccountController, TransactionController patterns
- Use AuthorizesRequests trait for policy integration

## Requirements

1. Generate Model with fillable and casts
2. Generate Migration with timestamps
3. Generate Policy with standard CRUD methods
4. Generate Controller with resource methods
5. Generate frontend CRUD pages (index, create, edit)

## Architecture

### Command Options
```bash
# Basic module
php artisan module:scaffold Inventory

# With CRUD for "Item" entity
php artisan module:scaffold Inventory --with-crud --entity=Item

# CRUD defaults to module name singular
php artisan module:scaffold Products --with-crud
# Creates Product model, products migration, etc.
```

### Generated Files for --with-crud
```
Modules/{{moduleName}}/
├── app/
│   ├── Http/Controllers/{{entityName}}Controller.php
│   ├── Models/{{entityName}}.php
│   └── Policies/{{entityName}}Policy.php
├── database/migrations/{{timestamp}}_create_{{tableNamePlural}}_table.php
└── resources/js/pages/
    ├── {{entityNamePluralLower}}/
    │   ├── index.tsx
    │   ├── components/
    │   │   └── {{entityNameLower}}-form.tsx
```

## Related Code Files

- `stubs/module-scaffold/crud/model.stub` (create)
- `stubs/module-scaffold/crud/migration.stub` (create)
- `stubs/module-scaffold/crud/policy.stub` (create)
- `stubs/module-scaffold/crud/controller.stub` (create)
- `stubs/module-scaffold/crud/pages/*.stub` (create)
- `Modules/Finance/app/Http/Controllers/AccountController.php` (reference)
- `Modules/Finance/app/Policies/AccountPolicy.php` (reference)

## Implementation Steps

### Step 1: Model Stub

**stubs/module-scaffold/crud/model.stub:**
```php
<?php

namespace Modules\{{moduleName}}\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class {{entityName}} extends Model
{
    use HasFactory;

    protected $table = '{{tableNamePlural}}';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(\App\Models\User::class);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeForUser($query, int $userId)
    {
        return $query->where('user_id', $userId);
    }
}
```

### Step 2: Migration Stub

**stubs/module-scaffold/crud/migration.stub:**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{{tableNamePlural}}', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{{tableNamePlural}}');
    }
};
```

### Step 3: Policy Stub

**stubs/module-scaffold/crud/policy.stub:**
```php
<?php

namespace Modules\{{moduleName}}\Policies;

use App\Models\User;
use Modules\{{moduleName}}\Models\{{entityName}};

class {{entityName}}Policy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, {{entityName}} ${{entityNameLower}}): bool
    {
        return $user->id === ${{entityNameLower}}->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, {{entityName}} ${{entityNameLower}}): bool
    {
        return $user->id === ${{entityNameLower}}->user_id;
    }

    public function delete(User $user, {{entityName}} ${{entityNameLower}}): bool
    {
        return $user->id === ${{entityNameLower}}->user_id;
    }
}
```

### Step 4: Controller Stub

**stubs/module-scaffold/crud/controller.stub:**
```php
<?php

namespace Modules\{{moduleName}}\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Modules\{{moduleName}}\Models\{{entityName}};

class {{entityName}}Controller extends Controller
{
    use AuthorizesRequests;

    public function index(): Response
    {
        ${{entityNamePluralLower}} = {{entityName}}::forUser(auth()->id())
            ->orderBy('created_at', 'desc')
            ->paginate(20);

        return Inertia::render('{{moduleName}}::{{entityNamePluralLower}}/index', [
            '{{entityNamePluralLower}}' => ${{entityNamePluralLower}},
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('{{moduleName}}::{{entityNamePluralLower}}/create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['boolean'],
        ]);

        {{entityName}}::create([
            'user_id' => auth()->id(),
            ...$validated,
        ]);

        return Redirect::route('dashboard.{{moduleNameKebab}}.{{entityNamePluralLower}}.index')
            ->with('success', '{{entityName}} created successfully');
    }

    public function edit({{entityName}} ${{entityNameLower}}): Response
    {
        $this->authorize('update', ${{entityNameLower}});

        return Inertia::render('{{moduleName}}::{{entityNamePluralLower}}/edit', [
            '{{entityNameLower}}' => ${{entityNameLower}},
        ]);
    }

    public function update(Request $request, {{entityName}} ${{entityNameLower}}): RedirectResponse
    {
        $this->authorize('update', ${{entityNameLower}});

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
        ]);

        ${{entityNameLower}}->update($validated);

        return Redirect::back()->with('success', '{{entityName}} updated successfully');
    }

    public function destroy({{entityName}} ${{entityNameLower}}): RedirectResponse
    {
        $this->authorize('delete', ${{entityNameLower}});

        ${{entityNameLower}}->delete();

        return Redirect::route('dashboard.{{moduleNameKebab}}.{{entityNamePluralLower}}.index')
            ->with('success', '{{entityName}} deleted successfully');
    }
}
```

### Step 5: Update Routes Stub for CRUD

Modify web-routes.stub to include resource route:
```php
Route::resource('{{entityNamePluralLower}}', {{entityName}}Controller::class);
```

### Step 6: CRUD Index Page Stub

**stubs/module-scaffold/crud/pages/index.stub:**
```tsx
import { Link, router } from '@inertiajs/react'
import { AuthenticatedLayout } from '@/layouts'
import { Main } from '@/components/layout/main'
import { Button } from '@/components/ui/button'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import {
  Table,
  TableBody,
  TableCell,
  TableHead,
  TableHeader,
  TableRow,
} from '@/components/ui/table'
import { Badge } from '@/components/ui/badge'
import { Plus, Pencil, Trash2 } from 'lucide-react'
import type { PaginatedData } from '@modules/{{moduleName}}/resources/js/types/{{moduleNameLower}}'

interface {{entityName}} {
  id: number
  name: string
  description?: string
  is_active: boolean
  created_at: string
}

interface Props {
  {{entityNamePluralLower}}: PaginatedData<{{entityName}}>
}

export default function {{entityName}}Index({ {{entityNamePluralLower}} }: Props) {
  function handleDelete(id: number) {
    if (confirm('Are you sure you want to delete this {{entityNameLower}}?')) {
      router.delete(route('dashboard.{{moduleNameKebab}}.{{entityNamePluralLower}}.destroy', id))
    }
  }

  return (
    <AuthenticatedLayout title="{{entityNamePlural}}">
      <Main>
        <div className="mb-4 flex items-center justify-between">
          <div>
            <h1 className="text-2xl font-bold tracking-tight">{{entityNamePlural}}</h1>
            <p className="text-muted-foreground">
              Manage your {{entityNamePluralLower}}
            </p>
          </div>
          <Link href={route('dashboard.{{moduleNameKebab}}.{{entityNamePluralLower}}.create')}>
            <Button>
              <Plus className="mr-2 h-4 w-4" />
              Add {{entityName}}
            </Button>
          </Link>
        </div>

        <Card>
          <CardContent className="p-0">
            <Table>
              <TableHeader>
                <TableRow>
                  <TableHead>Name</TableHead>
                  <TableHead>Status</TableHead>
                  <TableHead>Created</TableHead>
                  <TableHead className="text-right">Actions</TableHead>
                </TableRow>
              </TableHeader>
              <TableBody>
                {{{entityNamePluralLower}}.data.map((item) => (
                  <TableRow key={item.id}>
                    <TableCell className="font-medium">{item.name}</TableCell>
                    <TableCell>
                      <Badge variant={item.is_active ? 'default' : 'secondary'}>
                        {item.is_active ? 'Active' : 'Inactive'}
                      </Badge>
                    </TableCell>
                    <TableCell>
                      {new Date(item.created_at).toLocaleDateString()}
                    </TableCell>
                    <TableCell className="text-right">
                      <div className="flex justify-end gap-2">
                        <Link href={route('dashboard.{{moduleNameKebab}}.{{entityNamePluralLower}}.edit', item.id)}>
                          <Button variant="ghost" size="icon">
                            <Pencil className="h-4 w-4" />
                          </Button>
                        </Link>
                        <Button
                          variant="ghost"
                          size="icon"
                          onClick={() => handleDelete(item.id)}
                        >
                          <Trash2 className="h-4 w-4 text-destructive" />
                        </Button>
                      </div>
                    </TableCell>
                  </TableRow>
                ))}
              </TableBody>
            </Table>
          </CardContent>
        </Card>
      </Main>
    </AuthenticatedLayout>
  )
}
```

## Todo List

- [ ] Create model.stub
- [ ] Create migration.stub
- [ ] Create policy.stub
- [ ] Create controller.stub
- [ ] Create CRUD pages stubs (index, create, edit)
- [ ] Create form component stub
- [ ] Update web-routes.stub with resource route
- [ ] Update ServiceProvider stub to register policy
- [ ] Implement entity name derivation logic
- [ ] Test --with-crud option

## Success Criteria

- [ ] `--with-crud` generates Model, Migration, Policy, Controller
- [ ] CRUD pages render and function correctly
- [ ] Policy authorization works
- [ ] Resource routes registered properly
- [ ] Entity name defaults to singular module name

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Policy not auto-discovered | Medium | High | Register in ServiceProvider |
| Route model binding fails | Low | Medium | Use explicit binding in provider |

## Security Considerations

- Policy enforces user ownership
- Input validation in controller
- CSRF protection via Inertia

## Next Steps

After Phase 3:
- Proceed to Phase 4 for testing and documentation
- Manual testing of full CRUD flow
