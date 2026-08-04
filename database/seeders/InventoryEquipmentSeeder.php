<?php

namespace Database\Seeders;

use App\Models\Encounter;
use App\Models\Equipment;
use App\Models\EquipmentDowntimeReport;
use App\Models\EquipmentMaintenanceRecord;
use App\Models\InventoryBatch;
use App\Models\InventoryConsumption;
use App\Models\InventoryItem;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seeds inventory items (pharmacy/laboratory/imaging/theatre/ward) with
 * batches deliberately spread across "healthy", "low stock", "expiring
 * soon", and "already expired" so /inventory/alerts has real data to show
 * immediately — plus equipment across all three statuses with maintenance
 * history and both an open and a resolved downtime report, so those
 * views don't need to be built up manually before you can check them.
 */
class InventoryEquipmentSeeder extends Seeder
{
    public function run(): void
    {
        $pharmacist = User::where('username', 'pharmacist1')->first() ?? User::first();
        $labTech = User::where('username', 'labtech1')->first() ?? User::first();
        $radiologist = User::where('username', 'radiologist1')->first() ?? User::first();
        $nurse = User::where('username', 'nurse1')->first() ?? User::first();
        $dialysisTech = User::where('username', 'dialysis1')->first() ?? User::first();

        $this->pharmacyItems($pharmacist);
        $this->laboratoryItems($labTech);
        $this->imagingItems($radiologist);
        $this->theatreItems($nurse);
        $this->wardItems($nurse);
        $this->equipmentFleet($labTech, $radiologist, $dialysisTech);

        echo "\n✅ Inventory & equipment demo data seeded — low-stock, expiring, ".
            "expired, and equipment-down scenarios are all populated.\n";
    }

    // ---------------------------------------------------------------
    // Pharmacy
    // ---------------------------------------------------------------
    private function pharmacyItems(User $receivedBy): void
    {
        // Healthy stock, no alerts expected.
        $this->itemWithBatch('Amoxicillin 500mg capsules', 'pharmacy', 'capsules', 200, [
            ['quantity_received' => 1000, 'quantity_on_hand' => 640, 'expiry_date' => now()->addYear(), 'supplier' => 'Pharmanova Malawi'],
        ], $receivedBy);

        // Low stock: on-hand total sits below reorder level.
        $this->itemWithBatch('Ceftriaxone 1g injection', 'pharmacy', 'vials', 50, [
            ['quantity_received' => 100, 'quantity_on_hand' => 12, 'expiry_date' => now()->addMonths(8), 'supplier' => 'SFH Malawi'],
        ], $receivedBy);

        // Expiring within the default 90-day alert horizon.
        $this->itemWithBatch('Artemether-Lumefantrine (Coartem)', 'pharmacy', 'packs', 100, [
            ['quantity_received' => 300, 'quantity_on_hand' => 180, 'expiry_date' => now()->addDays(45), 'supplier' => 'Ministry of Health Central Store'],
        ], $receivedBy);

        // Already expired, still showing on-hand — the sharpest possible alert.
        $this->itemWithBatch('Oxytocin 10IU injection', 'pharmacy', 'ampoules', 30, [
            ['quantity_received' => 60, 'quantity_on_hand' => 18, 'expiry_date' => now()->subDays(10), 'supplier' => 'UNICEF Supply Division'],
        ], $receivedBy);

        // Controlled medicine, healthy stock — exercises is_controlled tracking.
        $item = $this->itemWithBatch('Morphine 10mg/ml injection', 'pharmacy', 'ampoules', 20, [
            ['quantity_received' => 50, 'quantity_on_hand' => 34, 'expiry_date' => now()->addMonths(14), 'supplier' => 'Ministry of Health Central Store'],
        ], $receivedBy);
        $item->update(['is_controlled' => true]);
    }

    // ---------------------------------------------------------------
    // Laboratory
    // ---------------------------------------------------------------
    private function laboratoryItems(User $receivedBy): void
    {
        $this->itemWithBatch('Malaria RDT cassettes', 'laboratory', 'kits', 50, [
            ['quantity_received' => 200, 'quantity_on_hand' => 96, 'expiry_date' => now()->addMonths(10), 'supplier' => 'Abbott Diagnostics'],
        ], $receivedBy);

        // Low stock + expiring soon at the same time — worst-case double alert.
        $this->itemWithBatch('HIV rapid test kits (Determine)', 'laboratory', 'kits', 40, [
            ['quantity_received' => 100, 'quantity_on_hand' => 15, 'expiry_date' => now()->addDays(21), 'supplier' => 'Alere/Abbott'],
        ], $receivedBy);

        $this->itemWithBatch('EDTA blood collection tubes', 'laboratory', 'tubes', 100, [
            ['quantity_received' => 500, 'quantity_on_hand' => 310, 'expiry_date' => now()->addYears(2), 'supplier' => 'BD Diagnostics'],
        ], $receivedBy);
    }

    // ---------------------------------------------------------------
    // Imaging
    // ---------------------------------------------------------------
    private function imagingItems(User $receivedBy): void
    {
        $this->itemWithBatch('X-ray film (chest-size)', 'imaging', 'sheets', 50, [
            ['quantity_received' => 200, 'quantity_on_hand' => 138, 'expiry_date' => null, 'supplier' => 'Fuji Medical'],
        ], $receivedBy);

        $this->itemWithBatch('Ultrasound gel', 'imaging', 'bottles', 10, [
            ['quantity_received' => 24, 'quantity_on_hand' => 4, 'expiry_date' => now()->addMonths(20), 'supplier' => 'Parker Laboratories'],
        ], $receivedBy);
    }

    // ---------------------------------------------------------------
    // Theatre
    // ---------------------------------------------------------------
    private function theatreItems(User $receivedBy): void
    {
        $this->itemWithBatch('Surgical gloves (sterile, size 7.5)', 'theatre', 'pairs', 100, [
            ['quantity_received' => 500, 'quantity_on_hand' => 320, 'expiry_date' => now()->addYears(3), 'supplier' => 'Ansell Medical'],
        ], $receivedBy);

        $this->itemWithBatch('Suture 3-0 Vicryl', 'theatre', 'units', 40, [
            ['quantity_received' => 100, 'quantity_on_hand' => 22, 'expiry_date' => now()->addDays(60), 'supplier' => 'Ethicon'],
        ], $receivedBy);
    }

    // ---------------------------------------------------------------
    // Ward
    // ---------------------------------------------------------------
    private function wardItems(User $receivedBy): void
    {
        $this->itemWithBatch('IV cannula 18G', 'ward', 'units', 100, [
            ['quantity_received' => 300, 'quantity_on_hand' => 205, 'expiry_date' => now()->addYears(2), 'supplier' => 'BD Diagnostics'],
        ], $receivedBy);

        // Consumption logged against a real encounter/patient, for the
        // "linkage between clinical use and inventory consumption" requirement.
        $normalSaline = $this->itemWithBatch('Normal saline 1L IV fluid', 'ward', 'bags', 60, [
            ['quantity_received' => 200, 'quantity_on_hand' => 74, 'expiry_date' => now()->addYear(), 'supplier' => 'Kyungdong Pharm'],
        ], $receivedBy);

        $patient = Patient::inRandomOrder()->first();
        $encounter = $patient ? Encounter::where('patient_id', $patient->id)->first() : null;

        InventoryConsumption::create([
            'item_id' => $normalSaline->id,
            'batch_id' => InventoryBatch::where('item_id', $normalSaline->id)->first()?->id,
            'quantity' => 2,
            'department' => 'ICU',
            'encounter_id' => $encounter?->id,
            'patient_id' => $encounter?->patient_id,
            'reason' => 'IV fluid resuscitation',
            'consumed_by' => $receivedBy->id,
        ]);
    }

    private function itemWithBatch(string $name, string $category, string $unit, int $reorderLevel, array $batches, User $receivedBy): InventoryItem
    {
        $item = InventoryItem::create([
            'name' => $name,
            'category' => $category,
            'unit' => $unit,
            'reorder_level' => $reorderLevel,
            'department' => ucfirst($category),
        ]);

        foreach ($batches as $b) {
            InventoryBatch::create(array_merge([
                'item_id' => $item->id,
                'batch_number' => 'B'.strtoupper(substr(md5(uniqid()), 0, 8)),
                'received_date' => now()->subMonths(rand(1, 6)),
                'unit_cost' => round(mt_rand(50, 5000) / 100, 2),
                'received_by' => $receivedBy->id,
            ], $b));
        }

        return $item;
    }

    // ---------------------------------------------------------------
    // Equipment
    // ---------------------------------------------------------------
    private function equipmentFleet(User $labTech, User $radiologist, User $dialysisTech): void
    {
        // Operational, with a routine maintenance history — the "everything's fine" baseline.
        $autoclave = Equipment::create([
            'name' => 'Autoclave Sterilizer #1', 'equipment_type' => 'Sterilization', 'department' => 'Laboratory',
            'serial_number' => 'AUTO-2024-014', 'status' => 'operational',
            'install_date' => now()->subYears(2), 'last_maintenance_at' => now()->subMonths(2),
            'next_maintenance_due' => now()->addMonths(4),
        ]);
        EquipmentMaintenanceRecord::create([
            'equipment_id' => $autoclave->id, 'maintenance_type' => 'routine_service',
            'performed_at' => now()->subMonths(2), 'performed_by_name' => 'BioMed Services Malawi',
            'notes' => 'Routine annual service, pressure calibration checked and passed.',
            'cost' => 45000, 'logged_by' => $labTech->id,
        ]);

        // Currently under maintenance — visible on the equipment dashboard as non-operational.
        $xray = Equipment::create([
            'name' => 'Digital X-ray Unit', 'equipment_type' => 'Imaging', 'department' => 'Imaging',
            'serial_number' => 'DXR-2022-007', 'status' => 'under_maintenance',
            'install_date' => now()->subYears(3), 'last_maintenance_at' => now()->subDays(2),
            'next_maintenance_due' => now()->addMonths(6),
        ]);
        EquipmentMaintenanceRecord::create([
            'equipment_id' => $xray->id, 'maintenance_type' => 'corrective_repair',
            'performed_at' => now()->subDays(2), 'performed_by_name' => 'Radiology Engineering Ltd',
            'notes' => 'Detector panel showing intermittent artifact — replacement part ordered, unit offline pending arrival.',
            'cost' => 0, 'logged_by' => $radiologist->id,
        ]);

        // Down, with an OPEN downtime report — the sharpest equipment alert.
        $dialysisMachine = Equipment::create([
            'name' => 'Haemodialysis Machine #2', 'equipment_type' => 'Dialysis', 'department' => 'Dialysis Unit',
            'serial_number' => 'HDM-2021-003', 'status' => 'down',
            'install_date' => now()->subYears(4), 'last_maintenance_at' => now()->subMonths(5),
            'next_maintenance_due' => now()->subDays(10), // already overdue, on top of being down
        ]);
        EquipmentDowntimeReport::create([
            'equipment_id' => $dialysisMachine->id, 'reported_by' => $dialysisTech->id,
            'started_at' => now()->subHours(6),
            'reason' => 'Water treatment alarm triggered mid-session, machine automatically halted',
            'impact_notes' => 'One scheduled session postponed to the remaining functional machine; two more sessions today at risk if not resolved by afternoon.',
            'status' => 'open',
        ]);

        // A second, already-resolved downtime report — shows the reporting workflow closing out cleanly too.
        EquipmentDowntimeReport::create([
            'equipment_id' => $xray->id, 'reported_by' => $radiologist->id,
            'started_at' => now()->subDays(3), 'resolved_at' => now()->subDays(2),
            'reason' => 'Detector panel artifact first noticed on chest films',
            'impact_notes' => 'Imaging redirected to portable unit for the affected day.',
            'status' => 'resolved',
        ]);

        // A fully healthy, uneventful piece of equipment for contrast.
        Equipment::create([
            'name' => 'Patient Monitor — ICU Bay 3', 'equipment_type' => 'Monitoring', 'department' => 'ICU',
            'serial_number' => 'PM-2023-021', 'status' => 'operational',
            'install_date' => now()->subYear(), 'last_maintenance_at' => now()->subMonth(),
            'next_maintenance_due' => now()->addMonths(5),
        ]);
    }
}
