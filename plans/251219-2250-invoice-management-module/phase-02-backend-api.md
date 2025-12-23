# Phase 02: Backend API

**Status:** Pending
**Estimated Effort:** 3-4 hours
**Depends On:** Phase 01

---

## Context Links

- [Main Plan](./plan.md)
- [Phase 01: Database](./phase-01-database-models.md)
- [Laravel PDF Research](./research/researcher-01-laravel-pdf-generation.md)
- Existing pattern: `/Users/binjuhor/Development/shadcn-admin/routes/dashboard.php`

---

## Overview

Create InvoiceController with CRUD operations and PDF export. Use Inertia.js for rendering React pages. Install `barryvdh/laravel-dompdf` for PDF generation.

---

## Key Insights

- Controller follows resource pattern (index, create, store, show, edit, update, destroy)
- Form Requests for validation (StoreInvoiceRequest, UpdateInvoiceRequest)
- PDF uses Blade template, not React
- Inertia passes data as props to React components
- Transaction wrapping for invoice + items save

---

## Requirements

1. InvoiceController with all CRUD methods
2. Form Request classes for validation
3. PDF export endpoint
4. Routes in dashboard.php
5. DomPDF package installation

---

## Architecture

### Route Structure

```
GET    /dashboard/invoices              -> index
GET    /dashboard/invoices/create       -> create
POST   /dashboard/invoices              -> store
GET    /dashboard/invoices/{invoice}    -> show
GET    /dashboard/invoices/{invoice}/edit -> edit
PUT    /dashboard/invoices/{invoice}    -> update
DELETE /dashboard/invoices/{invoice}    -> destroy
GET    /dashboard/invoices/{invoice}/pdf -> pdf (download)
```

### Controller Methods

| Method | Description | Returns |
|--------|-------------|---------|
| index | List all invoices (paginated) | Inertia page |
| create | Show create form | Inertia page |
| store | Save new invoice | Redirect |
| show | View single invoice | Inertia page |
| edit | Show edit form | Inertia page |
| update | Update invoice | Redirect |
| destroy | Delete invoice | Redirect |
| pdf | Download PDF | PDF file |

---

## Related Code Files

**Create:**
- `/Users/binjuhor/Development/shadcn-admin/app/Http/Controllers/InvoiceController.php`
- `/Users/binjuhor/Development/shadcn-admin/app/Http/Requests/Invoice/StoreInvoiceRequest.php`
- `/Users/binjuhor/Development/shadcn-admin/app/Http/Requests/Invoice/UpdateInvoiceRequest.php`
- `/Users/binjuhor/Development/shadcn-admin/resources/views/invoices/pdf.blade.php`

**Modify:**
- `/Users/binjuhor/Development/shadcn-admin/routes/dashboard.php`

**Reference:**
- `/Users/binjuhor/Development/shadcn-admin/app/Http/Controllers/ProfileController.php`
- `/Users/binjuhor/Development/shadcn-admin/app/Http/Requests/ProfileUpdateRequest.php`

---

## Implementation Steps

### 1. Install DomPDF

```bash
composer require barryvdh/laravel-dompdf
php artisan vendor:publish --provider="Barryvdh\DomPDF\ServiceProvider"
```

### 2. Create Form Requests

```php
// app/Http/Requests/Invoice/StoreInvoiceRequest.php
namespace App\Http\Requests\Invoice;

use Illuminate\Foundation\Http\FormRequest;

class StoreInvoiceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'invoice_date' => ['required', 'date'],
            'due_date' => ['required', 'date', 'after_or_equal:invoice_date'],
            'from_name' => ['required', 'string', 'max:255'],
            'from_address' => ['nullable', 'string', 'max:1000'],
            'from_email' => ['nullable', 'email', 'max:255'],
            'from_phone' => ['nullable', 'string', 'max:50'],
            'to_name' => ['required', 'string', 'max:255'],
            'to_address' => ['nullable', 'string', 'max:1000'],
            'to_email' => ['nullable', 'email', 'max:255'],
            'tax_rate' => ['required', 'numeric', 'min:0', 'max:1'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.description' => ['required', 'string', 'max:500'],
            'items.*.quantity' => ['required', 'numeric', 'min:0.01'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0'],
        ];
    }
}
```

```php
// app/Http/Requests/Invoice/UpdateInvoiceRequest.php
// Same as StoreInvoiceRequest, add status field
'status' => ['required', 'in:draft,sent,paid,overdue,cancelled'],
```

### 3. Create InvoiceController

```php
// app/Http/Controllers/InvoiceController.php
namespace App\Http\Controllers;

use App\Http\Requests\Invoice\StoreInvoiceRequest;
use App\Http\Requests\Invoice\UpdateInvoiceRequest;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class InvoiceController extends Controller
{
    public function index()
    {
        $invoices = Invoice::with('items')
            ->where('user_id', auth()->id())
            ->latest('invoice_date')
            ->paginate(15);

        return Inertia::render('invoices/index', [
            'invoices' => $invoices,
        ]);
    }

    public function create()
    {
        return Inertia::render('invoices/create');
    }

    public function store(StoreInvoiceRequest $request)
    {
        $validated = $request->validated();

        DB::transaction(function () use ($validated) {
            $invoice = Invoice::create([
                ...$validated,
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'user_id' => auth()->id(),
                'status' => 'draft',
            ]);

            foreach ($validated['items'] as $index => $item) {
                $invoice->items()->create([
                    ...$item,
                    'sort_order' => $index,
                ]);
            }

            $invoice->calculateTotals();
            $invoice->save();
        });

        return redirect()->route('invoices.index')
            ->with('success', 'Invoice created successfully.');
    }

    public function show(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        return Inertia::render('invoices/show', [
            'invoice' => $invoice->load('items'),
        ]);
    }

    public function edit(Invoice $invoice)
    {
        $this->authorize('update', $invoice);

        return Inertia::render('invoices/edit', [
            'invoice' => $invoice->load('items'),
        ]);
    }

    public function update(UpdateInvoiceRequest $request, Invoice $invoice)
    {
        $this->authorize('update', $invoice);
        $validated = $request->validated();

        DB::transaction(function () use ($validated, $invoice) {
            $invoice->update($validated);
            $invoice->items()->delete();

            foreach ($validated['items'] as $index => $item) {
                $invoice->items()->create([
                    ...$item,
                    'sort_order' => $index,
                ]);
            }

            $invoice->calculateTotals();
            $invoice->save();
        });

        return redirect()->route('invoices.index')
            ->with('success', 'Invoice updated successfully.');
    }

    public function destroy(Invoice $invoice)
    {
        $this->authorize('delete', $invoice);

        $invoice->delete();

        return redirect()->route('invoices.index')
            ->with('success', 'Invoice deleted successfully.');
    }

    public function pdf(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $pdf = Pdf::loadView('invoices.pdf', [
            'invoice' => $invoice->load('items'),
        ]);

        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }
}
```

### 4. Create Policy

```php
// app/Policies/InvoicePolicy.php
namespace App\Policies;

use App\Models\Invoice;
use App\Models\User;

class InvoicePolicy
{
    public function view(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id;
    }

    public function update(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id;
    }

    public function delete(User $user, Invoice $invoice): bool
    {
        return $user->id === $invoice->user_id;
    }
}
```

### 5. Add Routes

```php
// routes/dashboard.php - add to file
Route::resource('invoices', InvoiceController::class);
Route::get('invoices/{invoice}/pdf', [InvoiceController::class, 'pdf'])
    ->name('invoices.pdf');
```

### 6. Create PDF Template

```blade
{{-- resources/views/invoices/pdf.blade.php --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Invoice {{ $invoice->invoice_number }}</title>
    <style>
        body { font-family: sans-serif; font-size: 12px; }
        .header { margin-bottom: 20px; }
        .invoice-number { font-size: 24px; font-weight: bold; }
        .addresses { display: flex; justify-content: space-between; margin-bottom: 20px; }
        .from, .to { width: 45%; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 20px; }
        th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
        th { background-color: #f5f5f5; }
        .totals { text-align: right; }
        .totals td { border: none; }
        .total-row { font-weight: bold; font-size: 14px; }
        .notes { margin-top: 20px; padding: 10px; background: #f9f9f9; }
    </style>
</head>
<body>
    <div class="header">
        <div class="invoice-number">Invoice {{ $invoice->invoice_number }}</div>
        <div>Date: {{ $invoice->invoice_date->format('M d, Y') }}</div>
        <div>Due: {{ $invoice->due_date->format('M d, Y') }}</div>
        <div>Status: {{ ucfirst($invoice->status) }}</div>
    </div>

    <table style="border: none; margin-bottom: 30px;">
        <tr>
            <td style="border: none; width: 50%; vertical-align: top;">
                <strong>From:</strong><br>
                {{ $invoice->from_name }}<br>
                {!! nl2br(e($invoice->from_address)) !!}
                @if($invoice->from_email)<br>{{ $invoice->from_email }}@endif
                @if($invoice->from_phone)<br>{{ $invoice->from_phone }}@endif
            </td>
            <td style="border: none; width: 50%; vertical-align: top;">
                <strong>To:</strong><br>
                {{ $invoice->to_name }}<br>
                {!! nl2br(e($invoice->to_address)) !!}
                @if($invoice->to_email)<br>{{ $invoice->to_email }}@endif
            </td>
        </tr>
    </table>

    <table>
        <thead>
            <tr>
                <th style="width: 50%;">Description</th>
                <th style="width: 15%; text-align: right;">Qty</th>
                <th style="width: 15%; text-align: right;">Unit Price</th>
                <th style="width: 20%; text-align: right;">Amount</th>
            </tr>
        </thead>
        <tbody>
            @foreach($invoice->items as $item)
            <tr>
                <td>{{ $item->description }}</td>
                <td style="text-align: right;">{{ number_format($item->quantity, 2) }}</td>
                <td style="text-align: right;">${{ number_format($item->unit_price, 2) }}</td>
                <td style="text-align: right;">${{ number_format($item->amount, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals">
        <tr>
            <td style="width: 70%;"></td>
            <td>Subtotal:</td>
            <td style="text-align: right;">${{ number_format($invoice->subtotal, 2) }}</td>
        </tr>
        <tr>
            <td></td>
            <td>Tax ({{ $invoice->tax_rate * 100 }}%):</td>
            <td style="text-align: right;">${{ number_format($invoice->tax_amount, 2) }}</td>
        </tr>
        <tr class="total-row">
            <td></td>
            <td>Total:</td>
            <td style="text-align: right;">${{ number_format($invoice->total, 2) }}</td>
        </tr>
    </table>

    @if($invoice->notes)
    <div class="notes">
        <strong>Notes:</strong><br>
        {!! nl2br(e($invoice->notes)) !!}
    </div>
    @endif
</body>
</html>
```

---

## Todo List

- [ ] Install barryvdh/laravel-dompdf
- [ ] Create StoreInvoiceRequest
- [ ] Create UpdateInvoiceRequest
- [ ] Create InvoicePolicy
- [ ] Register policy in AuthServiceProvider
- [ ] Create InvoiceController with all methods
- [ ] Add routes to dashboard.php
- [ ] Create PDF Blade template
- [ ] Test endpoints with Postman/curl
- [ ] Verify PDF generation works

---

## Success Criteria

1. All CRUD routes accessible
2. Validation errors returned properly
3. Invoice created with auto-generated number
4. Invoice totals calculated correctly
5. PDF downloads with proper formatting
6. Authorization prevents access to other users' invoices

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| PDF styling issues | Medium | Low | Test early, use simple CSS |
| Transaction failures | Low | High | Proper DB::transaction |
| Large PDF files | Low | Medium | Keep template simple |

---

## Security Considerations

- Policy prevents unauthorized access
- Form validation prevents injection
- CSRF protection (Inertia default)
- Rate limiting: 10 PDF downloads per day per user

---

## Next Steps

After completing Phase 02:
1. Test all endpoints manually
2. Proceed to Phase 03: Frontend Components
