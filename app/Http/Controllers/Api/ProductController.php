<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Http\Resources\ProductResource;
use App\Models\Product;
use App\Models\ProductImage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;


class ProductController extends Controller
{
    /**
     * 後台使用
     */
    public function adminIndex()
    {
        $products = Product::with('images')->orderBy('created_at', 'desc')->paginate();

        return response()->json([
            'success' => true,
            'message' => __('product.fetch_success'),
            'data' => $products
        ], 200);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'description' => 'nullable|string',
            'product_type' => 'required|in:physical,digital',
            'stock' => 'nullable|integer|min:0',
            'price' => 'required|numeric|min:0',
            'special_price' => 'nullable|numeric|min:0',
            'status' => 'required|in:active,inactive',
            'is_favorites' => 'boolean',
            'images.*' => 'image|max:5120', // 多張圖片
        ]);

        DB::beginTransaction();

        try {
            $product = Product::create([
                'title' => $request->title,
                'description' => $request->description,
                'product_type' => $request->product_type,
                'stock' => $request->stock ?? null,
                'price' => $request->price,
                'special_price' => $request->special_price ?? null,
                'status' => $request->status,
                'is_favorites' => $request->is_favorites ?? false,
            ]);

            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    try {
                        if (!$image->isValid()) {
                            Log::warning(__('product.invalid_image'), [
                                'original_name' => $image->getClientOriginalName(),
                                'index' => $index,
                            ]);
                            continue;
                        }

                        // 生成唯一檔名
                        $uniqueId = Str::random(10);
                        $filename = 'product_' . $product->id . '_' . $uniqueId . '.' . $image->getClientOriginalExtension();

                        // 上傳到 R2 products 資料夾
                        $path = Storage::disk('r2')->putFileAs('products', $image, $filename);

                        if (!$path) {
                            Log::error(__('product.r2_upload_failed'), [
                                'original_name' => $image->getClientOriginalName(),
                                'index' => $index,
                            ]);
                            continue;
                        }

                        $url = config('filesystems.disks.r2.url') . '/' . $path;

                        ProductImage::create([
                            'product_id' => $product->id,
                            'image_url' => $url,
                            'sort_order' => $index,
                        ]);
                    } catch (\Exception $e) {
                        Log::error(__('product.upload_failed'), [
                            'original_name' => $image->getClientOriginalName(),
                            'index' => $index,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            } else {
                Log::warning(__('product.no_images'));
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('product.created_success'),
                'product' => $product->load('images'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('product.creation_failed') . ': ' . $e->getMessage(),
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        DB::beginTransaction();

        try {
            $product = Product::with('images')->find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => __('product.update_failed')
                ], 404);
            }

            $product->update([
                'title' => $request->title,
                'description' => $request->description,
                'product_type' => $request->product_type,
                'stock' => $request->stock,
                'price' => $request->price,
                'special_price' => $request->special_price,
                'status' => $request->status,
                'is_favorites' => $request->is_favorites ?? false,
            ]);

            /**
             * 刪除舊圖片
             */
            if ($request->remove_image_ids && is_array($request->remove_image_ids)) {
                foreach ($request->remove_image_ids as $imageId) {
                    $image = $product->images->firstWhere('id', $imageId);
                    if ($image) {
                        try {
                            $relativePath = str_replace(config('filesystems.disks.r2.url') . '/', '', $image->image_url);
                            if (Storage::disk('r2')->exists($relativePath)) {
                                Storage::disk('r2')->delete($relativePath);
                            }
                        } catch (\Exception $e) {
                            Log::error(__('product.r2_delete_failed'), [
                                'image_url' => $image->image_url,
                                'error' => $e->getMessage(),
                            ]);
                        }
                        $image->delete(); // DB 自動刪除
                    }
                }
            }

            /**
             * 新增新圖片
             */
            if ($request->hasFile('images')) {
                foreach ($request->file('images') as $index => $image) {
                    if (!$image->isValid()) {
                        Log::warning(__('product.invalid_image'));
                        continue;
                    }

                    $uniqueId = Str::random(10);
                    $filename = 'product_' . $product->id . '_' . $uniqueId . '_' . $index
                        . '.' . $image->getClientOriginalExtension();

                    $path = Storage::disk('r2')->putFileAs('products', $image, $filename);

                    if (!$path) {
                        Log::error(__('product.upload_failed'));
                        continue;
                    }

                    $url = config('filesystems.disks.r2.url') . '/' . $path;

                    ProductImage::create([
                        'product_id' => $product->id,
                        'image_url' => $url,
                        'sort_order' => $index,
                    ]);
                }
            }

            /**
             * 重新整理所有圖片的 sort_order
             */
            $allImages = $product->images()->orderBy('sort_order')->get();
            foreach ($allImages as $i => $image) {
                $image->sort_order = $i;
                $image->save();
            }

            DB::commit();

            return response()->json([
                'success' => true,
                'message' => __('product.updated_success'),
                'product' => $product->fresh()->load('images'),
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json([
                'success' => false,
                'message' => __('product.update_failed'),
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $product = Product::with('images')->find($id);

            if (!$product) {
                return response()->json([
                    'success' => false,
                    'message' => __('system.not_found')
                ], 404);
            }

            // 先刪除 R2 上的圖片
            foreach ($product->images as $image) {
                try {
                    // 從 image_url 取得相對路徑
                    $relativePath = str_replace(config('filesystems.disks.r2.url') . '/', '', $image->image_url);

                    if (Storage::disk('r2')->exists($relativePath)) {
                        Storage::disk('r2')->delete($relativePath);
                    }
                } catch (\Exception $e) {
                    Log::error(__('system.r2_delete_failed'), [
                        'image_url' => $image->image_url,
                        'error' => $e->getMessage(),
                    ]);
                }
            }

            // 刪除商品（cascade 會刪掉 ProductImage）
            $product->delete();

            return response()->json([
                'success' => true,
                'message' => __('product.deleted_success')
            ]);
        } catch (\Exception $e) {
            Log::error(__('product.deletion_failed'), [
                'product_id' => $id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => __('product.deletion_failed'),
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * 前台使用
     */
    public function index(Request $request)
    {
        $allowedTypes = ['physical', 'digital'];
        $productType = $request->query('product_type');
        // 安全取得 per_page（最大 50）
        $perPage = min((int) $request->query('per_page', 10), 50);

        $query = Product::with('images')
            // 確保只顯示已上架的商品 (符合前台邏輯)
            ->where('status', 'active')
            // 限制只選取前台需要的公開欄位
            ->select(['id', 'title', 'price', 'special_price', 'product_type', 'is_favorites', 'status']);

        // 根據 product_type 進行條件過濾
        if ($productType && in_array($productType, $allowedTypes)) {
            $query->where('product_type', $productType);
        }

        $products = $query->orderBy('created_at', 'desc')->paginate($perPage);

        return response()->json([
            'success' => true,
            'message' => __('product.fetch_success'),
            'data' => $products
        ], 200);
    }

    public function show($id)
    {
        $product = Product::with('images')->findOrFail($id);

        return response()->json([
            'success' => true,
            'message' => __('product.fetch_success'),
            'data' => $product
        ], 200);
    }
}
