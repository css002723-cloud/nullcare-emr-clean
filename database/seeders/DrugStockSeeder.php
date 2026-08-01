<?php

namespace Database\Seeders;

use App\Models\DrugStock;
use Illuminate\Database\Seeder;

/**
 * Ported directly from backend/app/seed.py's DEMO_DRUGS — includes
 * Lisinopril intentionally seeded BELOW its reorder level (8 on hand,
 * reorder at 30), so the low-stock alert has something real to show
 * immediately without needing to manually trigger it.
 */
class DrugStockSeeder extends Seeder
{
    public function run(): void
    {
        $drugs = [
            ['drug_name' => 'Paracetamol', 'quantity_on_hand' => 500, 'reorder_level' => 100, 'unit' => 'tablets'],
            ['drug_name' => 'Amoxicillin', 'quantity_on_hand' => 300, 'reorder_level' => 60, 'unit' => 'capsules'],
            ['drug_name' => 'Metformin', 'quantity_on_hand' => 120, 'reorder_level' => 40, 'unit' => 'tablets'],
            ['drug_name' => 'Artemether-Lumefantrine', 'quantity_on_hand' => 200, 'reorder_level' => 50, 'unit' => 'tablets'],
            ['drug_name' => 'Lisinopril', 'quantity_on_hand' => 8, 'reorder_level' => 30, 'unit' => 'tablets'],
            ['drug_name' => 'Ceftriaxone', 'quantity_on_hand' => 90, 'reorder_level' => 20, 'unit' => 'vials'],
            ['drug_name' => 'Oral Rehydration Salts', 'quantity_on_hand' => 400, 'reorder_level' => 100, 'unit' => 'sachets'],
        ];

        foreach ($drugs as $drug) {
            DrugStock::updateOrCreate(['drug_name' => $drug['drug_name']], $drug);
        }
    }
}
