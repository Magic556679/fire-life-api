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
        Schema::create('posts', function (Blueprint $table) {
            $table->id();
            $table->string('title')->comment('文章標題');
            $table->string('slug')->unique()->comment('自訂網址/slug');
            $table->text('meta_description')->nullable()->comment('SEO Meta Description');
            $table->longText('content')->comment('文章內容，HTML格式');

            $table->string('author')->nullable()->comment('文章作者');
            $table->boolean('is_enabled')->default(true)->comment('是否啟用文章，預設true');
            $table->boolean('is_pinned')->default(false)->comment('文章是否置頂，預設false');

            $table->string('og_image')->nullable()->comment('分享縮圖 URL (Open Graph)');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('posts');
    }
};
