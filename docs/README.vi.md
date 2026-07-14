# invoq PHP SDK

[English](../README.md) · [Bahasa Indonesia](./README.id.md) · [Español](./README.es-419.md) · [Français](./README.fr.md) · [Português](./README.pt-BR.md) · **Tiếng Việt** · [Türkçe](./README.tr.md) · [ไทย](./README.th.md) · [简体中文](./README.zh-Hans.md) · [繁體中文](./README.zh-Hant.md)

> Tài liệu này được dịch từ README tiếng Anh; nếu có chỗ khác nhau, [bản tiếng Anh](../README.md) là bản chuẩn.

SDK PHP dành cho các API server của invoq và xác minh webhook.

Chỉ dùng gói này trên máy chủ của bạn. Nó nhận khóa bí mật và không được đóng gói vào code chạy trên trình duyệt.

## SDK server

Tạo hóa đơn và xác minh webhook từ backend của bạn bằng bất kỳ ngôn ngữ nào dưới đây — cùng REST API, cùng chữ ký webhook. Repo này là SDK PHP.

| Ngôn ngữ | Repo |
| --- | --- |
| Node.js | [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js) (`@invoq/server`) |
| Python | [github.com/invoqmoney/sdk-python](https://github.com/invoqmoney/sdk-python) |
| PHP | **repo này** |
| Go | [github.com/invoqmoney/sdk-go](https://github.com/invoqmoney/sdk-go) |
| Rust | [github.com/invoqmoney/sdk-rust](https://github.com/invoqmoney/sdk-rust) |
| Ruby | [github.com/invoqmoney/sdk-ruby](https://github.com/invoqmoney/sdk-ruby) |

Dù bạn chọn backend nào, phía trình duyệt vẫn như nhau: **`@invoq/checkout`** (JavaScript, trong [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js)) mở cửa sổ thanh toán nhúng trong trang cho mọi frontend.

## Cài đặt

```bash
composer require invoq/invoq-php
```

Yêu cầu PHP 8.1 trở lên.

## Lấy khóa API

1. Đăng nhập [bảng điều khiển invoq](https://app.invoq.money) và tạo một dự án.
2. Ở trang **API keys**, tạo một khóa bí mật. Khóa thử nghiệm bắt đầu bằng `sk_test_`, khóa thật bằng `sk_live_`.
3. Trong phần cài đặt **webhooks** của dự án, lưu URL webhook của bạn. Mã bí mật của webhook (`whsec_...`) cho chế độ đó chỉ hiện đúng một lần, lúc bạn bật webhook lần đầu — hãy lưu lại ngay.

Thêm cả hai vào biến môi trường của máy chủ:

```bash
INVOQ_SECRET_KEY=sk_test_...
INVOQ_WEBHOOK_SECRET=whsec_...
```

## Tạo client

```php
<?php

use Invoq\Invoq;

$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY']);
```

Mặc định của API khi chạy thật:

```text
https://api.invoq.money
```

Ghi đè origin API khi phát triển:

```php
$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY'], [
    'apiOrigin' => 'http://localhost:8787',
    'timeoutMs' => 10_000,
]);
```

`apiOrigin` phải là origin `http` hoặc `https` tuyệt đối, không kèm phần username, password, path, query hay hash. SDK sẽ nối thêm các đường dẫn tài nguyên `/v1/...`. Mặc định request sẽ hết thời gian chờ sau 10 giây; truyền `timeoutMs` để ghi đè.

## Tạo hóa đơn

```php
$invoice = $invoq->invoices->create([
    'amount' => '129',
    'currency' => 'USD',
    'description' => 'SaaS boilerplate',
    'reference_id' => 'order_1234',
    'return_url' => 'https://merchant.test/thanks',
]);
```

Dùng số tiền do máy chủ quyết định. Đừng tin số tiền phía client gửi lên. `amount` là chuỗi thập phân USD từ `'0.01'` đến `'999.99'`, tối đa 2 chữ số lẻ, ví dụ `'129'` hoặc `'129.99'`.

Dùng một `reference_id` ổn định để nối webhook `invoice.paid` về đúng đơn hàng của bạn. Nó cũng giúp thao tác tạo an toàn khi thử lại: tạo lại với cùng `reference_id` và cùng nội dung hóa đơn sẽ trả về hóa đơn đã có thay vì tạo trùng; nếu nội dung khác nhau, API sẽ báo lỗi `409 reference_id_conflict`.

`description` và `reference_id` là các chuỗi tùy chọn trong request. Bỏ qua chúng khi không dùng; đừng truyền `null`. `return_url` là tùy chọn và có thể là chuỗi hoặc `null`.

SDK trả về trực tiếp đối tượng `data` của phản hồi dưới dạng mảng kết hợp (associative array).

## Lấy thông tin hóa đơn

```php
$invoice = $invoq->invoices->get('inv_123');
```

`get()` trả về dạng hóa đơn công khai mà trang checkout sử dụng. Nó bao gồm các trường như `amount_paid`, `amount_due`, `payment_status`, `project`, `deposit_address`, `monitoring_ends_at` và `direct_onchain_rails`, nhưng không bao gồm `reference_id`. Hãy dùng phản hồi tạo hóa đơn hoặc webhook `invoice.paid` khi bạn cần `reference_id` phía merchant.

Số tiền trong phản hồi được API chuẩn hóa: tạo với `'129'` thì hóa đơn trả về `amount: '129.0000'`. So sánh số tiền theo giá trị số, đừng so sánh chuỗi. `amount_due` được tính là `max(amount - amount_paid, 0)` và dùng cùng thang 18 chữ số thập phân như `amount_paid`.

## Tạo thanh toán thử nghiệm

```php
$paidInvoice = $invoq->invoices->createTestPayment($invoice['id'], [
    'amount' => $invoice['amount'],
    'reference_id' => 'test_payment_001',
]);
```

Endpoint này yêu cầu khóa bí mật thử nghiệm và chỉ hoạt động với hóa đơn thử nghiệm. Khi số tiền thanh toán đạt đủ giá trị hóa đơn, hóa đơn chuyển sang `paid` và invoq gửi một webhook `invoice.paid` có chữ ký thật đến URL webhook thử nghiệm của bạn. Có thể trả từng phần, hóa đơn sẽ thành `partially_paid`.

`reference_id` là một chuỗi tùy chọn trong request. Bỏ qua nó khi không dùng; đừng truyền `null`.

SDK trả về trực tiếp đối tượng `data` của phản hồi dưới dạng mảng kết hợp.

## Xác minh webhook

Truyền nội dung request gốc cho `verifyWebhook`. Đừng phân tích JSON rồi mã hóa lại trước khi xác minh.

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

Việc xác minh webhook thất bại sẽ ném `InvoqSignatureVerificationError`. `verifyWebhook` trả về sự kiện webhook đã giải mã dưới dạng mảng kết hợp.

`verifyWebhook` không cần `new Invoq(...)` hay khóa bí mật API của invoq. Hãy dùng mã bí mật webhook của bạn, không phải `INVOQ_SECRET_KEY`.

Hãy xử lý đơn hàng từ webhook đã xác minh, không phải từ kết quả checkout trên trình duyệt. `isInvoicePaid($event)` trả về true cho các sự kiện `invoice.paid` có thể xử lý — tức là hóa đơn ở trạng thái `paid`, `settling` hoặc `settled`; nó từ chối `review_required`. Hãy xử lý đơn hàng một cách an toàn khi lặp lại, vì các lần gửi webhook thất bại sẽ được gửi lại.

## Xử lý lỗi

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

Các phản hồi API không phải 2xx sẽ ném `InvoqApiError` kèm `status`, `code` của API, `fields`, `meta` và `payload` gốc. Lỗi kết nối, request hết thời gian chờ, lỗi phân tích phản hồi và dữ liệu đầu vào không hợp lệ sẽ ném `InvoqError`.

Dùng `$error->getApiCode()` để lấy mã lỗi API của invoq. Hàm `$error->getCode()` có sẵn của PHP trả về mã của ngoại lệ, không phải mã lỗi API.

Việc xác minh webhook thất bại sẽ ném `InvoqSignatureVerificationError` với một trong các mã sau:

```text
missing_signature
invalid_signature_header
timestamp_outside_tolerance
signature_mismatch
invalid_payload
```

Đọc mã đó bằng `$error->getSignatureCode()`.

## Phát triển

```bash
composer validate --strict
composer dump-autoload
composer test
```
