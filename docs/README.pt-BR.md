# SDK PHP da invoq

[English](../README.md) · [Bahasa Indonesia](./README.id.md) · [Español](./README.es-419.md) · [Français](./README.fr.md) · **Português** · [Tiếng Việt](./README.vi.md) · [Türkçe](./README.tr.md) · [ไทย](./README.th.md) · [简体中文](./README.zh-Hans.md) · [繁體中文](./README.zh-Hant.md)

> Este documento é uma tradução do README em inglês; se algo divergir, vale a [versão em inglês](../README.md).

SDK de PHP para as APIs de servidor da invoq e a verificação de webhooks.

Use este pacote apenas no seu servidor. Ele aceita chaves secretas e não deve ser incluído no código do navegador.

## SDKs de servidor

Crie faturas e verifique webhooks a partir do seu backend em qualquer uma destas linguagens — mesma REST API, mesma assinatura de webhook. Este repositório é o SDK de PHP.

| Linguagem | Repositório |
| --- | --- |
| Node.js | [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js) (`@invoq/server`) |
| Python | [github.com/invoqmoney/sdk-python](https://github.com/invoqmoney/sdk-python) |
| PHP | **este repositório** |
| Go | [github.com/invoqmoney/sdk-go](https://github.com/invoqmoney/sdk-go) |
| Rust | [github.com/invoqmoney/sdk-rust](https://github.com/invoqmoney/sdk-rust) |
| Ruby | [github.com/invoqmoney/sdk-ruby](https://github.com/invoqmoney/sdk-ruby) |

O lado do navegador é o mesmo para qualquer backend: **`@invoq/checkout`** (JavaScript, em [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js)) abre a janela de checkout dentro da página para qualquer frontend.

## Instalação

```bash
composer require invoq/invoq-php
```

Requer PHP 8.1 ou mais novo.

## Pegue suas chaves

1. Entre no [painel da invoq](https://app.invoq.money) e crie um projeto.
2. Na página **API keys**, crie uma chave secreta. Chaves de teste começam com
   `sk_test_`, chaves de produção com `sk_live_`.
3. Nas configurações de **webhooks** do projeto, salve a URL do seu webhook. O
   segredo do webhook (`whsec_...`) daquele modo aparece uma única vez, quando
   você ativa o webhook pela primeira vez — guarde na hora.

Adicione os dois ao ambiente do seu servidor:

```bash
INVOQ_SECRET_KEY=sk_test_...
INVOQ_WEBHOOK_SECRET=whsec_...
```

## Crie um cliente

```php
<?php

use Invoq\Invoq;

$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY']);
```

Padrão da API em produção:

```text
https://api.invoq.money
```

Sobrescreva o origin da API durante o desenvolvimento:

```php
$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY'], [
    'apiOrigin' => 'http://localhost:8787',
    'timeoutMs' => 10_000,
]);
```

`apiOrigin` precisa ser um origin `http` ou `https` absoluto, sem partes de
usuário, senha, caminho, query ou hash. O SDK anexa os caminhos de recurso
`/v1/...`. As requisições expiram em 10 segundos por padrão; passe `timeoutMs`
para sobrescrever esse valor.

## Crie uma fatura

```php
$invoice = $invoq->invoices->create([
    'amount' => '129',
    'currency' => 'USD',
    'description' => 'SaaS boilerplate',
    'reference_id' => 'order_1234',
    'return_url' => 'https://merchant.test/thanks',
]);
```

Use um valor definido no servidor. Não confie em valores vindos do cliente.
`amount` é uma string decimal em USD de `'0.01'` a `'999.99'`, com até 2 casas
decimais, como `'129'` ou `'129.99'`.

Use um `reference_id` estável para ligar os webhooks `invoice.paid` ao seu
pedido. Ele também deixa a criação segura para repetir: se você criar de novo
com o mesmo `reference_id` e os mesmos termos da fatura, recebe a fatura
existente em vez de uma duplicata; com termos diferentes, a chamada falha com o
erro de API `409 reference_id_conflict`.

`description` e `reference_id` são strings opcionais da requisição. Omita-os
quando não estiverem definidos; não passe `null`. `return_url` é opcional e pode
ser uma string ou `null`.

O SDK retorna o objeto `data` da resposta diretamente como um array associativo.

## Busque uma fatura

```php
$invoice = $invoq->invoices->get('inv_123');
```

`get()` retorna o formato de fatura pública usado pelo checkout. Ele inclui
campos como `amount_paid`, `amount_due`, `amount_overpaid`, `payment_status`,
`project`, `deposit_address`, `monitoring_ends_at`, `monitoring_status`,
`transfers` e `direct_onchain_rails`, mas não inclui `reference_id`. Use a
resposta de criação ou o webhook `invoice.paid` quando precisar do seu
`reference_id` de comerciante.

Os valores nas respostas são normalizados pela API: crie com `'129'` e a fatura
devolve `amount: '129.0000'`. Compare valores numericamente, não como texto.
`amount_due` é derivado como `max(amount - amount_paid, 0)` e usa a mesma escala
de 18 casas decimais de `amount_paid`; `amount_overpaid` é o espelho dele,
`max(amount_paid - amount, 0)`, então você nunca precisa subtrair dinheiro por
conta própria. `monitoring_status` é `'active'` ou `'ended'` — assim que fica
`'ended'`, o endereço de depósito deixa de ser monitorado — e `transfers` é o
registro confirmado de recebimentos on-chain (cada entrada tem `tx_hash`,
`amount` e `explorer_tx_url`). Ambos são `null` / `[]` em faturas de teste.

## Crie um pagamento de teste

```php
$paidInvoice = $invoq->invoices->createTestPayment($invoice['id'], [
    'amount' => $invoice['amount'],
    'reference_id' => 'test_payment_001',
]);
```

Este endpoint exige uma chave secreta de teste e só funciona em faturas de
teste. Quando os pagamentos atingem o valor da fatura, ela vira `paid` e a invoq
envia um webhook `invoice.paid` assinado de verdade para a sua URL de webhook de
teste. Valores parciais são permitidos e produzem `partially_paid`.

`reference_id` é uma string opcional da requisição. Omita-o quando não estiver
definido; não passe `null`.

O SDK retorna o objeto `data` da resposta diretamente como um array associativo.

## Verifique webhooks

Passe o corpo bruto da requisição para `verifyWebhook`. Não interprete o JSON
nem o codifique de novo antes da verificação.

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

Falhas na verificação de webhook lançam `InvoqSignatureVerificationError`. O
`verifyWebhook` retorna o evento de webhook decodificado como um array
associativo.

O `verifyWebhook` não precisa de `new Invoq(...)` nem da sua chave secreta de API
da invoq. Use o seu segredo de webhook, não a `INVOQ_SECRET_KEY`.

Processe os pedidos a partir de webhooks verificados, não de resultados de
checkout no navegador. `isInvoicePaid($event)` retorna true para eventos
`invoice.paid` processáveis, cujo status da fatura é `paid`, `settling` ou
`settled`; ele rejeita `review_required`. Processe de forma idempotente, porque
entregas de webhook que falham são reenviadas.

## Tratamento de erros

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

Respostas de API não 2xx lançam `InvoqApiError` com `status`, `code` de API,
`fields`, `meta` e o `payload` bruto. Falhas de conexão, tempos de espera
esgotados, falhas ao interpretar a resposta e entrada inválida lançam
`InvoqError`.

Use `$error->getApiCode()` para os códigos de erro da API da invoq. O
`$error->getCode()` nativo do PHP retorna o código da exceção, não o código de
erro da API.

Falhas na verificação de webhook lançam `InvoqSignatureVerificationError` com um
destes códigos:

```text
missing_signature
invalid_signature_header
timestamp_outside_tolerance
signature_mismatch
invalid_payload
```

Leia-o com `$error->getSignatureCode()`.

## Desenvolvimento

```bash
composer validate --strict
composer dump-autoload
composer test
```
