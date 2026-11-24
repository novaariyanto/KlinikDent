<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('icd10_codes', function (Blueprint $table) {
            $table->string('code', 32)->primary();
            $table->text('description');
            $table->string('version', 32)->nullable();
        });

        Schema::create('icd9_codes', function (Blueprint $table) {
            $table->string('code', 32)->primary();
            $table->text('description');
            $table->string('version', 32)->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('icd9_codes');
        Schema::dropIfExists('icd10_codes');
    }
};
