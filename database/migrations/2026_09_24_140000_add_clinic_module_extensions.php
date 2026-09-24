<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('queues', function (Blueprint $table) {
            $table->foreignId('doctor_schedule_id')->nullable()->after('visit_id')->constrained('doctor_schedules')->nullOnDelete();
            $table->foreignId('doctor_user_id')->nullable()->after('doctor_schedule_id')->constrained('users')->nullOnDelete();
            $table->index(['doctor_user_id', 'status']);
        });

        Schema::table('procedure_records', function (Blueprint $table) {
            $table->string('icd9_code', 16)->nullable()->after('tooth_number');
        });

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->string('kind', 20)->default('ready')->after('prescription_id');
            $table->unsignedBigInteger('medicine_id')->nullable()->change();
        });

        Schema::create('prescription_compound_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_item_id')->constrained('prescription_items')->cascadeOnDelete();
            $table->foreignId('medicine_id')->nullable()->constrained('medicines')->nullOnDelete();
            $table->string('name');
            $table->string('unit', 30)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
        });

        Schema::table('medicines', function (Blueprint $table) {
            $table->string('kfa_code', 40)->nullable()->after('name');
            $table->unique(['tenant_id', 'kfa_code']);
        });

        Schema::create('dental_lab_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->constrained()->cascadeOnDelete();
            $table->string('work_type');
            $table->string('tooth_numbers')->nullable();
            $table->string('shade')->nullable();
            $table->string('vendor')->nullable();
            $table->date('due_date')->nullable();
            $table->string('status', 20)->default('ordered');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('posted');
            $table->text('notes')->nullable();
            $table->timestamp('posted_at')->nullable();
            $table->timestamps();
        });

        Schema::create('stock_opname_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('stock_opname_id')->constrained()->cascadeOnDelete();
            $table->foreignId('medicine_stock_id')->constrained()->restrictOnDelete();
            $table->unsignedInteger('system_qty');
            $table->unsignedInteger('physical_qty');
            $table->timestamps();
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('kind', 20)->default('payment')->after('shift_id');
        });

        Schema::table('doctors', function (Blueprint $table) {
            $table->string('signature_path')->nullable()->after('notes');
        });

        Schema::table('medical_records', function (Blueprint $table) {
            $table->timestamp('finalized_at')->nullable()->after('followup_date');
            $table->foreignId('finalized_by')->nullable()->after('finalized_at')->constrained('users')->nullOnDelete();
        });

        Schema::create('medical_record_revisions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->json('snapshot');
            $table->timestamps();
        });

        Schema::create('medical_record_amendments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('medical_record_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();
        });

        Schema::create('clinical_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->string('type', 40);
            $table->string('number');
            $table->string('verification_code', 16)->unique();
            $table->string('patient_initials', 12)->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 20)->default('valid');
            $table->timestamp('signed_at')->nullable();
            $table->string('signature_path')->nullable();
            $table->string('signature_provider', 20)->default('visual');
            $table->string('certificate_sn')->nullable();
            $table->text('signed_payload')->nullable();
            $table->nullableMorphs('reference');
            $table->timestamps();

            $table->index(['tenant_id', 'type']);
        });

        Schema::create('practice_archives', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 20)->default('pending');
            $table->string('path')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('practice_archives');
        Schema::dropIfExists('clinical_documents');
        Schema::dropIfExists('medical_record_amendments');
        Schema::dropIfExists('medical_record_revisions');

        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('finalized_by');
            $table->dropColumn('finalized_at');
        });

        Schema::table('doctors', function (Blueprint $table) {
            $table->dropColumn('signature_path');
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn('kind');
        });

        Schema::dropIfExists('stock_opname_items');
        Schema::dropIfExists('stock_opnames');
        Schema::dropIfExists('dental_lab_orders');

        Schema::table('medicines', function (Blueprint $table) {
            $table->dropUnique(['tenant_id', 'kfa_code']);
            $table->dropColumn('kfa_code');
        });

        Schema::dropIfExists('prescription_compound_items');

        Schema::table('prescription_items', function (Blueprint $table) {
            $table->dropColumn('kind');
        });

        Schema::table('procedure_records', function (Blueprint $table) {
            $table->dropColumn('icd9_code');
        });

        Schema::table('queues', function (Blueprint $table) {
            $table->dropConstrainedForeignId('doctor_user_id');
            $table->dropConstrainedForeignId('doctor_schedule_id');
        });
    }
};
