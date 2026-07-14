<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Role;
use App\Models\Department;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    public function run(): void
    {
        // Create roles
        $adminRole = Role::firstOrCreate(['name' => 'admin']);
        $doctorRole = Role::firstOrCreate(['name' => 'doctor']);
        $nurseRole = Role::firstOrCreate(['name' => 'nurse']);
        $receptionistRole = Role::firstOrCreate(['name' => 'receptionist']);
        $labTechRole = Role::firstOrCreate(['name' => 'lab_tech']);
        $radiologistRole = Role::firstOrCreate(['name' => 'radiologist']);
        $pharmacistRole = Role::firstOrCreate(['name' => 'pharmacist']);
        $billingRole = Role::firstOrCreate(['name' => 'billing']);
        $dialysisTechRole = Role::firstOrCreate(['name' => 'dialysis_tech']);
        $recordsOfficerRole = Role::firstOrCreate(['name' => 'records_officer']);

        // Create departments
        $generalDept = Department::firstOrCreate(['name' => 'General']);
        $pediatricsDept = Department::firstOrCreate(['name' => 'Pediatrics']);
        $emergencyDept = Department::firstOrCreate(['name' => 'Emergency']);
        $labDept = Department::firstOrCreate(['name' => 'Laboratory']);
        $radiologyDept = Department::firstOrCreate(['name' => 'Radiology']);
        $pharmacyDept = Department::firstOrCreate(['name' => 'Pharmacy']);

        // Create test users
        User::firstOrCreate(
            ['email' => 'admin@nullcare.local'],
            [
                'full_name' => 'Admin User',
                'password' => Hash::make('password'),
                'role_id' => $adminRole->id,
                'department_id' => $generalDept->id,
                'phone' => '0123456789',
                'status' => 'active',
            ]
        );

        User::firstOrCreate(
            ['email' => 'doctor@nullcare.local'],
            [
                'full_name' => 'Dr. James Smith',
                'password' => Hash::make('password'),
                'role_id' => $doctorRole->id,
                'department_id' => $generalDept->id,
                'phone' => '0987654321',
                'status' => 'active',
            ]
        );

        User::firstOrCreate(
            ['email' => 'nurse@nullcare.local'],
            [
                'full_name' => 'Nurse Mary Johnson',
                'password' => Hash::make('password'),
                'role_id' => $nurseRole->id,
                'department_id' => $generalDept->id,
                'phone' => '0765432198',
                'status' => 'active',
            ]
        );

        User::firstOrCreate(
            ['email' => 'receptionist@nullcare.local'],
            [
                'full_name' => 'John Reception',
                'password' => Hash::make('password'),
                'role_id' => $receptionistRole->id,
                'department_id' => $generalDept->id,
                'phone' => '0654321987',
                'status' => 'active',
            ]
        );

        User::firstOrCreate(
            ['email' => 'labtech@nullcare.local'],
            [
                'full_name' => 'Lab Technician Peter',
                'password' => Hash::make('password'),
                'role_id' => $labTechRole->id,
                'department_id' => $labDept->id,
                'phone' => '0543219876',
                'status' => 'active',
            ]
        );

        User::firstOrCreate(
            ['email' => 'pharmacist@nullcare.local'],
            [
                'full_name' => 'Pharmacist Alice',
                'password' => Hash::make('password'),
                'role_id' => $pharmacistRole->id,
                'department_id' => $pharmacyDept->id,
                'phone' => '0432198765',
                'status' => 'active',
            ]
        );

        User::firstOrCreate(
            ['email' => 'billing@nullcare.local'],
            [
                'full_name' => 'Billing Officer Bob',
                'password' => Hash::make('password'),
                'role_id' => $billingRole->id,
                'department_id' => $generalDept->id,
                'phone' => '0321987654',
                'status' => 'active',
            ]
        );

        echo "\n✅ Test users created successfully!\n";
    }
}
