# invoq PHP SDK

[English](../README.md) · **Bahasa Indonesia** · [Español](./README.es-419.md) · [Français](./README.fr.md) · [Português](./README.pt-BR.md) · [Tiếng Việt](./README.vi.md) · [Türkçe](./README.tr.md) · [ไทย](./README.th.md) · [简体中文](./README.zh-Hans.md) · [繁體中文](./README.zh-Hant.md)

> Dokumen ini terjemahan dari README bahasa Inggris; kalau ada perbedaan, [versi bahasa Inggris](../README.md) yang berlaku.

SDK PHP untuk API server invoq dan verifikasi webhook.

Gunakan paket ini hanya di server Anda. Paket ini menerima kunci rahasia (secret key) dan tidak boleh disertakan ke dalam kode browser.

## SDK server

Buat invoice dan verifikasi webhook dari backend Anda dalam bahasa mana pun berikut — REST API dan tanda tangan webhook-nya sama persis. Repo ini adalah SDK PHP.

| Bahasa | Repositori |
| --- | --- |
| Node.js | [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js) (`@invoq/server`) |
| Python | [github.com/invoqmoney/sdk-python](https://github.com/invoqmoney/sdk-python) |
| PHP | **repo ini** |
| Go | [github.com/invoqmoney/sdk-go](https://github.com/invoqmoney/sdk-go) |
| Rust | [github.com/invoqmoney/sdk-rust](https://github.com/invoqmoney/sdk-rust) |
| Ruby | [github.com/invoqmoney/sdk-ruby](https://github.com/invoqmoney/sdk-ruby) |

Backend mana pun yang Anda pilih, sisi browser-nya sama: **`@invoq/checkout`** (JavaScript, di [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js)) membuka jendela checkout yang tertanam di halaman untuk frontend apa pun.

## Instalasi

```bash
composer require invoq/invoq-php
```

Membutuhkan PHP 8.1 atau lebih baru.

## Siapkan kunci Anda

1. Masuk ke [dashboard invoq](https://app.invoq.money) dan buat sebuah proyek.
2. Di halaman **API keys**, buat kunci rahasia (secret key). Kunci uji coba diawali
   `sk_test_`, kunci produksi diawali `sk_live_`.
3. Di pengaturan **webhooks** proyek Anda, simpan URL webhook Anda. Kunci rahasia
   webhook (`whsec_...`) untuk mode itu hanya ditampilkan sekali, saat webhook
   pertama kali diaktifkan — langsung simpan.

Tambahkan keduanya ke lingkungan server Anda:

```bash
INVOQ_SECRET_KEY=sk_test_...
INVOQ_WEBHOOK_SECRET=whsec_...
```

## Membuat klien

```php
<?php

use Invoq\Invoq;

$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY']);
```

Bawaan API produksi:

```text
https://api.invoq.money
```

Timpa origin API saat pengembangan:

```php
$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY'], [
    'apiOrigin' => 'http://localhost:8787',
    'timeoutMs' => 10_000,
]);
```

`apiOrigin` harus berupa origin `http` atau `https` absolut tanpa bagian username,
password, path, query, atau hash. SDK menambahkan path resource `/v1/...`.
Request akan timeout setelah 10 detik secara bawaan; berikan `timeoutMs` untuk menimpanya.

## Membuat invoice

```php
$invoice = $invoq->invoices->create([
    'amount' => '129',
    'currency' => 'USD',
    'description' => 'SaaS boilerplate',
    'reference_id' => 'order_1234',
    'return_url' => 'https://merchant.test/thanks',
]);
```

Tentukan jumlahnya di sisi server. Jangan percaya jumlah yang dikirim klien. `amount`
adalah string desimal USD dari `'0.01'` sampai `'999.99'` dengan maksimal 2 angka di
belakang koma, misalnya `'129'` atau `'129.99'`.

Pakai `reference_id` yang stabil untuk memetakan webhook `invoice.paid` kembali ke
pesanan Anda. Ini juga membuat pembuatan invoice aman diulang: membuat lagi dengan
`reference_id` yang sama dan ketentuan invoice yang sama mengembalikan invoice yang
sudah ada, bukan duplikat; ketentuan yang berbeda gagal dengan error API
`409 reference_id_conflict`.

`description` dan `reference_id` adalah string request opsional. Hilangkan kalau tidak
diisi; jangan berikan `null`. `return_url` opsional dan boleh berupa string atau
`null`.

SDK langsung mengembalikan objek `data` dari respons sebagai array asosiatif.

## Mengambil invoice

```php
$invoice = $invoq->invoices->get('inv_123');
```

`get()` mengembalikan bentuk invoice publik yang dipakai checkout. Ini mencakup field
seperti `amount_paid`, `amount_due`, `amount_overpaid`, `payment_status`, `project`,
`deposit_address`, `monitoring_ends_at`, `monitoring_status`, `transfers`, dan
`direct_onchain_rails`, tetapi tidak menyertakan `reference_id`. Gunakan respons
pembuatan atau webhook `invoice.paid` saat Anda membutuhkan `reference_id`
merchant Anda.

Jumlah di respons dinormalkan oleh API: buat dengan `'129'` dan invoice mengembalikan
`amount: '129.0000'`. Bandingkan jumlah secara numerik, bukan sebagai string.
`amount_due` diturunkan sebagai `max(amount - amount_paid, 0)` dan memakai skala 18
desimal yang sama dengan `amount_paid`; `amount_overpaid` adalah kebalikannya,
`max(amount_paid - amount, 0)`, jadi Anda tidak perlu mengurangkannya sendiri.
`monitoring_status` bernilai `'active'` atau `'ended'` — begitu bernilai
`'ended'`, alamat deposit tidak lagi dipantau — dan `transfers` adalah jejak
penerimaan on-chain yang sudah terkonfirmasi (tiap entri punya `tx_hash`,
`amount`, dan `explorer_tx_url`). Keduanya bernilai `null` / `[]` untuk invoice
uji coba.

## Membuat pembayaran uji coba

```php
$paidInvoice = $invoq->invoices->createTestPayment($invoice['id'], [
    'amount' => $invoice['amount'],
    'reference_id' => 'test_payment_001',
]);
```

Endpoint ini membutuhkan kunci rahasia uji coba (test secret key) dan hanya bekerja
pada invoice uji coba. Begitu pembayaran mencapai jumlah invoice, invoice menjadi
`paid` dan invoq mengirim webhook `invoice.paid` bertanda tangan sungguhan ke URL
webhook uji coba Anda. Jumlah parsial diperbolehkan dan menghasilkan `partially_paid`.

`reference_id` adalah string request opsional. Hilangkan kalau tidak diisi; jangan
berikan `null`.

SDK langsung mengembalikan objek `data` dari respons sebagai array asosiatif.

## Memverifikasi webhook

Berikan isi request mentah ke `verifyWebhook`. Jangan mem-parse JSON lalu
meng-encode-nya lagi sebelum verifikasi.

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

Kegagalan verifikasi webhook melempar `InvoqSignatureVerificationError`.
`verifyWebhook` mengembalikan event webhook yang sudah di-decode sebagai array
asosiatif.

`verifyWebhook` tidak membutuhkan `new Invoq(...)` atau kunci rahasia API invoq Anda.
Gunakan kunci rahasia webhook Anda, bukan `INVOQ_SECRET_KEY`.

Proses pesanan dari webhook yang terverifikasi, bukan dari hasil checkout di browser.
`isInvoicePaid($event)` mengembalikan true untuk event `invoice.paid` yang bisa
diproses, yaitu yang status invoice-nya `paid`, `settling`, atau `settled`; fungsi ini
menolak `review_required`. Tangani pemrosesan pesanan secara idempoten karena
pengiriman webhook yang gagal akan diulang.

## Penanganan error

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

Respons API non-2xx melempar `InvoqApiError` dengan `status`, `code` API, `fields`,
`meta`, dan `payload` mentah. Kegagalan koneksi, timeout request, kegagalan parsing
respons, dan input tidak valid melempar `InvoqError`.

Gunakan `$error->getApiCode()` untuk kode error API invoq. `$error->getCode()` bawaan
PHP mengembalikan kode exception, bukan kode error API.

Kegagalan verifikasi webhook melempar `InvoqSignatureVerificationError` dengan salah
satu kode berikut:

```text
missing_signature
invalid_signature_header
timestamp_outside_tolerance
signature_mismatch
invalid_payload
```

Baca kodenya dengan `$error->getSignatureCode()`.

## Pengembangan

```bash
composer validate --strict
composer dump-autoload
composer test
```
