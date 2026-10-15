<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('branches', function (Blueprint $table) {
            if (! Schema::hasColumn('branches', 'city')) {
                $table->string('city', 100)->nullable()->after('address');
            }

            if (! Schema::hasColumn('branches', 'postal_code')) {
                $table->string('postal_code', 10)->nullable()->after('city');
            }

            if (! Schema::hasColumn('branches', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }

            if (! Schema::hasColumn('branches', 'website')) {
                $table->string('website')->nullable()->after('email');
            }

            if (! Schema::hasColumn('branches', 'longitude')) {
                $table->decimal('longitude', 10, 7)->nullable()->after('website');
            }

            if (! Schema::hasColumn('branches', 'latitude')) {
                $table->decimal('latitude', 10, 7)->nullable()->after('longitude');
            }

            if (! Schema::hasColumn('branches', 'altitude')) {
                $table->decimal('altitude', 8, 2)->nullable()->after('latitude');
            }
        });

        Schema::table('rooms', function (Blueprint $table) {
            if (! Schema::hasColumn('rooms', 'code')) {
                $table->string('code', 64)->nullable()->after('name');
            }

            if (! Schema::hasColumn('rooms', 'description')) {
                $table->string('description', 500)->nullable()->after('code');
            }

            if (! Schema::hasColumn('rooms', 'phone')) {
                $table->string('phone', 30)->nullable()->after('description');
            }

            if (! Schema::hasColumn('rooms', 'email')) {
                $table->string('email')->nullable()->after('phone');
            }
        });

        $indexed = collect(DB::select('SHOW INDEX FROM rooms'))
            ->contains(fn ($index) => $index->Key_name === 'rooms_branch_id_code_unique');

        if (! $indexed) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->unique(['branch_id', 'code']);
            });
        }
    }

    public function down(): void
    {
        $indexed = collect(DB::select('SHOW INDEX FROM rooms'))
            ->contains(fn ($index) => $index->Key_name === 'rooms_branch_id_code_unique');

        if ($indexed) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->dropUnique('rooms_branch_id_code_unique');
            });
        }

        Schema::table('rooms', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['code', 'description', 'phone', 'email'],
                fn (string $column) => Schema::hasColumn('rooms', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });

        Schema::table('branches', function (Blueprint $table) {
            $columns = array_values(array_filter(
                ['city', 'postal_code', 'email', 'website', 'longitude', 'latitude', 'altitude'],
                fn (string $column) => Schema::hasColumn('branches', $column),
            ));

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
