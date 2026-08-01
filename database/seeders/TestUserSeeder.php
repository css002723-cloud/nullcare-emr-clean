<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Ported from backend/app/seed.py's DEMO_USERS, updated for the
 * first_name/last_name/email split. Same usernames, same password
 * ("nullcare123") as before — nothing to relearn.
 */
class TestUserSeeder extends Seeder
{
    public function run(): void
    {
        $demoUsers = [
            ['first_name' => 'System', 'last_name' => 'Administrator', 'username' => 'admin', 'email' => 'admin@nullcare.mw', 'role' => 'admin', 'department' => 'IT'],
            ['first_name' => 'Grace', 'last_name' => 'Banda', 'username' => 'reception1', 'email' => 'grace.banda@nullcare.mw', 'role' => 'reception', 'department' => 'Reception'],
            ['first_name' => 'Chikondi', 'last_name' => 'Phiri', 'username' => 'nurse1', 'email' => 'chikondi.phiri@nullcare.mw', 'role' => 'nurse', 'department' => 'Triage & Nursing'],
            ['first_name' => 'Thandiwe', 'last_name' => 'Mvula', 'username' => 'doctor1', 'email' => 'thandiwe.mvula@nullcare.mw', 'role' => 'doctor', 'department' => 'Outpatient'],
            ['first_name' => 'Blessings', 'last_name' => 'Kamanga', 'username' => 'labtech1', 'email' => 'blessings.kamanga@nullcare.mw', 'role' => 'lab_tech', 'department' => 'Laboratory'],
            ['first_name' => 'Yamikani', 'last_name' => 'Nyirenda', 'username' => 'radiologist1', 'email' => 'yamikani.nyirenda@nullcare.mw', 'role' => 'radiologist', 'department' => 'Imaging'],
            ['first_name' => 'Esther', 'last_name' => 'Chirwa', 'username' => 'pharmacist1', 'email' => 'esther.chirwa@nullcare.mw', 'role' => 'pharmacist', 'department' => 'Pharmacy'],
            ['first_name' => 'Frank', 'last_name' => 'Mbewe', 'username' => 'billing1', 'email' => 'frank.mbewe@nullcare.mw', 'role' => 'billing', 'department' => 'Billing'],
            ['first_name' => 'Patuma', 'last_name' => 'Gondwe', 'username' => 'dialysis1', 'email' => 'patuma.gondwe@nullcare.mw', 'role' => 'dialysis_tech', 'department' => 'Dialysis Unit'],
            ['first_name' => 'Ruth', 'last_name' => 'Mkandawire', 'username' => 'records1', 'email' => 'ruth.mkandawire@nullcare.mw', 'role' => 'records_officer', 'department' => 'Health Records'],
        ];

        foreach ($demoUsers as $u) {
            User::updateOrCreate(
                ['username' => $u['username']],
                [
                    'first_name' => $u['first_name'],
                    'last_name' => $u['last_name'],
                    'email' => $u['email'],
                    'role' => $u['role'],
                    'department' => $u['department'],
                    'password' => Hash::make('nullcare123'),
                    'password_changed_at' => now(),
                    'must_reset_password' => false,
                    'is_active' => true,
                ]
            );
        }
    }
}
