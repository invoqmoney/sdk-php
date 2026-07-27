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
4. Configure a sua **Receiving wallet** antes de ir para produção. Faturas de
   teste não precisam dela; uma fatura real sem destino de liquidação falha com
   `409 no_payment_options_available`.

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
    'description' => 'SaaS boilerplate',
    'reference_id' => 'order_1234',
    'return_url' => 'https://merchant.test/thanks',
]);
```

Use um valor definido no servidor. Não confie em valores vindos do cliente.
`amount` é uma string decimal em USD de `'0.01'` a `'1000000.00'`, com até 2 casas
decimais, como `'129'` ou `'129.99'`. A moeda é sempre USD, e teste ou live vem
da chave — nenhum dos dois é campo da requisição.

Use um `reference_id` estável para ligar os webhooks `invoice.paid` ao seu
pedido. Ele também deixa a criação segura para repetir: se você criar de novo
com o mesmo `reference_id` e os mesmos termos da fatura, recebe a fatura
existente em vez de uma duplicata; com termos diferentes, a chamada falha com o
erro de API `409 reference_id_conflict`.

`amount`, `description`, `reference_id` e `return_url` são os únicos campos da
requisição. `description` e `reference_id` são strings opcionais da requisição.
Omita-os quando não estiverem definidos; não passe `null`. `return_url` é
opcional e pode ser uma string ou `null`. Qualquer outra chave que você passar é
descartada em vez de enviada, porque a API rejeita chaves de corpo desconhecidas
com `400 invalid_request` e `fields[].code: "unknown_field"`.

O SDK retorna o objeto `data` da resposta diretamente como um array associativo.
Ele traz o resumo da fatura mais `status`, `checkout_status`,
`payment_revision`, `amount_due`, `amount_overpaid`, `monitoring_ends_at` e
`payment_options`.

## Busque uma fatura

```php
$invoice = $invoq->invoices->get('inv_123');
```

`get()` retorna o formato de fatura pública usado pelo checkout: o formato da
resposta de criação mais `project`, `amount_paid` e `transfers`, sem
`reference_id`. Use a resposta de criação ou o webhook `invoice.paid` quando
precisar do seu `reference_id` de comerciante.

Dois campos de status. `status` é o contábil — `unpaid`, `partially_paid`,
`paid`, `settling`, `settled`, `review_required` — e os três valores
equivalentes a pago diferem apenas em quanto os fundos já andaram até a sua
carteira. `checkout_status` é o que o pagador vê — `open`, `confirming`,
`expired`, `paid`, `unavailable` — e nunca autoriza processar o pedido.
`payment_revision` é um inteiro não negativo que sobe sempre que o conjunto de
pagamentos confirmados muda, então você pode descartar um snapshot mais antigo
do que o que já tem.

Os valores nas respostas são normalizados pela API: crie com `'129'` e a fatura
devolve `amount: '129.0000'`. Compare valores numericamente, não como texto.
`amount_due` é derivado como `max(amount - amount_paid, 0)` e usa a mesma escala
de 18 casas decimais de `amount_paid`; `amount_overpaid` é o espelho dele,
`max(amount_paid - amount, 0)`, então você nunca precisa subtrair dinheiro por
conta própria.

`payment_options` guarda as instruções de pagamento, fixadas na criação e `[]`
no modo de teste. As entradas são discriminadas por `status` e depois por
`collection_method`: só `'ready'` é pagável, `'evm_deposit'` traz
`deposit_address` e `suggested_amount`, `'direct_exact'` traz
`recipient_address` e um `exact_amount` que o comprador precisa enviar até o
último dígito. Identifique uma opção por
`(chain_namespace, chain_reference, token_address)`, nunca pela posição dela no
array. `monitoring_ends_at` fecha a janela de pagamento e é `null` no modo de
teste. `transfers` é o registro confirmado de recebimentos — `transaction_id`,
`event_index`, `amount`, `explorer_transaction_url` — e fica `[]` até um
pagamento confirmar. Referência completa dos campos:
[documentação da API REST](https://github.com/invoqmoney/api).

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

O SDK retorna o objeto `data` da resposta diretamente como um array associativo:
o formato da resposta de criação mais `amount_paid` e `fully_paid_at`.

## Verifique webhooks

Passe o corpo bruto da requisição para `verifyWebhook`. Não interprete o JSON
nem o codifique de novo antes da verificação.

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

Falhas na verificação de webhook lançam `InvoqSignatureVerificationError`. O
`verifyWebhook` retorna o evento de webhook decodificado como um array
associativo.

O `verifyWebhook` não precisa de `new Invoq(...)` nem da sua chave secreta de API
da invoq. Use o seu segredo de webhook, não a `INVOQ_SECRET_KEY`.

Processe os pedidos a partir de webhooks verificados, não de resultados de
checkout no navegador. `isInvoicePaid($event)` retorna true para eventos
`invoice.paid` processáveis, cujo status da fatura é `paid`, `settling` ou
`settled`; ele rejeita `review_required`.

A invoq também envia `invoice.payment_reversed` quando uma fatura já paga volta
a ficar abaixo do valor dela — por exemplo, quando uma reorganização da chain
derruba uma transferência confirmada. Capture com
`isInvoicePaymentReversed($event)` e segure ou reverta o processamento conforme
a sua própria política. Essa checagem aceita de propósito qualquer status de
fatura: descartar uma reversão deixaria um pedido processado em cima de um
pagamento que não existe mais. Um tipo de evento que esta versão do SDK ainda
não modela continua sendo verificado e devolvido como veio.

Os dois eventos trazem o mesmo `data['invoice']`: `id`, `mode`, `status`,
`amount`, `currency`, `amount_paid`, `reference_id`, `payment_revision` e
`fully_paid_at`. As instruções de pagamento e o `return_url` estão ausentes de
propósito — reconcilie pelo id da fatura mais o `reference_id`.

Processe de forma idempotente. Toda resposta não 2xx a uma entrega — inclusive
redirecionamentos e `4xx` — mais erros de rede e tempos de espera esgotados é
reenviada em uma escala limitada de 1 minuto, 5 minutos, 30 minutos e depois 2
horas, cinco tentativas no total, então a sua rota pode receber o mesmo evento
mais de uma vez. As entregas também podem chegar fora de ordem: fique com o
snapshot de maior `payment_revision`.

## Tratamento de erros

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
