# API Reference

**Base URL**：`http://localhost:8000/api`
**Content-Type**：`application/json`（檔案上傳用 `multipart/form-data`）
**語言切換**：`Accept-Language: zh-TW` 或 `Accept-Language: en`

---

## 目錄

1. [認證機制](#1-認證機制)
2. [統一回應格式](#2-統一回應格式)
3. [認證 Auth](#3-認證-auth)
4. [商品 Product](#4-商品-product)
5. [購物車 Cart](#5-購物車-cart)
6. [訂單 Order](#6-訂單-order)
   - [GET `/admin/orders`](#get-adminorders--後台訂單列表-)
   - [POST `/order`](#post-order--建立訂單)
   - [GET `/orders/{payment_trade_no}/result`](#get-orderspayment_trade_noresult--查詢付款完成訂單)
   - [POST `/orders/{order_no}/checkout`](#post-ordersorder_nocheckout--前往付款)
7. [付款 Payment](#7-付款-payment)
8. [文章 Post](#8-文章-post)
9. [檔案上傳 Upload](#9-檔案上傳-upload)

---

## 1. 認證機制

### 1.1 登入後的請求

登入 / 註冊成功後，後端回傳 `token`，前端需在每次需驗證的請求中帶入：

```
Authorization: Bearer <token>
```

### 1.2 訪客購物車

未登入的訪客以 UUID 作為身份識別，前端需自行生成並持久化（localStorage / cookie）：

```
X-Guest-Token: 550e8400-e29b-41d4-a716-446655440000
```

> `X-Guest-Token` 必須是合法的 UUID v4 格式，否則後端回傳 400。
> 加入購物車（`POST /cart/items`）時此 header 為**必填**；查詢 / 更新 / 刪除時，未登入同樣需要帶入。

---

## 2. 統一回應格式

### 成功

```json
{
  "success": true,
  "message": "操作成功",
  "data": { ... }
}
```

### 驗證失敗（422）

```json
{
  "message": "The email field is required.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

### 一般錯誤（4xx / 5xx）

```json
{
  "success": false,
  "message": "錯誤說明"
}
```

### 分頁資料結構

所有列表型資料統一包在 `data` 內，格式如下：

```json
{
  "success": true,
  "message": "...",
  "data": {
    "current_page": 1,
    "data": [ ... ],
    "last_page": 5,
    "per_page": 10,
    "total": 48,
    "next_page_url": "http://localhost:8000/api/products?page=2",
    "prev_page_url": null
  }
}
```

---

## 3. 認證 Auth

### POST `/register` — 註冊

**Request**

```json
{
  "name": "王小明",
  "email": "user@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

| 欄位 | 型別 | 必填 | 說明 |
|------|------|------|------|
| `name` | string | 是 | 最多 255 字元 |
| `email` | string | 是 | 格式驗證，不可重複 |
| `password` | string | 是 | 最少 8 字元 |
| `password_confirmation` | string | 是 | 須與 password 一致 |

**Response 200**

```json
{
  "user": {
    "id": 1,
    "name": "王小明",
    "email": "user@example.com",
    "created_at": "2026-04-08T10:00:00.000000Z",
    "updated_at": "2026-04-08T10:00:00.000000Z"
  },
  "token": "1|abcdefghijk..."
}
```

---

### POST `/login` — 登入

**Request**

```json
{
  "email": "user@example.com",
  "password": "password123"
}
```

**Response 200**

```json
{
  "user": {
    "id": 1,
    "name": "王小明",
    "email": "user@example.com"
  },
  "token": "2|abcdefghijk..."
}
```

**錯誤 422** — 帳號或密碼錯誤

```json
{
  "message": "The provided credentials are incorrect.",
  "errors": {
    "email": ["The provided credentials are incorrect."]
  }
}
```

---

### POST `/logout` — 登出 

撤銷當前使用的 token。

**Response 200**

```json
{ "message": "Logged out" }
```

---

### GET `/me` — 取得目前使用者 

**Response 200**

```json
{
  "id": 1,
  "name": "王小明",
  "email": "user@example.com",
  "email_verified_at": null,
  "created_at": "2026-04-08T10:00:00.000000Z",
  "updated_at": "2026-04-08T10:00:00.000000Z"
}
```

---

### POST `/forgot-password` — 寄送密碼重設信

**Request**

```json
{ "email": "user@example.com" }
```

**Response 200**

```json
{ "message": "Reset link sent" }
```

---

### POST `/reset-password` — 重設密碼（使用信件中的 token）

**Request**

```json
{
  "token": "信件中的 token 字串",
  "email": "user@example.com",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

**Response 200**

```json
{ "message": "Password reset successfully" }
```

**錯誤 500** — token 無效或 email 不符

```json
{ "message": "Invalid token or email" }
```

---

### POST `/change-password` — 更換密碼 

**Request**

```json
{
  "current_password": "oldpassword123",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

**Response 200**

```json
{ "message": "Password changed successfully" }
```

**錯誤 403** — 舊密碼錯誤

```json
{ "message": "Current password is incorrect" }
```

---

## 4. 商品 Product

### GET `/products` — 商品列表（前台）

僅回傳 `status = active` 的商品。

**Query Parameters**

| 參數 | 型別 | 預設 | 說明 |
|------|------|------|------|
| `product_type` | string | - | `physical` \| `digital` |
| `per_page` | integer | 10 | 每頁筆數，最大 50 |
| `page` | integer | 1 | 頁碼 |

**Response 200**

```json
{
  "success": true,
  "message": "...",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 3,
        "title": "JavaScript 入門",
        "price": "300.00",
        "special_price": "250.00",
        "product_type": "physical",
        "is_favorites": true,
        "status": "active",
        "images": [
          {
            "id": 7,
            "product_id": 3,
            "image_url": "https://cdn.firelifedev.com/products/product_3_abc123.jpg",
            "sort_order": 0
          }
        ]
      }
    ],
    "last_page": 3,
    "per_page": 10,
    "total": 28
  }
}
```

> `special_price` 為 `null` 時表示無特價。
> `images` 依 `sort_order` 升冪排列，`[0]` 為主圖。

---

### GET `/products/{id}` — 商品詳情（前台）

**Response 200**

```json
{
  "success": true,
  "message": "...",
  "data": {
    "id": 3,
    "title": "JavaScript 入門",
    "description": "適合初學者的完整入門書籍",
    "price": "300.00",
    "special_price": "250.00",
    "product_type": "physical",
    "stock": 15,
    "is_favorites": true,
    "status": "active",
    "created_at": "2026-04-01T08:00:00.000000Z",
    "updated_at": "2026-04-08T10:00:00.000000Z",
    "images": [
      {
        "id": 7,
        "product_id": 3,
        "image_url": "https://cdn.firelifedev.com/products/product_3_abc123.jpg",
        "sort_order": 0
      }
    ]
  }
}
```

**錯誤 404** — 找不到商品，Laravel 預設回傳 JSON 例外。

---

### GET `/admin/products` — 後台商品列表 

回傳所有狀態的商品（含 `inactive`），欄位完整。

**Response 200** — 結構同分頁格式，`data.data` 陣列中每筆商品包含全部欄位。

---

### POST `/admin/products` — 建立商品 

**Content-Type**：`multipart/form-data`

| 欄位 | 型別 | 必填 | 說明 |
|------|------|------|------|
| `title` | string | 是 | 最多 255 字元 |
| `description` | string | - | |
| `product_type` | string | 是 | `physical` \| `digital` |
| `stock` | integer | - | 最小 0；數位商品可不傳 |
| `price` | number | 是 | 最小 0 |
| `special_price` | number | - | 最小 0 |
| `status` | string | 是 | `active` \| `inactive` |
| `is_favorites` | boolean | - | 預設 false |
| `images[]` | file | - | 圖片檔，每張最大 5MB，可多張 |

**Response 200**

```json
{
  "success": true,
  "message": "...",
  "product": {
    "id": 10,
    "title": "新書名稱",
    "images": [ ... ]
  }
}
```

---

### PATCH `/admin/products/{id}` — 更新商品 

**Content-Type**：`multipart/form-data`

欄位與建立商品相同，另外新增：

| 欄位 | 型別 | 說明 |
|------|------|------|
| `remove_image_ids[]` | integer[] | 要刪除的圖片 ID 陣列 |
| `images[]` | file | 新增的圖片（可多張） |

> 刪圖與新增圖可同時操作。更新後圖片 `sort_order` 自動重排（從 0 開始）。

**Response 200**

```json
{
  "success": true,
  "message": "...",
  "product": {
    "id": 10,
    "title": "更新後書名",
    "images": [ ... ]
  }
}
```

---

### DELETE `/admin/products/{id}` — 刪除商品 

**Response 200**

```json
{
  "success": true,
  "message": "..."
}
```

---

## 5. 購物車 Cart

> 所有購物車 API 皆支援「已登入會員」與「訪客」兩種模式。
> 已登入：帶 `Authorization: Bearer <token>`，後端以 `user_id` 識別。
> 未登入：帶 `X-Guest-Token: <uuid>`，後端以 UUID 識別。

---

### GET `/cart` — 取得購物車

**Response 200**

```json
{
  "success": true,
  "message": "...",
  "data": {
    "id": 5,
    "guest_token": "550e8400-e29b-41d4-a716-446655440000",
    "items": [
      {
        "id": 12,
        "product_id": 3,
        "quantity": 2,
        "price": 300.00,
        "subtotal": 600.00,
        "product": {
          "id": 3,
          "name": "JavaScript 入門",
          "image": [
            {
              "id": 7,
              "product_id": 3,
              "image_url": "https://cdn.firelifedev.com/products/product_3_abc123.jpg",
              "sort_order": 0
            }
          ]
        }
      }
    ],
    "total": 600.00
  }
}
```

> 已登入的會員購物車 `guest_token` 為 `null`。

---

### POST `/cart/items` — 加入商品至購物車

> 訪客必須帶 `X-Guest-Token` header（UUID 格式）。
> 若該商品已在購物車中，**數量累加**而非新增一筆。
> 商品售價在加入時快照，不隨後續改價變動。

**Request**

```json
{
  "product_id": 3,
  "quantity": 1
}
```

| 欄位 | 型別 | 必填 | 說明 |
|------|------|------|------|
| `product_id` | integer | 是 | 必須存在於 products 表 |
| `quantity` | integer | 是 | 最小 1 |

**Response 200**

```json
{
  "success": true,
  "message": "...",
  "data": {
    "id": 12,
    "cart_id": 5,
    "product_id": 3,
    "quantity": 2,
    "price": "300.00",
    "created_at": "2026-04-08T10:00:00.000000Z",
    "updated_at": "2026-04-08T10:05:00.000000Z"
  }
}
```

**錯誤 400** — `X-Guest-Token` 格式非 UUID

```json
{
  "success": false,
  "message": "..."
}
```

**錯誤 404** — 找不到訪客購物車

---

### PATCH `/cart/items/{id}` — 更新購物車項目數量

**Request**

```json
{ "quantity": 3 }
```

| 欄位 | 型別 | 必填 | 說明 |
|------|------|------|------|
| `quantity` | integer | 是 | 最小 1 |

**Response 200**

```json
{
  "success": true,
  "message": "...",
  "data": {
    "id": 12,
    "cart_id": 5,
    "product_id": 3,
    "quantity": 3,
    "price": "300.00"
  }
}
```

---

### DELETE `/cart/items/{id}` — 移除購物車項目

**Response 200**

```json
{
  "success": true,
  "message": "..."
}
```

---

## 6. 訂單 Order

### GET `/admin/orders` — 後台訂單列表 

回傳所有訂單（含 `order_items`），依建立時間降冪，預設每頁 15 筆。

**Response 200**

```json
{
  "success": true,
  "message": "訂單查詢成功。",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 20,
        "order_no": "ORD-20260409123456-ABCDEF",
        "payment_trade_no": "EC20260409123456789",
        "ecpay_trade_no": "1809261503338172",
        "buyer_name": "王小明",
        "buyer_email": "user@example.com",
        "buyer_phone": "0912345678",
        "total_amount": 900,
        "status": "paid",
        "paid_at": "2026-04-09T10:30:00.000000Z",
        "store_info": { "store_id": "0001234", "store_name": "7-ELEVEN 信義門市" },
        "items_snapshot": [...],
        "created_at": "2026-04-09T10:00:00.000000Z",
        "items": [
          {
            "id": 1,
            "order_id": 20,
            "product_id": 3,
            "delivery_type": "physical",
            "price": "300.00",
            "quantity": 2,
            "subtotal": "600.00"
          }
        ]
      }
    ],
    "last_page": 3,
    "per_page": 15,
    "total": 42
  }
}
```

---

### POST `/order` — 建立訂單

可不登入下單（訪客訂單 `user_id` 為 null）。
後端在 DB transaction 中完成：庫存快照 → 建立 `Order` → 批次寫入 `OrderItem`。

**Request**

```json
{
  "buyer_name": "王小明",
  "buyer_email": "user@example.com",
  "buyer_phone": "0912345678",
  "items": [
    { "product_id": 3, "quantity": 2 },
    { "product_id": 7, "quantity": 1 }
  ],
  "store_info": {
    "store_id": "0001234",
    "store_name": "7-ELEVEN 信義門市"
  }
}
```

| 欄位 | 型別 | 必填 | 說明 |
|------|------|------|------|
| `buyer_name` | string | 是 | 最多 100 字元 |
| `buyer_email` | string | 是 | Email 格式 |
| `buyer_phone` | string | - | 最多 20 字元 |
| `items` | array | 是 | 至少 1 筆 |
| `items[].product_id` | integer | 是 | 必須存在於 products 表 |
| `items[].quantity` | integer | 是 | 最小 1 |
| `store_info` | object | - | 訂單含實體商品時必填 |
| `store_info.store_id` | string | - | 超商門市代號 |
| `store_info.store_name` | string | - | 超商門市名稱 |

**Response 201**

```json
{
  "message": "Order created",
  "data": {
    "id": 20,
    "order_no": "ORD-20260408123456-ABCDEF",
    "total_amount": 900,
    "status": "pending",
    "items_snapshot": [
      {
        "product_id": 3,
        "title": "JavaScript 入門",
        "price": 300,
        "quantity": 2,
        "type": "physical"
      },
      {
        "product_id": 7,
        "title": "Node.js 電子書",
        "price": 300,
        "quantity": 1,
        "type": "digital"
      }
    ]
  }
}
```

> `order_no` 用於後續呼叫 checkout，請前端妥善保存。

---

### POST `/orders/{order_no}/checkout` — 前往付款

>  此端點回傳的**不是 JSON**，而是一段 HTML（`Content-Type: text/html`）。
> HTML 內含一個會自動 submit 的 `<form>`，瀏覽器執行後會跳轉至綠界付款頁面。

**前端處理方式**

```javascript
// 方法一：以 fetch 取得 HTML 後注入頁面，瀏覽器會自動 submit form
const res = await fetch(`/api/orders/${orderNo}/checkout`, { method: 'POST' });
const html = await res.text();
document.open();
document.write(html);
document.close();

// 方法二：將 API URL 設為 <form action>，讓使用者點擊後直接 POST 跳轉
```

**錯誤 400** — 訂單狀態非 `pending`（已付款或重複送出）

```json
{ "message": "訂單已付款或不可付款" }
```

**錯誤 404** — `order_no` 不存在

---

### GET `/orders/{payment_trade_no}/result` — 查詢付款完成訂單

ECPay 付款成功後，前端從跳轉 URL 取得 `payment_trade_no`，呼叫此端點取得訂單與買家資訊以顯示購買完成頁。

**Response 200**

```json
{
  "success": true,
  "message": "Order retrieved successfully.",
  "data": {
    "order_no": "ORD-20260409123456-ABCDEF",
    "status": "paid",
    "paid_at": "2026-04-09T10:30:00.000000Z",
    "total_amount": 900,
    "buyer_name": "王小明",
    "buyer_email": "user@example.com",
    "buyer_phone": "0912345678",
    "store_info": {
      "store_id": "0001234",
      "store_name": "7-ELEVEN 信義門市"
    },
    "items_snapshot": [
      {
        "product_id": 3,
        "title": "JavaScript 入門",
        "price": 300,
        "quantity": 2,
        "type": "physical"
      }
    ]
  }
}
```

**錯誤 404** — `payment_trade_no` 不存在

```json
{ "success": false, "message": "找不到訂單。" }
```

**錯誤 400** — 訂單狀態非 `paid`（尚未付款）

```json
{ "success": false, "message": "訂單尚未完成付款。" }
```

> `store_info` 僅在訂單含實體商品時有值，否則為 `null`。

---

## 7. 付款 Payment

### POST `/payments/ecpay/callback` — ECPay 非同步付款通知（ReturnURL）

> 此端點由**綠界伺服器**呼叫（伺服器對伺服器），前端**不需要**也**不應該**直接呼叫此 API。
> 驗證 `CheckMacValue`，付款成功時將訂單狀態更新為 `paid`。

---

### POST `/payments/ecpay/result` — ECPay 付款結果導向（OrderResultURL）

> 此端點由綠界帶著**用戶瀏覽器**以 POST 跳轉至此，前端**不需要**直接呼叫。
> 後端解析付款結果後，302 redirect 至前端購買完成頁。

**完整付款流程**

```
綠界付款完成
  ├─ ReturnURL (server-to-server)
  │    POST /api/payments/ecpay/callback
  │    → 驗簽 → 更新 order.status = paid
  │
  └─ OrderResultURL (瀏覽器跳轉)
       POST /api/payments/ecpay/result
       → 解析 ResultData → 302 redirect
       → {ECPAY_ORDER_RESULT_URL}/{payment_trade_no}?status=success|failed
       → 前端呼叫 GET /api/orders/{payment_trade_no}/result 顯示訂單資訊
```

**Request**（綠界 POST，`application/x-www-form-urlencoded`）

| 欄位 | 說明 |
|------|------|
| `ResultData` | JSON 字串，包含付款結果 |

```json
{
  "MerchantID": "3002607",
  "RpHeader": { "Timestamp": 1234564848 },
  "TransCode": 1,
  "TransMsg": "Success",
  "Data": "{\"RtnCode\":1,\"OrderInfo\":{\"MerchantTradeNo\":\"EC20260409123456789\", ...}}"
}
```

**Response** — 302 Redirect

| 情況 | 跳轉目標 |
|------|---------|
| 付款成功 | `{ECPAY_ORDER_RESULT_URL}/{payment_trade_no}?status=success` |
| 付款失敗 | `{ECPAY_ORDER_RESULT_URL}/{payment_trade_no}?status=failed` |
| 無法解析 | `{ECPAY_ORDER_RESULT_URL}?error=invalid` |

**Env 設定**

| 變數 | 說明 | 範例 |
|------|------|------|
| `ECPAY_RESULT_URL` | 本端點的完整 URL（填入綠界後台 OrderResultURL） | `https://api.example.com/api/payments/ecpay/result` |
| `ECPAY_ORDER_RESULT_URL` | 前端購買完成頁路徑 | `https://frontend.example.com/payment/result` |

---

## 8. 文章 Post

### GET `/posts` — 文章列表

**Response 200**

```json
{
  "success": true,
  "message": "...",
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "title": "如何挑選二手書",
        "slug": "how-to-choose-used-books",
        "meta_description": "完整指南帶你找到好書",
        "content": "...",
        "created_at": "2026-04-01T08:00:00.000000Z",
        "updated_at": "2026-04-01T08:00:00.000000Z"
      }
    ],
    "last_page": 2,
    "per_page": 10,
    "total": 15
  }
}
```

---

### GET `/posts/{id}` — 文章詳情

**Response 200**

```json
{
  "success": true,
  "message": "...",
  "data": {
    "id": 1,
    "title": "如何挑選二手書",
    "slug": "how-to-choose-used-books",
    "meta_description": "完整指南帶你找到好書",
    "content": "完整文章內容...",
    "created_at": "2026-04-01T08:00:00.000000Z",
    "updated_at": "2026-04-01T08:00:00.000000Z"
  }
}
```

---

### POST `/posts` — 建立文章 

**Request**

```json
{
  "title": "如何挑選二手書",
  "slug": "how-to-choose-used-books",
  "metaDescription": "完整指南帶你找到好書",
  "content": "完整文章內容..."
}
```

| 欄位 | 型別 | 必填 | 說明 |
|------|------|------|------|
| `title` | string | 是 | 最多 255 字元 |
| `slug` | string | 是 | 全站唯一，最多 255 字元 |
| `metaDescription` | string | - | 最多 500 字元（注意用 camelCase） |
| `content` | string | 是 | |

**Response 201**

```json
{
  "success": true,
  "message": "文章建立成功",
  "post": { "id": 1, "title": "...", "slug": "...", ... }
}
```

---

### PATCH `/posts/{id}` — 更新文章 

所有欄位皆為**選填**（部分更新），欄位名稱與建立相同（`meta_description` 更新時用 snake_case）。

**Response 200**

```json
{
  "success": true,
  "message": "文章更新成功",
  "data": { "id": 1, "title": "更新後標題", ... }
}
```

---

### DELETE `/posts/{id}` — 刪除文章 

**Response 200**

```json
{
  "success": true,
  "message": "文章刪除成功"
}
```

---

## 9. 檔案上傳 Upload

### POST `/upload` — 上傳圖片 

**Content-Type**：`multipart/form-data`

| 欄位 | 型別 | 必填 | 說明 |
|------|------|------|------|
| `image` | file | 是 | 圖片檔，最大 5MB |

**Response 200**

```json
{
  "success": true,
  "message": "Upload successful",
  "path": "original_filename.jpg",
  "url": "https://cdn.firelifedev.com/original_filename.jpg"
}
```

> 回傳的 `url` 即可直接用於顯示圖片或作為其他 API 的圖片來源。

---

## 附錄：HTTP 狀態碼速查

| 狀態碼 | 說明 |
|--------|------|
| 200 | 成功 |
| 201 | 建立成功（POST /order） |
| 400 | 請求格式錯誤（如 Guest Token 非 UUID） |
| 401 | 未提供 token 或 token 無效 |
| 403 | 有登入但無權限（如舊密碼錯誤） |
| 404 | 資源不存在 |
| 422 | 表單驗證失敗（含 `errors` 欄位） |
| 500 | 伺服器錯誤 |

## 附錄：訂單狀態說明

| 狀態 | 說明 |
|------|------|
| `pending` | 訂單已建立，等待付款 |
| `paid` | 付款成功（由 ECPay callback 觸發更新） |
