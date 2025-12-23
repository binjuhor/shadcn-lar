# Phase 01: Module Setup & Configuration

## Context
- Parent plan: [plan.md](../plan.md)
- Dependencies: None (first phase)

## Overview
- Priority: high
- Status: pending
- Description: Scaffold Mokey module using nwidart/laravel-modules, configure autoloading, create base ServiceProvider following Blog/Ecommerce patterns.

## Key Insights
From research: Use `php artisan module:make Mokey` to generate structure. ServiceProvider must boot policies, load migrations via `loadMigrationsFrom()`. Use `PathNamespace` trait.

## Requirements
### Functional
- Module scaffold with proper directory structure
- ServiceProvider registering sub-providers
- Config file with module settings
- PSR-4 autoloading via composer

### Non-functional
- Follow existing Blog module patterns exactly
- Module must be enable/disable-able

## Related Code Files
### Files to Create
- `Modules/Mokey/Providers/MokeyServiceProvider.php`
- `Modules/Mokey/Providers/RouteServiceProvider.php`
- `Modules/Mokey/Providers/EventServiceProvider.php`
- `Modules/Mokey/config/config.php`
- `Modules/Mokey/module.json`

### Files to Modify
- `composer.json` (verify merge-plugin includes module)

## Implementation Steps

1. Generate module scaffold:
```bash
php artisan module:make Mokey
```

2. Verify directory structure created:
```
Modules/Mokey/
├── config/config.php
├── database/migrations/
├── database/seeders/
├── Http/Controllers/
├── Models/
├── Policies/
├── Providers/
├── routes/api.php
├── routes/web.php
└── module.json
```

3. Update `Modules/Mokey/config/config.php`:
```php
return [
    'name' => 'Mokey',
    'default_currency' => env('MOKEY_DEFAULT_CURRENCY', 'USD'),
    'supported_currencies' => ['USD', 'EUR', 'GBP', 'VND', 'JPY'],
    'audit_enabled' => true,
    'decimal_places' => 2, // for display
];
```

4. Update MokeyServiceProvider following BlogServiceProvider pattern:
- Add `registerPolicies()` method stub
- Use `PathNamespace` trait
- Boot migrations, config, views, translations

5. Run autoload dump:
```bash
composer dump-autoload
```

6. Verify module registered:
```bash
php artisan module:list
```

7. Install audit package:
```bash
composer require owen-it/laravel-auditing
php artisan vendor:publish --provider "OwenIt\Auditing\AuditingServiceProvider" --tag="config"
```

## Todo List
- [ ] Run `php artisan module:make Mokey`
- [ ] Update config.php with mokey settings
- [ ] Customize MokeyServiceProvider (copy Blog pattern)
- [ ] Add EventServiceProvider, RouteServiceProvider
- [ ] Run composer dump-autoload
- [ ] Verify module appears in module:list
- [ ] Install owen-it/laravel-auditing package

## Success Criteria
- [ ] `php artisan module:list` shows Mokey as enabled
- [ ] No autoload errors when running artisan commands
- [ ] Config values accessible via `config('mokey.default_currency')`

## Risk Assessment
- **Risk:** Module not autoloaded. **Mitigation:** Run `composer dump-autoload` and verify PSR-4 mapping.
- **Risk:** Conflicts with existing modules. **Mitigation:** Use unique table prefixes, isolated namespaces.

## Security Considerations
- Audit package will track all model changes (important for financial data)
- Config exposes no secrets, only environment variable references

## Next Steps
Proceed to Phase 02: Database Schema & Migrations
