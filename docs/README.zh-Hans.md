# invoq PHP SDK

[English](../README.md) · [Bahasa Indonesia](./README.id.md) · [Español](./README.es-419.md) · [Français](./README.fr.md) · [Português](./README.pt-BR.md) · [Tiếng Việt](./README.vi.md) · [Türkçe](./README.tr.md) · [ไทย](./README.th.md) · **简体中文** · [繁體中文](./README.zh-Hant.md)

> 本文是英文版 README 的简体中文翻译；若表述有出入，以[英文版](../README.md)为准。

面向 invoq 服务端 API 和 webhook 验签的 PHP SDK。

这个包只在你的服务器上用。它需要密钥（secret key），绝不能打包进浏览器端代码。

## 服务端 SDK

用下面任意一种语言，都能从你的后端创建账单、验证 webhook——REST API 和 webhook 签名完全一致。本仓库是 PHP SDK。

| 语言    | 仓库                                                                            |
| ------- | ------------------------------------------------------------------------------- |
| Node.js | [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js)（`@invoq/server`） |
| Python  | [github.com/invoqmoney/sdk-python](https://github.com/invoqmoney/sdk-python)    |
| PHP     | **本仓库**                                                                       |
| Go      | [github.com/invoqmoney/sdk-go](https://github.com/invoqmoney/sdk-go)            |
| Rust    | [github.com/invoqmoney/sdk-rust](https://github.com/invoqmoney/sdk-rust)        |
| Ruby    | [github.com/invoqmoney/sdk-ruby](https://github.com/invoqmoney/sdk-ruby)        |

浏览器这一侧，每种后端都一样：**`@invoq/checkout`**（JavaScript，在 [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js) 里）为任意前端打开嵌在页面里的收银台弹窗。

## 安装

```bash
composer require invoq/invoq-php
```

需要 PHP 8.1 或更高版本。

## 获取密钥

1. 登录 [invoq 商户后台](https://app.invoq.money)，创建一个项目。
2. 在 **API keys** 页面创建一把密钥（secret key）。测试密钥以 `sk_test_` 开头，正式密钥以 `sk_live_` 开头。
3. 在项目的 **webhooks** 设置里保存你的 webhook URL。对应模式的 webhook 签名密钥（`whsec_...`）只在首次启用 webhook 时展示一次——记得马上存好。

把两者都加进服务端环境变量：

```bash
INVOQ_SECRET_KEY=sk_test_...
INVOQ_WEBHOOK_SECRET=whsec_...
```

## 创建客户端

```php
<?php

use Invoq\Invoq;

$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY']);
```

生产环境 API 默认地址：

```text
https://api.invoq.money
```

开发时可以覆盖 API origin：

```php
$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY'], [
    'apiOrigin' => 'http://localhost:8787',
    'timeoutMs' => 10_000,
]);
```

`apiOrigin` 必须是完整的 `http` 或 `https` origin，不能带用户名、密码、路径、query 或 hash 部分。SDK 会在其后拼接 `/v1/...` 资源路径。请求默认 10 秒超时，传 `timeoutMs` 可以覆盖这个值。

## 创建账单

```php
$invoice = $invoq->invoices->create([
    'amount' => '129',
    'currency' => 'USD',
    'description' => 'SaaS boilerplate',
    'reference_id' => 'order_1234',
    'return_url' => 'https://merchant.test/thanks',
]);
```

金额要由服务端决定，不要相信客户端传来的金额。`amount` 是 `'0.01'` 到 `'999.99'` 之间的十进制美元字符串，最多两位小数，比如 `'129'` 或 `'129.99'`。

用一个稳定的 `reference_id`，把 `invoice.paid` webhook 对应回你的订单。它还让创建操作可以放心重试：用相同的 `reference_id` 和相同的账单条款再次创建，返回的是已有账单而不是重复开单；条款不同则会报 `409 reference_id_conflict` API 错误。

`description` 和 `reference_id` 是可选的请求字符串。不用时直接省略，不要传 `null`。`return_url` 是可选的，可以是字符串或 `null`。

SDK 会把响应里的 `data` 对象直接作为关联数组返回。

## 查询账单

```php
$invoice = $invoq->invoices->get('inv_123');
```

`get()` 返回收银台使用的公开账单结构。它包含 `amount_paid`、`amount_due`、`amount_overpaid`、`payment_status`、`project`、`deposit_address`、`monitoring_ends_at`、`monitoring_status`、`transfers`、`direct_onchain_rails` 等字段，但不包含 `reference_id`。如果需要商户侧的 `reference_id`，请使用创建账单的响应或 `invoice.paid` webhook。

响应里的金额由 API 统一格式化：用 `'129'` 创建，账单返回 `amount: '129.0000'`。比较金额请按数值比，不要按字符串比。`amount_due` 按 `max(amount - amount_paid, 0)` 派生，使用和 `amount_paid` 相同的 18 位小数 scale；`amount_overpaid` 与它互为镜像，即 `max(amount_paid - amount, 0)`，所以你不必自己做减法。`monitoring_status` 取值 `'active'` 或 `'ended'`——一旦变为 `'ended'`，收款地址就不再被监控——而 `transfers` 是已确认的链上收款记录（每一项都含 `tx_hash`、`amount` 和 `explorer_tx_url`）。测试账单里两者分别为 `null` / `[]`。

## 创建测试付款

```php
$paidInvoice = $invoq->invoices->createTestPayment($invoice['id'], [
    'amount' => $invoice['amount'],
    'reference_id' => 'test_payment_001',
]);
```

这个接口需要测试密钥，且只对测试账单有效。累计付款达到账单金额时，账单变为 `paid`，invoq 会向你的测试 webhook URL 发送一条真实签名的 `invoice.paid` webhook。也可以只付部分金额，账单会变成 `partially_paid`。

`reference_id` 是可选的请求字符串。不用时直接省略，不要传 `null`。

SDK 会把响应里的 `data` 对象直接作为关联数组返回。

## 验证 webhook

把原始请求体传给 `verifyWebhook`。验签前不要先把 JSON 解析再重新编码。

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

webhook 验签失败会抛出 `InvoqSignatureVerificationError`。`verifyWebhook` 会把解码后的 webhook 事件作为关联数组返回。

`verifyWebhook` 不需要 `new Invoq(...)`，也不需要你的 invoq API 密钥。用的是你的 webhook 签名密钥，而不是 `INVOQ_SECRET_KEY`。

订单要凭验证过的 webhook 来处理，而不是浏览器端的收银结果。`isInvoicePaid($event)` 对可履约的 `invoice.paid` 事件返回 true——即账单状态为 `paid`、`settling` 或 `settled`；它会拒绝 `review_required`。webhook 投递失败会重试，所以履约逻辑要做成幂等的。

## 错误处理

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

API 返回非 2xx 时会抛出 `InvoqApiError`，带有 `status`、API `code`、`fields`、`meta` 和原始 `payload`。连接失败、请求超时、响应解析失败以及入参不合法，则抛出 `InvoqError`。

取 invoq API 错误码请用 `$error->getApiCode()`。PHP 内置的 `$error->getCode()` 返回的是异常码，不是 API 错误码。

webhook 验签失败会抛出 `InvoqSignatureVerificationError`，错误码是下面之一：

```text
missing_signature
invalid_signature_header
timestamp_outside_tolerance
signature_mismatch
invalid_payload
```

用 `$error->getSignatureCode()` 读取它。

## 开发

```bash
composer validate --strict
composer dump-autoload
composer test
```
