<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // 移除 delivery_type
            $table->dropColumn('delivery_type');

            // 新增MerchantTradeNo (商店交易編號)
            $table->string('payment_trade_no')
                ->nullable()
                ->after('paid_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // 還原 delivery_type
            $table->enum('delivery_type', [
                'physical',
                'digital',
            ])->after('items_snapshot');

            // 移除 MerchantTradeNo (商店交易編號)
            $table->dropColumn('payment_trade_no');
        });
    }
};
