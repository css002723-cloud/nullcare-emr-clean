<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Small adjustments discovered only by reading the actual frontend submit
     * handlers line-by-line (Reception.jsx, EncounterWorkspace.jsx, Billing.jsx):
     * - Reception no longer sends department_id/clinician_id when opening an
     *   encounter, so those FKs must become nullable.
     * - DispositionPanel's outcome options include 'referred_out' and 'died',
     *   which weren't in the original status enum.
     * - Reception's patient_category select includes 'referred'.
     * - Billing needs its own human-readable invoice_number, same pattern as
     *   patient_number/encounter_number.
     */
    public function up(): void
    {
        Schema::table('encounters', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable()->change();
            $table->unsignedBigInteger('clinician_id')->nullable()->change();
        });

        DB::statement("ALTER TABLE encounters MODIFY status ENUM(
            'open', 'closed', 'referred', 'admitted', 'discharged', 'referred_out', 'died'
        ) NOT NULL DEFAULT 'open'");

        DB::statement("ALTER TABLE patients MODIFY patient_category ENUM(
            'outpatient', 'inpatient', 'student', 'staff', 'private', 'emergency', 'research', 'referred'
        ) NOT NULL");

        Schema::table('invoices', function (Blueprint $table) {
            $table->string('invoice_number', 20)->nullable()->unique()->after('id');
        });
    }

    public function down(): void
    {
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('invoice_number'));

        DB::statement("ALTER TABLE patients MODIFY patient_category ENUM(
            'outpatient', 'inpatient', 'student', 'staff', 'private', 'emergency', 'research'
        ) NOT NULL");

        DB::statement("ALTER TABLE encounters MODIFY status ENUM(
            'open', 'closed', 'referred', 'admitted', 'discharged'
        ) NOT NULL DEFAULT 'open'");

        Schema::table('encounters', function (Blueprint $table) {
            $table->unsignedBigInteger('department_id')->nullable(false)->change();
            $table->unsignedBigInteger('clinician_id')->nullable(false)->change();
        });
    }
};
