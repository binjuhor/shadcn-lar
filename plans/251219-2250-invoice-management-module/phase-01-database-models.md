# Phase 01: Database & Models

**Status:** Pending
**Estimated Effort:** 2-3 hours

---

## Context Links

- [Main Plan](./plan.md)
- [Laravel PDF Research](./research/researcher-01-laravel-pdf-generation.md)
- Existing pattern: `/Users/binjuhor/Development/shadcn-admin/app/Models/User.php`

---

## Overview

Create database migrations and Eloquent models for invoices and invoice line items. Two tables: `invoices` (header data) and `invoice_items` (line items). One-to-many relationship.

---

## Key Insights

- Invoice + InvoiceItem = two tables with FK relationship
- Auto-generate invoice number on create
- Store calculated totals for reporting (denormalized)
- Use decimal(12,2) for currency fields
- Status as enum string for flexibility

---

## Requirements

1. Invoices table with header fields
2. Invoice items table with line item fields
3. Eloquent models with relationships
4. Factory and seeder for testing

---

## Architecture

### Database Schema

**invoices table:**
```
id                  - bigint, PK
invoice_number      - string(50), unique, indexed
invoice_date        - date
due_date            - date
status              - enum: draft|sent|paid|overdue|cancelled
from_name           - string(255)
from_address        - text, nullable
from_email          - string(255), nullable
from_phone          - string(50), nullable
to_name             - string(255)
to_address          - text, nullable
to_email            - string(255), nullable
subtotal            - decimal(12,2), default 0
tax_rate            - decimal(5,4), default 0 (e.g., 0.10 = 10%)
tax_amount          - decimal(12,2), default 0
total               - decimal(12,2), default 0
notes               - text, nullable
user_id             - bigint, FK to users, indexed
created_at          - timestamp
updated_at          - timestamp
deleted_at          - timestamp, nullable (soft delete)
```

**invoice_items table:**
```
id                  - bigint, PK
invoice_id          - bigint, FK to invoices, cascades, indexed
description         - string(500)
quantity            - decimal(10,2), default 1
unit_price          - decimal(12,2), default 0
amount              - decimal(12,2), default 0 (quantity * unit_price)
sort_order          - int, default 0
created_at          - timestamp
updated_at          - timestamp
```

---

## Related Code Files

**Create:**
- `/Users/binjuhor/Development/shadcn-admin/database/migrations/YYYY_MM_DD_HHMMSS_create_invoices_table.php`
- `/Users/binjuhor/Development/shadcn-admin/database/migrations/YYYY_MM_DD_HHMMSS_create_invoice_items_table.php`
- `/Users/binjuhor/Development/shadcn-admin/app/Models/Invoice.php`
- `/Users/binjuhor/Development/shadcn-admin/app/Models/InvoiceItem.php`
- `/Users/binjuhor/Development/shadcn-admin/database/factories/InvoiceFactory.php`
- `/Users/binjuhor/Development/shadcn-admin/database/factories/InvoiceItemFactory.php`
- `/Users/binjuhor/Development/shadcn-admin/database/seeders/InvoiceSeeder.php`

**Reference:**
- `/Users/binjuhor/Development/shadcn-admin/app/Models/User.php`
- `/Users/binjuhor/Development/shadcn-admin/database/migrations/0001_01_01_000000_create_users_table.php`

---

## Implementation Steps

### 1. Create migrations

```php
// create_invoices_table.php
Schema::create('invoices', function (Blueprint $table) {
    $table->id();
    $table->string('invoice_number', 50)->unique();
    $table->date('invoice_date');
    $table->date('due_date');
    $table->string('status', 20)->default('draft');
    $table->string('from_name');
    $table->text('from_address')->nullable();
    $table->string('from_email')->nullable();
    $table->string('from_phone', 50)->nullable();
    $table->string('to_name');
    $table->text('to_address')->nullable();
    $table->string('to_email')->nullable();
    $table->decimal('subtotal', 12, 2)->default(0);
    $table->decimal('tax_rate', 5, 4)->default(0);
    $table->decimal('tax_amount', 12, 2)->default(0);
    $table->decimal('total', 12, 2)->default(0);
    $table->text('notes')->nullable();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->timestamps();

    $table->index('invoice_date');
    $table->index('status');
});

// create_invoice_items_table.php
Schema::create('invoice_items', function (Blueprint $table) {
    $table->id();
    $table->foreignId('invoice_id')->constrained()->cascadeOnDelete();
    $table->string('description', 500);
    $table->decimal('quantity', 10, 2)->default(1);
    $table->decimal('unit_price', 12, 2)->default(0);
    $table->decimal('amount', 12, 2)->default(0);
    $table->integer('sort_order')->default(0);
    $table->timestamps();
});
```

### 2. Create Invoice model

```php
// app/Models/Invoice.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_number',
        'invoice_date',
        'due_date',
        'status',
        'from_name',
        'from_address',
        'from_email',
        'from_phone',
        'to_name',
        'to_address',
        'to_email',
        'subtotal',
        'tax_rate',
        'tax_amount',
        'total',
        'notes',
        'user_id',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date',
            'due_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_rate' => 'decimal:4',
            'tax_amount' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class)->orderBy('sort_order');
    }

    public function calculateTotals(): void
    {
        $this->subtotal = $this->items->sum('amount');
        $this->tax_amount = $this->subtotal * $this->tax_rate;
        $this->total = $this->subtotal + $this->tax_amount;
    }

    public static function generateInvoiceNumber(): string
    {
        $date = now()->format('Ymd');
        $lastInvoice = static::where('invoice_number', 'like', "INV-{$date}-%")
            ->orderByDesc('invoice_number')
            ->first();

        if ($lastInvoice) {
            $lastNumber = (int) substr($lastInvoice->invoice_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "INV-{$date}-{$newNumber}";
    }
}
```

### 3. Create InvoiceItem model

```php
// app/Models/InvoiceItem.php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    use HasFactory;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price',
        'amount',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:2',
            'unit_price' => 'decimal:2',
            'amount' => 'decimal:2',
        ];
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    protected static function booted(): void
    {
        static::saving(function (InvoiceItem $item) {
            $item->amount = $item->quantity * $item->unit_price;
        });
    }
}
```

### 4. Create factories and seeder

Factory: generate realistic test data
Seeder: create 20-50 sample invoices with 1-5 items each

---

## Todo List

- [ ] Create invoices migration
- [ ] Create invoice_items migration
- [ ] Create Invoice model with relationships
- [ ] Create InvoiceItem model with auto-calculate
- [ ] Create InvoiceFactory
- [ ] Create InvoiceItemFactory
- [ ] Create InvoiceSeeder
- [ ] Run migrations
- [ ] Test with tinker

---

## Success Criteria

1. Migrations run without errors
2. Models have correct relationships
3. `Invoice::with('items')->first()` returns invoice with items
4. `InvoiceItem` auto-calculates amount on save
5. `Invoice::generateInvoiceNumber()` returns unique number
6. Seeder creates test data successfully

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Decimal precision issues | Low | Medium | Use decimal(12,2), test edge cases |
| FK constraint errors | Low | High | Use cascadeOnDelete |
| Invoice number collision | Low | Medium | Unique constraint, transaction |

---

## Security Considerations

- `user_id` FK ensures ownership
- No sensitive data stored (no payment info in MVP)
- Input validation in Phase 02
- Soft deletes enabled for audit trail

---

## Next Steps

After completing Phase 01:
1. Proceed to Phase 02: Backend API
2. Test models in tinker before building controller
