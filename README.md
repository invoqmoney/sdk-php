# invoq PHP SDK

**English** · [Bahasa Indonesia](./docs/README.id.md) · [Español](./docs/README.es-419.md) · [Français](./docs/README.fr.md) · [Português](./docs/README.pt-BR.md) · [Tiếng Việt](./docs/README.vi.md) · [Türkçe](./docs/README.tr.md) · [ไทย](./docs/README.th.md) · [简体中文](./docs/README.zh-Hans.md) · [繁體中文](./docs/README.zh-Hant.md)

PHP SDK for invoq server APIs and webhook verification.

Use this package only on your server. It accepts secret keys and must not be
bundled into browser code.

## Server SDKs

Create invoices and verify webhooks from your backend in any of these languages — same REST API, same webhook signature. This repository is the PHP SDK.

| Language | Repository |
| --- | --- |
| Node.js | [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js) (`@invoq/server`) |
| Python | [github.com/invoqmoney/sdk-python](https://github.com/invoqmoney/sdk-python) |
| PHP | **this repo** |
| Go | [github.com/invoqmoney/sdk-go](https://github.com/invoqmoney/sdk-go) |
| Rust | [github.com/invoqmoney/sdk-rust](https://github.com/invoqmoney/sdk-rust) |
| Ruby | [github.com/invoqmoney/sdk-ruby](https://github.com/invoqmoney/sdk-ruby) |

The browser side is the same for every backend: **`@invoq/checkout`** (JavaScript, in [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js)) opens the in-page checkout modal for any frontend.

## Installation

```bash
composer require invoq/invoq-php
```

Requires PHP 8.1 or newer.

## Get your keys

1. Sign in to the [invoq dashboard](https://app.invoq.money) and create a project.
2. On the **API keys** page, create a secret key. Test keys start with
   `sk_test_`, live keys with `sk_live_`.
3. In your project's **webhooks** settings, save your webhook URL. The webhook
   secret (`whsec_...`) for that mode is shown once, when you first enable the
   webhook. Store it right away.

Add both to your server environment:

```bash
INVOQ_SECRET_KEY=sk_test_...
INVOQ_WEBHOOK_SECRET=whsec_...
```

## Create a client

```php
<?php

use Invoq\Invoq;

$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY']);
```

Production API default:

```text
https://api.invoq.money
```

Override the API origin during development:

```php
$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY'], [
    'apiOrigin' => 'http://localhost:8787',
    'timeoutMs' => 10_000,
]);
```

`apiOrigin` must be an absolute `http` or `https` origin without username,
password, path, query, or hash parts. The SDK appends `/v1/...` resource paths.
Requests time out after 10 seconds by default; pass `timeoutMs` to override it.

## Create an invoice

```php
$invoice = $invoq->invoices->create([
    'amount' => '129',
    'currency' => 'USD',
    'description' => 'SaaS boilerplate',
    'reference_id' => 'order_1234',
    'return_url' => 'https://merchant.test/thanks',
]);
```

Use a server-side amount. Do not trust client-supplied amounts. `amount` is a
decimal USD string from `'0.01'` to `'999.99'` with up to 2 decimal places, such
as `'129'` or `'129.99'`.

Use a stable `reference_id` to map `invoice.paid` webhooks back to your order.
It also makes creation retry-safe: creating again with the same `reference_id`
and the same invoice terms returns the existing invoice instead of a duplicate;
different terms fail with a `409 reference_id_conflict` API error.

`description` and `reference_id` are optional request strings. Omit them when
unset; do not pass `null`. `return_url` is optional and may be a string or
`null`.

The SDK returns the response `data` object directly as an associative array.

## Get an invoice

```php
$invoice = $invoq->invoices->get('inv_123');
```

`get()` returns the public invoice shape used by checkout. It includes fields
such as `amount_paid`, `amount_due`, `amount_overpaid`, `payment_status`,
`project`, `deposit_address`, `monitoring_ends_at`, `monitoring_status`,
`transfers`, and `direct_onchain_rails`, but does not include `reference_id`.
Use the create response or `invoice.paid` webhook when you need your merchant
`reference_id`.

Amounts in responses are normalized by the API: create with `'129'` and the
invoice returns `amount: '129.0000'`. Compare amounts numerically, not as
strings. `amount_due` is derived as `max(amount - amount_paid, 0)` and uses the
same 18-decimal scale as `amount_paid`; `amount_overpaid` is its mirror,
`max(amount_paid - amount, 0)`, so you never subtract money yourself.
`monitoring_status` is `'active'` or `'ended'` — once it is `'ended'`, the
deposit address is no longer watched — and `transfers` is the confirmed
on-chain receipt trail (each entry has `tx_hash`, `amount`, and
`explorer_tx_url`). Both are `null` / `[]` for test invoices.

## Create a test payment

```php
$paidInvoice = $invoq->invoices->createTestPayment($invoice['id'], [
    'amount' => $invoice['amount'],
    'reference_id' => 'test_payment_001',
]);
```

This endpoint requires a test secret key and only works on test invoices. When
the payments reach the invoice amount, the invoice becomes `paid` and invoq
sends a real signed `invoice.paid` webhook to your test webhook URL. Partial
amounts are allowed and produce `partially_paid`.

`reference_id` is an optional request string. Omit it when unset; do not pass
`null`.

The SDK returns the response `data` object directly as an associative array.

## Verify webhooks

Pass the raw request body to `verifyWebhook`. Do not parse JSON and encode it
again before verification.

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

Webhook verification failures throw `InvoqSignatureVerificationError`.
`verifyWebhook` returns the decoded webhook event as an associative array.

`verifyWebhook` does not require `new Invoq(...)` or your invoq API secret key.
Use your webhook secret, not `INVOQ_SECRET_KEY`.

Fulfill orders from verified webhooks, not browser checkout results.
`isInvoicePaid($event)` returns true for fulfillable `invoice.paid` events whose
invoice status is `paid`, `settling`, or `settled`; it rejects
`review_required`. Handle fulfillment idempotently because failed webhook
deliveries are retried.

## Error handling

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

Non-2xx API responses throw `InvoqApiError` with `status`, API `code`, `fields`,
`meta`, and raw `payload`. Connection failures, request timeouts, response parse
failures, and invalid input throw `InvoqError`.

Use `$error->getApiCode()` for invoq API error codes. PHP's built-in
`$error->getCode()` returns the exception code, not the API error code.

Webhook verification failures throw `InvoqSignatureVerificationError` with one
of these codes:

```text
missing_signature
invalid_signature_header
timestamp_outside_tolerance
signature_mismatch
invalid_payload
```

Read it with `$error->getSignatureCode()`.

## Development

```bash
composer validate --strict
composer dump-autoload
composer test
```
