<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('saas_invoices', function (Blueprint $table) {
            $table->string('payment_channel', 30)->nullable()->after('paid_at');
            $table->string('midtrans_order_id')->nullable()->unique()->after('payment_channel');
            $table->string('midtrans_snap_token')->nullable()->after('midtrans_order_id');
            $table->string('midtrans_transaction_id')->nullable()->after('midtrans_snap_token');
            $table->string('midtrans_status', 50)->nullable()->after('midtrans_transaction_id');
            $table->json('payment_payload')->nullable()->after('midtrans_status');
        });
    }

    public function down(): void
    {
        Schema::table('saas_invoices', function (Blueprint $table) {
            $table->dropUnique(['midtrans_order_id']);
            $table->dropColumn([
                'payment_channel',
                'midtrans_order_id',
                'midtrans_snap_token',
                'midtrans_transaction_id',
                'midtrans_status',
                'payment_payload',
            ]);
        });
    }
};
