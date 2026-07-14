<?php

namespace Database\Seeders;

use App\Models\Role;
use Illuminate\Database\Seeder;

class RoleSeeder extends Seeder
{
    /**
     * Matches the exact ROLES list in frontend/src/pages/AdminUsers.jsx —
     * three roles here (reception, radiologist, dialysis_tech,
     * records_officer) didn't exist in the original brief-derived seeder
     * and are added now to match what the frontend actually offers.
     */
    public function run(): void
    {
        $roles = [
            ['name' => 'admin', 'description' => 'System administrator - full access'],
            ['name' => 'reception', 'description' => 'Patient registration, appointment booking'],
            ['name' => 'nurse', 'description' => 'Nursing notes, medication administration, care plans'],
            ['name' => 'doctor', 'description' => 'Clinician - diagnosis, orders, prescriptions'],
            ['name' => 'lab_tech', 'description' => 'Lab test receipt and result entry'],
            ['name' => 'radiologist', 'description' => 'Imaging order review and reporting'],
            ['name' => 'pharmacist', 'description' => 'Prescription review, dispensing, stock management'],
            ['name' => 'billing', 'description' => 'Invoice generation, payment recording'],
            ['name' => 'dialysis_tech', 'description' => 'Dialysis session management'],
            ['name' => 'records_officer', 'description' => 'Records/research data access'],
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role['name']], $role);
        }
    }
}