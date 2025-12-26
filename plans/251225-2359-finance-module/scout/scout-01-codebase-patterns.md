# Codebase Patterns Scout Report
**Date:** 2025-12-25 | **Task:** Finance Module Creation | **Status:** Complete

## Module Directory Structure

All modules follow Laravel modular architecture in `Modules/` directory:

```
Modules/{ModuleName}/
├── config/
│   └── config.php
├── database/
│   ├── migrations/
│   ├── seeders/
│   └── factories/
├── Http/
│   ├── Controllers/
│   ├── Requests/
│   ├── Resources/
│   └── Middleware/
├── Models/
├── Policies/
├── Providers/
│   ├── {Module}ServiceProvider.php
│   ├── RouteServiceProvider.php
│   └── EventServiceProvider.php
├── routes/
│   ├── web.php
│   └── api.php
├── resources/
│   └── views/ (for Blade exports)
├── module.json
└── composer.json
```

## Controller Patterns

Controllers extend `App\Http\Controllers\Controller` & follow RESTful CRUD:

**Key Patterns:**
- Resource controller methods: `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`
- Use `Inertia::render('page-slug', [props])` for frontend rendering
- Authorization via `$this->authorize('action', $model)` - requires Policy
- Use `DB::transaction()` for atomic operations (see Invoice store/update)
- Return redirect with flash messages: `redirect()->route(...)->with('success', msg)`

**Reference:** `/Users/binjuhor/Development/shadcn-admin/Modules/Invoice/Http/Controllers/InvoiceController.php`
- Lines 16-26: Index with pagination (15 per page)
- Lines 33-70: Store with transaction & related items
- Lines 90-128: Update with item management

## Model Patterns

Models use typed properties, relationships, and business logic methods:

**Key Patterns:**
- Use `HasFactory, SoftDeletes` traits
- Define `$fillable` array explicitly
- Use `protected function casts()` (PHP 8.2+ style)
- Define relationships: `BelongsTo`, `HasMany`, `HasManyThrough`
- Place calculation/generation methods on model (e.g., `calculateTotals()`, `generateInvoiceNumber()`)
- Use factory reference: `protected static function newFactory()`

**Reference:** `/Users/binjuhor/Development/shadcn-admin/Modules/Invoice/Models/Invoice.php`
- Lines 54-62: Relationships setup
- Lines 64-69: Business logic (`calculateTotals()`)
- Lines 71-87: Static generation method

## Request Classes

Form requests validate & authorize in one place:

**Pattern:**
```php
class Store{Resource}Request extends FormRequest {
  public function authorize(): bool { return true; }
  public function rules(): array { 
    return [
      'field' => ['required', 'type', 'constraints'],
      'nested.*' => ['rules']
    ];
  }
}
```

**Reference:** `/Users/binjuhor/Development/shadcn-admin/Modules/Invoice/Http/Requests/StoreInvoiceRequest.php`

## Migration Patterns

Migrations in `database/migrations/` follow timestamped naming:

**Key Patterns:**
- Use `Schema::create()` for tables
- Add indexes on frequently queried columns: `$table->index('column_name')`
- Use `decimal(12, 2)` for monetary values
- Use `softDeletes()` for soft delete support
- Foreign keys use `foreignId('user_id')->constrained()->cascadeOnDelete()`

**Reference:** `/Users/binjuhor/Development/shadcn-admin/Modules/Invoice/database/migrations/2025_12_19_160653_create_invoices_table.php`

## Frontend Component Patterns

Frontend pages in `/resources/js/pages/{module-slug}/`:

**Structure:**
```
resources/js/pages/{module}/
├── index.tsx          (list view)
├── create.tsx         (create form)
├── edit.tsx           (edit form)
├── show.tsx           (detail view)
├── components/
│   ├── {resource}-form.tsx
│   ├── {resource}s-table.tsx
│   ├── {resource}s-columns.tsx
│   ├── {resource}s-dialogs.tsx
│   └── {resource}-summary.tsx
├── context/
│   └── {resource}s-context.tsx
└── data/
    ├── schema.ts
    ├── data.ts
    └── {resource}s.ts
```

**Component Patterns:**
- Use Inertia `useForm()` hook for data binding
- Use ShadcnUI components: Button, Input, Textarea, Calendar, Popover, etc.
- Date handling: `date-fns` library (format, parse dates)
- Context for shared state between components
- Data schema files for TypeScript types
- Summary/totals calculated client-side

**Reference:** `/Users/binjuhor/Development/shadcn-admin/resources/js/pages/invoices/components/invoice-form.tsx`
- Lines 1-17: Imports (Inertia, UI, utilities)
- Lines 43-57: Form state setup with useForm hook
- Date handling with calendar popover

## Route Patterns

Routes registered in module's `RouteServiceProvider`:

**Pattern:**
```php
Route::middleware(['auth', 'verified'])
  ->prefix('dashboard')
  ->name('dashboard.')
  ->group(function () {
    Route::resource('resources', ResourceController::class);
    Route::get('resources/{id}/action', [Controller::class, 'action'])->name('action');
  });
```

**Reference:** `/Users/binjuhor/Development/shadcn-admin/Modules/Invoice/routes/web.php`

## Service Provider Pattern

Each module has a `{Module}ServiceProvider` that bootstraps everything:

**Responsibilities:**
- Register routes via `RouteServiceProvider`
- Register events via `EventServiceProvider`
- Register policies: `Gate::policy(Model::class, Policy::class)`
- Load migrations: `$this->loadMigrationsFrom(module_path())`
- Load translations, views, config

**Reference:** `/Users/binjuhor/Development/shadcn-admin/Modules/Invoice/Providers/InvoiceServiceProvider.php`

## Policy Pattern

Authorization policies define model-level access:

**Location:** `Modules/{Module}/Policies/{Model}Policy.php`
Methods should match controller actions: `viewAny()`, `view()`, `create()`, `update()`, `delete()`

## Existing Modules (Reference)

- **Invoice** - Financial/billing (most similar to Finance needs)
- **Blog** - Content management with categories, tags, resources
- **Ecommerce** - Product catalog, orders
- **Settings** - Configuration management
- **Permission** - RBAC system
- **Notification** - Alert/notification system

## Key Files to Reference

### Invoice Module (Primary Reference)
- Controller: `/Users/binjuhor/Development/shadcn-admin/Modules/Invoice/Http/Controllers/InvoiceController.php`
- Model: `/Users/binjuhor/Development/shadcn-admin/Modules/Invoice/Models/Invoice.php`
- Migration: `/Users/binjuhor/Development/shadcn-admin/Modules/Invoice/database/migrations/2025_12_19_160653_create_invoices_table.php`
- Frontend Form: `/Users/binjuhor/Development/shadcn-admin/resources/js/pages/invoices/components/invoice-form.tsx`
- Routes: `/Users/binjuhor/Development/shadcn-admin/Modules/Invoice/routes/web.php`

### Blog Module (Query Pattern Reference)
- Controller: `/Users/binjuhor/Development/shadcn-admin/Modules/Blog/Http/Controllers/PostController.php` (filtering, relationships)

### Settings Module (Bootstrap Reference)
- Service Provider: `/Users/binjuhor/Development/shadcn-admin/Modules/Settings/Providers/SettingsServiceProvider.php`

## Code Standards Observed

- PSR-12 compliance
- Typed properties in models
- Constructor property promotion where applicable
- Early returns for error conditions
- ShadcnUI + TailwindCSS for styling
- Inertia.js for server-driven components
