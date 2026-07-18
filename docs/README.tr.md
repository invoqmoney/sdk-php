# invoq PHP SDK

[English](../README.md) · [Bahasa Indonesia](./README.id.md) · [Español](./README.es-419.md) · [Français](./README.fr.md) · [Português](./README.pt-BR.md) · [Tiếng Việt](./README.vi.md) · **Türkçe** · [ไทย](./README.th.md) · [简体中文](./README.zh-Hans.md) · [繁體中文](./README.zh-Hant.md)

> Bu belge İngilizce README'nin çevirisidir; bir fark olursa [İngilizce sürüm](../README.md) esas alınır.

invoq sunucu API'leri ve webhook doğrulaması için PHP SDK'sı.

Bu paketi yalnızca sunucunuzda kullanın. Gizli anahtarları kabul eder ve tarayıcı koduna dahil edilmemelidir.

## Sunucu SDK'ları

Bu dillerin herhangi biriyle arka ucunuzdan fatura oluşturun ve webhook'ları doğrulayın — aynı REST API, aynı webhook imzası. Bu repo, PHP SDK'sıdır.

| Dil | Repo |
| --- | --- |
| Node.js | [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js) (`@invoq/server`) |
| Python | [github.com/invoqmoney/sdk-python](https://github.com/invoqmoney/sdk-python) |
| PHP | **bu repo** |
| Go | [github.com/invoqmoney/sdk-go](https://github.com/invoqmoney/sdk-go) |
| Rust | [github.com/invoqmoney/sdk-rust](https://github.com/invoqmoney/sdk-rust) |
| Ruby | [github.com/invoqmoney/sdk-ruby](https://github.com/invoqmoney/sdk-ruby) |

Hangi arka ucu seçerseniz seçin, tarayıcı tarafı aynıdır: **`@invoq/checkout`** (JavaScript, [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js) içinde) her ön uç için sayfa içi ödeme penceresini açar.

## Kurulum

```bash
composer require invoq/invoq-php
```

PHP 8.1 veya üstünü gerektirir.

## Anahtarlarınızı alın

1. [invoq paneline](https://app.invoq.money) giriş yapın ve bir proje oluşturun.
2. **API keys** sayfasında bir gizli anahtar oluşturun. Test anahtarları `sk_test_` ile, canlı anahtarlar `sk_live_` ile başlar.
3. Projenizin **webhooks** ayarlarında webhook URL'nizi kaydedin. O modun webhook sırrı (`whsec_...`) yalnızca bir kez, webhook'u ilk etkinleştirdiğinizde gösterilir — hemen saklayın.

İkisini de sunucu ortamınıza ekleyin:

```bash
INVOQ_SECRET_KEY=sk_test_...
INVOQ_WEBHOOK_SECRET=whsec_...
```

## İstemci oluşturma

```php
<?php

use Invoq\Invoq;

$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY']);
```

Canlı ortam API varsayılanı:

```text
https://api.invoq.money
```

Geliştirme sırasında API origin'ini değiştirin:

```php
$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY'], [
    'apiOrigin' => 'http://localhost:8787',
    'timeoutMs' => 10_000,
]);
```

`apiOrigin`, kullanıcı adı, parola, yol, sorgu veya hash bölümleri içermeyen mutlak bir `http` veya `https` origin'i olmalıdır. SDK, `/v1/...` kaynak yollarını sonuna ekler. İstekler varsayılan olarak 10 saniye sonra zaman aşımına uğrar; bunu değiştirmek için `timeoutMs` geçirin.

## Fatura oluşturma

```php
$invoice = $invoq->invoices->create([
    'amount' => '129',
    'currency' => 'USD',
    'description' => 'SaaS boilerplate',
    'reference_id' => 'order_1234',
    'return_url' => 'https://merchant.test/thanks',
]);
```

Tutarı sunucu tarafında belirleyin. İstemciden gelen tutarlara güvenmeyin. `amount`, `'0.01'` ile `'1000000.00'` arasında, en fazla 2 ondalık basamaklı, USD cinsinden ondalık bir dizedir — örneğin `'129'` veya `'129.99'`.

`invoice.paid` webhook'larını siparişinize geri bağlamak için kararlı bir `reference_id` kullanın. Oluşturmayı yeniden denemeyi de güvenli kılar: aynı `reference_id` ve aynı fatura koşullarıyla tekrar oluşturursanız kopya yerine mevcut faturayı alırsınız; farklı koşullar ise `409 reference_id_conflict` API hatasıyla başarısız olur.

`description` ve `reference_id` isteğe bağlı istek dizeleridir. Ayarlanmadıklarında bunları atlayın; `null` geçirmeyin. `return_url` isteğe bağlıdır ve bir dize ya da `null` olabilir.

SDK, yanıttaki `data` nesnesini doğrudan bir ilişkisel dizi (associative array) olarak döndürür.

## Fatura getirme

```php
$invoice = $invoq->invoices->get('inv_123');
```

`get()`, checkout'un kullandığı herkese açık fatura şeklini döndürür. `amount_paid`, `amount_due`, `amount_overpaid`, `payment_status`, `project`, `deposit_address`, `monitoring_ends_at`, `monitoring_status`, `transfers` ve `direct_onchain_rails` gibi alanları içerir, ancak `reference_id` içermez. Merchant `reference_id` değeriniz gerektiğinde oluşturma yanıtını veya `invoice.paid` webhook'unu kullanın.

Yanıtlardaki tutarlar API tarafından normalize edilir: `'129'` ile oluşturun, fatura `amount: '129.0000'` döndürür. Tutarları dize olarak değil, sayısal karşılaştırın. `amount_due`, `max(amount - amount_paid, 0)` olarak türetilir ve `amount_paid` ile aynı 18 ondalık basamak ölçeğini kullanır; `amount_overpaid` ise onun aynasıdır, `max(amount_paid - amount, 0)`, yani parayı kendiniz çıkarmanız hiç gerekmez. `monitoring_status`, `'active'` ya da `'ended'` olur — `'ended'` olduğunda yatırma adresi artık izlenmez — ve `transfers`, onaylanmış zincir üstü tahsilat kaydıdır (her girdide `tx_hash`, `amount` ve `explorer_tx_url` bulunur). İkisi de test faturaları için `null` / `[]` olur.

## Test ödemesi oluşturma

```php
$paidInvoice = $invoq->invoices->createTestPayment($invoice['id'], [
    'amount' => $invoice['amount'],
    'reference_id' => 'test_payment_001',
]);
```

Bu uç nokta bir test gizli anahtarı gerektirir ve yalnızca test faturalarında çalışır. Ödemeler fatura tutarına ulaştığında fatura `paid` olur ve invoq, test webhook URL'nize gerçekten imzalanmış bir `invoice.paid` webhook'u gönderir. Kısmi tutarlara izin verilir; sonuç `partially_paid` olur.

`reference_id` isteğe bağlı bir istek dizesidir. Ayarlanmadığında atlayın; `null` geçirmeyin.

SDK, yanıttaki `data` nesnesini doğrudan bir ilişkisel dizi olarak döndürür.

## Webhook'ları doğrulama

Ham istek gövdesini `verifyWebhook`'a geçirin. Doğrulamadan önce JSON'u ayrıştırıp yeniden kodlamayın.

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

Webhook doğrulama hataları `InvoqSignatureVerificationError` fırlatır. `verifyWebhook`, çözümlenmiş webhook olayını bir ilişkisel dizi olarak döndürür.

`verifyWebhook`, `new Invoq(...)` çağrısını veya invoq API gizli anahtarınızı gerektirmez. `INVOQ_SECRET_KEY` değil, webhook sırrınızı kullanın.

Siparişleri doğrulanmış webhook'lardan işleyin, tarayıcı checkout sonuçlarından değil. `isInvoicePaid($event)`, fatura durumu `paid`, `settling` veya `settled` olan işlenebilir `invoice.paid` olayları için true döndürür; `review_required` durumunu reddeder. Başarısız webhook teslimatları yeniden denendiği için sipariş işlemeyi idempotent biçimde ele alın.

## Hata yönetimi

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

2xx olmayan API yanıtları bir `InvoqApiError` fırlatır; bu hata `status`, API `code`, `fields`, `meta` ve ham `payload` taşır. Bağlantı hataları, istek zaman aşımları, yanıt ayrıştırma hataları ve geçersiz giriş `InvoqError` fırlatır.

invoq API hata kodları için `$error->getApiCode()` kullanın. PHP'nin yerleşik `$error->getCode()` metodu, API hata kodunu değil istisna kodunu döndürür.

Webhook doğrulama hataları, şu kodlardan biriyle birlikte `InvoqSignatureVerificationError` fırlatır:

```text
missing_signature
invalid_signature_header
timestamp_outside_tolerance
signature_mismatch
invalid_payload
```

Bunu `$error->getSignatureCode()` ile okuyun.

## Geliştirme

```bash
composer validate --strict
composer dump-autoload
composer test
```
