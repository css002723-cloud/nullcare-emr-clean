<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Order matters: roles before users; catalog is independent.
        $this->call([
            RoleSeeder::class,
            TestUserSeeder::class,
            LabTestCatalogSeeder::class,
        ]);
    }
}
