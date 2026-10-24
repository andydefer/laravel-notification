# MailDriver - Référence Technique

## Description

Driver d'envoi d'emails via le facade `Mail` de Laravel. Transforme un `NotificationMessageVO` et une `NotificationRouteVO` en un email HTML avec pièces jointes optionnelles, CC, BCC, reply-to, priorité et override d'expéditeur.

## Hiérarchie / Implémentations

```
AbstractDriver
    └── MailDriver
```

## Rôle principal

Ce driver est le **maillon d'exécution** du canal `MailChannel`. Sa philosophie stricte :

- **`$route`** ne porte que la **destination** (`$to`)
- **`$message`** porte le **contenu** (`subject`, `body`) et les **options** (`metadata`)

Toutes les options de transport (attachments, cc, bcc, reply-to, priority, from override) viennent de `$message->getMetadata()`. La route reste minimaliste : un email, rien d'autre.

## Installation

```bash
composer require laravel/framework
```

Configuration Laravel standard dans `config/mail.php`. Le driver utilise `Mail::send()`, donc tous les mailers Laravel (`smtp`, `log`, `array`, `sendmail`, `ses`…) fonctionnent sans modification.

## API / Méthodes publiques

### `__construct(MailConfigRecord $config)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$config` | `MailConfigRecord` | Configuration mail (enabled, default_from, default_from_name) |

**Exemple :**
```php
$config = new MailConfigRecord(
    enabled: true,
    default_from: 'noreply@afya.com',
    default_from_name: 'Afya Medical',
);

$driver = new MailDriver($config);
```

---

### `execute(NotificationMessageVO $message, NotificationRouteVO $route): bool`

**Méthode protégée** héritée d'`AbstractDriver`, exposée via `send()`. Effectue l'envoi réel.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `NotificationMessageVO` | Message (body, subject, metadata) |
| `$route` | `NotificationRouteVO` | Route (destination = email) |

**Retourne :** `bool` — `true` si l'envoi réussit.

**Exceptions :**
- `RuntimeException` — Configuration invalide
- `RuntimeException` — Destination vide ou non-string

**Exemple :**
```php
$route = new NotificationRouteVO(
    channelClass: MailChannel::class,
    destination: 'client@afya.com',
);

$message = new NotificationMessageVO(
    body: new MessageBodyVO('<h1>Bienvenue</h1>'),
    subject: new MessageSubjectVO('Bienvenue sur Afya'),
    type: 'welcome',
);

$result = $driver->send($message, $route);

if (! $result->success) {
    Log::error($result->error_message?->getValue());
}
```

---

### `getChannel(): string`

**Retourne :** `string` — Toujours `'mail'`.

---

### `validateConfiguration(): bool`

**Retourne :** `bool` — `true` si `enabled=true` et `default_from !== null`.

**Exemple :**
```php
if (! $driver->validateConfiguration()) {
    throw new RuntimeException('Mail non configuré');
}
```

## Options de `metadata`

Toutes les clés ci-dessous sont lues dans `$message->getMetadata()`. Toutes sont **optionnelles**.

| Clé | Type | Description |
|-----|------|-------------|
| `attachments` | `string\|array` | Pièces jointes : chemin seul, array de chemins, ou array d'objets `{path, as, mime}` |
| `cc` | `string\|string[]` | Destinataires en copie |
| `bcc` | `string\|string[]` | Destinataires en copie cachée |
| `reply_to` | `string` | Adresse de réponse |
| `priority` | `int` | Priorité : `1` (haute), `3` (normale), `5` (basse) |
| `from` | `string` | Override de l'expéditeur |
| `from_name` | `string` | Nom d'affichage de l'expéditeur |

## Cas d'utilisation

### Cas 1 : Email simple

Envoi avec les valeurs par défaut de la config.

```php
$message = new NotificationMessageVO(
    body: new MessageBodyVO('<h1>Bienvenue</h1>'),
    subject: new MessageSubjectVO('Bienvenue'),
    type: 'welcome',
);

$driver->send($message, $route);
// from = config.default_from
// to = route.destination
// attachments, cc, bcc = aucun
```

---

### Cas 2 : Facture avec pièces jointes

Pièces jointes locales avec renommage et MIME.

```php
$message = new NotificationMessageVO(
    body: new MessageBodyVO('<h1>Votre facture</h1>'),
    subject: new MessageSubjectVO('Facture #1234'),
    type: 'invoice',
    metadata: new StrictDataObject([
        'attachments' => [
            '/storage/invoices/1234.pdf',
            [
                'path' => '/storage/invoices/1234-summary.pdf',
                'as' => 'résumé.pdf',
                'mime' => 'application/pdf',
            ],
        ],
        'cc' => ['comptable@afya.com'],
        'priority' => 1,
        'from' => 'facturation@afya.com',
        'from_name' => 'Service Facturation',
    ]),
);

$driver->send($message, $route);
```

---

### Cas 3 : Notification avec reply-to

Réponse redirigée vers le support, expéditeur normal.

```php
$message = new NotificationMessageVO(
    body: new MessageBodyVO('<p>Votre ticket a été pris en charge.</p>'),
    subject: new MessageSubjectVO('Ticket #5678'),
    type: 'support_ticket',
    metadata: new StrictDataObject([
        'reply_to' => 'support@afya.com',
        'bcc' => 'audit@afya.com',
    ]),
);

$driver->send($message, $route);
```

## Flux d'exécution

```
execute()
  │
  ├─→ validateConfiguration()     [enabled + default_from]
  │     └─ si false → RuntimeException
  │
  ├─→ route->getDestination()
  │     └─ si vide/non-string → RuntimeException
  │
  ├─→ message->getSubjectValue()  → $subject
  ├─→ message->getBodyValue()     → $body
  ├─→ message->getMetadata()      → ?StrictDataObject
  │
  ├─→ resolveAttachments($metadata)   → array<{path, options}>
  ├─→ resolveRecipients($metadata, 'cc')
  ├─→ resolveRecipients($metadata, 'bcc')
  ├─→ resolveString($metadata, 'reply_to')
  ├─→ resolvePriority($metadata)      → ?int (1..5)
  ├─→ resolveString($metadata, 'from')      ?? config->default_from
  ├─→ resolveString($metadata, 'from_name') ?? config->default_from_name
  │
  └─→ Mail::send([], [], closure($mailMessage))
        ├─ from($from, $fromName)     si $from non null
        ├─ to($to)
        ├─ subject($subject)
        ├─ html($body)
        ├─ cc(...) pour chaque $cc
        ├─ bcc(...) pour chaque $bcc
        ├─ replyTo($replyTo)          si non null
        ├─ priority($priority)        si non null
        └─ attach($path, $options)    pour chaque attachment
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Config incomplète | `RuntimeException` | `Mail configuration is incomplete.` |
| Destination vide | `RuntimeException` | `Mail destination not specified.` |
| Destination non-string | `RuntimeException` | `Mail destination not specified.` |
| Erreur d'envoi Laravel | capturée dans `SendResultRecord.error_message` | (message Laravel) |

Le driver **lève** les `RuntimeException` de config/destination. Les erreurs d'envoi (`Mail::send` throw) sont **capturées** par `AbstractDriver::send()` et transformées en `SendResultRecord` avec `success = false`.

## Intégration

```
MailChannel
    └── createDriver() → MailDriver
            ├── dépend de MailConfigRecord (enabled, default_from, default_from_name)
            ├── consomme NotificationMessageVO (subject, body, metadata)
            ├── consomme NotificationRouteVO (destination = email)
            └── utilise Mail::send() (facade Laravel)
            └── retourne SendResultRecord
```

Le driver est invoqué par `NotificationSenderProcessor` pour chaque `NotificationRouteVO` dont le canal est `MailChannel`.

## Performance

| Aspect | Détail |
|---|---|
| **Coût réseau** | 1 connexion SMTP par email (pas de batch natif) |
| **Résolution attachments** | O(n) sur la liste des pièces jointes |
| **Validation** | O(1) — 2 vérifications simples |
| **Allocations** | Aucune tant que `Mail::send` n'est pas appelé |

Pour envoyer à **N destinataires**, itérer sur N `NotificationRouteVO`. Chaque appel crée une connexion SMTP — utiliser `Mail::queue()` à la place pour de gros volumes (voir section Compatibilité ci-dessous).

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet (readonly, promotion, named args) |
| PHP 8.0 | ✅ Complet |
| PHP 7.4 | ❌ Non supporté |

| Version Laravel | Support |
|---|---|
| Laravel 11.x | ✅ Complet |
| Laravel 10.x | ✅ Complet |
| Laravel 9.x | ⚠️ Fonctionne mais non testé |
| Laravel 8.x | ⚠️ `Mail::send()` disponible mais `priority()` peut varier |

**Drivers mail Laravel supportés** : `smtp`, `sendmail`, `ses`, `postmark`, `log`, `array`, `failover`, `roundrobin`.

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Drivers\MailDriver;
use AndyDefer\LaravelNotification\Records\MailConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

// 1. Configuration
$config = new MailConfigRecord(
    enabled: true,
    default_from: 'noreply@afya.com',
    default_from_name: 'Afya Medical',
);

// 2. Driver
$driver = new MailDriver($config);

// 3. Route — uniquement la destination
$route = new NotificationRouteVO(
    channelClass: MailChannel::class,
    destination: 'client@example.com',
);

// 4. Message — contenu + options via metadata
$message = new NotificationMessageVO(
    body: new MessageBodyVO('<h1>Votre facture</h1><p>Voir pièce jointe.</p>'),
    subject: new MessageSubjectVO('Facture #1234'),
    type: 'invoice',
    metadata: new StrictDataObject([
        'attachments' => [
            '/storage/invoices/1234.pdf',
            ['path' => '/storage/invoices/1234-summary.pdf', 'as' => 'résumé.pdf'],
        ],
        'cc' => ['comptable@afya.com', 'manager@afya.com'],
        'bcc' => 'audit@afya.com',
        'reply_to' => 'support@afya.com',
        'priority' => 1,
        'from' => 'facturation@afya.com',
        'from_name' => 'Service Facturation',
    ]),
);

// 5. Envoi
$result = $driver->send($message, $route);

if (! $result->success) {
    Log::error('Mail send failed', [
        'channel' => $result->channel->getValue(),
        'destination' => $result->destination,
        'error' => $result->error_message?->getValue(),
    ]);

    return;
}

Log::info('Email envoyé', [
    'destination' => $result->destination,
    'type' => $message->getType(),
]);
```