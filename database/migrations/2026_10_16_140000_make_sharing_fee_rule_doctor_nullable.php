<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('sharing_fee_rules', function (Blueprint $table) {
            $table->dropForeign(['doctor_user_id']);
        });

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE sharing_fee_rules MODIFY doctor_user_id BIGINT UNSIGNED NULL');
        } else {
            Schema::table('sharing_fee_rules', function (Blueprint $table) {
                $table->unsignedBigInteger('doctor_user_id')->nullable()->change();
            });
        }

        Schema::table('sharing_fee_rules', function (Blueprint $table) {
            $table->foreign('doctor_user_id')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        $driver = Schema::getConnection()->getDriverName();

        Schema::table('sharing_fee_rules', function (Blueprint $table) {
            $table->dropForeign(['doctor_user_id']);
        });

        DB::table('sharing_fee_rules')->whereNull('doctor_user_id')->update(['doctor_user_id' => 0]);

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE sharing_fee_rules MODIFY doctor_user_id BIGINT UNSIGNED NOT NULL');
        } else {
            Schema::table('sharing_fee_rules', function (Blueprint $table) {
                $table->unsignedBigInteger('doctor_user_id')->nullable(false)->change();
            });
        }

        Schema::table('sharing_fee_rules', function (Blueprint $table) {
            $table->foreign('doctor_user_id')->references('id')->on('users')->restrictOnDelete();
        });
    }
};
