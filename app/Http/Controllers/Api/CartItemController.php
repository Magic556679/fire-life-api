<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\CartService;
use App\Exceptions\CartException;
use Illuminate\Http\Request;


class CartItemController extends Controller
{
    public function __construct(
        protected CartService $cartService
    ) {}

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
        ]);

        $guestToken = $request->header('X-Guest-Token');

        $cart = $this->cartService->getCartByGuestToken($guestToken);

        $item = $cart->items()->where('product_id', $request->product_id)->first();

        if ($item) {
            $item->quantity += $request->quantity;
            $item->save();
        } else {
            // 如果不存在，新增一筆
            $productPrice = \App\Models\Product::findOrFail($request->product_id)->price;

            $item = $cart->items()->create([
                'product_id' => $request->product_id,
                'quantity' => $request->quantity,
                'price' => $productPrice,
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => __('cart.item_added'),
            'item' => $item,
        ]);
    }

    public function update(Request $request, $id)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1',
        ], [
            'quantity.min' => __('cart.invalid_quantity')
        ]);

        // 取得購物車，會員或訪客都支援
        $cart = $this->cartService->getCartForUserOrGuest($request);

        $item = $cart->items()->where('id', $id)->first();

        if (!$item) {
            throw new CartException(__('cart.item_not_found'), 404);
        }

        $item->quantity = $request->quantity;
        $item->save();

        return response()->json([
            'success' => true,
            'message' => __('cart.item_updated'),
            'item' => $item,
        ]);
    }

    public function  destroy(Request $request,  $itemId)
    {
        $cart = $this->cartService->getCartForUserOrGuest($request);

        if (!$cart) {
            throw new CartException(__('cart.cart_not_found'));
        }

        $cartItem = $cart->items()->find($itemId);
        if (!$cartItem) {
            throw new CartException(__('cart.item_not_found'));
        }

        $cartItem->delete();

        return response()->json([
            'success' => true,
            'message' => __('cart.item_removed')
        ]);
    }
}
