```markdown
# DatabaseChannel - Référence Technique

## Description

Canal de notification qui persiste les notifications en base de données. Toujours actif, il assure la traçabilité complète de tous les envois.

## Hiérarchie / Implémentations

```
ChannelInterface
    └── AbstractChannel
            └── DatabaseChannel
```

## Rôle principal

`DatabaseChannel` fait partie de la couche "channels" du package `laravel-notification`. Il est résolu par le registry de canaux pour chaque envoi ciblant le canal `database`. Il fournit :

- les métadonnées du canal (`name`, `label`, `icon`) ;
- un état toujours actif (traçabilité garantie) ;
- un `DatabaseDriver` construit à partir d'un `DatabaseConfigRecord` ;
- une validation statique de la destination (`'database'` uniquement).

C'est le seul canal recommandé dans **tous** les `getNotificationChannels()` d'un modèle notifiable pour ne jamais perdre la trace d'un envoi.

## Installation

Publier la configuration et les migrations du package :

```bash
php artisan vendor:publish --tag=notification-config
php artisan vendor:publish --tag=notification-migrations
php artisan migrate
```

La table par défaut est `notifications`. Elle peut être renommée via la configuration :

```php
// config/notification.php
'channels' => [
    'database' => [
        'driver' => 'database',
        'table' => 'notifications',
    ],
],
```

## API / Méthodes publiques

### `getName(): string`

**Retourne :** `string` - `'database'`

**Exemple :**

```php
$channel->getName(); // 'database'
```

### `getLabel(): string`

**Retourne :** `string` - `'Base de données'`

**Exemple :**

```php
$channel->getLabel(); // 'Base de données'
```

### `getIcon(): string`

**Retourne :** `string` - `'💾'`

**Exemple :**

```php
$channel->getIcon(); // '💾'
```

### `isEnabled(): bool`

**Retourne :** `bool` - Toujours `true`

**Exemple :**

```php
$channel->isEnabled(); // true (quel que soit l'environnement)
```

### `getConfig(): AbstractRecord`

**Retourne :** `DatabaseConfigRecord` - Configuration du canal database

**Exemple :**

```php
$config = $channel->getConfig();

$config->driver; // 'database'
$config->table;  // 'notifications'
```

### `createDriver(): AbstractDriver`

**Retourne :** `DatabaseDriver` construit avec `DatabaseConfigRecord`

**Exemple :**

```php
$driver = $channel->createDriver();
$driver->getChannel(); // 'database'
```

### `validateDestination(string $destination): bool` (statique)

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$destination` | `string` | Destination à valider |

**Retourne :** `bool` - `true` si `$destination === 'database'`

**Exemple :**

```php
DatabaseChannel::validateDestination('database');   // true
DatabaseChannel::validateDestination('');           // false
DatabaseChannel::validateDestination('some-table'); // false
```

## Cas d'utilisation

### Cas 1 : Ajouter la traçabilité à toutes les notifications

```php
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\PusherChannel;
use AndyDefer\LaravelNotification\Collections\NotificationRouteCollection;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

public function getNotificationChannels(): NotificationRouteCollection
{
    $collection = new NotificationRouteCollection;

    if ($this->email) {
        $collection->add(new NotificationRouteVO(
            MailChannel::class,
            $this->email,
        ));
    }

    $collection->add(new NotificationRouteVO(
        PusherChannel::class,
        "private-user.{$this->id}",
    ));

    $collection->add(new NotificationRouteVO(
        DatabaseChannel::class,
        'database',
    ));

    return $collection;
}
```

Chaque envoi mail ou Pusher est dupliqué en base pour audit.

### Cas 2 : Consulter l'historique d'un utilisateur

```php
use AndyDefer\LaravelNotification\Services\NotificationService;

$stats = app(NotificationService::class)->getStats($user);

$stats->total;         // 150
$stats->sent;          // 120
$stats->failed;        // 30
$stats->success_rate;  // 80.0
$stats->hasFailures(); // true
```

### Cas 3 : Envoyer uniquement vers la base (sans effet externe)

```php
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;

$message = new NotificationMessageVO(
    body: new MessageBodyVO('Note interne'),
    subject: new MessageSubjectVO('Audit'),
    type: 'audit',
);

$record = SendNowRecord::from([
    'channels' => [DatabaseChannel::class],
    'limit_per_channel' => 1,
]);

$results = $service->sendNow($user, $message, $record);
```

Utile pour les marqueurs d'audit, notes internes ou événements à historiser sans notification externe.

## Flux d'exécution

```
NotificationService::sendNow()
    → résolution du channel via ChannelInterface
    → DatabaseChannel::createDriver()
        → NotificationConfig::getDatabaseConfig() : DatabaseConfigRecord
        → new DatabaseDriver(DatabaseConfigRecord)
    → DatabaseDriver::send(message, route)
        → insertion dans la table configurée
    → SendResultRecord
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Table absente | `Illuminate\Database\QueryException` | `SQLSTATE[42S02]: Base table or view not found: ...` |
| Connexion DB indisponible | `Illuminate\Database\QueryException` | `SQLSTATE[HY000] [2002] ...` |
| Destination invalide | — | `validateDestination()` retourne `false` |

Le canal database n'a aucune condition de configuration invalidante : `validateConfiguration()` du driver retourne toujours `true` tant que la table existe.

## Intégration

- `NotificationConfigInterface::getDatabaseConfig()` fournit le `DatabaseConfigRecord`.
- `DatabaseDriver` consomme le record et écrit via Eloquent / query builder.
- À combiner systématiquement avec les autres canaux pour la traçabilité.
- Le `NotificationStatsVO` retourné par `NotificationService::getStats()` lit cette table.

## Performance

- Une insertion par notification envoyée.
- Pour un envoi massif, la table peut croître rapidement : prévoir un index sur `(notifiable_type, notifiable_id, created_at)`.
- Le nettoyage des anciennes entrées est recommandé via un scheduler (`notifications:clean` ou tâche custom).
- Aucun effet de bord réseau, contrairement aux canaux Mail/SMS/Pusher.

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1 | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| Laravel | 10.x, 11.x, 12.x |
| MySQL / PostgreSQL / SQLite | ✅ |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Configs\NotificationConfig;
use AndyDefer\LaravelNotification\Collections\NotificationRouteCollection;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

$channel = new DatabaseChannel(new NotificationConfig(app('config')));

if (! DatabaseChannel::validateDestination('database')) {
    throw new InvalidArgumentException('Invalid database destination.');
}

$driver = $channel->createDriver();
assert($driver->getChannel() === 'database');

// Construire les routes : mail + database pour traçabilité
$routes = new NotificationRouteCollection;
$routes->add(new NotificationRouteVO(
    channelClass: MailChannel::class,
    destination: 'john@example.com',
));
$routes->add(new NotificationRouteVO(
    channelClass: DatabaseChannel::class,
    destination: 'database',
));

$message = new NotificationMessageVO(
    body: new MessageBodyVO('<h1>Bienvenue</h1>'),
    subject: new MessageSubjectVO('Bienvenue'),
    type: 'welcome',
);

$results = app(NotificationService::class)->sendNow(
    $user,
    $message,
    SendNowRecord::from([
        'channels' => [MailChannel::class, DatabaseChannel::class],
        'limit_per_channel' => 1,
    ]),
);

if (! $results->allSuccess()) {
    Log::error('Notification failed', [
        'failures' => $results->getFailures()->toArray(),
    ]);
}
```
```