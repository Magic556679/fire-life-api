# 付款結果通知 - OrderResultURL

## 應用場景

當消費者付款完成後，綠界一次性反饋付款結果通知，並將頁面導至特店自製頁面。

流程：

1. **綠界**：傳送付款結果並將頁面導至特店的自製頁面網址 (`OrderResultURL`)。
2. **特店**：收到綠界的付款結果訊息。

### 注意事項

- 若要將付款結果頁顯示於特店自製頁面，請設定 `OrderResultURL`。
- 必須依照回傳的交易狀態判斷顯示付款成功或失敗的頁面。
- 各銀行授權時間不同，若因授權時間過久未收到回傳訊息，請使用 [查詢訂單 API](https://developers.ecpay.com.tw/?p=9083) 查詢後再顯示付款結果。
- `OrderResultURL` 未使用 HTTPS 時，部分瀏覽器可能會出現警告訊息。
- `ReturnURL` 與 `OrderResultURL` 沒有固定順序，依當下連線與系統速度而定。

## HTTPS 傳輸協定

- **Accept**: `text/html`
- **Content Type**: `application/x-www-form-urlencoded`
- **HTTP Method**: POST

## 綠界 Response 參數說明

### ResultData

- 回傳參數，特店自製頁面接收的回傳參數
- **範例**：
  ResultData = 綠界回傳參數

### JSON 格式參數

{
"MerchantID": "3002607",
"RpHeader": { "Timestamp": 1234564848 },
"TransCode": 1,
"TransMsg": "Success",
"Data": "…"
}

#### Data 參數說明

| 參數         | 型別   | 說明                                                                                         |
| ------------ | ------ | -------------------------------------------------------------------------------------------- |
| RtnCode      | Int    | 交易狀態：1 = 成功，其餘代碼請參考 [交易訊息代碼表](https://developers.ecpay.com.tw/?p=9108) |
| RtnMsg       | String | 回應訊息                                                                                     |
| PlatformID   | String | 平台商編號                                                                                   |
| MerchantID   | String | 特店編號                                                                                     |
| SimulatePaid | Int    | 模擬付款時回傳，1 = 模擬付款，不會實際撥款                                                   |

#### OrderInfo

| 參數            | 型別   | 說明                                                      |
| --------------- | ------ | --------------------------------------------------------- |
| MerchantTradeNo | String | 特店交易編號                                              |
| TradeNo         | String | 綠界交易編號                                              |
| TradeAmt        | Int    | 交易金額                                                  |
| TradeDate       | String | 訂單成立時間                                              |
| PaymentType     | String | 付款方式（Credit, ApplePay, UnionPay, CVS, BARCODE, ATM） |
| PaymentDate     | String | 付款時間 yyyy/MM/dd HH:mm:ss                              |
| ChargeFee       | Number | 金流服務費（手續費+交易處理費）                           |
| ProcessFee      | Number | 交易處理費                                                |
| TradeStatus     | String | 交易狀態 0=未付款, 1=已付款                               |

#### CardInfo（信用卡/銀聯卡）

| 參數        | 型別   | 說明                       |
| ----------- | ------ | -------------------------- |
| AuthCode    | String | 銀行授權碼                 |
| Gwsr        | Int    | 授權交易單號               |
| ProcessDate | String | 交易時間                   |
| Amount      | Int    | 金額                       |
| Stage       | Int    | 分期期數                   |
| Stast       | Int    | 首期金額                   |
| Staed       | Int    | 各期金額                   |
| Eci         | Int    | 3D(VBV) 回傳值             |
| Card6No     | String | 卡號前六碼（銀聯卡不回傳） |
| Card4No     | String | 卡號末四碼（銀聯卡不回傳） |
| RedDan      | Int    | 紅利扣點                   |
| RedOkAmt    | Int    | 實際扣款金額               |
| RedYet      | Int    | 紅利剩餘點數               |
| RedDeAmt    | Int    | 紅利折抵金額               |

#### CVSInfo / BarcodeInfo / ATMInfo

- 分別回傳對應付款方式的詳細資訊，如繳費代碼、超商門市、銀行帳號後五碼等。

### Data 參數範例

{
"RtnCode": 1,
"RtnMsg": "Success",
"MerchantID": "3002607",
"OrderInfo": {
"MerchantTradeNo": "20180914001",
"TradeNo": "1809261503338172",
"TradeDate": "2018/09/26 14:59:54"
},
"CardInfo": {
"Gwsr": 10735183,
"ProcessDate": "2018/09/26 14:59:54",
"AuthCode": "777777",
"Amount": 100,
"Eci": 2,
"Card4No": "2222",
"Card6No": "491122",
"RedDan": 0,
"RedOkAmt": 0,
"RedYet": 0
}
}

### 重要提醒

- 交易狀態與金額需依據 `Data` 解析後決定前端顯示。
- 模擬付款 (`SimulatePaid`) 不會實際撥款，僅用於測試。
- 海外信用卡可能不支援 3D 驗證，可依業務需求聯絡開啟「強制3D」。
