# invoq PHP SDK

[English](../README.md) · [Bahasa Indonesia](./README.id.md) · [Español](./README.es-419.md) · [Français](./README.fr.md) · [Português](./README.pt-BR.md) · [Tiếng Việt](./README.vi.md) · [Türkçe](./README.tr.md) · **ไทย** · [简体中文](./README.zh-Hans.md) · [繁體中文](./README.zh-Hant.md)

> เอกสารนี้แปลจาก README ภาษาอังกฤษ หากมีข้อความไม่ตรงกัน ให้ยึด[ฉบับภาษาอังกฤษ](../README.md)เป็นหลัก

PHP SDK สำหรับ API ฝั่งเซิร์ฟเวอร์ของ invoq และการตรวจสอบ webhook

ใช้แพ็กเกจนี้บนเซิร์ฟเวอร์ของคุณเท่านั้น แพ็กเกจนี้รับคีย์ลับ (secret key) จึงต้องไม่นำไปรวมไว้ในโค้ดฝั่งเบราว์เซอร์

## SDK ฝั่งเซิร์ฟเวอร์

สร้างใบแจ้งหนี้และตรวจสอบ webhook จากแบ็กเอนด์ของคุณด้วยภาษาใดก็ได้เหล่านี้ — REST API และลายเซ็น webhook เหมือนกันทุกภาษา repo นี้คือ SDK สำหรับ PHP

| ภาษา | Repo |
| --- | --- |
| Node.js | [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js) (`@invoq/server`) |
| Python | [github.com/invoqmoney/sdk-python](https://github.com/invoqmoney/sdk-python) |
| PHP | **repo นี้** |
| Go | [github.com/invoqmoney/sdk-go](https://github.com/invoqmoney/sdk-go) |
| Rust | [github.com/invoqmoney/sdk-rust](https://github.com/invoqmoney/sdk-rust) |
| Ruby | [github.com/invoqmoney/sdk-ruby](https://github.com/invoqmoney/sdk-ruby) |

จะเลือกแบ็กเอนด์ตัวไหนก็ตาม ฝั่งเบราว์เซอร์เหมือนกันหมด: **`@invoq/checkout`** (JavaScript อยู่ใน [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js)) เปิดหน้าชำระเงินแบบฝังในหน้าเว็บให้ฟรอนต์เอนด์ใดก็ได้

## ติดตั้ง

```bash
composer require invoq/invoq-php
```

ต้องใช้ PHP 8.1 ขึ้นไป

## รับคีย์ของคุณ

1. เข้าสู่ระบบ[แดชบอร์ด invoq](https://app.invoq.money) แล้วสร้างโปรเจกต์
2. ที่หน้า **API keys** สร้างคีย์ลับ (secret key) ขึ้นมา คีย์ทดสอบขึ้นต้นด้วย `sk_test_` คีย์จริงขึ้นต้นด้วย `sk_live_`
3. ในการตั้งค่า **webhooks** ของโปรเจกต์ บันทึก URL ของ webhook ที่จะใช้ ซีเคร็ตของ webhook (`whsec_...`) สำหรับโหมดนั้นจะแสดงแค่ครั้งเดียวตอนเปิดใช้ webhook ครั้งแรก — รีบเก็บไว้ทันที

เพิ่มทั้งสองค่าเข้าเป็นตัวแปรสภาพแวดล้อมของเซิร์ฟเวอร์:

```bash
INVOQ_SECRET_KEY=sk_test_...
INVOQ_WEBHOOK_SECRET=whsec_...
```

## สร้าง client

```php
<?php

use Invoq\Invoq;

$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY']);
```

ค่าเริ่มต้นของ API ในสภาพแวดล้อมจริง:

```text
https://api.invoq.money
```

ปรับทับ origin ของ API ระหว่างการพัฒนา:

```php
$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY'], [
    'apiOrigin' => 'http://localhost:8787',
    'timeoutMs' => 10_000,
]);
```

`apiOrigin` ต้องเป็น origin แบบ `http` หรือ `https` เต็มรูปแบบ ที่ไม่มีส่วน username, password, path, query หรือ hash SDK จะต่อท้ายพาธทรัพยากร `/v1/...` ให้เอง โดยค่าเริ่มต้น request จะหมดเวลารอหลังจาก 10 วินาที ส่ง `timeoutMs` เข้ามาเพื่อปรับทับค่านี้ได้

## สร้างใบแจ้งหนี้

```php
$invoice = $invoq->invoices->create([
    'amount' => '129',
    'currency' => 'USD',
    'description' => 'SaaS boilerplate',
    'reference_id' => 'order_1234',
    'return_url' => 'https://merchant.test/thanks',
]);
```

กำหนดยอดเงินจากฝั่งเซิร์ฟเวอร์ อย่าเชื่อยอดเงินที่ส่งมาจากฝั่งไคลเอนต์ `amount` เป็นสตริงเลขทศนิยมสกุล USD ตั้งแต่ `'0.01'` ถึง `'999.99'` ทศนิยมไม่เกิน 2 ตำแหน่ง เช่น `'129'` หรือ `'129.99'`

ใช้ `reference_id` ที่คงที่เพื่อโยง webhook `invoice.paid` กลับไปหาคำสั่งซื้อของคุณ และยังทำให้การสร้างใบแจ้งหนี้ลองใหม่ได้อย่างปลอดภัย: ถ้าสร้างซ้ำด้วย `reference_id` เดิมและเงื่อนไขของใบแจ้งหนี้เดิม จะได้ใบแจ้งหนี้ใบเดิมกลับมาแทนที่จะเกิดใบซ้ำ ส่วนเงื่อนไขที่ต่างกันจะล้มเหลวด้วยข้อผิดพลาด API `409 reference_id_conflict`

`description` และ `reference_id` เป็นสตริงใน request ที่จะระบุหรือไม่ก็ได้ หากไม่ได้ตั้งค่าให้ละไว้ อย่าส่งเป็น `null` ส่วน `return_url` จะระบุหรือไม่ก็ได้ และเป็นได้ทั้งสตริงหรือ `null`

SDK จะคืนออบเจกต์ `data` ของ response กลับมาโดยตรงเป็น associative array

## ดึงข้อมูลใบแจ้งหนี้

```php
$invoice = $invoq->invoices->get('inv_123');
```

`get()` จะคืนรูปแบบใบแจ้งหนี้สาธารณะที่หน้า checkout ใช้ โดยมีฟิลด์ต่าง ๆ เช่น `amount_paid`, `amount_due`, `payment_status`, `project`, `deposit_address`, `monitoring_ends_at` และ `direct_onchain_rails` แต่ไม่มี `reference_id` ถ้าต้องใช้ `reference_id` ฝั่ง merchant ให้ใช้ response ตอนสร้างใบแจ้งหนี้หรือ webhook `invoice.paid`

ยอดเงินในการตอบกลับจะถูกปรับให้เป็นรูปแบบมาตรฐานโดย API: สร้างด้วย `'129'` ใบแจ้งหนี้จะตอบกลับ `amount: '129.0000'` เวลาจะเทียบยอดเงินให้เทียบเป็นตัวเลข อย่าเทียบเป็นสตริง `amount_due` คำนวณจาก `max(amount - amount_paid, 0)` และใช้สเกลทศนิยม 18 ตำแหน่งเหมือน `amount_paid`

## สร้างการชำระเงินทดสอบ

```php
$paidInvoice = $invoq->invoices->createTestPayment($invoice['id'], [
    'amount' => $invoice['amount'],
    'reference_id' => 'test_payment_001',
]);
```

endpoint นี้ต้องใช้คีย์ลับทดสอบ และใช้ได้กับใบแจ้งหนี้ทดสอบเท่านั้น เมื่อยอดจ่ายครบตามจำนวนของใบแจ้งหนี้ ใบแจ้งหนี้จะกลายเป็น `paid` แล้ว invoq จะส่ง webhook `invoice.paid` ที่ลงลายเซ็นจริงไปยัง URL webhook ทดสอบของคุณ จ่ายบางส่วนก็ได้ ผลจะเป็น `partially_paid`

`reference_id` เป็นสตริงใน request ที่จะระบุหรือไม่ก็ได้ หากไม่ได้ตั้งค่าให้ละไว้ อย่าส่งเป็น `null`

SDK จะคืนออบเจกต์ `data` ของ response กลับมาโดยตรงเป็น associative array

## ตรวจสอบ webhook

ส่งเนื้อหา request ดิบเข้าไปยัง `verifyWebhook` อย่าแปลง JSON เป็นออบเจกต์แล้ว encode กลับก่อนการตรวจสอบ

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

การตรวจสอบ webhook ที่ล้มเหลวจะโยน `InvoqSignatureVerificationError` ส่วน `verifyWebhook` จะคืนเหตุการณ์ webhook ที่แปลงแล้วเป็น associative array

`verifyWebhook` ไม่จำเป็นต้องใช้ `new Invoq(...)` หรือคีย์ลับ API ของ invoq ให้ใช้ซีเคร็ต webhook ของคุณ ไม่ใช่ `INVOQ_SECRET_KEY`

ให้จัดการคำสั่งซื้อจาก webhook ที่ผ่านการตรวจสอบแล้ว ไม่ใช่จากผลการชำระเงินในเบราว์เซอร์ `isInvoicePaid($event)` จะคืนค่า true สำหรับเหตุการณ์ `invoice.paid` ที่จัดการคำสั่งซื้อได้ ซึ่งใบแจ้งหนี้มีสถานะเป็น `paid`, `settling` หรือ `settled` และจะปฏิเสธ `review_required` จัดการคำสั่งซื้อให้ปลอดภัยเมื่อรับซ้ำ เพราะการส่ง webhook ที่ล้มเหลวจะถูกส่งซ้ำ

## การจัดการข้อผิดพลาด

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

การตอบกลับ API ที่ไม่ใช่ 2xx จะโยน `InvoqApiError` พร้อมกับ `status`, `code` ของ API, `fields`, `meta` และ `payload` ดิบ ส่วนการเชื่อมต่อล้มเหลว, request หมดเวลารอ, การแปลง response ล้มเหลว และอินพุตไม่ถูกต้อง จะโยน `InvoqError`

ใช้ `$error->getApiCode()` เพื่อดูรหัสข้อผิดพลาด API ของ invoq ส่วน `$error->getCode()` ที่เป็นเมธอดในตัวของ PHP จะคืนรหัสของ exception ไม่ใช่รหัสข้อผิดพลาดของ API

การตรวจสอบ webhook ที่ล้มเหลวจะโยน `InvoqSignatureVerificationError` พร้อมกับรหัสใดรหัสหนึ่งต่อไปนี้:

```text
missing_signature
invalid_signature_header
timestamp_outside_tolerance
signature_mismatch
invalid_payload
```

อ่านค่ารหัสนี้ได้ด้วย `$error->getSignatureCode()`

## การพัฒนา

```bash
composer validate --strict
composer dump-autoload
composer test
```
