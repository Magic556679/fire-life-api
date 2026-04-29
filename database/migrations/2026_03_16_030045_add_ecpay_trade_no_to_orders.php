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
            // 新增綠界交易編號欄位，用來存 TradeNo
            $table->string('ecpay_trade_no')
                ->nullable()
                ->unique()
                ->after('payment_trade_no')
                ->comment('綠界交易編號 TradeNo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            // 移除綠界交易編號欄位
            $table->dropColumn('ecpay_trade_no');
        });
    }
};
