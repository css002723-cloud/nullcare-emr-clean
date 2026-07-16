<?php

namespace Database\Seeders;

use App\Models\Department;
use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Creates one login per role, matching EXACTLY what Login.jsx's own
 * hardcoded hint text displays on screen: "Demo accounts (password
 * nullcare123): admin, reception1, nurse1, doctor1, labtech1,
 * radiologist1, pharmacist1, billing1, dialysis1, records1" — so anyone
 * on the team can just read the screen and type it correctly.
 */
class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $department = Department::firstOrCreate(
            ['name' => 'OPD'],
            ['type' => 'outpatient']
        );

        $accounts = [
            ['role' => 'admin', 'username' => 'admin', 'email' => 'admin@nullcare.test', 'name' => 'System Admin'],
            ['role' => 'reception', 'username' => 'reception1', 'email' => 'reception@nullcare.test', 'name' => 'Reception Desk'],
            ['role' => 'nurse', 'username' => 'nurse1', 'email' => 'nurse@nullcare.test', 'name' => 'Nurse Grace Mvula'],
            ['role' => 'doctor', 'username' => 'doctor1', 'email' => 'doctor@nullcare.test', 'name' => 'Dr. Jane Phiri'],
            ['role' => 'lab_tech', 'username' => 'labtech1', 'email' => 'lab@nullcare.test', 'name' => 'Lab Tech Chisomo'],
            ['role' => 'radiologist', 'username' => 'radiologist1', 'email' => 'radiologist@nullcare.test', 'name' => 'Dr. Radiologist'],
            ['role' => 'pharmacist', 'username' => 'pharmacist1', 'email' => 'pharmacist@nullcare.test', 'name' => 'Pharmacist Blessings'],
            ['role' => 'billing', 'username' => 'billing1', 'email' => 'billing@nullcare.test', 'name' => 'Billing Officer'],
            ['role' => 'dialysis_tech', 'username' => 'dialysis1', 'email' => 'dialysis@nullcare.test', 'name' => 'Dialysis Tech'],
            ['role' => 'records_officer', 'username' => 'records1', 'email' => 'records@nullcare.test', 'name' => 'Records Officer'],
        ];

        foreach ($accounts as $account) {
            $role = Role::where('name', $account['role'])->firstOrFail();

            User::updateOrCreate(
                ['email' => $account['email']],
                [
                    'full_name' => $account['name'],
                    'username' => $account['username'],
                    'password' => Hash::make('nullcare123'),
                    'role_id' => $role->id,
                    'department_id' => $department->id,
                    'status' => 'active',
                    'is_active' => true,
                ]
            );
        }
    }
}