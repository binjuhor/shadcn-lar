# Invoice Management Module - Implementation Plan

**Created:** 2025-12-19
**Status:** Planning
**Complexity:** Basic
**Tech Stack:** Laravel 12.x + React/TypeScript + Inertia.js + ShadcnUI + Tabler Icons

---

## Overview

Basic invoice management module with persistent storage, full CRUD operations, and PDF export. Follows existing codebase patterns (users module). Uses `barryvdh/laravel-dompdf` for PDF generation (shared hosting compatible).

---

## Phases

| Phase | Name | Status | File |
|-------|------|--------|------|
| 01 | Database & Models | Pending | [phase-01-database-models.md](./phase-01-database-models.md) |
| 02 | Backend API | Pending | [phase-02-backend-api.md](./phase-02-backend-api.md) |
| 03 | Frontend Components | Pending | [phase-03-frontend-components.md](./phase-03-frontend-components.md) |
| 04 | Integration & Testing | Pending | [phase-04-integration-testing.md](./phase-04-integration-testing.md) |

---

## Dependencies

**Backend:**
- `barryvdh/laravel-dompdf` - PDF generation

**Frontend:**
- Existing: ShadcnUI, TanStack React Table, react-hook-form, zod

---

## Key Files (to create)

**Backend:**
- `app/Models/Invoice.php`, `app/Models/InvoiceItem.php`
- `app/Http/Controllers/InvoiceController.php`
- `app/Http/Requests/Invoice/StoreInvoiceRequest.php`, `UpdateInvoiceRequest.php`
- `database/migrations/*_create_invoices_table.php`, `*_create_invoice_items_table.php`
- `resources/views/invoices/pdf.blade.php`

**Frontend:**
- `resources/js/pages/invoices/index.tsx`
- `resources/js/pages/invoices/create.tsx`, `edit.tsx`, `show.tsx`
- `resources/js/pages/invoices/components/*`
- `resources/js/pages/invoices/context/invoices-context.tsx`
- `resources/js/pages/invoices/data/schema.ts`

---

## Research Reports

1. [Laravel PDF Generation](./research/researcher-01-laravel-pdf-generation.md)
2. [React Invoice UI Patterns](./research/researcher-02-react-invoice-ui-patterns.md)

---

## Notes

- Follow users module pattern exactly
- Keep MVP scope: no discounts, no multi-currency, no recurring invoices
- Invoice number format: `INV-YYYYMMDD-XXXX` (auto-generated)
- Status values: draft, sent, paid, overdue, cancelled
