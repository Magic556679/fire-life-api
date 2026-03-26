<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'buyer_name'  => ['required', 'string', 'max:100'],
            'buyer_email' => ['required', 'email'],
            'buyer_phone' => ['nullable', 'string', 'max:20'],

            'items'               => ['required', 'array', 'min:1'],
            'items.*.product_id'  => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity'    => ['required', 'integer', 'min:1'],

            'store_info' => ['array'],
            'store_info.store_id'   => ['string'],
            'store_info.store_name' => ['string'],
        ];
    }
}
