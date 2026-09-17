<?php

use App\Models\Branch;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            $table->string('queue_monitor_token', 64)->nullable()->unique()->after('is_active');
        });

        Branch::withoutGlobalScopes()->each(function (Branch $branch) {
            if (! $branch->queue_monitor_token) {
                $branch->forceFill([
                    'queue_monitor_token' => Str::random(48),
                ])->saveQuietly();
            }
        });

        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->string('type');
            $table->string('period');
            $table->unsignedInteger('last_number')->default(0);
            $table->timestamps();

            $table->unique(['tenant_id', 'branch_id', 'type', 'period'], 'document_sequences_unique');
        });

        Schema::create('patients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->string('medical_record_number');
            $table->string('name');
            $table->string('nik', 16)->nullable();
            $table->date('dob')->nullable();
            $table->string('gender')->nullable();
            $table->string('phone', 30)->nullable();
            $table->text('address')->nullable();
            $table->foreignId('default_payer_id')->nullable()->constrained('payers')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'medical_record_number']);
            $table->unique(['tenant_id', 'nik']);
            $table->index(['tenant_id', 'name']);
        });

        Schema::create('visits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->foreignId('doctor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('room_id')->nullable()->constrained('rooms')->nullOnDelete();
            $table->foreignId('payer_id')->constrained('payers')->restrictOnDelete();
            $table->string('status');
            $table->date('visit_date');
            $table->timestamps();

            $table->index(['tenant_id', 'visit_date', 'branch_id']);
            $table->index(['tenant_id', 'patient_id']);
            $table->index(['doctor_id', 'visit_date']);
        });

        Schema::create('queues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('visit_id')->constrained('visits')->cascadeOnDelete();
            $table->unsignedInteger('queue_number');
            $table->timestamp('called_at')->nullable();
            $table->string('status');
            $table->timestamps();

            $table->unique('visit_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('queues');
        Schema::dropIfExists('visits');
        Schema::dropIfExists('patients');
        Schema::dropIfExists('document_sequences');

        Schema::table('branches', function (Blueprint $table) {
            $table->dropUnique(['queue_monitor_token']);
            $table->dropColumn('queue_monitor_token');
        });
    }
};
