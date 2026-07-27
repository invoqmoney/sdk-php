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
4. 上线前先设置 **Receiving wallet**。测试账单不需要它；没有结算去向的正式账单会以 `409 no_payment_options_available` 失败。

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
    'description' => 'SaaS boilerplate',
    'reference_id' => 'order_1234',
    'return_url' => 'https://merchant.test/thanks',
]);
```

金额要由服务端决定，不要相信客户端传来的金额。`amount` 是 `'0.01'` 到 `'1000000.00'` 之间的十进制美元字符串，最多两位小数，比如 `'129'` 或 `'129.99'`。币种恒为 USD，测试还是正式由密钥决定——两者都不是请求字段。

用一个稳定的 `reference_id`，把 `invoice.paid` webhook 对应回你的订单。它还让创建操作可以放心重试：用相同的 `reference_id` 和相同的账单条款再次创建，返回的是已有账单而不是重复开单；条款不同则会报 `409 reference_id_conflict` API 错误。

`amount`、`description`、`reference_id` 和 `return_url` 是仅有的请求字段。`description` 和 `reference_id` 是可选的请求字符串。不用时直接省略，不要传 `null`。`return_url` 是可选的，可以是字符串或 `null`。你传的其他键会被丢弃而不会发出去，因为 API 会用 `400 invalid_request` 和 `fields[].code: "unknown_field"` 拒绝未知的 body 键。

SDK 会把响应里的 `data` 对象直接作为关联数组返回。它带有账单摘要，外加 `status`、`checkout_status`、`payment_revision`、`amount_due`、`amount_overpaid`、`monitoring_ends_at` 和 `payment_options`。

## 查询账单

```php
$invoice = $invoq->invoices->get('inv_123');
```

`get()` 返回收银台使用的公开账单结构：即创建响应的结构，加上 `project`、`amount_paid` 和 `transfers`，去掉 `reference_id`。如果需要商户侧的 `reference_id`，请使用创建账单的响应或 `invoice.paid` webhook。

账单有两个状态字段。`status` 是记账状态——`unpaid`、`partially_paid`、`paid`、`settling`、`settled`、`review_required`，其中三个等同于已付款的取值只差在资金离你的钱包还有多远。`checkout_status` 是付款人看到的状态——`open`、`confirming`、`expired`、`paid`、`unavailable`——它从不构成履约依据。`payment_revision` 是一个非负整数，每当已确认的付款集合变化就加一，你可以据此丢掉比手上更旧的快照。

响应里的金额由 API 统一格式化：用 `'129'` 创建，账单返回 `amount: '129.0000'`。比较金额请按数值比，不要按字符串比。`amount_due` 按 `max(amount - amount_paid, 0)` 派生，使用和 `amount_paid` 相同的 18 位小数 scale；`amount_overpaid` 与它互为镜像，即 `max(amount_paid - amount, 0)`，所以你不必自己做减法。

`payment_options` 装的是付款指令，创建时即固定，测试模式下为 `[]`。每一项先按 `status` 分辨，再按 `collection_method` 分辨：只有 `'ready'` 可付，`'evm_deposit'` 带 `deposit_address` 和 `suggested_amount`，`'direct_exact'` 带 `recipient_address` 以及买家必须一位不差转出的 `exact_amount`。识别某一项请用 `(chain_namespace, chain_reference, token_address)`，绝不要用它在数组里的位置。`monitoring_ends_at` 标记付款窗口的关闭时间，测试模式下为 `null`。`transfers` 是已确认的收款记录——`transaction_id`、`event_index`、`amount`、`explorer_transaction_url`——在有付款确认前一直是 `[]`。完整字段说明见 [REST API 文档](https://github.com/invoqmoney/api)。

## 创建测试付款

```php
$paidInvoice = $invoq->invoices->createTestPayment($invoice['id'], [
    'amount' => $invoice['amount'],
    'reference_id' => 'test_payment_001',
]);
```

这个接口需要测试密钥，且只对测试账单有效。累计付款达到账单金额时，账单变为 `paid`，invoq 会向你的测试 webhook URL 发送一条真实签名的 `invoice.paid` webhook。也可以只付部分金额，账单会变成 `partially_paid`。

`reference_id` 是可选的请求字符串。不用时直接省略，不要传 `null`。

SDK 会把响应里的 `data` 对象直接作为关联数组返回：即创建响应的结构，加上 `amount_paid` 和 `fully_paid_at`。

## 验证 webhook

把原始请求体传给 `verifyWebhook`。验签前不要先把 JSON 解析再重新编码。

```php
<?php

use function Invoq\isInvoicePaid;
use function Invoq\isInvoicePaymentReversed;
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
} elseif (isInvoicePaymentReversed($event)) {
    holdOrder($event['data']['invoice']['reference_id']);
}

http_response_code(200);
```

webhook 验签失败会抛出 `InvoqSignatureVerificationError`。`verifyWebhook` 会把解码后的 webhook 事件作为关联数组返回。

`verifyWebhook` 不需要 `new Invoq(...)`，也不需要你的 invoq API 密钥。用的是你的 webhook 签名密钥，而不是 `INVOQ_SECRET_KEY`。

订单要凭验证过的 webhook 来处理，而不是浏览器端的收银结果。`isInvoicePaid($event)` 对可履约的 `invoice.paid` 事件返回 true——即账单状态为 `paid`、`settling` 或 `settled`；它会拒绝 `review_required`。

账单从已付款跌回不足额时，invoq 还会发 `invoice.payment_reversed`——比如链重组把一笔已确认的转账拿掉了。用 `isInvoicePaymentReversed($event)` 接住它，再按你自己的策略暂停或撤销履约。这个判断有意接受任何账单状态：漏掉一次回退，会让订单基于一笔已经不存在的付款完成履约。本版 SDK 尚未建模的事件类型同样能验签通过，并原样返回。

两种事件带的 `data['invoice']` 完全一样：`id`、`mode`、`status`、`amount`、`currency`、`amount_paid`、`reference_id`、`payment_revision` 和 `fully_paid_at`。付款指令和 `return_url` 按设计不会出现——请用账单 id 加 `reference_id` 来对账。

履约逻辑要做成幂等的。投递收到的每一个非 2xx 响应——包括重定向和 `4xx`——以及网络错误和超时都会重试，重试节奏是固定的：间隔依次为 1 分钟、5 分钟、30 分钟、2 小时，总共五次，所以你的接口可能会多次收到同一个事件。送达顺序也不保证：请保留 `payment_revision` 最大的那份快照。

## 错误处理

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
