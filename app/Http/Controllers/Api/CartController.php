<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use Illuminate\Http\Request;

class CartController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    public function show(Request $request)
    {
        $cart = $this->cartService->getCartForUserOrGuest($request);
        $cart->load('items.product.images');

        $cartData = [
            'id' => $cart->id,
            'guest_token' => $cart->guest_token,
            'items' => $cart->items->map(function ($item) {
                $currentPrice = (float) $item->product->price;
                return [
                    'id'         => $item->id,
                    'product_id' => $item->product_id,
                    'quantity'   => $item->quantity,
                    'price'      => $currentPrice,
                    'subtotal'   => $currentPrice * $item->quantity,
                    'product'    => [
                        'id'           => $item->product->id,
                        'name'         => $item->product->title,
                        'image'        => $item->product->images ?? null,
                        'product_type' => $item->product->product_type,
                        'stock'        => $item->product->stock,
                    ],
                ];
            }),
            'total' => $cart->items->sum(fn($item) => (float) $item->product->price * $item->quantity),
        ];

        return response()->json([
            'success' => true,
            'message' => __('cart.fetch_success'),
            'data' => $cartData
        ], 200);
    }
}
