<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('odontogram_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->string('tooth_number', 2);
            $table->string('status');
            $table->json('conditions')->nullable();
            $table->string('notes')->nullable();
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['visit_id', 'tooth_number']);
            $table->index(['patient_id', 'visit_id']);
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->index(['patient_id', 'visit_date'], 'visits_patient_id_visit_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            $table->dropIndex('visits_patient_id_visit_date_index');
        });

        Schema::dropIfExists('odontogram_snapshots');
    }
};
