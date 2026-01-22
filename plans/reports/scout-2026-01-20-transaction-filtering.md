# Scout Report: Transaction Filtering Implementation

**Date:** 2026-01-20  
**Topic:** Transaction Page Filtering  
**Scope:** Finance Module - Transaction list with filtering UI & backend logic

---

## Overview

Located complete transaction filtering system across frontend (React/TypeScript) and backend (Laravel/PHP). Frontend uses Inertia.js for seamless form handling, backend implements query scoping with form request validation.

---

## Files Found

### Frontend - Transaction Page

1. **Transaction Index Page (Main Component)**
   - `/Users/binjuhor/Development/app.mokey/Modules/Finance/resources/js/pages/transactions/index.tsx`
   - 679 lines
   - Contains complete transaction listing with inline filtering UI
   - Implements debounced search (500ms)
   - Shows/hide filter panel
   - Pagination with page numbers
   - Totals summary display when filters active

2. **Export Dialog Component**
   - `/Users/binjuhor/Development/app.mokey/Modules/Finance/resources/js/pages/transactions/components/export-dialog.tsx`
   - 233 lines
   - Separate UI component for exporting filtered transactions
   - Supports CSV & Excel formats
   - Period selection (month/year/custom date range)

3. **Transaction Form Component**
   - `/Users/binjuhor/Development/app.mokey/Modules/Finance/resources/js/pages/transactions/components/transaction-form.tsx`
   - Edit/create transaction form (not primary filtering, but referenced by index)

4. **Create & Edit Pages**
   - `/Users/binjuhor/Development/app.mokey/Modules/Finance/resources/js/pages/transactions/create.tsx`
   - `/Users/binjuhor/Development/app.mokey/Modules/Finance/resources/js/pages/transactions/edit.tsx`

### Backend - Controller & Validation

5. **Transaction Controller**
   - `/Users/binjuhor/Development/app.mokey/Modules/Finance/app/Http/Controllers/TransactionController.php`
   - 340 lines
   - `index()` method: Main filtering logic (lines 29-94)
   - Handles all filter parameters from request
   - Calculates totals using SQL aggregation
   - Loads accounts & categories for filter dropdowns
   - Uses Inertia to render frontend

6. **Form Request Validation**
   - `/Users/binjuhor/Development/app.mokey/Modules/Finance/app/Http/Requests/Transaction/IndexTransactionRequest.php`
   - 31 lines
   - Validates all filter parameters
   - `filters()` method: Returns active filters as array (line 26-29)

### Backend - Model

7. **Transaction Model**
   - `/Users/binjuhor/Development/app.mokey/Modules/Finance/Models/Transaction.php`
   - 104 lines
   - Database relationships: account, category, transferAccount, transferTransaction
   - Accessor: `type` (maps transaction_type)
   - Utility methods: `isTransfer()`, `isReconciled()`

---

## Filter Implementation Details

### Frontend Filters (lines 275-375 in index.tsx)

**Currently Implemented:**
- **Account**: Dropdown filter (SelectItem per account)
- **Category**: Dropdown filter (SelectItem per category)
- **Type**: Dropdown for income/expense/transfer
- **Date Range**: DatePicker components (from & to dates)
- **Search**: Debounced text input for description search
- **Clear Filters**: Button to reset all filters to defaults

**UI State Management:**
- `showFilters` state: Toggle filter panel visibility
- `searchQuery` state: Debounced search input
- `handleFilterChange()`: Updates router with new filter params
- `handleSuccess()`: Reloads transaction data after form submission

**Filter Props Passed from Backend:**
```typescript
filters: {
  account_id?: string
  category_id?: string
  type?: string
  search?: string
  date_from?: string
  date_to?: string
}
```

**Totals Display:**
- Shows when any filter is active
- Grid display: Total Count, Total Income, Total Expense, Net
- Calculations done server-side (lines 61-65 in controller)

### Backend Filter Logic (TransactionController::index)

**Query Building (lines 33-58):**
```php
$query = Transaction::with(['account', 'category', 'transferAccount'])
    ->whereHas('account', fn ($q) => $q->where('user_id', $userId));

// Apply filters conditionally
if ($request->account_id) { $query->where('account_id', $request->account_id); }
if ($request->category_id) { $query->where('category_id', $request->category_id); }
if ($request->type) { $query->where('transaction_type', $request->type); }
if ($request->date_from) { $query->whereDate('transaction_date', '>=', $request->date_from); }
if ($request->date_to) { $query->whereDate('transaction_date', '<=', $request->date_to); }
if ($request->search) { $query->where('description', 'like', '%'.$request->search.'%'); }
```

**Totals Calculation (lines 61-65):**
- Clones query before pagination
- Uses SQL CASE statements to sum income/expense separately
- Counts total transactions matching filters
- Returns: total_income, total_expense, transaction_count

**Pagination:**
- 50 items per page
- `withQueryString()` maintains filter params across pages

**Data Returned to Frontend:**
- Paginated transactions with relationships
- Active accounts (is_active=true)
- Active categories
- Calculated totals
- Original request filters for UI state

---

## Key Integration Points

### Inertia.js Flow
1. Frontend component sends router.get() with filter params
2. Route calls TransactionController::index()
3. Controller validates via IndexTransactionRequest
4. Backend calculates results + totals
5. Inertia::render() sends data back to frontend
6. React component updates with `preserveState: true`

### User Authorization
- Filtered by current authenticated user via `$userId = auth()->id()`
- Accounts must belong to user: `whereHas('account', fn ($q) => $q->where('user_id', $userId))`

### Database Schema Fields
- `finance_transactions` table columns used:
  - `account_id` (FK to finance_accounts)
  - `category_id` (FK to finance_categories)
  - `transaction_type` (enum: income, expense, transfer)
  - `description` (searchable text)
  - `transaction_date` (date for range filtering)
  - `amount` (currency field)
  - `currency_code`
  - `reconciled_at` (soft reconciliation tracking)

---

## Filter Features Summary

| Feature | Frontend | Backend | Notes |
|---------|----------|---------|-------|
| Account Filter | Dropdown (SelectItem) | where('account_id', ...) | Loads active accounts |
| Category Filter | Dropdown (SelectItem) | where('category_id', ...) | Loads active categories |
| Type Filter | Dropdown (income/expense/transfer) | where('transaction_type', ...) | 3 fixed options |
| Date Range | DatePicker (from/to) | whereDate() >= / <= | Server-side date validation |
| Search | Debounced text input | like '%search%' | Searches description field only |
| Clear Filters | Reset button | Removes all params | Keeps search if set |
| Totals | Shows when filters active | selectRaw() SQL aggregation | Income/Expense/Count/Net |
| Pagination | 50 per page with buttons | paginate(50) | withQueryString() maintains filters |

---

## Code Architecture Notes

### React/TypeScript Frontend
- Uses Inertia router for server-side filtering (not client-side)
- Debounced search with useRef timeout management (lines 107-138)
- Preserves scroll/state across filter changes
- Type-safe Props interface (lines 66-84)

### Laravel Backend
- Form Request validation before controller logic
- Eager loading relationships to avoid N+1 queries
- Cloned query for aggregation (doesn't affect pagination)
- User-scoped queries for data security
- Soft deletes respected (via SoftDeletes trait)

### Database Considerations
- All filter fields have indexes (implied by FK relationships)
- Date range filtering uses whereDate() for proper comparison
- Description search uses LIKE (could benefit from FULLTEXT if scaling)

---

## Unresolved Questions

- Are there any query scopes defined on Transaction model for filtering? (None found in model file)
- Is there any advanced filtering logic in the TransactionService? (Not reviewed)
- Are there API endpoints that also support this same filtering? (API controller exists but not reviewed)
