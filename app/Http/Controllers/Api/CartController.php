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

        return response()->json([
            'id' => $cart->id,
            'guest_token' => $cart->guest_token,
            'items' => $cart->items->map(function ($item) {
                return [
                    'id' => $item->id,
                    'product_id' => $item->product_id,
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'subtotal' => $item->price * $item->quantity,
                    'product' => [
                        'id' => $item->product->id,
                        'name' => $item->product->name,
                        'image' => $item->product->image ?? null,
                    ],
                ];
            }),
            'total' => $cart->items->sum(fn($item) => $item->price * $item->quantity),
        ]);
    }
}
