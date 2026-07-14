<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // ── USERS: username login, plain role/department strings, is_active ──
        Schema::table('users', function (Blueprint $table) {
            $table->string('username', 100)->unique()->nullable()->after('id');
            $table->boolean('is_active')->default(true)->after('status');
        });

        // ── PATIENTS: new fields the frontend's Reception/PatientDetail/Patients pages need ──
        Schema::table('patients', function (Blueprint $table) {
            $table->string('region', 50)->nullable()->after('district');
            $table->boolean('is_deceased')->default(false)->after('consent_research');
            $table->string('referred_doctor', 150)->nullable()->after('is_deceased');
            $table->string('referred_doctor_department', 100)->nullable()->after('referred_doctor');
            $table->enum('completion_status', ['not_completed', 'completed'])->default('not_completed')->after('referred_doctor_department');
            $table->string('client_uuid', 36)->nullable()->unique()->after('id');
        });

        // ── ENCOUNTERS: workflow stage, referral tracking, offline reconciliation ──
        Schema::table('encounters', function (Blueprint $table) {
            $table->string('encounter_number', 20)->nullable()->unique()->after('id');
            $table->string('client_uuid', 36)->nullable()->unique()->after('encounter_number');
            // 'stage' drives the Triage/Consultation queue views; 'current_department'
            // drives the ReferralPanel's "currently in" display. Kept as two columns
            // since the frontend reads both independently, but they're updated together
            // in normal flow — see EncounterController notes in Phase 2.
            $table->string('stage', 30)->default('triage')->after('status');
            $table->string('current_department', 30)->default('triage')->after('stage');
            $table->text('disposition_notes')->nullable()->after('clinical_plan');
        });

        // ── VITAL_SIGNS: Early Warning Score ──
        Schema::table('vital_signs', function (Blueprint $table) {
            $table->unsignedTinyInteger('early_warning_score')->nullable()->after('is_abnormal');
        });

        // ── PHARMACY_STOCK: unit field the Pharmacy.jsx stock table displays ──
        Schema::table('pharmacy_stock', function (Blueprint $table) {
            $table->string('unit', 20)->default('units')->after('quantity_available');
        });

        // ── PRESCRIPTIONS: pediatric flag + CDS alert list ──
        Schema::table('prescriptions', function (Blueprint $table) {
            $table->boolean('is_pediatric_dose')->default(false)->after('duration');
            $table->text('cds_alerts')->nullable()->after('is_pediatric_dose'); // JSON-encoded array of alert strings
        });

        // ── LAB_ORDERS: catalog reference, barcode, 'resulted' status support ──
        Schema::table('lab_orders', function (Blueprint $table) {
            $table->string('test_code', 30)->nullable()->after('test_name');
            $table->string('barcode', 30)->nullable()->unique()->after('test_code');
        });

        // ── LAB_RESULTS: clinical interpretation + separate abnormal flag ──
        Schema::table('lab_results', function (Blueprint $table) {
            $table->text('interpretation')->nullable()->after('reference_range');
            $table->boolean('is_abnormal')->default(false)->after('is_critical');
        });

        // ── INVOICES / INVOICE_ITEMS: payer type + richer line items ──
        Schema::table('invoices', function (Blueprint $table) {
            $table->string('payer_type', 30)->default('cash')->after('patient_id');
        });
        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('service_category', 30)->nullable()->after('invoice_id');
        });

        // ── ADMISSIONS: bed occupancy needs a plain bed/ward already present; add client_uuid ──
        Schema::table('admissions', function (Blueprint $table) {
            $table->string('client_uuid', 36)->nullable()->unique()->after('id');
        });

        // ── NEW: generic clinical notes (multi-type documentation) ──
        Schema::create('clinical_notes', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid', 36)->nullable()->unique();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->enum('note_type', ['history_physical', 'progress', 'nursing', 'consult', 'discharge_summary']);
            $table->string('diagnosis', 255)->nullable();
            $table->text('plan')->nullable();
            $table->text('body')->nullable();
            $table->foreignId('recorded_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // ── NEW: generic quick-orders (imaging/procedure/admission; lab shortcuts too) ──
        Schema::create('clinical_orders', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid', 36)->nullable()->unique();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->enum('order_type', ['lab', 'imaging', 'procedure', 'admission']);
            $table->text('details')->nullable();
            $table->string('target_department', 30)->nullable();
            $table->enum('priority', ['routine', 'urgent', 'stat'])->default('routine');
            $table->string('status', 30)->default('pending');
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // ── NEW: inter-department referrals ──
        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->string('client_uuid', 36)->nullable()->unique();
            $table->foreignId('encounter_id')->constrained('encounters')->cascadeOnDelete();
            $table->string('to_department', 30);
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        // ── NEW: LOINC-coded lab test catalog, powers the Laboratory quick-order dropdown ──
        Schema::create('lab_test_catalog', function (Blueprint $table) {
            $table->string('test_code', 30)->primary();
            $table->string('loinc_code', 20)->nullable();
            $table->string('loinc_display', 150);
            $table->string('default_specimen_type', 100)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('lab_test_catalog');
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('clinical_orders');
        Schema::dropIfExists('clinical_notes');

        Schema::table('admissions', fn (Blueprint $t) => $t->dropColumn('client_uuid'));
        Schema::table('invoice_items', fn (Blueprint $t) => $t->dropColumn('service_category'));
        Schema::table('invoices', fn (Blueprint $t) => $t->dropColumn('payer_type'));
        Schema::table('lab_results', fn (Blueprint $t) => $t->dropColumn(['interpretation', 'is_abnormal']));
        Schema::table('lab_orders', fn (Blueprint $t) => $t->dropColumn(['test_code', 'barcode']));
        Schema::table('prescriptions', fn (Blueprint $t) => $t->dropColumn(['is_pediatric_dose', 'cds_alerts']));
        Schema::table('pharmacy_stock', fn (Blueprint $t) => $t->dropColumn('unit'));
        Schema::table('vital_signs', fn (Blueprint $t) => $t->dropColumn('early_warning_score'));
        Schema::table('encounters', fn (Blueprint $t) => $t->dropColumn(['encounter_number', 'client_uuid', 'stage', 'current_department', 'disposition_notes']));
        Schema::table('patients', fn (Blueprint $t) => $t->dropColumn(['region', 'is_deceased', 'referred_doctor', 'referred_doctor_department', 'completion_status', 'client_uuid']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn(['username', 'is_active']));
    }
};
