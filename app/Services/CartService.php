<?php

namespace App\Services;

use App\Models\Cart;
use App\Exceptions\CartException;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CartService
{
    /**
     * Create a new class instance.
     */
    public function __construct()
    {
        //
    }

    /**
     * 決定要抓「會員購物車」或「訪客購物車」
     */
    public function getCartForUserOrGuest(Request $request): Cart
    {
        // 檢查是否為會員
        if ($request->user()) {
            return Cart::firstOrCreate([
                'user_id' => $request->user()->id,
            ]);
        }

        // 目前：訪客購物車
        $guestToken = $request->header('X-Guest-Token');

        if (!$guestToken) {
            $guestToken = Str::uuid()->toString();
        }

        // firstOrCreate 會自動創建或抓取
        $cart = Cart::firstOrCreate([
            'guest_token' => $guestToken,
        ]);

        return $cart;
    }

    /**
     * 檢查 GuestToken
     */
    public function getCartByGuestToken(string $guestToken): Cart
    {

        if (!Str::isUuid($guestToken)) {
            throw new CartException(__('cart.invalid_guest_token'), 400);
        }

        $cart = Cart::where('guest_token', $guestToken)->first();

        if (!$cart) {
            throw new CartException(__('cart.guest_cart_not_found'), 404);
        }

        return $cart;
    }
}
