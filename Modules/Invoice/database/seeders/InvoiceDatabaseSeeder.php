<?php

namespace Modules\Invoice\Database\Seeders;

use Illuminate\Database\Seeder;

class InvoiceDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            InvoiceSeeder::class,
        ]);
    }
}
