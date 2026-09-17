<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medical_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->unique()->constrained('visits')->cascadeOnDelete();
            $table->text('chief_complaint')->nullable();
            $table->text('anamnesis')->nullable();
            $table->text('clinical_notes')->nullable();
            $table->text('initial_examination')->nullable();
            $table->json('vital_signs')->nullable();
            $table->text('care_notes')->nullable();
            $table->timestamps();
        });

        Schema::create('odontogram_teeth', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('tooth_number', 2);
            $table->string('status');
            $table->string('notes')->nullable();
            $table->timestamps();

            $table->unique(['patient_id', 'tooth_number']);
        });

        Schema::create('odontogram_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->string('tooth_number', 2);
            $table->string('previous_status')->nullable();
            $table->string('new_status');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['patient_id', 'visit_id']);
        });

        Schema::create('diagnoses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->string('tooth_number', 2)->nullable();
            $table->string('code');
            $table->string('description');
            $table->timestamps();
        });

        Schema::create('procedure_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignId('procedure_id')->constrained('procedures')->restrictOnDelete();
            $table->string('tooth_number', 2)->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->decimal('price_at_time', 15, 2);
            $table->string('billing_status')->default('unbilled');
            $table->timestamps();

            $table->index(['visit_id', 'billing_status']);
        });

        Schema::create('prescriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status');
            $table->timestamps();

            $table->index(['status', 'visit_id']);
        });

        Schema::create('prescription_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('prescription_id')->constrained('prescriptions')->cascadeOnDelete();
            $table->foreignId('medicine_id')->constrained('medicines')->restrictOnDelete();
            $table->string('dosage')->nullable();
            $table->string('frequency')->nullable();
            $table->string('duration')->nullable();
            $table->unsignedInteger('quantity')->default(1);
            $table->timestamps();
        });

        Schema::create('referrals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->string('referred_to');
            $table->string('reason');
            $table->text('notes')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('referrals');
        Schema::dropIfExists('prescription_items');
        Schema::dropIfExists('prescriptions');
        Schema::dropIfExists('procedure_records');
        Schema::dropIfExists('diagnoses');
        Schema::dropIfExists('odontogram_logs');
        Schema::dropIfExists('odontogram_teeth');
        Schema::dropIfExists('medical_records');
    }
};
