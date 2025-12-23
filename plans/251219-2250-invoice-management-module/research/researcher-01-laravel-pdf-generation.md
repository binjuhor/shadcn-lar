# Research Report: Laravel PDF Generation for Invoice Module

**Date:** 2025-12-19
**Research Scope:** Laravel 12.x PDF packages for invoice generation
**Sources Consulted:** 12+ authoritative sources

---

## Executive Summary

Three primary PDF solutions exist for Laravel invoice generation: **DomPDF** (pure PHP, simple, limited CSS), **Snappy** (wkhtmltopdf-based, better rendering, requires binary), and **Spatie Browsershot** (Chrome-based, modern CSS/JS support, best quality). For invoices specifically, **laraveldaily/laravel-invoices** provides a ready-made solution. All are Laravel 12 compatible. DomPDF best for shared hosting; Snappy for better CSS; Browsershot for production-grade invoices with complex layouts.

---

## Key Findings

### 1. PDF Generation Packages (Ranked by Fit for Invoices)

| Package | Engine | CSS Support | Performance | Laravel 12 | Hosting | Recommendation |
|---------|--------|-------------|-------------|-----------|---------|-----------------|
| **Spatie Browsershot** | Chromium | Excellent | Good | ✓ | VPS/Cloud | Best quality output |
| **Snappy (Barryvdh)** | wkhtmltopdf | Very Good | Better | ✓ | VPS/Cloud | CSS-heavy invoices |
| **DomPDF (Barryvdh)** | Pure PHP | Good | Fast | ✓ | Shared | Quick, simple invoices |
| **himelali/pdf-generator** | Multi-driver | Configurable | Varies | ✓ | Both | Unified API, May 2025 |

### 2. Invoice-Specific Packages

**laraveldaily/laravel-invoices** - Most popular ready-made solution
- Publishable Blade templates (customizable)
- Automatic calculations (subtotal, tax, discount, total)
- Multi-locale + multi-currency support
- Built-in DomPDF; can swap engines
- Installation: `composer require laraveldaily/laravel-invoices`

**akira/laravel-pdf-invoices** - New fluent builder API
- Laravel 12+ optimized
- 3 professional Tailwind templates (minimal, modern, branded)
- Fluent chainable API: `PdfInvoice::make($invoice)->generate()`
- Supports DomPDF or Spatie Browsershot engines
- Strict types, readonly DTOs, Carbon support
- Installation: `composer require akira/laravel-pdf-invoices`

**Other options:**
- `aroutinr/laravel-invoice` - Invoice traits for models, payment tracking
- `sandervanhooft/laravel-invoicable` - Payment gateway agnostic
- `ElegantEngineeringTech/laravel-invoices` - Eloquent model integration

### 3. Core Packages Installation

```bash
# DomPDF (most common, shared hosting compatible)
composer require barryvdh/laravel-dompdf

# Snappy (requires wkhtmltopdf binary)
composer require barryvdh/laravel-snappy

# Spatie Browsershot (requires Chromium, best for invoices)
composer require spatie/laravel-pdf
# Also install: composer require spatie/browsershot

# Ready-made invoice solution
composer require laraveldaily/laravel-invoices

# Unified multi-driver wrapper (2025 release)
composer require himelali/pdf-generator
```

### 4. Basic Usage Examples

**DomPDF Approach:**
```php
use Barryvdh\DomPDF\Facade\Pdf;

// Generate from view
$pdf = Pdf::loadView('invoices.template', ['invoice' => $invoice]);
return $pdf->download('invoice-' . $invoice->id . '.pdf');

// Or stream
return $pdf->stream();
```

**Snappy Approach:**
```php
use Barryvdh\Snappy\Facades\SnappyPdf;

$pdf = SnappyPdf::loadView('invoices.template', ['invoice' => $invoice])
    ->setOption('page-size', 'A4')
    ->setOption('margin-top', 0);

return $pdf->download('invoice.pdf');
```

**Spatie Browsershot Approach:**
```php
use Spatie\LaravelPdf\Facades\Pdf;

Pdf::view('invoices.template', ['invoice' => $invoice])
    ->format('a4')
    ->margins(10, 10, 10, 10)
    ->save('invoices/' . $invoice->id . '.pdf');

// Or return download
return Pdf::view('invoices.template', ['invoice' => $invoice])->download('invoice.pdf');
```

**Ready-Made Solution:**
```php
use LaravelDaily\Invoices\Invoice;
use LaravelDaily\Invoices\Classes\Buyer;
use LaravelDaily\Invoices\Classes\InvoiceItem;

$invoice = Invoice::make()
    ->buyer(new Buyer(['name' => 'John Doe']))
    ->addItem(new InvoiceItem('Item', 1, 100))
    ->status('paid')
    ->filename('invoice-' . time())
    ->render();

return response()->download($invoice);
```

### 5. Best Practices for Invoice Templates

1. **Use Blade views** - Keep templates as `.blade.php` files for flexibility
2. **Copy default templates** - Customize published templates, don't modify package originals
3. **Test layouts** - Preview HTML before PDF conversion to catch styling issues
4. **Use CSS wisely:**
   - DomPDF: inline CSS preferred, avoid complex selectors
   - Snappy: supports modern CSS, but avoid JavaScript animations
   - Browsershot: full CSS3/Flexbox/Grid support
5. **Performance:** Queue PDF generation for high volume (use Laravel jobs)
6. **Styling:** Tailor CSS to chosen engine - test on target engine

### 6. Performance Considerations

**Shared Hosting:** Use DomPDF (no binary dependencies)
**VPS/Cloud:** Use Snappy (wkhtmltopdf binary) or Browsershot (Chromium)
**High Volume:** Implement queue jobs + caching
**File Size:** Browsershot produces larger files (Chrome overhead); DomPDF lighter
**CPU:** Browsershot highest, DomPDF lowest

**Optimization Tips:**
```php
// Queue invoice generation
dispatch(new GenerateInvoicePdf($invoice));

// Cache if static content
cache()->remember('invoice-' . $invoice->id, 3600, fn() =>
    Pdf::view('invoices.template')->render()
);
```

### 7. Laravel 12 Compatibility Status

| Package | Status | Notes |
|---------|--------|-------|
| barryvdh/laravel-dompdf | ✓ Stable | Works out-of-box |
| barryvdh/laravel-snappy | ✓ Stable | Requires wkhtmltopdf binary |
| spatie/laravel-pdf | ✓ Stable | v1 release, full support |
| laraveldaily/laravel-invoices | ✓ Stable | Actively maintained |
| akira/laravel-pdf-invoices | ✓ New | Built for Laravel 12+ |
| himelali/pdf-generator | ✓ New | May 2025 release |

---

## Recommendation for Invoice Module

**Primary Choice:** `laraveldaily/laravel-invoices` + `spatie/laravel-pdf`
- Ready-made invoice logic (taxes, discounts, line items)
- Professional Browsershot output quality
- Customizable Blade templates
- Scales to production use

**Alternative (Simpler):** `laraveldaily/laravel-invoices` + `barryvdh/laravel-dompdf`
- Faster initial implementation
- Works on shared hosting
- Sufficient for basic invoicing

**Alternative (Advanced):** `akira/laravel-pdf-invoices`
- Modern fluent API
- Built-in templates
- Better DX with strict types
- Newer, smaller community

---

## Configuration Notes

**DomPDF Config:**
```php
// config/dompdf.php
'public_path' => public_path(),
'font_dir' => storage_path('fonts/'),
```

**Snappy Config:**
```php
// config/snappy.php (binary paths for production)
'pdf' => [
    'binary' => '/usr/bin/wkhtmltopdf',
],
```

**Spatie PDF Config:**
```php
// config/pdf.php
'format' => 'A4',
'chrome_path' => env('CHROME_PATH'),
'timeout' => 60,
```

---

## Unresolved Questions

1. Will invoice PDFs need to support barcodes/QR codes? (Impacts template choice)
2. Expected monthly invoice volume? (Determines queue/caching strategy)
3. Must invoice PDFs work on shared hosting or dedicated infrastructure?
4. Need digital signatures or encryption? (DomPDF supports this)
5. Multi-language invoice support required?

---

## Sources

- [Barryvdh Laravel DomPDF - GitHub](https://github.com/barryvdh/laravel-dompdf)
- [Barryvdh Laravel Snappy - GitHub](https://github.com/barryvdh/laravel-snappy)
- [Spatie Laravel PDF - Official Docs](https://spatie.be/docs/laravel-pdf/v1/introduction)
- [LaravelDaily Invoices - Packagist](https://packagist.org/packages/laraveldaily/laravel-invoices)
- [Akira Laravel PDF Invoices - Packagist](https://packagist.org/packages/akira/laravel-pdf-invoices)
- [himelali PDF Generator - Packagist](https://packagist.org/packages/himelali/pdf-generator)
- [Laravel 12 PDF Generation with DomPDF - Kite Metric](https://kitemetric.com/blogs/generate-pdfs-in-laravel-12-with-dompdf-a-comprehensive-guide)
- [Best PHP PDF Libraries - Codeboxr](https://codeboxr.com/best-php-pdf-libraries-comparison-pros-cons-and-installation-guide/)
- [Invoice PDF Generation with Browsershot - Fly.io](https://fly.io/laravel-bytes/invoice-pdf-generation-with-browsershot/)
- [Best PDF Generation Packages for Laravel - Medium](https://myelwaganam-menisha.medium.com/best-pdf-generation-packages-for-laravel-fe4c0f388702)
- [Best Laravel Packages for Invoice Management - Prateeksha](https://prateeksha.com/blog/top-laravel-packages-for-invoice-management-and-generation)
