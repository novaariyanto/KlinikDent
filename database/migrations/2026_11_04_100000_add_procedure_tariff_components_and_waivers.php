<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tariffs', function (Blueprint $table) {
            $table->decimal('doctor_fee', 15, 2)->default(0)->after('price');
            $table->decimal('bhp_amount', 15, 2)->default(0)->after('doctor_fee');
            $table->decimal('clinic_fee', 15, 2)->default(0)->after('bhp_amount');
        });

        DB::table('tariffs')
            ->where('doctor_fee', 0)
            ->where('bhp_amount', 0)
            ->where('clinic_fee', 0)
            ->update(['clinic_fee' => DB::raw('price')]);

        Schema::table('procedure_records', function (Blueprint $table) {
            $table->decimal('tariff_price_at_time', 15, 2)->default(0)->after('price_at_time');
            $table->decimal('doctor_fee_at_time', 15, 2)->default(0)->after('tariff_price_at_time');
            $table->decimal('bhp_amount_at_time', 15, 2)->default(0)->after('doctor_fee_at_time');
            $table->decimal('clinic_fee_at_time', 15, 2)->default(0)->after('bhp_amount_at_time');
            $table->string('adjustment_type', 32)->default('normal')->after('clinic_fee_at_time');
            $table->string('discount_type', 16)->nullable()->after('adjustment_type');
            $table->decimal('discount_value', 15, 2)->default(0)->after('discount_type');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('discount_value');
            $table->text('waiver_reason')->nullable()->after('discount_amount');
            $table->foreignId('waived_by')->nullable()->after('waiver_reason')->constrained('users')->nullOnDelete();
            $table->timestamp('waived_at')->nullable()->after('waived_by');
            $table->string('waiver_status', 16)->default('none')->after('waived_at');
            $table->foreignId('approved_by')->nullable()->after('waiver_status')->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable()->after('approved_by');
            $table->boolean('include_waived_in_sharing_fee')->default(false)->after('approved_at');
        });

        DB::table('procedure_records')->update([
            'tariff_price_at_time' => DB::raw('price_at_time'),
            'clinic_fee_at_time' => DB::raw('price_at_time'),
        ]);

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->string('adjustment_type', 32)->default('normal')->after('subtotal');
            $table->decimal('original_unit_price', 15, 2)->default(0)->after('adjustment_type');
            $table->decimal('doctor_fee_original', 15, 2)->default(0)->after('original_unit_price');
            $table->decimal('doctor_fee_charged', 15, 2)->default(0)->after('doctor_fee_original');
            $table->decimal('doctor_fee_waived', 15, 2)->default(0)->after('doctor_fee_charged');
            $table->decimal('bhp_amount', 15, 2)->default(0)->after('doctor_fee_waived');
            $table->decimal('clinic_fee', 15, 2)->default(0)->after('bhp_amount');
            $table->decimal('discount_amount', 15, 2)->default(0)->after('clinic_fee');
            $table->boolean('include_waived_in_sharing_fee')->default(false)->after('discount_amount');
            $table->json('component_snapshot')->nullable()->after('include_waived_in_sharing_fee');
        });

        DB::table('invoice_items')->update([
            'original_unit_price' => DB::raw('unit_price'),
            'clinic_fee' => DB::raw('unit_price'),
        ]);

        Schema::create('doctor_fee_waiver_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('doctor_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('is_allowed')->default(true);
            $table->boolean('require_approval')->default(false);
            $table->boolean('include_waived_in_sharing_fee')->default(false);
            $table->string('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'doctor_user_id', 'branch_id'], 'dfw_settings_doctor_branch_uq');
            $table->index(['tenant_id', 'doctor_user_id', 'is_allowed'], 'dfw_settings_doctor_allowed_idx');
        });

        Schema::create('doctor_fee_waiver_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('visit_id')->constrained()->restrictOnDelete();
            $table->foreignId('procedure_record_id')->constrained()->restrictOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('doctor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('waived_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('adjustment_type', 32);
            $table->string('status', 16)->default('applied');
            $table->text('reason')->nullable();
            $table->decimal('doctor_fee_before', 15, 2)->default(0);
            $table->decimal('doctor_fee_after', 15, 2)->default(0);
            $table->decimal('tariff_price', 15, 2)->default(0);
            $table->decimal('charged_unit_price', 15, 2)->default(0);
            $table->string('discount_type', 16)->nullable();
            $table->decimal('discount_value', 15, 2)->default(0);
            $table->decimal('discount_amount', 15, 2)->default(0);
            $table->boolean('include_waived_in_sharing_fee')->default(false);
            $table->timestamp('waived_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->json('snapshot')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'status', 'waived_at'], 'dfw_logs_status_idx');
            $table->index(['tenant_id', 'doctor_user_id', 'waived_at'], 'dfw_logs_doctor_idx');
            $table->index(['procedure_record_id'], 'dfw_logs_record_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('doctor_fee_waiver_logs');
        Schema::dropIfExists('doctor_fee_waiver_settings');

        Schema::table('invoice_items', function (Blueprint $table) {
            $table->dropColumn([
                'adjustment_type',
                'original_unit_price',
                'doctor_fee_original',
                'doctor_fee_charged',
                'doctor_fee_waived',
                'bhp_amount',
                'clinic_fee',
                'discount_amount',
                'include_waived_in_sharing_fee',
                'component_snapshot',
            ]);
        });

        Schema::table('procedure_records', function (Blueprint $table) {
            $table->dropConstrainedForeignId('waived_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn([
                'tariff_price_at_time',
                'doctor_fee_at_time',
                'bhp_amount_at_time',
                'clinic_fee_at_time',
                'adjustment_type',
                'discount_type',
                'discount_value',
                'discount_amount',
                'waiver_reason',
                'waived_at',
                'waiver_status',
                'approved_at',
                'include_waived_in_sharing_fee',
            ]);
        });

        Schema::table('tariffs', function (Blueprint $table) {
            $table->dropColumn(['doctor_fee', 'bhp_amount', 'clinic_fee']);
        });
    }
};
