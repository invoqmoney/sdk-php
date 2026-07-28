# invoq PHP SDK

[English](../README.md) · [Bahasa Indonesia](./README.id.md) · [Español](./README.es-419.md) · [Français](./README.fr.md) · [Português](./README.pt-BR.md) · **Tiếng Việt** · [Türkçe](./README.tr.md) · [ไทย](./README.th.md) · [简体中文](./README.zh-Hans.md) · [繁體中文](./README.zh-Hant.md)

> Tài liệu này được dịch từ README tiếng Anh; nếu có chỗ khác nhau, [bản tiếng Anh](../README.md) là bản chuẩn.

SDK PHP dành cho các API server của invoq và xác minh webhook.

Chỉ dùng gói này trên máy chủ của bạn. Nó nhận khóa bí mật và không được đóng gói vào code chạy trên trình duyệt.

**Đang code bằng AI? Dán câu này.**

```
Thêm thanh toán stablecoin vào dự án của tôi bằng invoq. Bắt đầu ở chế độ thử nghiệm. Đọc tài liệu trước khi viết code: https://invoq.money/llms.txt
```

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
4. Thiết lập **Receiving wallet** của bạn trước khi lên live. Hóa đơn thử nghiệm không cần ví này; hóa đơn live không có nơi để tất toán sẽ lỗi `409 no_payment_options_available`.

Thêm cả hai vào biến môi trường của máy chủ:

```bash
INVOQ_SECRET_KEY=sk_test_...
INVOQ_WEBHOOK_SECRET=whsec_...
```

## Tạo client

```php
<?php

use Invoq\Invoq;

$invoq = new Invoq(getenv('INVOQ_SECRET_KEY'));
```

Mặc định của API khi chạy thật:

```text
https://api.invoq.money
```

Ghi đè origin API khi phát triển:

```php
$invoq = new Invoq(getenv('INVOQ_SECRET_KEY'), [
    'apiOrigin' => 'http://localhost:8787',
    'timeoutMs' => 10_000,
]);
```

`apiOrigin` phải là origin `http` hoặc `https` tuyệt đối, không kèm phần username, password, path, query hay hash. SDK sẽ nối thêm các đường dẫn tài nguyên `/v1/...`. Mặc định request sẽ hết thời gian chờ sau 10 giây; truyền `timeoutMs` để ghi đè.

## Tạo hóa đơn

```php
$invoice = $invoq->invoices->create([
    'amount' => '129',
    'description' => 'SaaS boilerplate',
    'reference_id' => 'order_1234',
    'return_url' => 'https://merchant.test/thanks',
]);
```

Dùng số tiền do máy chủ quyết định. Đừng tin số tiền phía client gửi lên. `amount` là chuỗi thập phân USD từ `'0.01'` đến `'1000000.00'`, tối đa 2 chữ số lẻ, ví dụ `'129'` hoặc `'129.99'`. Đơn vị tiền luôn là USD, còn thử nghiệm hay live thì do khóa quyết định — cả hai đều không phải trường trong request.

Dùng một `reference_id` ổn định để nối webhook `invoice.paid` về đúng đơn hàng của bạn. Nó cũng giúp thao tác tạo an toàn khi thử lại: tạo lại với cùng `reference_id` và cùng nội dung hóa đơn sẽ trả về hóa đơn đã có thay vì tạo trùng; nếu nội dung khác nhau, API sẽ báo lỗi `409 reference_id_conflict`.

`amount`, `description`, `reference_id` và `return_url` là toàn bộ các trường của request. `description` và `reference_id` là các chuỗi tùy chọn trong request. Bỏ qua chúng khi không dùng; đừng truyền `null`. `return_url` là tùy chọn và có thể là chuỗi hoặc `null`. Mọi khóa khác bạn truyền vào sẽ bị loại bỏ chứ không được gửi đi, vì API từ chối các khóa lạ trong body với `400 invalid_request` và `fields[].code: "unknown_field"`.

SDK trả về trực tiếp đối tượng `data` của phản hồi dưới dạng mảng kết hợp (associative array). Mảng này chứa phần tóm tắt hóa đơn cùng với `status`, `checkout_status`, `payment_revision`, `amount_due`, `amount_overpaid`, `monitoring_ends_at` và `payment_options`.

## Lấy thông tin hóa đơn

```php
$invoice = $invoq->invoices->get('inv_123');
```

`get()` trả về dạng hóa đơn công khai mà trang checkout sử dụng: dạng phản hồi khi tạo, cộng thêm `project`, `amount_paid` và `transfers`, và bỏ `reference_id`. Hãy dùng phản hồi tạo hóa đơn hoặc webhook `invoice.paid` khi bạn cần `reference_id` phía merchant.

Hai trường trạng thái. `status` là trạng thái kế toán — `unpaid`, `partially_paid`, `paid`, `settling`, `settled`, `review_required` — và ba giá trị coi như đã thanh toán chỉ khác nhau ở việc tiền đã đi được bao xa về ví của bạn. `checkout_status` là trạng thái người trả tiền thấy — `open`, `confirming`, `expired`, `paid`, `unavailable` — và không bao giờ là căn cứ xử lý đơn. `payment_revision` là một số nguyên không âm, tăng mỗi khi tập hợp thanh toán đã xác nhận thay đổi, nên bạn bỏ được bản chụp cũ hơn bản đang giữ.

Số tiền trong phản hồi được API chuẩn hóa: tạo với `'129'` thì hóa đơn trả về `amount: '129.0000'`. So sánh số tiền theo giá trị số, đừng so sánh chuỗi. `amount_due` được tính là `max(amount - amount_paid, 0)` và dùng cùng thang 18 chữ số thập phân như `amount_paid`; `amount_overpaid` là bản đối xứng của nó, `max(amount_paid - amount, 0)`, nên bạn không bao giờ phải tự trừ tiền.

`payment_options` chứa hướng dẫn thanh toán, cố định lúc tạo và `[]` ở chế độ thử nghiệm. Các mục phân biệt theo `status`, rồi `collection_method`: chỉ `'ready'` mới trả được, `'evm_deposit'` mang `deposit_address` và `suggested_amount`, `'direct_exact'` mang `recipient_address` và `exact_amount` mà người mua phải gửi đúng đến từng chữ số. Hãy nhận diện một tùy chọn bằng bộ `(chain_namespace, chain_reference, token_address)`, đừng bao giờ dựa vào vị trí của nó trong mảng. `monitoring_ends_at` đóng cửa sổ thanh toán và là `null` ở chế độ thử nghiệm. `transfers` là danh sách biên nhận đã xác nhận — `transaction_id`, `event_index`, `amount`, `explorer_transaction_url` — và vẫn là `[]` cho tới khi có thanh toán được xác nhận. Tham chiếu đầy đủ các trường: [tài liệu REST API](https://github.com/invoqmoney/api).

## Tạo thanh toán thử nghiệm

```php
$paidInvoice = $invoq->invoices->createTestPayment($invoice['id'], [
    'amount' => $invoice['amount'],
    'reference_id' => 'test_payment_001',
]);
```

Endpoint này yêu cầu khóa bí mật thử nghiệm và chỉ hoạt động với hóa đơn thử nghiệm. Khi số tiền thanh toán đạt đủ giá trị hóa đơn, hóa đơn chuyển sang `paid` và invoq gửi một webhook `invoice.paid` có chữ ký thật đến URL webhook thử nghiệm của bạn. Có thể trả từng phần, hóa đơn sẽ thành `partially_paid`.

`reference_id` là một chuỗi tùy chọn trong request. Bỏ qua nó khi không dùng; đừng truyền `null`.

SDK trả về trực tiếp đối tượng `data` của phản hồi dưới dạng mảng kết hợp: dạng phản hồi khi tạo, cộng thêm `amount_paid` và `fully_paid_at`.

## Trang thanh toán được lưu trữ sẵn

Mỗi hóa đơn còn có một trang thanh toán được lưu trữ sẵn tại:

```text
https://pay.invoq.money/<id hóa đơn>
```

Cứ gửi link hoặc chuyển hướng sang đó khi cửa sổ thanh toán nhúng trong trang không phù hợp.

## Xác minh webhook

Truyền nội dung request gốc cho `verifyWebhook`. Đừng phân tích JSON rồi mã hóa lại trước khi xác minh.

```php
<?php

use function Invoq\isInvoicePaid;
use function Invoq\isInvoicePaymentReversed;
use function Invoq\verifyWebhook;

$rawBody = file_get_contents('php://input');
$event = verifyWebhook(
    $rawBody === false ? '' : $rawBody,
    ['invoq-signature' => $_SERVER['HTTP_INVOQ_SIGNATURE'] ?? null],
    getenv('INVOQ_WEBHOOK_SECRET'),
);

if (isInvoicePaid($event)) {
    $orderId = $event['data']['invoice']['reference_id'];

    if ($orderId === null) {
        throw new RuntimeException('Missing invoice reference_id for fulfillment.');
    }

    fulfillOrder($orderId);
} elseif (isInvoicePaymentReversed($event)) {
    holdOrder($event['data']['invoice']['reference_id']);
}

http_response_code(200);
```

Việc xác minh webhook thất bại sẽ ném `InvoqSignatureVerificationError`. `verifyWebhook` trả về sự kiện webhook đã giải mã dưới dạng mảng kết hợp.

`verifyWebhook` không cần `new Invoq(...)` hay khóa bí mật API của invoq. Hãy dùng mã bí mật webhook của bạn, không phải `INVOQ_SECRET_KEY`.

Hãy xử lý đơn hàng từ webhook đã xác minh, không phải từ kết quả checkout trên trình duyệt. `isInvoicePaid($event)` trả về true cho các sự kiện `invoice.paid` có thể xử lý — tức là hóa đơn ở trạng thái `paid`, `settling` hoặc `settled`; nó từ chối `review_required`.

invoq cũng gửi `invoice.payment_reversed` khi một hóa đơn đã thanh toán tụt trở lại dưới số tiền của nó — chẳng hạn khi chuỗi reorg làm mất một giao dịch đã xác nhận. Bắt sự kiện đó bằng `isInvoicePaymentReversed($event)`, rồi tạm dừng hoặc hoàn tác việc xử lý theo chính sách của bạn. Hàm kiểm tra đó cố ý chấp nhận mọi trạng thái hóa đơn: bỏ qua một lần đảo chiều sẽ khiến đơn hàng vẫn được xử lý dựa trên một khoản thanh toán không còn tồn tại. Một loại sự kiện mà phiên bản SDK này chưa mô hình hóa vẫn qua được bước xác thực và được trả về nguyên trạng.

Cả hai sự kiện đều mang cùng một `data['invoice']`: `id`, `mode`, `status`, `amount`, `currency`, `amount_paid`, `reference_id`, `payment_revision` và `fully_paid_at`. Hướng dẫn thanh toán và `return_url` cố tình không có ở đó — hãy đối soát bằng id hóa đơn cộng với `reference_id`.

Hãy xử lý đơn hàng một cách an toàn khi lặp lại. Mọi phản hồi không phải 2xx cho một lần gửi — kể cả redirect và `4xx` — cùng với lỗi mạng và hết thời gian chờ đều được gửi lại theo một lịch có giới hạn: cách nhau 1 phút, 5 phút, 30 phút, rồi 2 giờ, tổng cộng năm lần, nên endpoint của bạn có thể nhận cùng một sự kiện nhiều lần. Thứ tự đến cũng không được đảm bảo: hãy giữ bản chụp có `payment_revision` cao nhất.

## Xử lý lỗi

```php
<?php

use Invoq\InvoqApiError;
use Invoq\InvoqError;

try {
    $invoq->invoices->create(['amount' => '0.001']);
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
