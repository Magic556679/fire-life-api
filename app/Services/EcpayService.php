<?php

namespace App\Services;

use Carbon\Carbon;

class EcpayService
{
    protected string $merchantID;
    protected string $hashKey;
    protected string $hashIV;
    protected string $returnURL;
    protected string $orderResultURL;
    protected string $paymentURL;

    public function __construct()
    {
        $this->merchantID = env('ECPAY_MERCHANT_ID');
        $this->hashKey = env('ECPAY_HASH_KEY');
        $this->hashIV = env('ECPAY_HASH_IV');
        $this->returnURL = env('ECPAY_RETURN_URL');           // 後端 callback
        // $this->orderResultURL = env('FRONTEND_URL') . '/payment/result'; // 導回前端頁
        // $this->paymentURL = 'https://payment.ecpay.com.tw/Cashier/AioCheckOut/V5'; // 後端傳給前端 submit
        $this->paymentURL = 'https://payment-stage.ecpay.com.tw/Cashier/AioCheckOut/V5';
    }

    /**
     * 生成付款表單
     *
     * @param string $orderId
     * @param int $amount
     * @param string $items
     * @return string HTML form
     */
    public function generateCheckoutForm(string $orderId, int $amount, string $items): string
    {
        $data = [
            'MerchantID' => $this->merchantID,
            'MerchantTradeNo' => $orderId,
            'MerchantTradeDate' => Carbon::now()->format('Y/m/d H:i:s'),
            'PaymentType' => 'aio',
            'TotalAmount' => $amount,
            'TradeDesc' => '購買二手書/電子書',
            'ItemName' => $items,
            'ReturnURL' => $this->returnURL,
            // 'OrderResultURL' => $this->orderResultURL,
            'ChoosePayment' => 'Credit', // 信用卡及銀聯卡(需申請開通)
        ];

        // 計算 CheckMacValue
        $data['CheckMacValue'] = $this->generateCheckMacValue($data);

        $form = '<form id="ecpay-form" method="post" action="' . $this->paymentURL . '">';
        foreach ($data as $key => $value) {
            $form .= '<input type="hidden" name="' . $key . '" value="' . $value . '">';
        }
        $form .= '</form>';
        $form .= '<script>document.getElementById("ecpay-form").submit();</script>';

        return $form;
    }

    /**
     * 計算綠界 CheckMacValue
     */
    protected function generateCheckMacValue(array $params): string
    {
        ksort($params);
        $str = 'HashKey=' . $this->hashKey;
        foreach ($params as $k => $v) {
            $str .= "&$k=$v";
        }
        $str .= '&HashIV=' . $this->hashIV;

        // URL encode
        $str = strtolower(urlencode($str));

        // replace
        $str = str_replace(['%2d', '%5f', '%2e', '%21', '%2a', '%28', '%29'], ['-', '_', '.', '!', '*', '(', ')'], $str);

        return strtoupper(md5($str));
    }

    public function verifyCheckMacValue(array $params): bool
    {
        $checkMacValue = $params['CheckMacValue'] ?? null;

        if (!$checkMacValue) {
            return false;
        }

        // 移除原本的 CheckMacValue
        unset($params['CheckMacValue']);

        $generated = $this->generateCheckMacValue($params);

        return $generated === $checkMacValue;
    }
}
