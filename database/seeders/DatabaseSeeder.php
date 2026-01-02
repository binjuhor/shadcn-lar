<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Modules\Blog\Database\Seeders\BlogDatabaseSeeder;
use Modules\Ecommerce\Database\Seeders\EcommerceDatabaseSeeder;
use Modules\Finance\Database\Seeders\FinanceDatabaseSeeder;
use Modules\Invoice\Database\Seeders\InvoiceSeeder;
use Modules\Notification\Database\Seeders\NotificationDatabaseSeeder;
use Modules\Permission\Database\Seeders\PermissionDatabaseSeeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Seed permissions and roles first (creates Super Admin user)
        $this->call(PermissionDatabaseSeeder::class);

        // Seed module data
        $this->call(BlogDatabaseSeeder::class);
        $this->call(EcommerceDatabaseSeeder::class);
        $this->call(FinanceDatabaseSeeder::class);
        $this->call(NotificationDatabaseSeeder::class);

        // Seed invoices
        $this->call(InvoiceSeeder::class);
    }
}
