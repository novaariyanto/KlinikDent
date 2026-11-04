<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('care_episodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained('tenants')->cascadeOnDelete();
            $table->foreignId('branch_id')->constrained('branches')->cascadeOnDelete();
            $table->foreignId('patient_id')->constrained('patients')->cascadeOnDelete();
            $table->string('status', 16)->default('open');
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'patient_id', 'status'], 'care_episodes_patient_status_index');
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->foreignId('episode_id')->nullable()->after('patient_id')->constrained('care_episodes')->nullOnDelete();
        });

        Schema::table('medical_records', function (Blueprint $table) {
            $table->string('assessment_kind', 16)->nullable()->after('visit_id');
            $table->string('assessment_status', 16)->default('draft')->after('assessment_kind');
            $table->timestamp('assessment_completed_at')->nullable()->after('assessment_status');
            $table->foreignId('assessment_completed_by')->nullable()->after('assessment_completed_at')->constrained('users')->nullOnDelete();
            $table->json('follow_up_review')->nullable()->after('plan_instructions');
        });

        $groups = DB::table('visits')
            ->select('tenant_id', 'branch_id', 'patient_id')
            ->whereNull('episode_id')
            ->groupBy('tenant_id', 'branch_id', 'patient_id')
            ->get();

        foreach ($groups as $group) {
            $openedAt = DB::table('visits')
                ->where('tenant_id', $group->tenant_id)
                ->where('branch_id', $group->branch_id)
                ->where('patient_id', $group->patient_id)
                ->min('created_at');

            $episodeId = DB::table('care_episodes')->insertGetId([
                'tenant_id' => $group->tenant_id,
                'branch_id' => $group->branch_id,
                'patient_id' => $group->patient_id,
                'status' => 'open',
                'opened_at' => $openedAt,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            DB::table('visits')
                ->where('tenant_id', $group->tenant_id)
                ->where('branch_id', $group->branch_id)
                ->where('patient_id', $group->patient_id)
                ->whereNull('episode_id')
                ->update(['episode_id' => $episodeId]);

            $recordIds = DB::table('medical_records')
                ->join('visits', 'visits.id', '=', 'medical_records.visit_id')
                ->where('visits.episode_id', $episodeId)
                ->orderBy('visits.visit_date')
                ->orderBy('visits.id')
                ->pluck('medical_records.id');

            if ($recordIds->isEmpty()) {
                continue;
            }

            DB::table('medical_records')->where('id', $recordIds->first())->update([
                'assessment_kind' => 'initial',
            ]);

            $later = $recordIds->slice(1)->values();
            if ($later->isNotEmpty()) {
                DB::table('medical_records')->whereIn('id', $later)->update([
                    'assessment_kind' => 'follow_up',
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('medical_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('assessment_completed_by');
            $table->dropColumn([
                'assessment_kind',
                'assessment_status',
                'assessment_completed_at',
                'follow_up_review',
            ]);
        });

        Schema::table('visits', function (Blueprint $table) {
            $table->dropConstrainedForeignId('episode_id');
        });

        Schema::dropIfExists('care_episodes');
    }
};
