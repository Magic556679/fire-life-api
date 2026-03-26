<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOrderRequest;
use App\Models\Order;
use App\Models\Product;
use App\Models\OrderItem;
use App\Services\EcpayService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;


class OrderController extends Controller
{
    protected EcpayService $ecpay;

    public function __construct(EcpayService $ecpay)
    {
        $this->ecpay = $ecpay;
    }

    public function store(StoreOrderRequest $request)
    {
        return DB::transaction(function () use ($request) {

            // 一次撈出所有商品，避免 N+1
            $productIds = collect($request->items)
                ->pluck('product_id')
                ->unique();

            $products = Product::whereIn('id', $productIds)
                ->get()
                ->keyBy('id');

            // 組 items_snapshot
            $itemsSnapshot = collect($request->items)->map(function ($item) use ($products) {
                $product = $products[$item['product_id']];
                return [
                    'product_id' => $product->id,
                    'title'      => $product->title,
                    'price'      => $product->price,
                    'quantity'   => $item['quantity'],
                    'type'       => $product->type,
                ];
            })->toArray();

            // 計算總金額
            $totalAmount = collect($request->items)->reduce(function ($carry, $item) use ($products) {
                $product = $products[$item['product_id']];
                return $carry + ($product->price * $item['quantity']);
            }, 0);

            // 建立訂單（包含快照）
            $order = Order::create([
                'user_id'        => $request->user()?->id ?? null,
                'order_no'       => $this->generateOrderNo(),
                'payment_trade_no' => $this->generatePaymentTradeNo(),
                'buyer_name'     => $request->buyer_name,
                'buyer_email'    => $request->buyer_email,
                'buyer_phone'    => $request->buyer_phone,
                'items_snapshot' => $itemsSnapshot,
                // 'delivery_type'  => $request->delivery_type,
                // 'store_info'     => $request->delivery_type === 'physical' ? $request->store_info : null,
                'store_info'     => collect($request->items)->contains(function ($item) use ($products) {
                    $product = $products[$item['product_id']];
                    return $product->type === 'physical';
                }) ? $request->store_info : null, // 如果有實體商品才存
                'total_amount'   => $totalAmount,
                'status'         => 'pending',
            ]);

            // 組 order_items（批次寫入）
            $orderItems = collect($request->items)->map(function ($item) use ($products, $order) {
                $product = $products[$item['product_id']];
                $subtotal = $product->price * $item['quantity'];
                return [
                    'order_id'   => $order->id,
                    'product_id' => $product->id,
                    'delivery_type' => $product->type === 'physical' ? 'physical' : 'digital',
                    'price'      => $product->price,
                    'quantity'   => $item['quantity'],
                    'subtotal'   => $subtotal,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            })->toArray();

            OrderItem::insert($orderItems);

            return response()->json([
                'message' => 'Order created',
                'data' => [
                    'id'           => $order->id,
                    'order_no'     => $order->order_no,
                    'total_amount' => $order->total_amount,
                    'status'       => $order->status,
                    'items_snapshot' => $order->items_snapshot, // 直接回傳快照
                ],
            ], 201);
        });
    }

    private function generateOrderNo(): string
    {
        return 'ORD-' . now()->format('YmdHis') . '-' . Str::upper(Str::random(6));
    }

    private function generatePaymentTradeNo(): string
    {
        return 'EC' . now()->format('YmdHis') . random_int(1000, 9999);
    }

    public function checkout($order_no)
    {
        $order = Order::where('order_no', $order_no)->firstOrFail();


        if ($order->status !== 'pending') {
            return response()->json([
                'message' => '訂單已付款或不可付款'
            ], 400);
        }

        // 使用 snapshot 組商品名稱
        $itemNames = collect($order->items_snapshot)
            ->map(fn($item) => $item['title'] . ' x ' . $item['quantity'])
            ->implode('#');

        $itemNames = Str::limit($itemNames, 380);

        $form = $this->ecpay->generateCheckoutForm(
            $order->payment_trade_no,
            $order->total_amount,
            $itemNames
        );

        return response($form)->header('Content-Type', 'text/html');
    }
}
