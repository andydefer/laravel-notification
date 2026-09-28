# WebPushChannel - Référence Technique

## Description

Canal de notification implémentant le protocole Web Push (W3C) avec authentification VAPID, pour livrer des notifications aux navigateurs.

## Hiérarchie

```
AbstractChannel
    └── WebPushChannel
```

## Rôle principal

Ce canal expose les métadonnées descriptives du canal Web Push (`nom`, `label`, `icône`) et sert de pont entre l'orchestrateur de notifications et le `WebPushDriver`. Il résout également la configuration active via `NotificationConfig` et valide les endpoints fournis par les clients.

## Prérequis

- Le canal nécessite les clés VAPID (`public_key`, `private_key`, `subject`) configurées dans `config/notification.php`.
- Le package `minishlink/web-push` doit être installé pour le driver.

## API / Méthodes publiques

### `getName(): string`

Retourne l'identifiant technique du canal.

**Retourne :** `string` - La clé du canal, toujours `"webpush"`.

**Exemple :**
```php
$channel = new WebPushChannel($config);
echo $channel->getName(); // "webpush"
```

### `getLabel(): string`

Retourne le libellé humain du canal pour l'affichage.

**Retourne :** `string` - Le libellé, toujours `"Web Push (VAPID)"`.

**Exemple :**
```php
echo $channel->getLabel(); // "Web Push (VAPID)"
```

### `getIcon(): string`

Retourne l'icône représentant le canal.

**Retourne :** `string` - Une chaîne courte, `"🌐"`.

**Exemple :**
```php
echo $channel->getIcon(); // "🌐"
```

### `isEnabled(): bool`

Indique si le canal est activé dans la configuration.

**Retourne :** `bool` - `true` si le canal Web Push est activé.

**Exemple :**
```php
if ($channel->isEnabled()) {
    // Le canal peut être utilisé
}
```

### `getConfig(): AbstractRecord`

Retourne le record de configuration du canal.

**Retourne :** `AbstractRecord` - Une instance de `WebPushConfigRecord`.

**Exemple :**
```php
$config = $channel->getConfig();
// $config est un WebPushConfigRecord
```

### `createDriver(): AbstractDriver`

Instancie le driver Web Push avec la configuration courante.

**Retourne :** `AbstractDriver` - Une instance de `WebPushDriver` prête à l'emploi.

**Exemple :**
```php
$driver = $channel->createDriver();
```

### `validateDestination(string $destination): bool` (statique)

Vérifie que la destination est une URL HTTP ou HTTPS valide.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$destination` | `string` | L'endpoint à valider |

**Retourne :** `bool` - `true` si l'URL est valide et utilise un schéma `http://` ou `https://`.

**Exemple :**
```php
WebPushChannel::validateDestination('https://jmt17.google.com/fcm/send/...');
// true

WebPushChannel::validateDestination('ftp://example.com');
// false
```

## Cas d'utilisation

### Cas 1 : Vérification d'un endpoint avant enregistrement

Avant de persister une souscription, valider le format de l'endpoint fourni par le client.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Channels\WebPushChannel;

$endpoint = $request->input('endpoint');

if (! WebPushChannel::validateDestination($endpoint)) {
    throw new \InvalidArgumentException('Invalid Web Push endpoint.');
}
```

### Cas 2 : Envoi d'une notification via le driver

Récupérer le driver du canal pour envoyer une notification Web Push.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Channels\WebPushChannel;

$driver = WebPushChannel::createDriver();

if (! $driver->validateConfiguration()) {
    // La configuration VAPID est incomplète
}

$result = $driver->send($message, $route);
```

### Cas 3 : Inspection du canal dans un dashboard

Lister tous les canaux disponibles avec leur état.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Channels\WebPushChannel;

$channel = app(WebPushChannel::class);

echo sprintf(
    '%s %s (%s) — enabled: %s',
    $channel->getIcon(),
    $channel->getLabel(),
    $channel->getName(),
    $channel->isEnabled() ? 'yes' : 'no',
);
// 🌐 Web Push (VAPID) (webpush) — enabled: yes
```

## Flux d'exécution

```
Orchestrateur de notifications
    ↓
WebPushChannel::isEnabled()         → vérifie l'activation
    ↓
WebPushChannel::createDriver()      → instancie WebPushDriver
    ↓
WebPushDriver::send($message, $route)
    ↓
Service Push du navigateur (Google, Mozilla, etc.)
```

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Configuration VAPID incomplète | `WebPushDriver::send()` lève une `RuntimeException` |
| Destination invalide | `validateDestination()` retourne `false` (pas d'exception) |
| Canal désactivé | `isEnabled()` retourne `false`, l'orchestrateur ignore le canal |

## Intégration

| Composant | Rôle |
|-----------|------|
| `NotificationConfig` | Fournit `isWebPushEnabled()` et `getWebPushConfig()` |
| `WebPushDriver` | Exécute l'envoi via le protocole Web Push |
| `WebPushConfigRecord` | Record de configuration du canal |
| `AbstractChannel` | Contrat parent implémenté |

## Performance

- Les méthodes `getName()`, `getLabel()`, `getIcon()` sont des constantes en mémoire — coût O(1).
- `getConfig()` et `createDriver()` appellent `NotificationConfig::getWebPushConfig()`. Le coût dépend de l'implémentation du config (potentiellement deux constructions distinctes).
- Aucune mise en cache interne.
- `validateDestination()` utilise `filter_var()` — coût O(1).

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ |
| Laravel 12+ | ✅ |
| `minishlink/web-push` | ✅ Requis par le driver |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Channels\WebPushChannel;

$channel = app(WebPushChannel::class);

// 1. Vérifier l'activation
if (! $channel->isEnabled()) {
    throw new \RuntimeException('Web Push channel is disabled.');
}

// 2. Récupérer la configuration
$config = $channel->getConfig();

// 3. Valider un endpoint entrant
$endpoint = 'https://jmt17.google.com/fcm/send/c9ptagJdvGY:APA91b...';

if (! WebPushChannel::validateDestination($endpoint)) {
    throw new \InvalidArgumentException('Invalid endpoint.');
}

// 4. Créer le driver et envoyer
$driver = $channel->createDriver();
$result = $driver->send($message, $route);

if (! $result->success) {
    logger()->error('Web Push send failed', [
        'error' => $result->error_message?->getValue(),
    ]);
}
```