# SDK de invoq para PHP

[English](../README.md) · [Bahasa Indonesia](./README.id.md) · **Español** · [Français](./README.fr.md) · [Português](./README.pt-BR.md) · [Tiếng Việt](./README.vi.md) · [Türkçe](./README.tr.md) · [ไทย](./README.th.md) · [简体中文](./README.zh-Hans.md) · [繁體中文](./README.zh-Hant.md)

> Este documento es una traducción del README en inglés; si algo difiere, vale la [versión en inglés](../README.md).

SDK de PHP para las APIs de servidor de invoq y la verificación de webhooks.

Usa este paquete solo en tu servidor. Acepta claves secretas y no debe incluirse en el código del navegador.

**¿Programas con IA? Pega esto.**

```
Agrega pagos con stablecoins a mi proyecto con invoq. Empieza en modo de prueba. Lee la documentación antes de escribir código: https://invoq.money/llms.txt
```

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
4. Configura tu **Receiving wallet** antes de pasar a producción. Las facturas de prueba no la necesitan; una factura real sin destino de liquidación falla con `409 no_payment_options_available`.

Agrega ambos al entorno de tu servidor:

```bash
INVOQ_SECRET_KEY=sk_test_...
INVOQ_WEBHOOK_SECRET=whsec_...
```

## Crea un cliente

```php
<?php

use Invoq\Invoq;

$invoq = new Invoq(getenv('INVOQ_SECRET_KEY'));
```

Valor predeterminado de la API en producción:

```text
https://api.invoq.money
```

Sobrescribe el origin de la API durante el desarrollo:

```php
$invoq = new Invoq(getenv('INVOQ_SECRET_KEY'), [
    'apiOrigin' => 'http://localhost:8787',
    'timeoutMs' => 10_000,
]);
```

`apiOrigin` debe ser un origin `http` o `https` absoluto, sin partes de usuario, contraseña, ruta, query ni hash. El SDK agrega las rutas de recurso `/v1/...`. Las solicitudes expiran a los 10 segundos por defecto; pasa `timeoutMs` para sobrescribirlo.

## Crea una factura

```php
$invoice = $invoq->invoices->create([
    'amount' => '129',
    'description' => 'SaaS boilerplate',
    'reference_id' => 'order_1234',
    'return_url' => 'https://merchant.test/thanks',
]);
```

Define el monto en el servidor. No confíes en montos que manda el cliente. `amount` es una cadena decimal en USD de `'0.01'` a `'1000000.00'` con hasta 2 decimales, como `'129'` o `'129.99'`. La moneda siempre es USD, y el modo de prueba o real viene de la clave — ninguno de los dos es un campo de la solicitud.

Usa un `reference_id` estable para vincular los webhooks `invoice.paid` con tu pedido. También hace que puedas reintentar la creación sin riesgo: si creas otra factura con el mismo `reference_id` y los mismos términos, recibes la factura existente en lugar de un duplicado; si los términos son distintos, falla con un error de API `409 reference_id_conflict`.

`amount`, `description`, `reference_id` y `return_url` son los únicos campos de la solicitud. `description` y `reference_id` son cadenas opcionales en la solicitud. Omítelas cuando no tengan valor; no pases `null`. `return_url` es opcional y puede ser una cadena o `null`. Cualquier otra clave que pases se descarta en lugar de enviarse, porque la API rechaza las claves de cuerpo desconocidas con `400 invalid_request` y `fields[].code: "unknown_field"`.

El SDK devuelve el objeto `data` de la respuesta directamente como un arreglo asociativo. Trae el resumen de la factura más `status`, `checkout_status`, `payment_revision`, `amount_due`, `amount_overpaid`, `monitoring_ends_at` y `payment_options`.

## Obtén una factura

```php
$invoice = $invoq->invoices->get('inv_123');
```

`get()` devuelve la forma de factura pública que usa el checkout: la forma de la respuesta de creación más `project`, `amount_paid` y `transfers`, y sin `reference_id`. Usa la respuesta de creación o el webhook `invoice.paid` cuando necesites tu `reference_id` de comercio.

Dos campos de estado. `status` es el contable — `unpaid`, `partially_paid`, `paid`, `settling`, `settled`, `review_required` — y los tres valores equivalentes a pagada solo se diferencian en qué tan lejos llegaron los fondos hacia tu billetera. `checkout_status` es el que ve quien paga — `open`, `confirming`, `expired`, `paid`, `unavailable` — y nunca autoriza procesar el pedido. `payment_revision` es un entero no negativo que sube cada vez que cambia el conjunto de pagos confirmados, así descartas una instantánea más vieja que la que ya tienes.

La API normaliza los montos en las respuestas: crea con `'129'` y la factura devuelve `amount: '129.0000'`. Compara montos numéricamente, no como cadenas. `amount_due` se deriva como `max(amount - amount_paid, 0)` y usa la misma escala de 18 decimales que `amount_paid`; `amount_overpaid` es su reflejo, `max(amount_paid - amount, 0)`, así que nunca restas dinero por tu cuenta.

`payment_options` contiene las instrucciones de pago, fijadas al crear la factura y `[]` en modo de prueba. Las entradas se discriminan por `status` y luego por `collection_method`: solo `'ready'` es pagable, `'evm_deposit'` trae `deposit_address` y `suggested_amount`, `'direct_exact'` trae `recipient_address` y un `exact_amount` que el comprador debe enviar hasta el último dígito. Identifica una opción por `(chain_namespace, chain_reference, token_address)`, nunca por su posición en el arreglo. `monitoring_ends_at` cierra la ventana de pago y es `null` en modo de prueba. `transfers` es el registro confirmado de recepciones — `transaction_id`, `event_index`, `amount`, `explorer_transaction_url` — y queda en `[]` hasta que se confirme un pago. Referencia completa de campos: [documentación de la API REST](https://github.com/invoqmoney/api).

## Crea un pago de prueba

```php
$paidInvoice = $invoq->invoices->createTestPayment($invoice['id'], [
    'amount' => $invoice['amount'],
    'reference_id' => 'test_payment_001',
]);
```

Este endpoint requiere una clave secreta de prueba y solo funciona con facturas de prueba. Cuando los pagos alcanzan el monto de la factura, la factura pasa a `paid` e invoq envía un webhook `invoice.paid` firmado de verdad a tu URL de webhook de prueba. Se permiten montos parciales, que producen `partially_paid`.

`reference_id` es una cadena opcional en la solicitud. Omítelo cuando no tenga valor; no pases `null`.

El SDK devuelve el objeto `data` de la respuesta directamente como un arreglo asociativo: la forma de la respuesta de creación más `amount_paid` y `fully_paid_at`.

## Página de pago alojada

Cada factura también tiene una página de pago alojada en:

```text
https://pay.invoq.money/<id de factura>
```

Comparte el enlace o redirige ahí cuando una ventana de pago integrada en la página no encaje.

## Verifica webhooks

Pasa el cuerpo sin procesar de la solicitud a `verifyWebhook`. No proceses el JSON ni lo vuelvas a codificar antes de la verificación.

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

Las fallas de verificación de webhooks lanzan `InvoqSignatureVerificationError`. `verifyWebhook` devuelve el evento de webhook decodificado como un arreglo asociativo.

`verifyWebhook` no requiere `new Invoq(...)` ni tu clave secreta de la API de invoq. Usa tu secreto de webhook, no `INVOQ_SECRET_KEY`.

Procesa los pedidos a partir de webhooks verificados, no de los resultados del checkout en el navegador. `isInvoicePaid($event)` devuelve true para eventos `invoice.paid` que permiten procesar pedidos y cuyo estado de factura es `paid`, `settling` o `settled`; rechaza `review_required`.

invoq también envía `invoice.payment_reversed` cuando una factura ya pagada vuelve a quedar por debajo de su monto — por ejemplo, si una reorganización de la cadena descarta una transferencia confirmada. Detéctalo con `isInvoicePaymentReversed($event)` y retén o revierte el procesamiento según tu propia política. Esa comprobación acepta a propósito cualquier estado de factura: descartar una reversión dejaría un pedido procesado sobre un pago que ya no existe. Un tipo de evento que esta versión del SDK no modela igual se verifica y se devuelve tal cual.

Ambos eventos traen el mismo `data['invoice']`: `id`, `mode`, `status`, `amount`, `currency`, `amount_paid`, `reference_id`, `payment_revision` y `fully_paid_at`. Las instrucciones de pago y `return_url` están ausentes a propósito — concilia por id de factura y `reference_id`.

Procesa de forma idempotente. Toda respuesta que no sea 2xx a una entrega — incluidos los redireccionamientos y los `4xx` — más los errores de red y los tiempos de espera agotados se reintenta en una escala acotada de 1 minuto, 5 minutos, 30 minutos y luego 2 horas, cinco intentos en total, así que tu endpoint puede recibir el mismo evento más de una vez. Las entregas también pueden llegar desordenadas: quédate con la instantánea que tenga el `payment_revision` más alto.

## Manejo de errores

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
