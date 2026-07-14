<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * The original enum only covered module_down/low_stock/sync_pending/
     * user_approval/backup. Clinical safety events (critical lab values,
     * allergy conflicts at dispensing) need their own types rather than
     * being crammed into an unrelated category.
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE system_alerts MODIFY type ENUM(
            'module_down', 'low_stock', 'sync_pending', 'user_approval', 'backup',
            'critical_result', 'allergy_conflict'
        ) NOT NULL");
    }

    public function down(): void
    {
        DB::statement("ALTER TABLE system_alerts MODIFY type ENUM(
            'module_down', 'low_stock', 'sync_pending', 'user_approval', 'backup'
        ) NOT NULL");
    }
};
