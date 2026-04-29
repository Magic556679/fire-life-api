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
        Schema::create('orders', function (Blueprint $table) {
            $table->id();

            // 對外訂單編號（給客戶 / 金流用）
            $table->string('order_no')->unique();

            // 未來會員系統用（現在允許 NULL）
            $table->foreignId('user_id')
                ->nullable()
                ->constrained()
                ->nullOnDelete();

            // 購買者資訊（無會員也能下單）
            $table->string('buyer_name');
            $table->string('buyer_email');
            $table->string('buyer_phone')->nullable();

            // 商品成交快照
            $table->json('items_snapshot');

            // 金額（綠界）
            $table->unsignedInteger('total_amount');

            // 配送 / 商品類型
            $table->enum('delivery_type', [
                'physical',   // 實體商品（超商取貨）
                'digital',    // 電子書 / 虛擬服務
            ]);

            // 超商門市資訊（只在 physical 時有值）
            $table->json('store_info')->nullable();

            // 訂單狀態
            $table->enum('status', [
                'pending',    // 已建立，未付款
                'paid',       // 已付款
                'shipping',   // 出貨中（實體）
                'completed',  // 完成
                'failed',     // 付款失敗
                'cancelled',  // 取消
            ])->default('pending');

            // 金流時間
            $table->timestamp('paid_at')->nullable();

            $table->timestamps();

            // 查詢效能
            $table->index('buyer_email');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('orders');
    }
};
