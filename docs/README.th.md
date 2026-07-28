# invoq PHP SDK

[English](../README.md) · [Bahasa Indonesia](./README.id.md) · [Español](./README.es-419.md) · [Français](./README.fr.md) · [Português](./README.pt-BR.md) · [Tiếng Việt](./README.vi.md) · [Türkçe](./README.tr.md) · **ไทย** · [简体中文](./README.zh-Hans.md) · [繁體中文](./README.zh-Hant.md)

> เอกสารนี้แปลจาก README ภาษาอังกฤษ หากมีข้อความไม่ตรงกัน ให้ยึด[ฉบับภาษาอังกฤษ](../README.md)เป็นหลัก

PHP SDK สำหรับ API ฝั่งเซิร์ฟเวอร์ของ invoq และการตรวจสอบ webhook

ใช้แพ็กเกจนี้บนเซิร์ฟเวอร์ของคุณเท่านั้น แพ็กเกจนี้รับคีย์ลับ (secret key) จึงต้องไม่นำไปรวมไว้ในโค้ดฝั่งเบราว์เซอร์

**ใช้ AI เขียนโค้ดอยู่ไหม วางข้อความนี้**

```
เพิ่มการรับชำระด้วย stablecoin เข้าโปรเจกต์ของฉันด้วย invoq เริ่มที่โหมดทดสอบ อ่านเอกสารก่อนเขียนโค้ด: https://invoq.money/llms.txt
```

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
4. ตั้งค่า **Receiving wallet** ของคุณก่อนเปิดใช้งานจริง ใบแจ้งหนี้ทดสอบไม่ต้องใช้ แต่ใบแจ้งหนี้จริงที่ไม่มีปลายทางให้เงินเข้าจะล้มเหลวด้วย `409 no_payment_options_available`

เพิ่มทั้งสองค่าเข้าเป็นตัวแปรสภาพแวดล้อมของเซิร์ฟเวอร์:

```bash
INVOQ_SECRET_KEY=sk_test_...
INVOQ_WEBHOOK_SECRET=whsec_...
```

## สร้าง client

```php
<?php

use Invoq\Invoq;

$invoq = new Invoq(getenv('INVOQ_SECRET_KEY'));
```

ค่าเริ่มต้นของ API ในสภาพแวดล้อมจริง:

```text
https://api.invoq.money
```

ปรับทับ origin ของ API ระหว่างการพัฒนา:

```php
$invoq = new Invoq(getenv('INVOQ_SECRET_KEY'), [
    'apiOrigin' => 'http://localhost:8787',
    'timeoutMs' => 10_000,
]);
```

`apiOrigin` ต้องเป็น origin แบบ `http` หรือ `https` เต็มรูปแบบ ที่ไม่มีส่วน username, password, path, query หรือ hash SDK จะต่อท้ายพาธทรัพยากร `/v1/...` ให้เอง โดยค่าเริ่มต้น request จะหมดเวลารอหลังจาก 10 วินาที ส่ง `timeoutMs` เข้ามาเพื่อปรับทับค่านี้ได้

## สร้างใบแจ้งหนี้

```php
$invoice = $invoq->invoices->create([
    'amount' => '129',
    'description' => 'SaaS boilerplate',
    'reference_id' => 'order_1234',
    'return_url' => 'https://merchant.test/thanks',
]);
```

กำหนดยอดเงินจากฝั่งเซิร์ฟเวอร์ อย่าเชื่อยอดเงินที่ส่งมาจากฝั่งไคลเอนต์ `amount` เป็นสตริงเลขทศนิยมสกุล USD ตั้งแต่ `'0.01'` ถึง `'1000000.00'` ทศนิยมไม่เกิน 2 ตำแหน่ง เช่น `'129'` หรือ `'129.99'` สกุลเงินเป็น USD เสมอ ส่วนโหมดทดสอบหรือโหมดจริงมาจากคีย์ ทั้งสองอย่างไม่ใช่ฟิลด์ใน request

ใช้ `reference_id` ที่คงที่เพื่อโยง webhook `invoice.paid` กลับไปหาคำสั่งซื้อของคุณ และยังทำให้การสร้างใบแจ้งหนี้ลองใหม่ได้อย่างปลอดภัย: ถ้าสร้างซ้ำด้วย `reference_id` เดิมและเงื่อนไขของใบแจ้งหนี้เดิม จะได้ใบแจ้งหนี้ใบเดิมกลับมาแทนที่จะเกิดใบซ้ำ ส่วนเงื่อนไขที่ต่างกันจะล้มเหลวด้วยข้อผิดพลาด API `409 reference_id_conflict`

`amount`, `description`, `reference_id` และ `return_url` คือฟิลด์ทั้งหมดที่มีใน request `description` และ `reference_id` เป็นสตริงใน request ที่จะระบุหรือไม่ก็ได้ หากไม่ได้ตั้งค่าให้ละไว้ อย่าส่งเป็น `null` ส่วน `return_url` จะระบุหรือไม่ก็ได้ และเป็นได้ทั้งสตริงหรือ `null` คีย์อื่นใดที่คุณส่งเข้ามาจะถูกตัดทิ้งแทนที่จะถูกส่งออกไป เพราะ API จะปฏิเสธคีย์ที่ไม่รู้จักใน body ด้วย `400 invalid_request` และ `fields[].code: "unknown_field"`

SDK จะคืนออบเจกต์ `data` ของ response กลับมาโดยตรงเป็น associative array ซึ่งบรรจุข้อมูลสรุปของใบแจ้งหนี้ บวก `status`, `checkout_status`, `payment_revision`, `amount_due`, `amount_overpaid`, `monitoring_ends_at` และ `payment_options`

## ดึงข้อมูลใบแจ้งหนี้

```php
$invoice = $invoq->invoices->get('inv_123');
```

`get()` จะคืนรูปแบบใบแจ้งหนี้สาธารณะที่หน้า checkout ใช้ คือรูปแบบเดียวกับตอนสร้าง บวก `project`, `amount_paid` และ `transfers` แต่ไม่มี `reference_id` ถ้าต้องใช้ `reference_id` ฝั่ง merchant ให้ใช้ response ตอนสร้างใบแจ้งหนี้หรือ webhook `invoice.paid`

มีฟิลด์สถานะสองตัว `status` คือสถานะทางบัญชี ได้แก่ `unpaid`, `partially_paid`, `paid`, `settling`, `settled`, `review_required` โดยสามค่าที่ถือว่าชำระแล้วต่างกันแค่ว่าเงินเดินทางไปถึงกระเป๋าเงินของคุณไกลแค่ไหน ส่วน `checkout_status` คือสถานะที่ผู้จ่ายเห็น ได้แก่ `open`, `confirming`, `expired`, `paid`, `unavailable` และไม่เคยเป็นสิ่งที่อนุญาตให้จัดการคำสั่งซื้อ `payment_revision` เป็นจำนวนเต็มไม่ติดลบที่เพิ่มขึ้นทุกครั้งที่ชุดการชำระเงินที่ยืนยันแล้วเปลี่ยนไป คุณจึงทิ้งสแนปช็อตที่เก่ากว่าของที่ถืออยู่ได้

ยอดเงินในการตอบกลับจะถูกปรับให้เป็นรูปแบบมาตรฐานโดย API: สร้างด้วย `'129'` ใบแจ้งหนี้จะตอบกลับ `amount: '129.0000'` เวลาจะเทียบยอดเงินให้เทียบเป็นตัวเลข อย่าเทียบเป็นสตริง `amount_due` คำนวณจาก `max(amount - amount_paid, 0)` และใช้สเกลทศนิยม 18 ตำแหน่งเหมือน `amount_paid` ขณะที่ `amount_overpaid` เป็นภาพสะท้อนของมัน คือ `max(amount_paid - amount, 0)` คุณจึงไม่ต้องลบเงินเอง

`payment_options` เก็บคำสั่งการชำระเงินไว้ ถูกกำหนดตายตัวตอนสร้างและเป็น `[]` ในโหมดทดสอบ แต่ละรายการแยกด้วย `status` แล้วจึงแยกด้วย `collection_method` มีเฉพาะ `'ready'` ที่จ่ายได้ `'evm_deposit'` จะมี `deposit_address` และ `suggested_amount` ส่วน `'direct_exact'` จะมี `recipient_address` และ `exact_amount` ที่ผู้ซื้อต้องโอนให้ตรงทุกหลัก ให้ระบุตัวเลือกด้วย `(chain_namespace, chain_reference, token_address)` อย่าอ้างอิงจากตำแหน่งของมันในอาร์เรย์ `monitoring_ends_at` เป็นเวลาปิดช่วงรับชำระเงิน และเป็น `null` ในโหมดทดสอบ ส่วน `transfers` คือรายการรับเงินที่ยืนยันแล้ว — `transaction_id`, `event_index`, `amount`, `explorer_transaction_url` — และเป็น `[]` จนกว่าจะมีการชำระเงินที่ยืนยันแล้ว รายละเอียดทุกฟิลด์: [เอกสาร REST API](https://github.com/invoqmoney/api)

## สร้างการชำระเงินทดสอบ

```php
$paidInvoice = $invoq->invoices->createTestPayment($invoice['id'], [
    'amount' => $invoice['amount'],
    'reference_id' => 'test_payment_001',
]);
```

endpoint นี้ต้องใช้คีย์ลับทดสอบ และใช้ได้กับใบแจ้งหนี้ทดสอบเท่านั้น เมื่อยอดจ่ายครบตามจำนวนของใบแจ้งหนี้ ใบแจ้งหนี้จะกลายเป็น `paid` แล้ว invoq จะส่ง webhook `invoice.paid` ที่ลงลายเซ็นจริงไปยัง URL webhook ทดสอบของคุณ จ่ายบางส่วนก็ได้ ผลจะเป็น `partially_paid`

`reference_id` เป็นสตริงใน request ที่จะระบุหรือไม่ก็ได้ หากไม่ได้ตั้งค่าให้ละไว้ อย่าส่งเป็น `null`

SDK จะคืนออบเจกต์ `data` ของ response กลับมาโดยตรงเป็น associative array คือรูปแบบเดียวกับตอนสร้าง บวก `amount_paid` และ `fully_paid_at`

## หน้าชำระเงินที่โฮสต์ให้

ใบแจ้งหนี้ทุกใบมีหน้าชำระเงินที่โฮสต์ให้อยู่แล้วที่:

```text
https://pay.invoq.money/<id ใบแจ้งหนี้>
```

แชร์ลิงก์หรือเปลี่ยนเส้นทางไปที่นั่นได้เลยเมื่อหน้าชำระเงินแบบฝังในเว็บไม่ตอบโจทย์

## ตรวจสอบ webhook

ส่งเนื้อหา request ดิบเข้าไปยัง `verifyWebhook` อย่าแปลง JSON เป็นออบเจกต์แล้ว encode กลับก่อนการตรวจสอบ

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

การตรวจสอบ webhook ที่ล้มเหลวจะโยน `InvoqSignatureVerificationError` ส่วน `verifyWebhook` จะคืนเหตุการณ์ webhook ที่แปลงแล้วเป็น associative array

`verifyWebhook` ไม่จำเป็นต้องใช้ `new Invoq(...)` หรือคีย์ลับ API ของ invoq ให้ใช้ซีเคร็ต webhook ของคุณ ไม่ใช่ `INVOQ_SECRET_KEY`

ให้จัดการคำสั่งซื้อจาก webhook ที่ผ่านการตรวจสอบแล้ว ไม่ใช่จากผลการชำระเงินในเบราว์เซอร์ `isInvoicePaid($event)` จะคืนค่า true สำหรับเหตุการณ์ `invoice.paid` ที่จัดการคำสั่งซื้อได้ ซึ่งใบแจ้งหนี้มีสถานะเป็น `paid`, `settling` หรือ `settled` และจะปฏิเสธ `review_required`

invoq จะส่ง `invoice.payment_reversed` ด้วย เมื่อใบแจ้งหนี้ที่เคยชำระแล้วกลับลงมาต่ำกว่ายอดอีกครั้ง เช่น เมื่อการ reorg ของเชนทำให้ธุรกรรมที่ยืนยันแล้วหลุดไป ให้ดักด้วย `isInvoicePaymentReversed($event)` แล้วพักหรือย้อนการจัดการคำสั่งซื้อตามนโยบายของคุณเอง helper ตัวนี้จงใจรับใบแจ้งหนี้ทุกสถานะ เพราะการทิ้งเหตุการณ์ย้อนเงินไปจะทำให้คำสั่งซื้อถูกจัดการค้างอยู่บนการชำระเงินที่ไม่มีอยู่แล้ว ส่วนชนิดเหตุการณ์ที่ SDK เวอร์ชันนี้ยังไม่รู้จักก็ยังผ่านการตรวจลายเซ็นและถูกคืนมาตามเดิม

เหตุการณ์ทั้งสองแบบมี `data['invoice']` หน้าตาเหมือนกัน คือ `id`, `mode`, `status`, `amount`, `currency`, `amount_paid`, `reference_id`, `payment_revision` และ `fully_paid_at` ส่วนคำสั่งการชำระเงินและ `return_url` จงใจไม่ใส่มาให้ — ให้กระทบยอดด้วย id ของใบแจ้งหนี้คู่กับ `reference_id`

จัดการคำสั่งซื้อให้ปลอดภัยเมื่อรับซ้ำ การตอบกลับที่ไม่ใช่ 2xx ทุกครั้ง — รวมถึง redirect และ `4xx` ด้วย — บวกกับข้อผิดพลาดของเครือข่ายและการหมดเวลารอ จะถูกส่งซ้ำตามลำดับขั้นที่จำกัดไว้ คือ 1 นาที, 5 นาที, 30 นาที แล้ว 2 ชั่วโมง รวมทั้งหมดห้าครั้ง ปลายทางของคุณจึงอาจได้รับเหตุการณ์เดียวกันมากกว่าหนึ่งครั้ง การส่งยังมาถึงแบบสลับลำดับได้ด้วย ให้เก็บสแนปช็อตที่ `payment_revision` สูงสุดไว้

## การจัดการข้อผิดพลาด

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
