# WebPushPingPong - Référence Technique

## Description

Helper de ping/pong dédié aux souscriptions Web Push. Envoie une notification légère pour vérifier qu'une souscription est toujours active, et supprime toute souscription en échec.

## Hiérarchie

```
PingPongInterface
    └── WebPushPingPong
```

## Rôle principal

Vérifier la validité d'une souscription Web Push en envoyant un ping minimal via `NotificationService`. Toute réponse non-succès (network, 404, 410, config manquante) est traitée comme une souscription invalide et provoque sa suppression.

## Prérequis

- Le package `andydefer/laravel-notification` doit être configuré.
- Le canal `WebPushChannel` doit être activé avec des clés VAPID valides.
- Le notifiable doit être un `Model` Eloquent implémentant `NotifiableInterface` et possédant les métadonnées de route Web Push (`endpoint`, `p256dh`, `auth`).

## Constantes

| Constante | Valeur | Description |
|-----------|--------|-------------|
| `PING_TYPE` | `"ping"` | Type de notification envoyé |
| `PING_SUBJECT` | `"ping"` | Sujet du ping |
| `PING_BODY` | `"ping"` | Corps du ping |

## API / Méthodes publiques

### `__construct(NotificationServiceInterface $service)`

Injecte le service de notification utilisé pour envoyer le ping.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$service` | `NotificationServiceInterface` | Service d'envoi de notifications |

**Exemple :**
```php
$pingPong = new WebPushPingPong($notificationService);
```

### `ping(Model $notifiable): PingStatus`

Envoie un ping à la souscription et retourne son statut.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `Model` | La souscription à pinger |

**Retourne :**
- `PingStatus::PONG` — le ping a réussi, `last_seen_at` mis à jour.
- `PingStatus::INVALID` — l'envoi a échoué, la souscription est considérée invalide.

**Exceptions :** `RuntimeException` si le modèle n'implémente pas `NotifiableInterface`.

**Exemple :**
```php
$status = $pingPong->ping($subscription);

if ($status->isPong()) {
    echo 'Souscription active.';
}
```

### `isAlive(Model $notifiable): bool`

Retourne `true` si le ping répond par un pong.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `Model` | La souscription à tester |

**Retourne :** `bool` - `true` si la souscription est active.

**Exemple :**
```php
if (! $pingPong->isAlive($subscription)) {
    logger()->warning('Subscription dead', ['id' => $subscription->id]);
}
```

### `pingOrPrune(Model $notifiable): PingStatus`

Ping la souscription et la supprime lorsqu'elle est invalide.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `Model` | La souscription à ping et éventuellement supprimer |

**Retourne :** `PingStatus` - Le statut du ping.

**Effet de bord :** `$notifiable->delete()` est appelé si le statut est `INVALID`.

**Exemple :**
```php
$status = $pingPong->pingOrPrune($subscription);

if ($status->isInvalid()) {
    echo 'Souscription supprimée.';
}
```

## Comportement détaillé

### Envoi du ping

Le helper construit un message avec :
- **Corps :** `"ping"`
- **Sujet :** `"ping"`
- **Type :** `"ping"`
- **Data :** `['subscription_id' => (string) $notifiable->getKey()]`

Le message est envoyé via le canal `WebPushChannel`, avec `limit_per_channel = 1` pour ne toucher qu'une seule route.

### Traitement du résultat

```
$result->allSuccess()
    ├── true  → last_seen_at = now(), save() → PingStatus::PONG
    └── false → PingStatus::INVALID
```

Aucun parsing du message d'erreur n'est effectué : tout échec est traité comme invalide.

## Cas d'utilisation

### Cas 1 : Vérifier une souscription après un échec d'envoi

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Helpers\WebPushPingPong;

$pingPong = app(WebPushPingPong::class);

$result = $notificationService->sendNow($subscription, $message, $record);

if (! $result->allSuccess()) {
    $status = $pingPong->pingOrPrune($subscription);

    logger()->warning('Subscription pruned', [
        'id' => $subscription->id,
        'status' => $status->value,
    ]);
}
```

### Cas 2 : Nettoyage périodique par tâche récurrente

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Helpers\WebPushPingPong;

$pingPong = app(WebPushPingPong::class);

foreach ($staleSubscriptions as $subscription) {
    $pingPong->pingOrPrune($subscription);
}
```

### Cas 3 : Vérification manuelle avant envoi critique

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Helpers\WebPushPingPong;

$pingPong = app(WebPushPingPong::class);

if (! $pingPong->isAlive($subscription)) {
    throw new \RuntimeException('Subscription is not reachable.');
}

// Envoi de la notification critique
$notificationService->sendNow($subscription, $criticalMessage, $record);
```

## Flux d'exécution

```
pingOrPrune($subscription)
    ↓
ping($subscription)
    ├── Vérification NotifiableInterface
    │       └── Échec → RuntimeException
    ├── Construction de la notification "ping"
    ├── sendNow($subscription, $message, $record)
    │       ├── allSuccess() → last_seen_at, save(), PONG
    │       └── ! allSuccess() → INVALID
    ↓
if INVALID → $subscription->delete()
    ↓
Retourne PingStatus
```

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Le modèle n'implémente pas `NotifiableInterface` | `RuntimeException` |
| Configuration Web Push manquante | `SendResultRecord` échoue → `INVALID` |
| Endpoint invalide ou expiré | `SendResultRecord` échoue → `INVALID` |
| Erreur réseau | `SendResultRecord` échoue → `INVALID` |
| Endpoint valide | `PONG` et `last_seen_at` mis à jour |

## Intégration

| Composant | Rôle |
|-----------|------|
| `NotificationServiceInterface` | Envoi de la notification de ping |
| `PingPongInterface` | Contrat implémenté |
| `WebPushChannel` | Canal utilisé pour l'envoi |
| `SendNowRecord` | Configuration de l'envoi |
| `NotificationMessageVO` | Message de ping |
| `PingStatus` | Résultat du ping |

## Performance

- **Coût** : un appel HTTP sortant vers le service Push (via `WebPushDriver`).
- **Latence** : dominée par le réseau.
- **Idempotence** : `pingOrPrune` peut être appelé plusieurs fois sans effet secondaire sur une souscription déjà supprimée (le `find` dans le modèle retournera `null` lors de tentatives ultérieures).
- Aucune mise en cache interne.

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ |
| Laravel 12+ | ✅ |
| `andydefer/laravel-notification` | ✅ Requis |
| `minishlink/web-push` | ✅ Requis (transitivement) |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Helpers\WebPushPingPong;
use AndyDefer\LaravelNotification\Models\WebPushSubscription;

$pingPong = app(WebPushPingPong::class);

$subscription = WebPushSubscription::find('0192f3a1-...');

if ($subscription === null) {
    return;
}

// 1. Vérifier l'état
if (! $pingPong->isAlive($subscription)) {
    logger()->info('Subscription is dead.', ['id' => $subscription->id]);
}

// 2. Ping + prune atomique
$status = $pingPong->pingOrPrune($subscription);

echo match ($status) {
    \AndyDefer\LaravelNotification\Enums\PingStatus::PONG => 'Alive',
    \AndyDefer\LaravelNotification\Enums\PingStatus::INVALID => 'Pruned',
};
```