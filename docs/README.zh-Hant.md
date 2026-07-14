# invoq PHP SDK

[English](../README.md) · [Bahasa Indonesia](./README.id.md) · [Español](./README.es-419.md) · [Français](./README.fr.md) · [Português](./README.pt-BR.md) · [Tiếng Việt](./README.vi.md) · [Türkçe](./README.tr.md) · [ไทย](./README.th.md) · [简体中文](./README.zh-Hans.md) · **繁體中文**

> 本文是英文版 README 的繁體中文翻譯；若表述有出入，以[英文版](../README.md)為準。

面向 invoq 伺服器端 API 和 webhook 驗簽的 PHP SDK。

這個套件只在你的伺服器上用。它需要金鑰（secret key），絕不能打包進瀏覽器端程式碼。

## 伺服器端 SDK

用下面任一種語言，都能從你的後端建立帳單、驗證 webhook——REST API 和 webhook 簽章完全一致。本倉庫是 PHP SDK。

| 語言    | 倉庫                                                                            |
| ------- | ------------------------------------------------------------------------------- |
| Node.js | [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js)（`@invoq/server`） |
| Python  | [github.com/invoqmoney/sdk-python](https://github.com/invoqmoney/sdk-python)    |
| PHP     | **本倉庫**                                                                       |
| Go      | [github.com/invoqmoney/sdk-go](https://github.com/invoqmoney/sdk-go)            |
| Rust    | [github.com/invoqmoney/sdk-rust](https://github.com/invoqmoney/sdk-rust)        |
| Ruby    | [github.com/invoqmoney/sdk-ruby](https://github.com/invoqmoney/sdk-ruby)        |

瀏覽器這一側，每種後端都一樣：**`@invoq/checkout`**（JavaScript，在 [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js) 裡）為任意前端打開嵌在頁面裡的結帳彈窗。

## 安裝

```bash
composer require invoq/invoq-php
```

需要 PHP 8.1 或更新版本。

## 取得金鑰

1. 登入 [invoq 商家後台](https://app.invoq.money)，建立一個專案。
2. 在 **API keys** 頁面建立一組私密金鑰（secret key）。測試金鑰以 `sk_test_` 開頭，正式金鑰以 `sk_live_` 開頭。
3. 在專案的 **webhooks** 設定裡儲存你的 webhook URL。對應模式的 webhook 簽章金鑰（`whsec_...`）只在首次啟用 webhook 時顯示一次——記得馬上存好。

把兩者都加進伺服器的環境變數：

```bash
INVOQ_SECRET_KEY=sk_test_...
INVOQ_WEBHOOK_SECRET=whsec_...
```

## 建立客戶端

```php
<?php

use Invoq\Invoq;

$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY']);
```

正式環境 API 預設位址：

```text
https://api.invoq.money
```

開發時可以覆寫 API origin：

```php
$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY'], [
    'apiOrigin' => 'http://localhost:8787',
    'timeoutMs' => 10_000,
]);
```

`apiOrigin` 必須是完整的 `http` 或 `https` origin，不能帶使用者名稱、密碼、路徑、query 或 hash 部分。SDK 會在其後接上 `/v1/...` 資源路徑。請求預設 10 秒逾時，傳 `timeoutMs` 可以覆寫這個值。

## 建立帳單

```php
$invoice = $invoq->invoices->create([
    'amount' => '129',
    'currency' => 'USD',
    'description' => 'SaaS boilerplate',
    'reference_id' => 'order_1234',
    'return_url' => 'https://merchant.test/thanks',
]);
```

金額要由伺服器端決定，不要相信用戶端傳來的金額。`amount` 是 `'0.01'` 到 `'999.99'` 之間的十進位美元字串，最多兩位小數，例如 `'129'` 或 `'129.99'`。

用一個穩定的 `reference_id`，把 `invoice.paid` webhook 對應回你的訂單。它也讓建立動作可以放心重試：用相同的 `reference_id` 和相同的帳單條件再建立一次，回傳的是既有帳單而不是重複開單；條件不同則會回 `409 reference_id_conflict` API 錯誤。

`description` 和 `reference_id` 是可選的請求字串。不用時直接省略，不要傳 `null`。`return_url` 是可選的，可以是字串或 `null`。

SDK 會把回應裡的 `data` 物件直接以關聯陣列回傳。

## 查詢帳單

```php
$invoice = $invoq->invoices->get('inv_123');
```

`get()` 回傳結帳頁使用的公開帳單結構。它包含 `amount_paid`、`amount_due`、`payment_status`、`project`、`deposit_address`、`monitoring_ends_at`、`direct_onchain_rails` 等欄位，但不包含 `reference_id`。如果需要商家端的 `reference_id`，請使用建立帳單的回應或 `invoice.paid` webhook。

回應裡的金額由 API 統一格式化：用 `'129'` 建立，帳單回傳 `amount: '129.0000'`。比較金額請按數值比，不要按字串比。`amount_due` 依 `max(amount - amount_paid, 0)` 衍生，使用和 `amount_paid` 相同的 18 位小數 scale。

## 建立測試付款

```php
$paidInvoice = $invoq->invoices->createTestPayment($invoice['id'], [
    'amount' => $invoice['amount'],
    'reference_id' => 'test_payment_001',
]);
```

這個介面需要測試金鑰，且只對測試帳單有效。累計付款達到帳單金額時，帳單變為 `paid`，invoq 會向你的測試 webhook URL 送出一條真實簽章的 `invoice.paid` webhook。也可以只付部分金額，帳單會變成 `partially_paid`。

`reference_id` 是可選的請求字串。不用時直接省略，不要傳 `null`。

SDK 會把回應裡的 `data` 物件直接以關聯陣列回傳。

## 驗證 webhook

把原始請求內容傳給 `verifyWebhook`。驗簽前不要先把 JSON 解析再重新編碼。

```php
<?php

use function Invoq\isInvoicePaid;
use function Invoq\verifyWebhook;

$rawBody = file_get_contents('php://input');
$event = verifyWebhook(
    $rawBody === false ? '' : $rawBody,
    ['invoq-signature' => $_SERVER['HTTP_INVOQ_SIGNATURE'] ?? null],
    $_ENV['INVOQ_WEBHOOK_SECRET'],
);

if (isInvoicePaid($event)) {
    $orderId = $event['data']['invoice']['reference_id'];

    if ($orderId === null) {
        throw new RuntimeException('Missing invoice reference_id for fulfillment.');
    }

    fulfillOrder($orderId);
}

http_response_code(200);
```

webhook 驗簽失敗會擲出 `InvoqSignatureVerificationError`。`verifyWebhook` 會把解碼後的 webhook 事件以關聯陣列回傳。

`verifyWebhook` 不需要 `new Invoq(...)`，也不需要你的 invoq API 金鑰。用的是你的 webhook 簽章金鑰，而不是 `INVOQ_SECRET_KEY`。

訂單要憑驗證過的 webhook 來處理，而不是瀏覽器端的結帳結果。`isInvoicePaid($event)` 對可履約的 `invoice.paid` 事件回傳 true——即帳單狀態為 `paid`、`settling` 或 `settled`；它會拒絕 `review_required`。webhook 投遞失敗會重試，所以履約邏輯要做成冪等的。

## 錯誤處理

```php
<?php

use Invoq\InvoqApiError;
use Invoq\InvoqError;

try {
    $invoq->invoices->create(['amount' => '0.001', 'currency' => 'USD']);
} catch (InvoqApiError $error) {
    error_log((string) $error->status);
    error_log((string) $error->getApiCode());
    error_log(json_encode($error->fields));
    error_log(json_encode($error->meta));
} catch (InvoqError $error) {
    throw $error;
}
```

API 回應非 2xx 時會擲出 `InvoqApiError`，帶有 `status`、API `code`、`fields`、`meta` 和原始 `payload`。連線失敗、請求逾時、回應解析失敗以及參數不合法，則擲出 `InvoqError`。

取 invoq API 錯誤碼請用 `$error->getApiCode()`。PHP 內建的 `$error->getCode()` 回傳的是例外碼，不是 API 錯誤碼。

webhook 驗簽失敗會擲出 `InvoqSignatureVerificationError`，錯誤碼是下面之一：

```text
missing_signature
invalid_signature_header
timestamp_outside_tolerance
signature_mismatch
invalid_payload
```

用 `$error->getSignatureCode()` 讀取它。

## 開發

```bash
composer validate --strict
composer dump-autoload
composer test
```
