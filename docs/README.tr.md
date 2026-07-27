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
4. Canlıya geçmeden önce **Receiving wallet** ayarınızı yapın. Test faturaları buna ihtiyaç duymaz; paranın gideceği yer olmayan canlı bir fatura `409 no_payment_options_available` ile başarısız olur.

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
    'description' => 'SaaS boilerplate',
    'reference_id' => 'order_1234',
    'return_url' => 'https://merchant.test/thanks',
]);
```

Tutarı sunucu tarafında belirleyin. İstemciden gelen tutarlara güvenmeyin. `amount`, `'0.01'` ile `'1000000.00'` arasında, en fazla 2 ondalık basamaklı, USD cinsinden ondalık bir dizedir — örneğin `'129'` veya `'129.99'`. Para birimi her zaman USD'dir ve test mi live mı olduğu anahtardan gelir — ikisi de istek alanı değildir.

`invoice.paid` webhook'larını siparişinize geri bağlamak için kararlı bir `reference_id` kullanın. Oluşturmayı yeniden denemeyi de güvenli kılar: aynı `reference_id` ve aynı fatura koşullarıyla tekrar oluşturursanız kopya yerine mevcut faturayı alırsınız; farklı koşullar ise `409 reference_id_conflict` API hatasıyla başarısız olur.

İstek alanları yalnızca `amount`, `description`, `reference_id` ve `return_url`'dir. `description` ve `reference_id` isteğe bağlı istek dizeleridir. Ayarlanmadıklarında bunları atlayın; `null` geçirmeyin. `return_url` isteğe bağlıdır ve bir dize ya da `null` olabilir. Geçirdiğiniz başka herhangi bir anahtar gönderilmez, atılır; çünkü API bilinmeyen gövde anahtarlarını `400 invalid_request` ve `fields[].code: "unknown_field"` ile reddeder.

SDK, yanıttaki `data` nesnesini doğrudan bir ilişkisel dizi (associative array) olarak döndürür. Bu dizi, fatura özetine ek olarak `status`, `checkout_status`, `payment_revision`, `amount_due`, `amount_overpaid`, `monitoring_ends_at` ve `payment_options` alanlarını taşır.

## Fatura getirme

```php
$invoice = $invoq->invoices->get('inv_123');
```

`get()`, checkout'un kullandığı herkese açık fatura şeklini döndürür: oluşturma şekli, artı `project`, `amount_paid` ve `transfers`, eksi `reference_id`. Merchant `reference_id` değeriniz gerektiğinde oluşturma yanıtını veya `invoice.paid` webhook'unu kullanın.

İki durum alanı. `status` muhasebe durumudur — `unpaid`, `partially_paid`, `paid`, `settling`, `settled`, `review_required` — ve ödeme tamamlanmış sayılan üç değer yalnızca paranın cüzdanınıza ne kadar yaklaştığıyla ayrılır. `checkout_status` ödeyenin gördüğüdür — `open`, `confirming`, `expired`, `paid`, `unavailable` — ve siparişi işlemek için asla yetki vermez. `payment_revision`, onaylanmış ödeme kümesi her değiştiğinde artan negatif olmayan bir tam sayıdır; böylece elinizdekinden eski bir anlık görüntüyü eleyebilirsiniz.

Yanıtlardaki tutarlar API tarafından normalize edilir: `'129'` ile oluşturun, fatura `amount: '129.0000'` döndürür. Tutarları dize olarak değil, sayısal karşılaştırın. `amount_due`, `max(amount - amount_paid, 0)` olarak türetilir ve `amount_paid` ile aynı 18 ondalık basamak ölçeğini kullanır; `amount_overpaid` ise onun aynasıdır, `max(amount_paid - amount, 0)`, yani parayı kendiniz çıkarmanız hiç gerekmez.

`payment_options` ödeme talimatlarını taşır; oluşturulurken sabitlenir ve test modunda `[]` olur. Girdiler önce `status`, sonra `collection_method` ile ayrışır: yalnızca `'ready'` ödenebilir, `'evm_deposit'` `deposit_address` ve `suggested_amount` taşır, `'direct_exact'` `recipient_address` ile alıcının son hanesine kadar göndermesi gereken `exact_amount` değerini taşır. Bir seçeneği `(chain_namespace, chain_reference, token_address)` üçlüsüyle tanımlayın, dizideki konumuyla asla değil. `monitoring_ends_at` ödeme penceresini kapatır ve test modunda `null` olur. `transfers` onaylanmış tahsilat kaydıdır — `transaction_id`, `event_index`, `amount`, `explorer_transaction_url` — ve bir ödeme onaylanana kadar `[]` kalır. Tüm alanlar: [REST API belgeleri](https://github.com/invoqmoney/api).

## Test ödemesi oluşturma

```php
$paidInvoice = $invoq->invoices->createTestPayment($invoice['id'], [
    'amount' => $invoice['amount'],
    'reference_id' => 'test_payment_001',
]);
```

Bu uç nokta bir test gizli anahtarı gerektirir ve yalnızca test faturalarında çalışır. Ödemeler fatura tutarına ulaştığında fatura `paid` olur ve invoq, test webhook URL'nize gerçekten imzalanmış bir `invoice.paid` webhook'u gönderir. Kısmi tutarlara izin verilir; sonuç `partially_paid` olur.

`reference_id` isteğe bağlı bir istek dizesidir. Ayarlanmadığında atlayın; `null` geçirmeyin.

SDK, yanıttaki `data` nesnesini doğrudan bir ilişkisel dizi olarak döndürür: oluşturma şekli, artı `amount_paid` ve `fully_paid_at`.

## Webhook'ları doğrulama

Ham istek gövdesini `verifyWebhook`'a geçirin. Doğrulamadan önce JSON'u ayrıştırıp yeniden kodlamayın.

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

Webhook doğrulama hataları `InvoqSignatureVerificationError` fırlatır. `verifyWebhook`, çözümlenmiş webhook olayını bir ilişkisel dizi olarak döndürür.

`verifyWebhook`, `new Invoq(...)` çağrısını veya invoq API gizli anahtarınızı gerektirmez. `INVOQ_SECRET_KEY` değil, webhook sırrınızı kullanın.

Siparişleri doğrulanmış webhook'lardan işleyin, tarayıcı checkout sonuçlarından değil. `isInvoicePaid($event)`, fatura durumu `paid`, `settling` veya `settled` olan işlenebilir `invoice.paid` olayları için true döndürür; `review_required` durumunu reddeder.

invoq, daha önce ödenmiş bir fatura kendi tutarının altına geri düştüğünde `invoice.payment_reversed` de gönderir — örneğin zincir reorg'u onaylanmış bir transferi düşürdüğünde. Bunu `isInvoicePaymentReversed($event)` ile yakalayın ve kendi politikanıza göre siparişi bekletin veya geri alın. Bu kontrol bilerek her fatura durumunu kabul eder: bir geri almayı elemek, artık var olmayan bir ödemeye dayanan bir siparişi işlenmiş halde bırakırdı. Bu SDK sürümünün modellemediği bir olay tipi de doğrulanır ve olduğu gibi döndürülür.

Her iki olay da aynı `data['invoice']` yapısını taşır: `id`, `mode`, `status`, `amount`, `currency`, `amount_paid`, `reference_id`, `payment_revision` ve `fully_paid_at`. Ödeme talimatları ile `return_url` tasarım gereği yoktur — mutabakatı fatura id'si ve `reference_id` ile yapın.

Sipariş işlemeyi idempotent biçimde ele alın. Bir teslimata verilen 2xx olmayan her yanıt — yönlendirmeler ve `4xx` dahil — ayrıca ağ hataları ve zaman aşımları yeniden denenir; aralar 1 dakika, 5 dakika, 30 dakika, ardından 2 saat olmak üzere sınırlıdır ve toplam beş denemedir, yani uç noktanız aynı olayı birden fazla kez alabilir. Teslimatlar sırasız da gelebilir: `payment_revision` değeri en yüksek olan anlık görüntüyü saklayın.

## Hata yönetimi

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
