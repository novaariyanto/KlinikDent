<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (! Schema::hasColumn('visits', 'arrived_at')) {
                $table->timestamp('arrived_at')->nullable()->after('visit_date');
            }

            if (! Schema::hasColumn('visits', 'completed_at')) {
                $table->timestamp('completed_at')->nullable()->after('arrived_at');
            }
        });

        if (Schema::hasColumn('visits', 'arrived_at')) {
            DB::table('visits')->whereNull('arrived_at')->update([
                'arrived_at' => DB::raw('created_at'),
            ]);
        }

        if (Schema::hasColumn('visits', 'completed_at')) {
            DB::table('visits')
                ->where('status', 'done')
                ->whereNull('completed_at')
                ->update([
                    'completed_at' => DB::raw('updated_at'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('visits', function (Blueprint $table) {
            if (Schema::hasColumn('visits', 'completed_at')) {
                $table->dropColumn('completed_at');
            }

            if (Schema::hasColumn('visits', 'arrived_at')) {
                $table->dropColumn('arrived_at');
            }
        });
    }
};
