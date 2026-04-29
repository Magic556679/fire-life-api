# Backend Software Design Document

**專案**：Fire Life API
**框架**：Laravel 11 / PHP 8.2
**驗證**：Laravel Sanctum（Personal Access Token）
**儲存**：Cloudflare R2（S3 相容）
**金流**：綠界科技 ECPay

---

## 目錄

1. [功能模組總覽](#1-功能模組總覽)
2. [API 設計](#2-api-設計)
3. [資料模型](#3-資料模型)
4. [服務層](#4-服務層)
5. [業務流程](#5-業務流程)
6. [統一回應格式](#6-統一回應格式)

---

## 1. 功能模組總覽

| 模組 | 描述 | 負責檔案 |
|------|------|---------|
| **Auth** | 註冊、登入、密碼管理、Token 撤銷 | `AuthController` |
| **Product** | 前台商品列表 / 詳情、後台 CRUD + 圖片管理 | `ProductController`, `ProductImageController` |
| **Cart** | 購物車查詢（支援會員 & 訪客） | `CartController`, `CartService` |
| **CartItem** | 加入 / 更新 / 刪除購物車項目 | `CartItemController` |
| **Order** | 建立訂單、產生 ECPay 付款表單 | `OrderController`, `EcpayService` |
| **Payment** | 接收 ECPay 非同步付款回調 | `PaymentController`, `EcpayService` |
| **Post** | 部落格文章 CRUD | `PostController` |
| **Upload** | 單張圖片上傳至 R2 | `UploadController` |

---

## 2. API 設計

> 所有路由皆掛在 `/api` 前綴下（定義於 `bootstrap/app.php`）。
> 需驗證的端點須攜帶 `Authorization: Bearer <token>` header。

### 2.1 認證模組 `/api`

| Method | Path | 驗證 | 描述 |
|--------|------|------|------|
| POST | `/register` | 否 | 註冊並回傳 token |
| POST | `/login` | 否 | 登入並回傳 token |
| POST | `/forgot-password` | 否 | 發送密碼重設信 |
| POST | `/reset-password` | 否 | 以 token 重設密碼 |
| POST | `/logout` | 是 | 撤銷當前 token |
| POST | `/logout-all` | 是 | 清空全站所有 token（`truncate`） |
| GET | `/me` | 是 | 取得目前登入使用者資訊 |
| POST | `/change-password` | 是 | 更換密碼（需輸入舊密碼） |

**POST `/register`**
```
Request:  { name, email, password, password_confirmation }
Response: { user, token }
```

**POST `/login`**
```
Request:  { email, password }
Response: { user, token }
```

**POST `/reset-password`**
```
Request:  { token, email, password, password_confirmation }
Response: { message }
```

---

### 2.2 商品模組 `/api/products`

#### 前台（公開）

| Method | Path | 驗證 | 描述 |
|--------|------|------|------|
| GET | `/products` | 否 | 商品列表（僅 `status=active`） |
| GET | `/products/{id}` | 否 | 商品詳情 |

**GET `/products`** 查詢參數：

| 參數 | 型別 | 說明 |
|------|------|------|
| `product_type` | string | `physical` \| `digital` |
| `per_page` | integer | 每頁筆數，最大 50，預設 10 |

前台回傳欄位限制為：`id, title, price, special_price, product_type, is_favorites, status, images`。

#### 後台（需驗證）

| Method | Path | 驗證 | 描述 |
|--------|------|------|------|
| GET | `/admin/products` | 是 | 後台商品列表（含所有欄位 + 分頁） |
| POST | `/admin/products` | 是 | 建立商品 + 多張圖片（multipart/form-data） |
| PATCH | `/admin/products/{id}` | 是 | 更新商品資料、新增 / 刪除圖片 |
| DELETE | `/admin/products/{id}` | 是 | 刪除商品（連帶刪除 R2 圖片） |

**POST/PATCH `/admin/products`** 欄位：

| 欄位 | 規則 | 說明 |
|------|------|------|
| `title` | required, max:255 | 商品名稱 |
| `description` | nullable | 商品描述 |
| `product_type` | required, `physical`\|`digital` | 商品類型 |
| `stock` | nullable, integer≥0 | 庫存（數位商品可為 null） |
| `price` | required, numeric≥0 | 售價 |
| `special_price` | nullable, numeric≥0 | 特價 |
| `status` | required, `active`\|`inactive` | 上下架 |
| `is_favorites` | boolean | 精選推薦 |
| `images[]` | image, max:5120KB | 多張圖片（新增時） |
| `remove_image_ids[]` | array | 要刪除的圖片 ID（更新時） |

> 圖片上傳至 R2 `products/` 資料夾，使用 `product_{id}_{uuid}.{ext}` 命名。
> 更新後會自動重新整理 `sort_order`。

---

### 2.3 購物車模組 `/api/cart`

| Method | Path | 驗證 | 描述 |
|--------|------|------|------|
| GET | `/cart` | 否（支援訪客） | 取得購物車及明細 |
| POST | `/cart/items` | 否（需訪客 token） | 加入商品 |
| PATCH | `/cart/items/{id}` | 否（支援訪客） | 更新數量 |
| DELETE | `/cart/items/{id}` | 否（支援訪客） | 移除項目 |

**身份識別策略**：

- 已登入：以 `user_id` 識別購物車（`Authorization` header）
- 訪客：以 `X-Guest-Token` header 傳入 UUID 識別購物車

**GET `/cart`** 回應：
```json
{
  "success": true,
  "data": {
    "id": 1,
    "guest_token": "uuid",
    "items": [
      {
        "id": 1,
        "product_id": 5,
        "quantity": 2,
        "price": 150.00,
        "subtotal": 300.00,
        "product": { "id": 5, "name": "書名", "image": [...] }
      }
    ],
    "total": 300.00
  }
}
```

**POST `/cart/items`** 欄位：

| 欄位 | 規則 | 說明 |
|------|------|------|
| `product_id` | required, exists:products | 商品 ID |
| `quantity` | required, integer≥1 | 數量 |
| `X-Guest-Token` | header, UUID | 訪客身份識別（必須） |

> 若商品已在購物車中，數量累加而非新增。價格於加入時以當前商品售價快照。

---

### 2.4 訂單模組 `/api/order`

| Method | Path | 驗證 | 描述 |
|--------|------|------|------|
| POST | `/order` | 否（支援訪客） | 建立訂單 |
| GET | `/orders/{payment_trade_no}/result` | 否 | 以 payment_trade_no 查詢已付款訂單 |
| POST | `/orders/{order_no}/checkout` | 否 | 產生 ECPay 付款表單 |
| GET | `/admin/orders` | 是 | 後台訂單列表（含 order_items + 分頁） |

**POST `/order`** 欄位（`StoreOrderRequest`）：

| 欄位 | 規則 | 說明 |
|------|------|------|
| `buyer_name` | required, max:100 | 買家姓名 |
| `buyer_email` | required, email | 買家 Email |
| `buyer_phone` | nullable, max:20 | 買家電話 |
| `items` | required, array≥1 | 訂購項目陣列 |
| `items[].product_id` | required, exists:products | 商品 ID |
| `items[].quantity` | required, integer≥1 | 數量 |
| `store_info` | array | 超商取貨資訊（含實體商品時） |
| `store_info.store_id` | string | 超商門市代號 |
| `store_info.store_name` | string | 超商門市名稱 |

**POST `/order`** 回應（201）：
```json
{
  "message": "Order created",
  "data": {
    "id": 1,
    "order_no": "ORD-20260408123456-ABCDEF",
    "total_amount": 500,
    "status": "pending",
    "items_snapshot": [...]
  }
}
```

**GET `/orders/{payment_trade_no}/result`**：
- `payment_trade_no` 不存在 → 404
- 訂單狀態非 `paid` → 400
- 成功 → 回傳買家資訊、訂單資訊、`items_snapshot`、`store_info`

> ECPay 付款完成後跳轉前端，前端從 URL 取出 `payment_trade_no` 呼叫此端點顯示購買完成頁。

**POST `/orders/{order_no}/checkout`** 回應：直接回傳 HTML（`text/html`），包含自動 submit 的 ECPay 表單，瀏覽器導向綠界付款頁。

---

### 2.5 金流回調 `/api/payments`

| Method | Path | 驗證 | 描述 |
|--------|------|------|------|
| POST | `/payments/ecpay/callback` | 否（綠界伺服器呼叫） | ECPay 非同步付款通知（ReturnURL） |
| POST | `/payments/ecpay/result` | 否（綠界帶瀏覽器跳轉） | ECPay 付款結果頁（OrderResultURL） |

**`/payments/ecpay/callback`**（ReturnURL，伺服器對伺服器）：
> 綠界以 form-data 方式 POST，系統驗證 `CheckMacValue`，若 `RtnCode=1` 且訂單尚未有 `ecpay_trade_no`，則更新訂單狀態為 `paid` 並記錄 `paid_at`、`ecpay_trade_no`。
> 成功回應 `1|OK`，失敗回應 `0|<reason>`。

**`/payments/ecpay/result`**（OrderResultURL，瀏覽器跳轉）：
> 綠界以 form-encoded POST 將用戶瀏覽器帶到此端點，body 含 `ResultData`（JSON 字串）。
> 解析 `ResultData.Data.OrderInfo.MerchantTradeNo` 取得 `payment_trade_no`，依 `RtnCode` 判斷付款是否成功。
> 302 redirect 至 `ECPAY_ORDER_RESULT_URL/{payment_trade_no}?status=success|failed`（前端購買完成頁）。
> 若無法解析 `ResultData`，fallback 讀取傳統 form-encoded 欄位 `MerchantTradeNo` / `RtnCode`。

---

### 2.6 文章模組 `/api/posts`

| Method | Path | 驗證 | 描述 |
|--------|------|------|------|
| GET | `/posts` | 否 | 文章列表（10 筆 / 頁） |
| GET | `/posts/{id}` | 否 | 文章詳情 |
| POST | `/posts` | 是 | 建立文章 |
| PATCH | `/posts/{id}` | 是 | 更新文章（`sometimes` 規則，部分更新） |
| DELETE | `/posts/{id}` | 是 | 刪除文章 |

**POST `/posts`** 欄位：

| 欄位 | 規則 |
|------|------|
| `title` | required, max:255 |
| `slug` | required, unique:posts |
| `metaDescription` | nullable, max:500 |
| `content` | required |

---

### 2.7 檔案上傳 `/api/upload`

| Method | Path | 驗證 | 描述 |
|--------|------|------|------|
| POST | `/upload` | 是 | 上傳單張圖片至 R2 根目錄 |

```
Request:  multipart/form-data, image (max 5MB)
Response: { success, message, path, url }
```

---

## 3. 資料模型

### 3.1 關聯圖

```
User ─── Cart ──< CartItem >── Product ──< ProductImage
                                  │
User ──< Order ──< OrderItem >────┘
```

---

### 3.2 User

| 欄位 | 型別 | 說明 |
|------|------|------|
| `id` | bigint PK | |
| `name` | string | |
| `email` | string unique | |
| `password` | string | Bcrypt, cost=12 |
| `email_verified_at` | timestamp nullable | |
| `remember_token` | string nullable | |
| `created_at` / `updated_at` | timestamp | |

Trait：`HasApiTokens`（Sanctum）、`Notifiable`

---

### 3.3 Product

| 欄位 | 型別 | 說明 |
|------|------|------|
| `id` | bigint PK | |
| `title` | string | 商品名稱 |
| `description` | text nullable | 商品描述 |
| `product_type` | enum | `physical` \| `digital` |
| `stock` | integer nullable | 庫存（數位商品可為 null） |
| `price` | decimal | 售價 |
| `special_price` | decimal nullable | 特價 |
| `status` | enum | `active` \| `inactive` |
| `is_favorites` | boolean | 是否精選 |
| `created_at` / `updated_at` | timestamp | |

關聯：`hasMany(ProductImage)`

---

### 3.4 ProductImage

| 欄位 | 型別 | 說明 |
|------|------|------|
| `id` | bigint PK | |
| `product_id` | bigint FK | |
| `image_url` | string | R2 完整 CDN URL |
| `sort_order` | integer | 排列順序（從 0 開始） |
| `created_at` / `updated_at` | timestamp | |

---

### 3.5 Cart

| 欄位 | 型別 | 說明 |
|------|------|------|
| `id` | bigint PK | |
| `user_id` | bigint FK nullable | 會員（互斥於 guest_token） |
| `guest_token` | string nullable | 訪客 UUID |
| `created_at` / `updated_at` | timestamp | |

關聯：`hasMany(CartItem)`

---

### 3.6 CartItem

| 欄位 | 型別 | 說明 |
|------|------|------|
| `id` | bigint PK | |
| `cart_id` | bigint FK | |
| `product_id` | bigint FK | |
| `quantity` | integer | |
| `price` | decimal | 加入時快照的商品售價 |
| `created_at` / `updated_at` | timestamp | |

關聯：`belongsTo(Product)`

---

### 3.7 Order

| 欄位 | 型別 | 說明 |
|------|------|------|
| `id` | bigint PK | |
| `user_id` | bigint FK nullable | 可為訪客訂單 |
| `order_no` | string unique | `ORD-YmdHis-XXXXXX` |
| `payment_trade_no` | string | `ECYmdHisXXXX`（送給 ECPay 的交易編號） |
| `ecpay_trade_no` | string nullable | 綠界回傳的交易編號（防重複付款依據） |
| `buyer_name` | string | |
| `buyer_email` | string | |
| `buyer_phone` | string nullable | |
| `items_snapshot` | JSON | 下單當下商品資料快照 |
| `total_amount` | integer | 總金額（以元為單位） |
| `delivery_type` | string nullable | 配送類型（目前由 items 判斷） |
| `store_info` | JSON nullable | 超商取貨資訊 |
| `status` | enum | `pending` \| `paid`（其他狀態待擴展） |
| `paid_at` | timestamp nullable | 付款時間 |
| `created_at` / `updated_at` | timestamp | |

關聯：`hasMany(OrderItem)`

`items_snapshot` 結構：
```json
[
  {
    "product_id": 5,
    "title": "書名",
    "price": 150,
    "quantity": 2,
    "type": "physical"
  }
]
```

---

### 3.8 OrderItem

| 欄位 | 型別 | 說明 |
|------|------|------|
| `id` | bigint PK | |
| `order_id` | bigint FK | |
| `product_id` | bigint FK | |
| `delivery_type` | string | `physical` \| `digital` |
| `price` | decimal | 單價快照 |
| `quantity` | integer | |
| `subtotal` | decimal | |
| `created_at` / `updated_at` | timestamp | |

關聯：`belongsTo(Order)`、`belongsTo(Product)`

---

### 3.9 Post

| 欄位 | 型別 | 說明 |
|------|------|------|
| `id` | bigint PK | |
| `title` | string | |
| `slug` | string unique | URL 友善識別碼 |
| `meta_description` | string nullable | SEO 描述 |
| `content` | longtext | 文章內容 |
| `created_at` / `updated_at` | timestamp | |

---

## 4. 服務層

### 4.1 CartService

**`getCartForUserOrGuest(Request)`**
優先以 `auth:sanctum` 識別會員（`user_id`），其次讀取 `X-Guest-Token` header（UUID），若無則自動生成。以 `firstOrCreate` 確保購物車存在。

**`getCartByGuestToken(string)`**
嚴格驗證 UUID 格式，找不到則拋出 `CartException(404)`。
> 此方法僅用於 `CartItemController::store`（加入商品時）。

---

### 4.2 EcpayService

**`generateCheckoutForm(orderId, amount, items): string`**
組裝 ECPay AIO Checkout V5 必要參數（`MerchantID`、`MerchantTradeNo` 等），計算 `CheckMacValue`，輸出自動提交的 HTML `<form>`。

> 目前指向**測試環境**（`payment-stage.ecpay.com.tw`），正式上線須切換 `$paymentURL`。

**`generateCheckMacValue(array): string`**
依綠界規格：key 排序 → 組字串 → URL encode → 特殊字元替換 → md5 → 大寫。

**`verifyCheckMacValue(array): bool`**
移除收到的 `CheckMacValue` 後重新計算比對，防止偽造回調。

---

## 5. 業務流程

### 5.1 購買流程

```
[前端] 加入商品 → POST /cart/items (X-Guest-Token)
       瀏覽購物車 → GET /cart
       送出訂單   → POST /order        → 建立 Order + OrderItem（DB transaction）
       前往付款   → POST /orders/{no}/checkout → 回傳 HTML form，瀏覽器導向綠界
       付款完成   → ECPay POST /payments/ecpay/callback → 驗簽 → 更新 status=paid
```

### 5.2 訂單狀態機

```
pending → paid
```

> 目前僅有兩個狀態。`ecpay_trade_no` 作為冪等鍵：若已存在則忽略重複的付款通知。

### 5.3 商品圖片生命週期

```
建立商品 → 圖片上傳至 R2 (products/{filename}) → ProductImage 記錄 URL
更新商品 → remove_image_ids: 從 R2 刪除 + DB 刪除 → 上傳新圖片 → 重排 sort_order
刪除商品 → 逐一從 R2 刪除圖片 → cascade 刪除 ProductImage → 刪除 Product
```

---

## 6. 統一回應格式

大部分 API 回應遵循以下結構（部分舊端點如 Auth 僅回傳 `user`/`token`/`message`）：

```json
{
  "success": true,
  "message": "操作成功訊息",
  "data": { ... }
}
```

錯誤時：
```json
{
  "success": false,
  "message": "錯誤說明",
  "errors": { "field": ["驗證訊息"] }
}
```

> 所有例外由 `bootstrap/app.php` 集中處理，確保 API 路由統一回傳 JSON。
> 訊息文字透過 `__('key')` 支援多語言（`en` / `zh-TW`），由 `SetLocale` middleware 依 `Accept-Language` header 切換。
