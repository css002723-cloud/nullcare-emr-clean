<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Records who overrode an allergy alert to dispense a prescription
     * anyway, and when. Unlike the unlisted-drug confirmation (which is
     * only reflected in the audit log), an allergy override needs its own
     * queryable trail on the prescription itself so a dispense that
     * proceeded despite a documented allergy is permanently distinguishable
     * from a normal dispense — not just visible in the log if someone goes
     * looking for it.
     */
    public function up(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->foreignId('allergy_override_by')->nullable()->after('dispensed_at')->constrained('users')->nullOnDelete();
            $table->timestamp('allergy_override_at')->nullable()->after('allergy_override_by');
        });
    }

    public function down(): void
    {
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('allergy_override_by');
            $table->dropColumn('allergy_override_at');
        });
    }
};
