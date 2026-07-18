# SDK PHP invoq

[English](../README.md) · [Bahasa Indonesia](./README.id.md) · [Español](./README.es-419.md) · **Français** · [Português](./README.pt-BR.md) · [Tiếng Việt](./README.vi.md) · [Türkçe](./README.tr.md) · [ไทย](./README.th.md) · [简体中文](./README.zh-Hans.md) · [繁體中文](./README.zh-Hant.md)

> Ce document est une traduction du README anglais ; en cas de divergence, la [version anglaise](../README.md) fait foi.

SDK PHP pour les API serveur d’invoq et la vérification des webhooks.

N’utilisez ce paquet que sur votre serveur. Il accepte des clés secrètes et ne doit pas être intégré à du code côté navigateur.

## SDK serveur

Créez des factures et vérifiez les webhooks depuis votre backend dans l’un de ces langages — même REST API, même signature de webhook. Ce dépôt est le SDK PHP.

| Langage | Dépôt |
| --- | --- |
| Node.js | [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js) (`@invoq/server`) |
| Python | [github.com/invoqmoney/sdk-python](https://github.com/invoqmoney/sdk-python) |
| PHP | **ce dépôt** |
| Go | [github.com/invoqmoney/sdk-go](https://github.com/invoqmoney/sdk-go) |
| Rust | [github.com/invoqmoney/sdk-rust](https://github.com/invoqmoney/sdk-rust) |
| Ruby | [github.com/invoqmoney/sdk-ruby](https://github.com/invoqmoney/sdk-ruby) |

Quel que soit le backend choisi, le côté navigateur reste le même : **`@invoq/checkout`** (JavaScript, dans [github.com/invoqmoney/sdk-js](https://github.com/invoqmoney/sdk-js)) ouvre la fenêtre de paiement intégrée à la page pour n’importe quel frontend.

## Installation

```bash
composer require invoq/invoq-php
```

Nécessite PHP 8.1 ou plus récent.

## Récupérez vos clés

1. Connectez-vous au [tableau de bord invoq](https://app.invoq.money) et créez un projet.
2. Sur la page **API keys**, créez une clé secrète. Les clés de test commencent par `sk_test_`, les clés de production par `sk_live_`.
3. Dans les réglages **webhooks** de votre projet, enregistrez votre URL de webhook. Le secret du webhook (`whsec_...`) pour ce mode ne s’affiche qu’une seule fois, à la première activation du webhook — notez-le tout de suite.

Ajoutez les deux à l’environnement de votre serveur :

```bash
INVOQ_SECRET_KEY=sk_test_...
INVOQ_WEBHOOK_SECRET=whsec_...
```

## Créez un client

```php
<?php

use Invoq\Invoq;

$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY']);
```

Valeur par défaut de l’API en production :

```text
https://api.invoq.money
```

Surchargez l’origine de l’API en développement local :

```php
$invoq = new Invoq($_ENV['INVOQ_SECRET_KEY'], [
    'apiOrigin' => 'http://localhost:8787',
    'timeoutMs' => 10_000,
]);
```

`apiOrigin` doit être une origine `http` ou `https` absolue, sans nom d’utilisateur, mot de passe, chemin, chaîne de requête ni fragment. Le SDK y ajoute les chemins de ressources `/v1/...`. Les requêtes expirent au bout de 10 secondes par défaut ; passez `timeoutMs` pour changer cette valeur.

## Créez une facture

```php
$invoice = $invoq->invoices->create([
    'amount' => '129',
    'currency' => 'USD',
    'description' => 'SaaS boilerplate',
    'reference_id' => 'order_1234',
    'return_url' => 'https://merchant.test/thanks',
]);
```

Définissez le montant côté serveur. Ne faites pas confiance aux montants envoyés par le client. `amount` est une chaîne décimale en USD de `'0.01'` à `'1000000.00'`, avec au plus 2 décimales, comme `'129'` ou `'129.99'`.

Utilisez un `reference_id` stable pour relier les webhooks `invoice.paid` à votre commande. Il permet aussi de relancer la création sans risque : si vous recréez avec le même `reference_id` et les mêmes conditions, vous récupérez la facture existante au lieu d’un doublon ; avec des conditions différentes, l’appel échoue avec une erreur d’API `409 reference_id_conflict`.

`description` et `reference_id` sont des chaînes optionnelles dans la requête. Omettez-les lorsqu’elles ne sont pas définies ; ne passez pas `null`. `return_url` est optionnel et peut être une chaîne ou `null`.

Le SDK renvoie directement l’objet `data` de la réponse sous forme de tableau associatif.

## Récupérez une facture

```php
$invoice = $invoq->invoices->get('inv_123');
```

`get()` renvoie la forme de facture publique utilisée par la page de paiement. Elle inclut des champs tels que `amount_paid`, `amount_due`, `amount_overpaid`, `payment_status`, `project`, `deposit_address`, `monitoring_ends_at`, `monitoring_status`, `transfers` et `direct_onchain_rails`, mais n’inclut pas `reference_id`. Utilisez la réponse de création ou le webhook `invoice.paid` quand vous avez besoin de votre `reference_id` marchand.

Les montants des réponses sont normalisés par l’API : créez avec `'129'` et la facture renvoie `amount: '129.0000'`. Comparez les montants numériquement, pas comme des chaînes. `amount_due` est dérivé sous la forme `max(amount - amount_paid, 0)` et utilise la même échelle à 18 décimales que `amount_paid` ; `amount_overpaid` en est le miroir, `max(amount_paid - amount, 0)`, si bien que vous n’avez jamais à soustraire d’argent vous-même. `monitoring_status` vaut `'active'` ou `'ended'` — une fois à `'ended'`, l’adresse de dépôt n’est plus surveillée — et `transfers` est le journal confirmé des encaissements on-chain (chaque entrée a `tx_hash`, `amount` et `explorer_tx_url`). Les deux valent `null` / `[]` pour les factures de test.

## Créez un paiement de test

```php
$paidInvoice = $invoq->invoices->createTestPayment($invoice['id'], [
    'amount' => $invoice['amount'],
    'reference_id' => 'test_payment_001',
]);
```

Cet endpoint nécessite une clé secrète de test et ne fonctionne que sur les factures de test. Quand les paiements atteignent le montant de la facture, celle-ci passe à `paid` et invoq envoie un vrai webhook `invoice.paid` signé à votre URL de webhook de test. Les montants partiels sont autorisés et produisent `partially_paid`.

`reference_id` est une chaîne optionnelle dans la requête. Omettez-le lorsqu’il n’est pas défini ; ne passez pas `null`.

Le SDK renvoie directement l’objet `data` de la réponse sous forme de tableau associatif.

## Vérifiez les webhooks

Passez le corps brut de la requête à `verifyWebhook`. N’analysez pas le JSON pour le réencoder avant la vérification.

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

Les échecs de vérification de webhook lèvent `InvoqSignatureVerificationError`. `verifyWebhook` renvoie l’événement de webhook décodé sous forme de tableau associatif.

`verifyWebhook` ne nécessite ni `new Invoq(...)` ni votre clé secrète d’API invoq. Utilisez votre secret de webhook, pas `INVOQ_SECRET_KEY`.

Traitez les commandes à partir des webhooks vérifiés, pas des résultats renvoyés par le navigateur. `isInvoicePaid($event)` renvoie true pour les événements `invoice.paid` permettant de traiter une commande, dont le statut de facture est `paid`, `settling` ou `settled` ; il rejette `review_required`. Gérez le traitement des commandes de façon idempotente, car les livraisons de webhook échouées sont retentées.

## Gestion des erreurs

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

Les réponses d’API non 2xx lèvent `InvoqApiError` avec `status`, le `code` d’API, `fields`, `meta` et le `payload` brut. Les échecs de connexion, les délais d’attente dépassés, les échecs d’analyse de la réponse et les entrées invalides lèvent `InvoqError`.

Utilisez `$error->getApiCode()` pour les codes d’erreur de l’API invoq. La méthode `$error->getCode()` native de PHP renvoie le code de l’exception, pas le code d’erreur de l’API.

Les échecs de vérification de webhook lèvent `InvoqSignatureVerificationError` avec l’un de ces codes :

```text
missing_signature
invalid_signature_header
timestamp_outside_tolerance
signature_mismatch
invalid_payload
```

Lisez-le avec `$error->getSignatureCode()`.

## Développement

```bash
composer validate --strict
composer dump-autoload
composer test
```
