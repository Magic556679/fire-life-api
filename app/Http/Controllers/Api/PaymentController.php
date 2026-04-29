<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Product;
use Illuminate\Http\Request;
use App\Services\EcpayService;

class PaymentController extends Controller
{
    protected EcpayService $ecpay;

    public function __construct(EcpayService $ecpay)
    {
        $this->ecpay = $ecpay;
    }

    public function result(Request $request)
    {
        $payment_trade_no = null;
        $success = false;

        // 解析綠界 OrderResultURL 格式：form field ResultData 內含 JSON
        $resultDataRaw = $request->input('ResultData');

        if ($resultDataRaw) {
            $resultData = json_decode($resultDataRaw, true);
            $dataRaw = $resultData['Data'] ?? null;

            if ($dataRaw) {
                $data = json_decode($dataRaw, true);
                $payment_trade_no = $data['OrderInfo']['MerchantTradeNo'] ?? null;
                $success = ($data['RtnCode'] ?? 0) == 1;
            }
        }

        // Fallback：傳統 AIO form-encoded 格式（與 ReturnURL 相同欄位）
        if (!$payment_trade_no) {
            $payment_trade_no = $request->input('MerchantTradeNo');
            $success = $request->input('RtnCode') == 1;
        }

        $frontendUrl = rtrim(env('ECPAY_ORDER_RESULT_URL'), '/');

        if (!$payment_trade_no) {
            return redirect($frontendUrl . '?error=invalid');
        }

        return redirect($frontendUrl . '/' . $payment_trade_no . '?status=' . ($success ? 'success' : 'failed'));
    }

    public function callback(Request $request)
    {
        $data = $request->all();

        // 驗證 CheckMacValue
        if (!$this->ecpay->verifyCheckMacValue($data)) {
            return response('0|CheckMacValue error');
        }

        $order = Order::where('payment_trade_no', $data['MerchantTradeNo'])->first();

        if (!$order) {
            return response('0|Order not found');
        }

        // 驗證付款成功且尚未更新過 TradeNo（避免重複付款）
        if ($data['RtnCode'] == 1 && !$order->ecpay_trade_no) {
            $order->update([
                'status' => 'paid',
                'paid_at' => now(),
                'ecpay_trade_no' => $data['TradeNo'],
            ]);

            // 扣減實體商品庫存（付款確認後才扣）
            $productIds = collect($order->items_snapshot)
                ->where('type', '!=', 'digital')
                ->pluck('product_id');

            if ($productIds->isNotEmpty()) {
                foreach ($order->items_snapshot as $item) {
                    if (($item['type'] ?? '') === 'digital') {
                        continue;
                    }
                    Product::where('id', $item['product_id'])
                        ->decrement('stock', $item['quantity']);
                }
            }

            if ($order->user_id) {
                Cart::where('user_id', $order->user_id)->first()?->items()->delete();
            } elseif ($order->guest_token) {
                Cart::where('guest_token', $order->guest_token)->first()?->items()->delete();
            }
        }

        return response('1|OK');
    }
}
