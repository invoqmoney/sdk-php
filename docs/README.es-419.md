# SDK de invoq para PHP

[English](../README.md) · [Bahasa Indonesia](./README.id.md) · **Español** · [Français](./README.fr.md) · [Português](./README.pt-BR.md) · [Tiếng Việt](./README.vi.md) · [Türkçe](./README.tr.md) · [ไทย](./README.th.md) · [简体中文](./README.zh-Hans.md) · [繁體中文](./README.zh-Hant.md)

> Este documento es una traducción del README en inglés; si algo difiere, vale la [versión en inglés](../README.md).

SDK de PHP para las APIs de servidor de invoq y la verificación de webhooks.

Usa este paquete solo en tu servidor. Acepta claves secretas y no debe incluirse en el código del navegador.

## SDKs de servidor

Crea facturas y verifica webhooks desde tu backend en cualquiera de estos lenguajes — la misma REST API y la misma firma de webhook. Este repositorio es el SDK de PHP.

| Lenguaje | Repositorio |
| --- | --- |
| Node.js | [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js) (`@invoq/server`) |
| Python | [github.com/invoqmoney/sdk-python](https://github.com/invoqmoney/sdk-python) |
| PHP | **este repositorio** |
| Go | [github.com/invoqmoney/sdk-go](https://github.com/invoqmoney/sdk-go) |
| Rust | [github.com/invoqmoney/sdk-rust](https://github.com/invoqmoney/sdk-rust) |
| Ruby | [github.com/invoqmoney/sdk-ruby](https://github.com/invoqmoney/sdk-ruby) |

El lado del navegador es el mismo para todos los backends: **`@invoq/checkout`** (JavaScript, en [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js)) abre la ventana de pago integrada en la página para cualquier frontend.

## Instalación

```bash
composer require invoq/invoq-php
```

Requiere PHP 8.1 o más nuevo.

## Consigue tus claves

1. Inicia sesión en el [panel de invoq](https://app.invoq.money) y crea un proyecto.
2. En la página **API keys**, crea una clave secreta. Las claves de prueba empiezan con `sk_test_`, las claves de producción con `sk_live_`.
3. En la configuración de **webhooks** de tu proyecto, guarda tu URL de webhook. El secreto del webhook (`whsec_...`) de ese modo se muestra una sola vez, cuando activas el webhook por primera vez. Guárdalo de inmediato.

Agrega ambos al entorno de tu servidor:

```bash
INVOQ_SECRET_KEY=sk_test_...
INVOQ_WEBHOOK_SECRET=whsec_...
```

## Crea un cliente

```php
<?php

use Invoq\Invoq;

$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY']);
```

Valor predeterminado de la API en producción:

```text
https://api.invoq.money
```

Sobrescribe el origin de la API durante el desarrollo:

```php
$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY'], [
    'apiOrigin' => 'http://localhost:8787',
    'timeoutMs' => 10_000,
]);
```

`apiOrigin` debe ser un origin `http` o `https` absoluto, sin partes de usuario, contraseña, ruta, query ni hash. El SDK agrega las rutas de recurso `/v1/...`. Las solicitudes expiran a los 10 segundos por defecto; pasa `timeoutMs` para sobrescribirlo.

## Crea una factura

```php
$invoice = $invoq->invoices->create([
    'amount' => '129',
    'currency' => 'USD',
    'description' => 'SaaS boilerplate',
    'reference_id' => 'order_1234',
    'return_url' => 'https://merchant.test/thanks',
]);
```

Define el monto en el servidor. No confíes en montos que manda el cliente. `amount` es una cadena decimal en USD de `'0.01'` a `'999.99'` con hasta 2 decimales, como `'129'` o `'129.99'`.

Usa un `reference_id` estable para vincular los webhooks `invoice.paid` con tu pedido. También hace que puedas reintentar la creación sin riesgo: si creas otra factura con el mismo `reference_id` y los mismos términos, recibes la factura existente en lugar de un duplicado; si los términos son distintos, falla con un error de API `409 reference_id_conflict`.

`description` y `reference_id` son cadenas opcionales en la solicitud. Omítelas cuando no tengan valor; no pases `null`. `return_url` es opcional y puede ser una cadena o `null`.

El SDK devuelve el objeto `data` de la respuesta directamente como un arreglo asociativo.

## Obtén una factura

```php
$invoice = $invoq->invoices->get('inv_123');
```

`get()` devuelve la forma de factura pública que usa el checkout. Incluye campos como `amount_paid`, `amount_due`, `payment_status`, `project`, `deposit_address`, `monitoring_ends_at` y `direct_onchain_rails`, pero no incluye `reference_id`. Usa la respuesta de creación o el webhook `invoice.paid` cuando necesites tu `reference_id` de comercio.

La API normaliza los montos en las respuestas: crea con `'129'` y la factura devuelve `amount: '129.0000'`. Compara montos numéricamente, no como cadenas. `amount_due` se deriva como `max(amount - amount_paid, 0)` y usa la misma escala de 18 decimales que `amount_paid`.

## Crea un pago de prueba

```php
$paidInvoice = $invoq->invoices->createTestPayment($invoice['id'], [
    'amount' => $invoice['amount'],
    'reference_id' => 'test_payment_001',
]);
```

Este endpoint requiere una clave secreta de prueba y solo funciona con facturas de prueba. Cuando los pagos alcanzan el monto de la factura, la factura pasa a `paid` e invoq envía un webhook `invoice.paid` firmado de verdad a tu URL de webhook de prueba. Se permiten montos parciales, que producen `partially_paid`.

`reference_id` es una cadena opcional en la solicitud. Omítelo cuando no tenga valor; no pases `null`.

El SDK devuelve el objeto `data` de la respuesta directamente como un arreglo asociativo.

## Verifica webhooks

Pasa el cuerpo sin procesar de la solicitud a `verifyWebhook`. No proceses el JSON ni lo vuelvas a codificar antes de la verificación.

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

Las fallas de verificación de webhooks lanzan `InvoqSignatureVerificationError`. `verifyWebhook` devuelve el evento de webhook decodificado como un arreglo asociativo.

`verifyWebhook` no requiere `new Invoq(...)` ni tu clave secreta de la API de invoq. Usa tu secreto de webhook, no `INVOQ_SECRET_KEY`.

Procesa los pedidos a partir de webhooks verificados, no de los resultados del checkout en el navegador. `isInvoicePaid($event)` devuelve true para eventos `invoice.paid` que permiten procesar pedidos y cuyo estado de factura es `paid`, `settling` o `settled`; rechaza `review_required`. Procesa de forma idempotente, porque las entregas de webhooks fallidas se reintentan.

## Manejo de errores

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

Las respuestas de API que no son 2xx lanzan `InvoqApiError` con `status`, el `code` de la API, `fields`, `meta` y el `payload` crudo. Las fallas de conexión, los tiempos de espera de la solicitud, las fallas al procesar la respuesta y las entradas inválidas lanzan `InvoqError`.

Usa `$error->getApiCode()` para los códigos de error de la API de invoq. El `$error->getCode()` integrado de PHP devuelve el código de la excepción, no el código de error de la API.

Las fallas de verificación de webhooks lanzan `InvoqSignatureVerificationError` con uno de estos códigos:

```text
missing_signature
invalid_signature_header
timestamp_outside_tolerance
signature_mismatch
invalid_payload
```

Léelo con `$error->getSignatureCode()`.

## Desarrollo

```bash
composer validate --strict
composer dump-autoload
composer test
```
