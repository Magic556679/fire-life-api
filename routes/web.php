<?php

use Illuminate\Support\Facades\Route;
use App\Models\Post;
use Spatie\Sitemap\Sitemap;
use Spatie\Sitemap\Tags\Url;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/reset-password/{token}', function ($token) {
    return response()->json([
        'message' => '這裡應該導向前端的 ResetPassword 頁面',
        'token' => $token,
    ]);
})->name('password.reset');

Route::get('/sitemap.xml', function () {
    $sitemap = Sitemap::create();

    // 文章
    $posts = Post::all();
    foreach ($posts as $post) {
        $sitemap->add(
            Url::create("/blog/{$post->slug}")
                ->setLastModificationDate($post->updated_at)
        );
    }

    // 商品
    // $products = Product::all();
    // foreach ($products as $product) {
    //     $sitemap->add(
    //         Url::create("/products/{$product->id}")
    //             ->setLastModificationDate($product->updated_at)
    //     );
    // }


    return $sitemap->toResponse(request());
});
