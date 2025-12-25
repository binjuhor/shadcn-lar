<?php

namespace Modules\Invoice\Http\Controllers;

use App\Http\Controllers\Controller;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Modules\Invoice\Http\Requests\StoreInvoiceRequest;
use Modules\Invoice\Http\Requests\UpdateInvoiceRequest;
use Modules\Invoice\Models\Invoice;

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
                'invoice_number' => Invoice::generateInvoiceNumber(),
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'from_name' => $validated['from_name'],
                'from_address' => $validated['from_address'] ?? null,
                'from_email' => $validated['from_email'] ?? null,
                'from_phone' => $validated['from_phone'] ?? null,
                'to_name' => $validated['to_name'],
                'to_address' => $validated['to_address'] ?? null,
                'to_email' => $validated['to_email'] ?? null,
                'tax_rate' => $validated['tax_rate'],
                'notes' => $validated['notes'] ?? null,
                'user_id' => auth()->id(),
                'status' => 'draft',
            ]);

            foreach ($validated['items'] as $index => $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'sort_order' => $index,
                ]);
            }

            $invoice->calculateTotals();
            $invoice->save();
        });

        return redirect()->route('dashboard.invoices.index')
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
            $invoice->update([
                'invoice_date' => $validated['invoice_date'],
                'due_date' => $validated['due_date'],
                'status' => $validated['status'],
                'from_name' => $validated['from_name'],
                'from_address' => $validated['from_address'] ?? null,
                'from_email' => $validated['from_email'] ?? null,
                'from_phone' => $validated['from_phone'] ?? null,
                'to_name' => $validated['to_name'],
                'to_address' => $validated['to_address'] ?? null,
                'to_email' => $validated['to_email'] ?? null,
                'tax_rate' => $validated['tax_rate'],
                'notes' => $validated['notes'] ?? null,
            ]);

            $invoice->items()->delete();

            foreach ($validated['items'] as $index => $item) {
                $invoice->items()->create([
                    'description' => $item['description'],
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'sort_order' => $index,
                ]);
            }

            $invoice->calculateTotals();
            $invoice->save();
        });

        return redirect()->route('dashboard.invoices.index')
            ->with('success', 'Invoice updated successfully.');
    }

    public function destroy(Invoice $invoice)
    {
        $this->authorize('delete', $invoice);

        $invoice->delete();

        return redirect()->route('dashboard.invoices.index')
            ->with('success', 'Invoice deleted successfully.');
    }

    public function pdf(Invoice $invoice)
    {
        $this->authorize('view', $invoice);

        $userId = auth()->id();
        $cacheKey = "pdf_downloads:{$userId}:".now()->format('Y-m-d');
        $downloads = Cache::get($cacheKey, 0);

        if ($downloads >= 10) {
            return back()->with('error', 'Daily PDF download limit reached (10 per day).');
        }

        Cache::put($cacheKey, $downloads + 1, now()->endOfDay());

        $pdf = Pdf::loadView('invoice::pdf', [
            'invoice' => $invoice->load('items'),
        ]);

        return $pdf->download("invoice-{$invoice->invoice_number}.pdf");
    }
}
