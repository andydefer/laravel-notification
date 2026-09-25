```markdown
# PusherDriver - Référence Technique

## Description

Driver de notification qui publie des événements temps réel sur l'API Pusher via le SDK `pusher/pusher-php-server`.

## Hiérarchie / Implémentations

```
DriverInterface
    └── AbstractDriver
            └── PusherDriver
```

## Rôle principal

`PusherDriver` fait partie de la couche "drivers" du package `laravel-notification`. Il est instancié par `PusherChannel::createDriver()` et reçoit un `PusherConfigRecord`. Il :

- valide la configuration Pusher ;
- résout le nom du channel et le nom de l'événement ;
- publie le payload via le client Pusher en lazy loading ;
- convertit les exceptions Pusher en `RuntimeException`.

## Installation

```bash
composer require pusher/pusher-php-server:^7.2
```

Configuration requise (dans `config/notification.php`) :

```php
'channels' => [
    'pusher' => [
        'enabled' => env('PUSHER_NOTIFICATION_ENABLED', false),
        'app_id' => env('PUSHER_APP_ID'),
        'key' => env('PUSHER_APP_KEY'),
        'secret' => env('PUSHER_APP_SECRET'),
        'cluster' => env('PUSHER_APP_CLUSTER', 'eu'),
        'use_tls' => env('PUSHER_USE_TLS', true),
        'timeout' => env('PUSHER_TIMEOUT', 30),
        'default_channel' => env('PUSHER_NOTIFICATION_CHANNEL', 'notifications'),
    ],
],
```

## API / Méthodes publiques

### `__construct(PusherConfigRecord $config)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$config` | `PusherConfigRecord` | Configuration du canal Pusher |

**Retourne :** —

**Exceptions :** —

**Exemple :**

```php
use AndyDefer\LaravelNotification\Drivers\PusherDriver;
use AndyDefer\LaravelNotification\Records\PusherConfigRecord;

$config = new PusherConfigRecord(
    enabled: true,
    app_id: '2197533',
    key: 'xxx',
    secret: 'yyy',
    cluster: 'ap2',
);

$driver = new PusherDriver($config);
```

### `getChannel(): string`

**Retourne :** `string` - `'pusher'`

**Exemple :**

```php
$driver->getChannel(); // 'pusher'
```

### `validateConfiguration(): bool`

**Retourne :** `bool` - `true` si `enabled`, `app_id`, `key`, `secret` et `cluster` sont tous définis

**Exemple :**

```php
$driver->validateConfiguration(); // true
```

### `send(NotificationMessageVO $message, NotificationRouteVO $route): SendResultRecord`

Hérité de `AbstractDriver`. Orchestre `validateConfiguration()` puis `execute()`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `NotificationMessageVO` | Message à diffuser |
| `$route` | `NotificationRouteVO` | Destination + metadata |

**Retourne :** `SendResultRecord` - Résultat de l'envoi (`success`, `channel`, `destination`, `error_message`)

**Exceptions :** `RuntimeException` - Configuration invalide

**Exemple :**

```php
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use AndyDefer\LaravelNotification\Channels\PusherChannel;

$message = new NotificationMessageVO(
    body: new MessageBodyVO('Test Body'),
    subject: new MessageSubjectVO('Test Subject'),
    type: 'test',
);

$route = new NotificationRouteVO(
    channelClass: PusherChannel::class,
    destination: 'private-user.42',
);

$result = $driver->send($message, $route);

$result->success;      // true
$result->destination;  // 'private-user.42'
```

### `execute(NotificationMessageVO $message, NotificationRouteVO $route): bool` (protégé)

Méthode interne appelée par `send()`. Publie l'événement Pusher et retourne `true` en cas de succès.

## Cas d'utilisation

### Cas 1 : Notification temps réel simple

```php
use AndyDefer\LaravelNotification\Channels\PusherChannel;
use AndyDefer\LaravelNotification\Drivers\PusherDriver;
use AndyDefer\LaravelNotification\Records\PusherConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

$config = new PusherConfigRecord(
    enabled: true,
    app_id: (string) config('notification.channels.pusher.app_id'),
    key: (string) config('notification.channels.pusher.key'),
    secret: (string) config('notification.channels.pusher.secret'),
    cluster: (string) config('notification.channels.pusher.cluster'),
);

$driver = new PusherDriver($config);

$result = $driver->send(
    new NotificationMessageVO(
        body: new MessageBodyVO('Votre commande est confirmée.'),
        subject: new MessageSubjectVO('Commande confirmée'),
        type: 'order_confirmation',
    ),
    new NotificationRouteVO(
        channelClass: PusherChannel::class,
        destination: 'private-user.42',
    ),
);

if (! $result->success) {
    Log::error($result->error_message->getValue());
}
```

### Cas 2 : Surcharger channel et événement via metadata

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

$driver->send($message, $route);
// Channel utilisé : 'private-tenant.7'
// Événement utilisé : 'notification.tenant'
```

### Cas 3 : Payload enrichi avec data

```php
$message = new NotificationMessageVO(
    body: new MessageBodyVO('Nouvelle commande'),
    subject: new MessageSubjectVO('Commande #42'),
    type: 'order_created',
    data: new StrictDataObject([
        'order_id' => 42,
        'amount' => 129.99,
    ]),
);

$driver->send($message, $route);
// Le payload Pusher inclut : body, subject, type, data, sent_at
```

## Flux d'exécution

```
PusherDriver::send(message, route)
    → validateConfiguration()
        ├── false → RuntimeException
        └── true  → execute(message, route)
                → resolveChannelName(route)
                    metadata['channel'] → destination → config->default_channel
                → resolveEventName(route)
                    metadata['event'] → 'notification'
                → client()->trigger(channel, event, payload)
                    Pusher API
                → true
    → SendResultRecord
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Configuration incomplète | `RuntimeException` | `Driver AndyDefer\LaravelNotification\Drivers\PusherDriver configuration is invalid.` |
| Échec de l'appel Pusher | `RuntimeException` | `Pusher trigger failed: {message}` |

Les `RuntimeException` sont capturées par `AbstractDriver::send()` et transformées en `SendResultRecord::$error_message`.

## Intégration

- Instancié par `PusherChannel::createDriver()` avec `PusherConfigRecord` issu de `NotificationConfig::getPusherConfig()`.
- Utilise `Pusher\Pusher` (SDK officiel).
- Publie sur l'API Pusher — les clients web/mobile abonnés reçoivent l'événement via Laravel Echo ou le SDK JS Pusher.
- Payload inclut systématiquement `body`, `subject`, `type`, `sent_at` et optionnellement `data`.

## Performance

- Client Pusher instancié en lazy (mémoïsé dans la propriété `$client`), évitant une reconnexion par envoi.
- `validateConfiguration()` en O(1).
- Résolution du channel et de l'événement en O(1).
- Latence réseau dominée par l'API Pusher.
- Pour un envoi massif, préférer un `SendRecurringRecord` ou une queue dédiée.

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1 | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| `pusher/pusher-php-server` | `^7.2` |
| Laravel | 10.x, 11.x, 12.x |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Channels\PusherChannel;
use AndyDefer\LaravelNotification\Drivers\PusherDriver;
use AndyDefer\LaravelNotification\Records\PusherConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

$config = new PusherConfigRecord(
    enabled: true,
    app_id: (string) config('notification.channels.pusher.app_id'),
    key: (string) config('notification.channels.pusher.key'),
    secret: (string) config('notification.channels.pusher.secret'),
    cluster: (string) config('notification.channels.pusher.cluster'),
    use_tls: true,
    timeout: 30,
    default_channel: 'notifications',
);

$driver = new PusherDriver($config);

if (! $driver->validateConfiguration()) {
    throw new RuntimeException('Pusher configuration is invalid.');
}

$route = new NotificationRouteVO(
    channelClass: PusherChannel::class,
    destination: 'private-user.42',
    metadata: new StrictDataObject([
        'event' => 'notification.tenant',
    ]),
);

$message = new NotificationMessageVO(
    body: new MessageBodyVO('Votre commande est confirmée.'),
    subject: new MessageSubjectVO('Commande confirmée'),
    type: 'order_confirmation',
    data: new StrictDataObject(['order_id' => 42]),
);

$result = $driver->send($message, $route);

if (! $result->success) {
    Log::error('Pusher notification failed', [
        'destination' => $result->destination,
        'error' => $result->error_message?->getValue(),
    ]);
}
```
```