```markdown
# PusherChannel - Référence Technique

## Description

Canal de notification qui diffuse des événements temps réel via Pusher. Il expose un driver Pusher et valide les noms de channels autorisés par l'API Pusher.

## Hiérarchie / Implémentations

```
ChannelInterface
    └── AbstractChannel
            └── PusherChannel
```

## Rôle principal

`PusherChannel` fait partie de la couche "channels" du package `laravel-notification`. Il est résolu par le registry de canaux pour chaque envoi ciblant le canal `pusher`. Il fournit :

- les métadonnées du canal (`name`, `label`, `icon`) ;
- l'état d'activation dérivé de la configuration ;
- un `PusherDriver` construit à partir d'un `PusherConfigRecord` ;
- une validation statique des destinations (noms de channels Pusher).

## Installation

```bash
composer require pusher/pusher-php-server:^7.2
```

Puis publier la configuration du package :

```bash
php artisan vendor:publish --tag=notification-config
```

Variables `.env` requises :

```env
PUSHER_APP_ID=xxxxx
PUSHER_APP_KEY=xxxxx
PUSHER_APP_SECRET=xxxxx
PUSHER_APP_CLUSTER=eu
PUSHER_USE_TLS=true
PUSHER_TIMEOUT=30
PUSHER_NOTIFICATION_CHANNEL=notifications
```

## API / Méthodes publiques

### `__construct(NotificationConfigInterface $config)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$config` | `NotificationConfigInterface` | Contrat de configuration du package |

**Retourne :** —

**Exceptions :** —

**Exemple :**

```php
use AndyDefer\LaravelNotification\Channels\PusherChannel;
use AndyDefer\LaravelNotification\Configs\NotificationConfig;

$channel = new PusherChannel(
    new NotificationConfig(app('config')),
);
```

### `getName(): string`

**Retourne :** `string` - `'pusher'`

**Exemple :**

```php
$channel->getName(); // 'pusher'
```

### `getLabel(): string`

**Retourne :** `string` - `'Pusher'`

**Exemple :**

```php
$channel->getLabel(); // 'Pusher'
```

### `getIcon(): string`

**Retourne :** `string` - `'radio'`

**Exemple :**

```php
$channel->getIcon(); // 'radio'
```

### `isEnabled(): bool`

**Retourne :** `bool` - `true` si `notification.channels.pusher.enabled` est vrai

**Exemple :**

```php
config()->set('notification.channels.pusher.enabled', true);

$channel->isEnabled(); // true
```

### `validateDestination(string $destination): bool` (statique)

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$destination` | `string` | Nom du channel Pusher à valider |

**Retourne :** `bool` - `true` si le nom respecte `[a-zA-Z0-9_\-=@,.;]+` et fait au plus 164 caractères

**Exemple :**

```php
PusherChannel::validateDestination('private-user.1');        // true
PusherChannel::validateDestination('invalid channel!');       // false
PusherChannel::validateDestination(str_repeat('a', 165));     // false
```

### `createDriver(): AbstractDriver`

**Retourne :** `PusherDriver` construit avec `PusherConfigRecord`

**Exemple :**

```php
$driver = $channel->createDriver();
$driver->getChannel(); // 'pusher'
```

## Cas d'utilisation

### Cas 1 : Notifier un utilisateur sur un channel privé

```php
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\Records\SendNowRecord;

$route = new NotificationRouteVO(
    channelClass: PusherChannel::class,
    destination: 'private-user.42',
);

$message = new NotificationMessageVO(
    body: new MessageBodyVO('Votre commande est confirmée.'),
    subject: new MessageSubjectVO('Commande confirmée'),
    type: 'order_confirmation',
);

$record = SendNowRecord::from([
    'channels' => [PusherChannel::class],
    'limit_per_channel' => 1,
]);

$results = $service->sendNow($user, $message, $record);
```

Côté client Laravel Echo :

```ts
Echo.private('user.42').listen('.notification.received', (payload) => {
    console.log(payload.body, payload.subject);
});
```

### Cas 2 : Surcharger le channel et l'événement via metadata

```php
use AndyDefer\DomainStructures\Utils\StrictDataObject;

$route = new NotificationRouteVO(
    channelClass: PusherChannel::class,
    destination: 'private-user.42',
    metadata: new StrictDataObject([
        'channel' => 'private-tenant.7',
        'event' => 'notification.tenant',
    ]),
);
```

Le driver utilise `private-tenant.7` comme channel et `notification.tenant` comme événement. La destination sert de repli si `channel` est absent.

### Cas 3 : Désactiver le canal en production

```php
// config/notification.php
'channels' => [
    'pusher' => [
        'enabled' => env('PUSHER_NOTIFICATION_ENABLED', false),
        // ...
    ],
],
```

Tant que `enabled` vaut `false`, `PusherChannel::isEnabled()` retourne `false` et le service n'utilise pas ce canal.

## Flux d'exécution

```
NotificationService::sendNow()
    → résolution du channel via ChannelInterface
    → PusherChannel::createDriver()
        → NotificationConfig::getPusherConfig() : PusherConfigRecord
        → new PusherDriver(PusherConfigRecord)
    → PusherDriver::send(message, route)
        → validateConfiguration()
        → resolveChannelName()
        → resolveEventName()
        → Pusher::trigger()
    → SendResultRecord
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Configuration Pusher incomplète | `RuntimeException` | `Driver AndyDefer\LaravelNotification\Drivers\PusherDriver configuration is invalid.` |
| Échec de l'appel Pusher | `RuntimeException` | `Pusher trigger failed: {message}` |
| Destination vide ou caractères invalides | — | `validateDestination()` retourne `false` |

## Intégration

- `NotificationConfigInterface::getPusherConfig()` fournit le `PusherConfigRecord`.
- `NotificationConfigInterface::isPusherEnabled()` contrôle `isEnabled()`.
- `PusherDriver` consomme `PusherConfigRecord` et publie sur l'API Pusher.
- Le canal peut être ciblé via `SendNowRecord`, `SendLaterRecord`, `SendAtRecord`, `SendRecurringRecord` ou `NotifiableBuilder`.

## Performance

- Construction du `PusherDriver` : O(1), instanciation légère.
- Le client Pusher est instancié en lazy dans le driver et mis en cache pour éviter une reconnexion par envoi.
- `validateDestination()` : O(n) sur la longueur de la chaîne, `preg_match` sans backtracking significatif.
- Aucune I/O dans le canal lui-même ; le réseau est déclenché uniquement au `trigger()`.

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1 | ✅ Complet |
| PHP 8.0 | ⚠️ `readonly` properties OK mais pusher/pusher-php-server v7 nécessite PHP >= 7.4 ; comportement identique |
| pusher/pusher-php-server | `^7.2` |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Channels\PusherChannel;
use AndyDefer\LaravelNotification\Configs\NotificationConfig;
use AndyDefer\LaravelNotification\Drivers\PusherDriver;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

$channel = new PusherChannel(new NotificationConfig(app('config')));

if (! $channel->isEnabled()) {
    throw new RuntimeException('Pusher channel is disabled.');
}

if (! PusherChannel::validateDestination('private-user.42')) {
    throw new InvalidArgumentException('Invalid Pusher channel name.');
}

$driver = $channel->createDriver();
assert($driver instanceof PusherDriver);

$route = new NotificationRouteVO(
    channelClass: PusherChannel::class,
    destination: 'private-user.42',
);

$message = new NotificationMessageVO(
    body: new MessageBodyVO('Bienvenue sur la plateforme.'),
    subject: new MessageSubjectVO('Bienvenue'),
    type: 'welcome',
);

$results = app(NotificationService::class)->sendNow(
    $user,
    $message,
    SendNowRecord::from([
        'channels' => [PusherChannel::class],
        'limit_per_channel' => 1,
    ]),
);

if (! $results->allSuccess()) {
    Log::error('Pusher notification failed', [
        'failures' => $results->getFailures()->toArray(),
    ]);
}
```
```