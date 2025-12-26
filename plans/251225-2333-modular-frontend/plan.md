# Modular Frontend Architecture Plan

**Date:** 2025-12-25
**Status:** Completed
**Goal:** Move module-related frontend pages to respective Laravel module directories for independent distribution

---

## Overview

Migrate React/Inertia pages from `resources/js/pages/` to `Modules/*/resources/js/pages/` enabling:
- Independent module download/distribution
- Module-specific frontend ownership
- Clean separation of concerns

## Key Decisions

1. **Resolution Pattern:** Namespace syntax (`Module::page/path`) - avoids collisions, semantic clarity
2. **Build Strategy:** Unified Vite config with manual chunks - single process, shared vendor bundles
3. **Shared Components:** Keep in `resources/js/components/` - DRY principle, avoid duplication
4. **Migration Order:** Build system first, then migrate modules alphabetically

## Phases

| # | Phase | Priority | Status | Doc |
|---|-------|----------|--------|-----|
| 01 | Vite & TypeScript Config | P0 | completed | [phase-01](phases/phase-01-vite-typescript-config.md) |
| 02 | Inertia Page Resolution | P0 | completed | [phase-02](phases/phase-02-inertia-page-resolution.md) |
| 03 | Module Directory Structure | P1 | completed | [phase-03](phases/phase-03-module-directory-structure.md) |
| 04 | Blog Module Migration | P1 | completed | [phase-04](phases/phase-04-blog-module-migration.md) |
| 05 | Ecommerce Module Migration | P1 | completed | [phase-05](phases/phase-05-ecommerce-module-migration.md) |
| 06 | Invoice Module Migration | P1 | completed | [phase-06](phases/phase-06-invoice-module-migration.md) |
| 07 | Notification Module Migration | P1 | completed | [phase-07](phases/phase-07-notification-module-migration.md) |
| 08 | Permission Module Migration | P1 | completed | [phase-08](phases/phase-08-permission-module-migration.md) |
| 09 | Testing & Validation | P0 | completed | [phase-09](phases/phase-09-testing-validation.md) |

## Build Output (Module Chunks)

| Module | Chunk Size | Gzip |
|--------|------------|------|
| Blog | 203 KB | 44 KB |
| Ecommerce | 74 KB | 12 KB |
| Invoice | 31 KB | 8 KB |
| Notification | 30 KB | 7 KB |
| Permission | 55 KB | 11 KB |

## Dependencies

- Research: `research/researcher-01-inertia-modular-pages.md`
- Research: `research/researcher-02-vite-multi-module.md`

## Success Criteria

- [x] All module pages load correctly via namespace syntax
- [x] HMR works in development for all modules
- [x] Production build generates proper chunks
- [x] Existing routes unchanged (backward compatible)
- [x] TypeScript path aliases resolve correctly
