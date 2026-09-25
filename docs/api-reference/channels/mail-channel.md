```markdown
# MailChannel - Référence Technique

## Description

Canal de notification qui envoie des emails via le driver Laravel Mail. Valide les adresses email destinataires et expose la configuration SMTP du package.

## Hiérarchie / Implémentations

```
ChannelInterface
    └── AbstractChannel
            └── MailChannel
```

## Rôle principal

`MailChannel` fait partie de la couche "channels" du package `laravel-notification`. Il est résolu par le registry de canaux pour chaque envoi ciblant le canal `mail`. Il fournit :

- les métadonnées du canal (`name`, `label`, `icon`) ;
- l'état d'activation dérivé de la configuration ;
- un `MailDriver` construit à partir d'un `MailConfigRecord` ;
- une validation statique des destinations (adresses email RFC).

## Installation

Aucune dépendance externe requise. Le package utilise le système de mail natif de Laravel.

Publier la configuration du package :

```bash
php artisan vendor:publish --tag=notification-config
```

Variables `.env` typiques :

```env
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="Mon Application"
MAIL_DEFAULT_TO=contact@example.com
```

## API / Méthodes publiques

### `getName(): string`

**Retourne :** `string` - `'mail'`

**Exemple :**

```php
$channel->getName(); // 'mail'
```

### `getLabel(): string`

**Retourne :** `string` - `'Email'`

**Exemple :**

```php
$channel->getLabel(); // 'Email'
```

### `getIcon(): string`

**Retourne :** `string` - `'📧'`

**Exemple :**

```php
$channel->getIcon(); // '📧'
```

### `isEnabled(): bool`

**Retourne :** `bool` - `true` si `notification.channels.mail.enabled` est vrai

**Exemple :**

```php
config()->set('notification.channels.mail.enabled', true);

$channel->isEnabled(); // true
```

### `getConfig(): AbstractRecord`

**Retourne :** `MailConfigRecord` - Configuration du canal mail

**Exemple :**

```php
$config = $channel->getConfig();

$config->enabled;            // true
$config->default_from;       // 'noreply@example.com'
$config->default_from_name;  // 'Mon Application'
```

### `createDriver(): AbstractDriver`

**Retourne :** `MailDriver` construit avec `MailConfigRecord`

**Exemple :**

```php
$driver = $channel->createDriver();
$driver->getChannel(); // 'mail'
```

### `validateDestination(string $destination): bool` (statique)

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$destination` | `string` | Adresse email à valider |

**Retourne :** `bool` - `true` si l'adresse passe `filter_var(..., FILTER_VALIDATE_EMAIL)`

**Exemple :**

```php
MailChannel::validateDestination('john@example.com');       // true
MailChannel::validateDestination('john+tag@example.com');   // true
MailChannel::validateDestination('john doe@example.com');   // false
MailChannel::validateDestination('john@');                  // false
```

## Cas d'utilisation

### Cas 1 : Envoyer un email de bienvenue

```php
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\Records\SendNowRecord;

$route = new NotificationRouteVO(
    channelClass: MailChannel::class,
    destination: 'john@example.com',
);

$message = new NotificationMessageVO(
    body: new MessageBodyVO(view('emails.welcome', ['user' => $user])->render()),
    subject: new MessageSubjectVO('Bienvenue !'),
    type: 'welcome',
);

$record = SendNowRecord::from([
    'channels' => [MailChannel::class],
    'limit_per_channel' => 1,
]);

$results = $service->sendNow($user, $message, $record);
```

### Cas 2 : Surcharger l'expéditeur via metadata

```php
use AndyDefer\DomainStructures\Utils\StrictDataObject;

$route = new NotificationRouteVO(
    channelClass: MailChannel::class,
    destination: 'client@example.com',
    metadata: new StrictDataObject([
        'from' => 'support@monsite.com',
        'from_name' => 'Support Client',
    ]),
);
```

Le driver utilise `support@monsite.com` comme expéditeur au lieu de `default_from`.

### Cas 3 : Envoi différé

```php
use AndyDefer\LaravelNotification\Records\SendLaterRecord;

$record = SendLaterRecord::from([
    'delay_seconds' => 1800,
    'channels' => [MailChannel::class],
    'limit_per_channel' => 1,
]);

$alias = $service->sendLater($user, $message, $record);
```

L'envoi est planifié pour 30 minutes plus tard via le système de queues Laravel.

## Flux d'exécution

```
NotificationService::sendNow()
    → résolution du channel via ChannelInterface
    → MailChannel::createDriver()
        → NotificationConfig::getMailConfig() : MailConfigRecord
        → new MailDriver(MailConfigRecord)
    → MailDriver::send(message, route)
        → validateConfiguration()
        → Mail::to(destination)->send()
    → SendResultRecord
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Configuration mail invalide (from manquant) | `RuntimeException` | `Driver AndyDefer\LaravelNotification\Drivers\MailDriver configuration is invalid.` |
| Échec SMTP | — | Reportée dans `SendResultRecord::$error_message` |
| Destination vide ou invalide | — | `validateDestination()` retourne `false` |

## Intégration

- `NotificationConfigInterface::getMailConfig()` fournit le `MailConfigRecord`.
- `MailDriver` consomme `MailConfigRecord` et utilise `Illuminate\Support\Facades\Mail`.
- Le canal peut être ciblé via `SendNowRecord`, `SendLaterRecord`, `SendAtRecord`, `SendRecurringRecord` ou `NotifiableBuilder`.
- Une surcharge de l'expéditeur par route est possible via les metadata `from` et `from_name`.

## Performance

- Construction du `MailDriver` : O(1).
- L'envoi dépend du transport Laravel configuré (`smtp`, `log`, `ses`, etc.).
- Pour un envoi massif, préférer `SendLaterRecord` ou `SendRecurringRecord` afin de déléguer au scheduler / queues.
- Le HTML de l'email peut être mis en cache via `view(...)->render()` appelé une seule fois.

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1 | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| Laravel | 10.x, 11.x, 12.x |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Configs\NotificationConfig;
use AndyDefer\LaravelNotification\Drivers\MailDriver;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

$channel = new MailChannel(new NotificationConfig(app('config')));

if (! $channel->isEnabled()) {
    throw new RuntimeException('Mail channel is disabled.');
}

if (! MailChannel::validateDestination('john@example.com')) {
    throw new InvalidArgumentException('Invalid email address.');
}

$driver = $channel->createDriver();
assert($driver instanceof MailDriver);

$route = new NotificationRouteVO(
    channelClass: MailChannel::class,
    destination: 'john@example.com',
);

$message = new NotificationMessageVO(
    body: new MessageBodyVO('<h1>Bienvenue</h1><p>Merci de votre inscription.</p>'),
    subject: new MessageSubjectVO('Bienvenue sur la plateforme'),
    type: 'welcome',
);

$results = app(NotificationService::class)->sendNow(
    $user,
    $message,
    SendNowRecord::from([
        'channels' => [MailChannel::class],
        'limit_per_channel' => 1,
    ]),
);

if (! $results->allSuccess()) {
    Log::error('Mail notification failed', [
        'failures' => $results->getFailures()->toArray(),
    ]);
}
```
```