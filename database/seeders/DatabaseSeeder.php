<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // No RoleSeeder anymore — role is a plain string column on users
        // now, matching the Python reference. LabTestCatalogSeeder is also
        // gone — the test catalog is a PHP constant (LabOrder::CATALOG),
        // not a database table, matching the reference's LOINC_TEST_CATALOG.
        $this->call([
            TestUserSeeder::class,
            DrugStockSeeder::class,
            DemoClinicalSeeder::class,
        ]);
    }
}
