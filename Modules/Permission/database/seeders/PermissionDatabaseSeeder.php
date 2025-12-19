<?php

namespace Modules\Permission\Database\Seeders;

use Illuminate\Database\Seeder;

class PermissionDatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            PermissionSeeder::class,
        ]);
    }
}
