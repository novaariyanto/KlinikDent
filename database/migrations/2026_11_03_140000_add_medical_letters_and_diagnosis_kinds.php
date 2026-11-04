<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('diagnoses', function (Blueprint $table) {
            $table->string('kind', 20)->default('clinical')->after('visit_id');
            $table->string('rank', 20)->default('secondary')->after('kind');
            $table->text('clinical_note')->nullable()->after('description');
            $table->string('code')->nullable()->change();
        });

        Schema::create('medical_letters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained()->restrictOnDelete();
            $table->foreignId('patient_id')->constrained()->restrictOnDelete();
            $table->foreignId('visit_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('type', 30);
            $table->string('number', 40);
            $table->string('purpose')->nullable();
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->json('snapshot')->nullable();
            $table->string('status', 20)->default('issued');
            $table->string('signature_path')->nullable();
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason')->nullable();
            $table->foreignId('revises_id')->nullable()->constrained('medical_letters')->nullOnDelete();
            $table->foreignId('revised_by_id')->nullable()->constrained('medical_letters')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'branch_id', 'number']);
            $table->index(['tenant_id', 'issued_at']);
            $table->index(['patient_id', 'issued_at']);
            $table->index(['visit_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medical_letters');

        Schema::table('diagnoses', function (Blueprint $table) {
            $table->dropColumn(['kind', 'rank', 'clinical_note']);
            $table->string('code')->nullable(false)->change();
        });
    }
};
