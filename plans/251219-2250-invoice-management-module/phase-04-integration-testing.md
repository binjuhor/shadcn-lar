# Phase 04: Integration & Testing

**Status:** Pending
**Estimated Effort:** 2-3 hours
**Depends On:** Phase 01, Phase 02, Phase 03

---

## Context Links

- [Main Plan](./plan.md)
- [Phase 01: Database](./phase-01-database-models.md)
- [Phase 02: Backend API](./phase-02-backend-api.md)
- [Phase 03: Frontend](./phase-03-frontend-components.md)
- Sidebar: `/Users/binjuhor/Development/shadcn-admin/resources/js/components/layout/data/sidebar-data.ts`

---

## Overview

Final integration: connect frontend to backend, add sidebar navigation, verify all flows work end-to-end. Write feature tests for critical paths. Add permission for sidebar item.

---

## Key Insights

- Sidebar follows existing pattern with icon and permission
- Feature tests use Laravel's HTTP testing
- Test PDF generation separately (memory intensive)
- Use RefreshDatabase trait for clean state

---

## Requirements

1. Add Invoices to sidebar navigation
2. Verify all CRUD operations work
3. Write feature tests for key flows
4. Test PDF generation
5. Verify authorization works

---

## Architecture

### Sidebar Addition

```typescript
// Add to navGroups in sidebar-data.ts under 'Ecommerce' or new 'Finance' group
{
  title: 'Invoices',
  url: '/dashboard/invoices',
  icon: IconFileInvoice,
  permission: 'invoices.view',
}
```

### Test Structure

```
tests/Feature/
└── Invoice/
    ├── InvoiceListTest.php
    ├── InvoiceCreateTest.php
    ├── InvoiceUpdateTest.php
    ├── InvoiceDeleteTest.php
    └── InvoicePdfTest.php
```

---

## Related Code Files

**Modify:**
- `/Users/binjuhor/Development/shadcn-admin/resources/js/components/layout/data/sidebar-data.ts`

**Create:**
- `/Users/binjuhor/Development/shadcn-admin/tests/Feature/Invoice/InvoiceListTest.php`
- `/Users/binjuhor/Development/shadcn-admin/tests/Feature/Invoice/InvoiceCreateTest.php`
- `/Users/binjuhor/Development/shadcn-admin/tests/Feature/Invoice/InvoiceUpdateTest.php`
- `/Users/binjuhor/Development/shadcn-admin/tests/Feature/Invoice/InvoiceDeleteTest.php`
- `/Users/binjuhor/Development/shadcn-admin/tests/Feature/Invoice/InvoicePdfTest.php`

---

## Implementation Steps

### 1. Add Sidebar Navigation

```typescript
// In sidebar-data.ts, add import
import { IconFileInvoice } from '@tabler/icons-react'

// Add to navGroups array, in 'Ecommerce' section or create 'Finance' section
{
  title: 'Finance',
  items: [
    {
      title: 'Invoices',
      url: '/dashboard/invoices',
      icon: IconFileInvoice,
      permission: 'invoices.view',
    },
  ],
},
```

### 2. Create Permission (if using Spatie)

```bash
php artisan tinker
```
```php
// Create permissions
Spatie\Permission\Models\Permission::create(['name' => 'invoices.view']);
Spatie\Permission\Models\Permission::create(['name' => 'invoices.create']);
Spatie\Permission\Models\Permission::create(['name' => 'invoices.update']);
Spatie\Permission\Models\Permission::create(['name' => 'invoices.delete']);

// Assign to admin role (or as needed)
$role = Spatie\Permission\Models\Role::findByName('admin');
$role->givePermissionTo(['invoices.view', 'invoices.create', 'invoices.update', 'invoices.delete']);
```

### 3. Create Feature Tests

```php
// tests/Feature/Invoice/InvoiceListTest.php
namespace Tests\Feature\Invoice;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceListTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_view_own_invoices(): void
    {
        $user = User::factory()->create();
        Invoice::factory()->count(3)->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get(route('invoices.index'));

        $response->assertOk();
        $response->assertInertia(fn ($page) =>
            $page->component('invoices/index')
                ->has('invoices.data', 3)
        );
    }

    public function test_user_cannot_see_others_invoices(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        Invoice::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)
            ->get(route('invoices.index'));

        $response->assertInertia(fn ($page) =>
            $page->has('invoices.data', 0)
        );
    }
}
```

```php
// tests/Feature/Invoice/InvoiceCreateTest.php
namespace Tests\Feature\Invoice;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceCreateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_invoice(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('invoices.store'), [
                'invoice_date' => '2025-12-19',
                'due_date' => '2025-12-26',
                'from_name' => 'My Business',
                'to_name' => 'Client Inc',
                'tax_rate' => 0.1,
                'items' => [
                    ['description' => 'Service', 'quantity' => 2, 'unit_price' => 100],
                ],
            ]);

        $response->assertRedirect(route('invoices.index'));
        $this->assertDatabaseHas('invoices', [
            'from_name' => 'My Business',
            'user_id' => $user->id,
        ]);
    }

    public function test_invoice_number_is_auto_generated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('invoices.store'), [
                'invoice_date' => '2025-12-19',
                'due_date' => '2025-12-26',
                'from_name' => 'My Business',
                'to_name' => 'Client Inc',
                'tax_rate' => 0.1,
                'items' => [
                    ['description' => 'Service', 'quantity' => 1, 'unit_price' => 100],
                ],
            ]);

        $this->assertDatabaseHas('invoices', [
            'invoice_number' => 'INV-20251219-0001',
        ]);
    }

    public function test_totals_are_calculated_correctly(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('invoices.store'), [
                'invoice_date' => '2025-12-19',
                'due_date' => '2025-12-26',
                'from_name' => 'My Business',
                'to_name' => 'Client Inc',
                'tax_rate' => 0.1,
                'items' => [
                    ['description' => 'Item 1', 'quantity' => 2, 'unit_price' => 50],  // 100
                    ['description' => 'Item 2', 'quantity' => 1, 'unit_price' => 100], // 100
                ],
            ]);

        $this->assertDatabaseHas('invoices', [
            'subtotal' => 200,
            'tax_amount' => 20,
            'total' => 220,
        ]);
    }

    public function test_validation_requires_at_least_one_item(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)
            ->post(route('invoices.store'), [
                'invoice_date' => '2025-12-19',
                'due_date' => '2025-12-26',
                'from_name' => 'My Business',
                'to_name' => 'Client Inc',
                'tax_rate' => 0.1,
                'items' => [],
            ]);

        $response->assertSessionHasErrors('items');
    }
}
```

```php
// tests/Feature/Invoice/InvoicePdfTest.php
namespace Tests\Feature\Invoice;

use App\Models\Invoice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoicePdfTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_download_own_invoice_pdf(): void
    {
        $user = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $user->id]);

        $response = $this->actingAs($user)
            ->get(route('invoices.pdf', $invoice));

        $response->assertOk();
        $response->assertHeader('content-type', 'application/pdf');
    }

    public function test_user_cannot_download_others_invoice_pdf(): void
    {
        $user = User::factory()->create();
        $otherUser = User::factory()->create();
        $invoice = Invoice::factory()->create(['user_id' => $otherUser->id]);

        $response = $this->actingAs($user)
            ->get(route('invoices.pdf', $invoice));

        $response->assertForbidden();
    }
}
```

### 4. Manual Testing Checklist

```markdown
## Manual Test Checklist

### List Page
- [ ] Page loads without errors
- [ ] Table shows invoices
- [ ] Sorting works (click column headers)
- [ ] Filtering by status works
- [ ] Pagination works
- [ ] "New Invoice" button navigates to create page

### Create Page
- [ ] Form displays correctly
- [ ] All required fields show validation errors
- [ ] Can add line items
- [ ] Can remove line items (except last one)
- [ ] Totals update in real-time
- [ ] Submit creates invoice
- [ ] Redirects to list with success message

### Edit Page
- [ ] Loads existing invoice data
- [ ] Can modify all fields
- [ ] Can add/remove line items
- [ ] Totals recalculate
- [ ] Submit updates invoice
- [ ] Redirects to list

### Show Page
- [ ] Displays invoice details correctly
- [ ] PDF download button works
- [ ] Edit button navigates to edit page
- [ ] Back button works

### Delete
- [ ] Confirmation dialog appears
- [ ] Cancel closes dialog
- [ ] Confirm deletes invoice
- [ ] Success message shown

### Authorization
- [ ] Cannot access other users' invoices
- [ ] Cannot edit other users' invoices
- [ ] Cannot delete other users' invoices
```

---

## Todo List

- [ ] Add IconFileInvoice import to sidebar-data.ts
- [ ] Add Invoices item to sidebar navigation
- [ ] Create invoice permissions in database
- [ ] Assign permissions to appropriate roles
- [ ] Create tests/Feature/Invoice directory
- [ ] Create InvoiceListTest.php
- [ ] Create InvoiceCreateTest.php
- [ ] Create InvoiceUpdateTest.php
- [ ] Create InvoiceDeleteTest.php
- [ ] Create InvoicePdfTest.php
- [ ] Run all tests
- [ ] Fix any failing tests
- [ ] Complete manual testing checklist
- [ ] Fix any bugs found

---

## Success Criteria

1. Invoices appears in sidebar
2. All feature tests pass
3. Manual testing checklist complete
4. No console errors in browser
5. No PHP errors in logs
6. PDF generation works

---

## Risk Assessment

| Risk | Likelihood | Impact | Mitigation |
|------|------------|--------|------------|
| Test flakiness | Low | Low | Use RefreshDatabase |
| PDF test memory | Medium | Low | Run PDF tests separately |
| Permission issues | Low | Medium | Test authorization explicitly |

---

## Security Considerations

- Test authorization for all operations
- Ensure user can only access own invoices
- Verify PDF endpoint is protected

---

## Next Steps

After completing Phase 04:
1. Module is complete and ready for deployment
2. Consider future enhancements:
   - Email invoice to client
   - Recurring invoices
   - Multi-currency support
   - Payment tracking

---

## Resolved Questions

1. ✅ Invoice permissions added to seeder for fresh installs
2. ✅ Rate limiting: 10 PDF downloads per day per user
3. ✅ Soft delete enabled (deleted_at column)
