<?php

namespace Modules\Invoice\Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Modules\Invoice\Models\Invoice;
use Modules\Invoice\Models\InvoiceItem;

class InvoiceSeeder extends Seeder
{
    public function run(): void
    {
        $users = User::all();

        if ($users->isEmpty()) {
            $users = User::factory()->count(3)->create();
        }

        foreach ($users->take(3) as $user) {
            $invoiceCount = rand(5, 15);

            for ($i = 0; $i < $invoiceCount; $i++) {
                $invoice = Invoice::factory()->create([
                    'user_id' => $user->id,
                    'invoice_number' => Invoice::generateInvoiceNumber(),
                ]);

                $itemCount = rand(1, 5);
                for ($j = 0; $j < $itemCount; $j++) {
                    InvoiceItem::factory()->create([
                        'invoice_id' => $invoice->id,
                        'sort_order' => $j,
                    ]);
                }

                $invoice->calculateTotals();
                $invoice->save();
            }
        }
    }
}
