<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use App\Services\EcpayService;

class PaymentController extends Controller
{
    protected EcpayService $ecpay;

    public function __construct(EcpayService $ecpay)
    {
        $this->ecpay = $ecpay;
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
                'ecpay_trade_no' => $data['TradeNo'], // 存綠界交易編號
            ]);
        }

        return response('1|OK');
    }
}
