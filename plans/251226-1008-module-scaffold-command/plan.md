# Module Scaffold Command Implementation Plan

**Created:** 2024-12-26
**Status:** Complete
**Priority:** High

## Overview

Create `php artisan module:scaffold` command to generate complete Laravel modules with React/TypeScript/Inertia frontend scaffolding, extending nwidart/laravel-modules.

## Phases

| # | Phase | Status | Progress |
|---|-------|--------|----------|
| 1 | [Core Command & Stubs](./phase-01-core-command-stubs.md) | Complete | 100% |
| 2 | [Frontend Templates](./phase-02-frontend-templates.md) | Complete | 100% |
| 3 | [CRUD Generator](./phase-03-crud-generator.md) | Complete | 100% |
| 4 | [Testing & Documentation](./phase-04-testing-documentation.md) | Partial | 50% |

## Key Decisions

1. **Command Location**: `app/Console/Commands/ModuleScaffoldCommand.php`
2. **Stubs Location**: `stubs/module-scaffold/`
3. **Approach**: Wrap existing `module:make` then add frontend scaffolding
4. **Options**: `--with-crud`, `--entity=`, `--dry-run`

## Architecture

```
app/Console/Commands/
└── ModuleScaffoldCommand.php

stubs/module-scaffold/
├── service-provider.stub
├── route-service-provider.stub
├── event-service-provider.stub
├── web-routes.stub
├── api-routes.stub
├── config.stub
├── controller.stub
├── database-seeder.stub
├── vite.config.stub
├── package.json.stub
├── composer.json.stub
├── module.json.stub
├── resources/
│   └── js/
│       ├── pages/
│       │   └── index.stub
│       └── types/
│           └── module.stub
└── crud/
    ├── model.stub
    ├── migration.stub
    ├── policy.stub
    ├── controller.stub
    └── pages/
        └── index.stub
```

## Success Criteria

- [x] `php artisan module:scaffold ModuleName` creates full module structure
- [x] Generated module appears in `module:list`
- [x] Routes registered and accessible
- [x] React pages render via Inertia
- [x] TypeScript types importable via `@modules/`
- [x] `--with-crud` generates model, migration, policy, CRUD controller
- [x] `--dry-run` shows preview without file creation

## Dependencies

- nwidart/laravel-modules v12.0
- Inertia.js + React + TypeScript
- Vite with @modules alias
- Existing module patterns (Finance, Invoice, Blog)
