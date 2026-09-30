# NotificationRouteBuilder - Référence Technique

## Description

Builder fluent qui construit une `NotificationRouteCollection` en garantissant l'unicité par paire `(channel, destination)`. Encapsule la construction répétitive des routes de notification (mail, database, FCM, Web Push, Pusher) partagée par tous les modèles notifiables.

## Hiérarchie / Implémentations

```
NotificationRouteBuilder (final)
```

Aucune interface, aucune classe parente. La classe est `final` et n'implémente aucun contrat : c'est un utilitaire de construction.

## Rôle principal

Offrir une API fluide et typée aux modèles qui implémentent `NotifiableInterface::getNotificationChannels()`. Le builder :

1. Valide les entrées (types, valeurs vides).
2. Ignore silencieusement les entrées vides ou incomplètes.
3. Empêche les doublons sur la paire `(channelClass, destination)`.
4. Retourne une `NotificationRouteCollection` prête à être consommée par `NotificationSenderProcessor`.

Le but est de supprimer la duplication entre modèles (`User`, `Pharmacy`, etc.) et de centraliser les règles de validation de route.

## Installation

Aucune installation spécifique. La classe est livrée avec le package `andydefer/laravel-notification`.

**Prérequis :**

- PHP 8.2+
- `andydefer/domain-structures` (`StrictDataObject`)
- Les modèles `FcmDevice`, `WebPushSubscription` du package
- Les Value Objects `NotificationRouteVO`, `PusherChannelNameVO`
- Toutes les classes `*Channel` du package

## API / Méthodes publiques

### `make(): self`

Crée une nouvelle instance vide du builder.

**Retourne :** `self` — Un builder sans aucune route.

**Exemple :**

```php
$builder = NotificationRouteBuilder::make();
```

---

### `addMail(?string $email, array $metadata = []): self`

Ajoute une route `MailChannel` pour l'adresse donnée. Ignorée si `$email` est `null` ou vide. Ignorée si une route mail existe déjà pour cette adresse.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$email` | `?string` | Adresse email de destination |
| `$metadata` | `array` | Métadonnées arbitraires attachées à la route |

**Retourne :** `self` — Le builder pour chaînage.

**Exemple :**

```php
NotificationRouteBuilder::make()
    ->addMail('user@example.com', ['name' => 'John'])
    ->build();
```

---

### `addMails(iterable $emails, array $metadata = []): self`

Ajoute une route mail par adresse. Délègue à `addMail()` pour chaque entrée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$emails` | `iterable<string>` | Liste d'adresses email |
| `$metadata` | `array` | Métadonnées appliquées à toutes les routes |

**Retourne :** `self`

**Exceptions :** `InvalidArgumentException` — Si une entrée n'est pas un `string`.

**Exemple :**

```php
NotificationRouteBuilder::make()
    ->addMails(['a@example.com', 'b@example.com'])
    ->build();
```

---

### `addDatabase(): self`

Ajoute une route `DatabaseChannel` avec destination `'database'`. Ignorée si une route database existe déjà.

**Retourne :** `self`

**Exemple :**

```php
NotificationRouteBuilder::make()->addDatabase()->build();
```

---

### `addFcm(FcmDevice $device, array $metadata = []): self`

Ajoute une route `FirebaseCloudMessagingChannel` pour le token du device. Ignorée si le token est vide ou si une route FCM existe déjà pour ce token.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$device` | `FcmDevice` | Device FCM à attacher |
| `$metadata` | `array` | Métadonnées additionnelles |

**Retourne :** `self`

**Exemple :**

```php
$device = new FcmDevice(['token' => 'token-abc', 'device_id' => 'device-1']);

NotificationRouteBuilder::make()->addFcm($device)->build();
```

---

### `addFcms(iterable $devices, array $metadata = []): self`

Ajoute une route FCM par device.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$devices` | `iterable<FcmDevice>` | Liste de devices FCM |
| `$metadata` | `array` | Métadonnées appliquées à toutes les routes |

**Retourne :** `self`

**Exceptions :** `InvalidArgumentException` — Si une entrée n'est pas une instance de `FcmDevice`.

**Exemple :**

```php
NotificationRouteBuilder::make()->addFcms([$deviceA, $deviceB])->build();
```

---

### `addWebPush(WebPushSubscription $subscription, array $metadata = []): self`

Ajoute une route `WebPushChannel` pour la souscription. Ignorée si `endpoint`, `p256dh` ou `auth` est vide. Ignorée si une route Web Push existe déjà pour cet endpoint.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$subscription` | `WebPushSubscription` | Souscription navigateur |
| `$metadata` | `array` | Métadonnées additionnelles |

**Retourne :** `self`

**Exemple :**

```php
$sub = new WebPushSubscription([
    'endpoint' => 'https://push.example.com/abc',
    'p256dh' => 'p256dh-key',
    'auth' => 'auth-secret',
]);

NotificationRouteBuilder::make()->addWebPush($sub)->build();
```

---

### `addWebPushes(iterable $subscriptions, array $metadata = []): self`

Ajoute une route Web Push par souscription.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$subscriptions` | `iterable<WebPushSubscription>` | Liste de souscriptions |
| `$metadata` | `array` | Métadonnées appliquées à toutes les routes |

**Retourne :** `self`

**Exceptions :** `InvalidArgumentException` — Si une entrée n'est pas une instance de `WebPushSubscription`.

**Exemple :**

```php
NotificationRouteBuilder::make()->addWebPushes([$subA, $subB])->build();
```

---

### `addPusher(string $channel, string $event = 'notification'): self`

Ajoute une route `PusherChannel` pour un nom de canal quelconque. Ignorée si `$channel` est vide ou si une route Pusher existe déjà pour ce nom de canal (peu importe l'événement).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$channel` | `string` | Nom du canal Pusher |
| `$event` | `string` | Nom de l'événement diffusé |

**Retourne :** `self`

**Exemple :**

```php
NotificationRouteBuilder::make()->addPusher('announcements')->build();
```

---

### `addPusherForModel(Model $model, string $event = 'notification'): self`

Ajoute une route Pusher sur le canal privé dérivé du modèle. Le nom est calculé par `PusherChannelNameVO::forModel($model)`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$model` | `Model` | Modèle notifiable |
| `$event` | `string` | Nom de l'événement |

**Retourne :** `self`

**Exceptions :** `InvalidArgumentException` — Si le modèle n'a pas de morph class ou de clé exploitable (via `PusherChannelNameVO`).

**Exemple :**

```php
NotificationRouteBuilder::make()->addPusherForModel($user)->build();
```

---

### `build(): NotificationRouteCollection`

Retourne la collection construite.

**Retourne :** `NotificationRouteCollection` — La collection finale.

**Exemple :**

```php
$collection = NotificationRouteBuilder::make()
    ->addMail('user@example.com')
    ->addDatabase()
    ->build();
```

## Cas d'utilisation

### Cas 1 : Modèle avec tous les canaux

Un modèle notifiable expose ses canaux mail, database, FCM, Web Push et Pusher en une seule chaîne.

```php
public function getNotificationChannels(): NotificationRouteCollection
{
    return NotificationRouteBuilder::make()
        ->addMail($this->email, [
            'name' => $this->name,
            'user_id' => $this->id,
        ])
        ->addDatabase()
        ->addFcms($this->fcmDevices, ['user_id' => $this->id])
        ->addWebPushes($this->webPushSubscriptions, ['user_id' => $this->id])
        ->addPusher('announcements')
        ->addPusherForModel($this)
        ->build();
}
```

### Cas 2 : Modèle minimal mail + database

Un modèle qui ne supporte que les canaux de base.

```php
public function getNotificationChannels(): NotificationRouteCollection
{
    return NotificationRouteBuilder::make()
        ->addMail($this->email?->getValue(), [
            'name' => $this->name,
            'pharmacy_id' => $this->id,
            'type' => 'pharmacy',
        ])
        ->addDatabase()
        ->build();
}
```

### Cas 3 : Ignore silencieusement les entrées invalides

Un modèle dont certains devices FCM ont un token vide n'échoue pas — les routes invalides sont ignorées.

```php
$deviceWithEmptyToken = new FcmDevice(['token' => '', 'device_id' => 'x']);

$collection = NotificationRouteBuilder::make()
    ->addFcm($deviceWithEmptyToken)
    ->build();

// $collection est vide, aucune exception
```

## Flux d'exécution

```
make() → builder vide
    ↓
addMail / addDatabase / addFcm / addWebPush / addPusher
    ↓
Validation :
    ├── Entrée vide → ignorée
    ├── Doublon (channel, destination) → ignoré
    └── Entrée invalide (mauvais type) → InvalidArgumentException
    ↓
NotificationRouteVO construit et ajouté à la collection
    ↓
build() → NotificationRouteCollection
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| `addMails()` avec entrée non-string | `InvalidArgumentException` | `Emails must be strings.` |
| `addFcms()` avec entrée non-`FcmDevice` | `InvalidArgumentException` | `Expected an instance of AndyDefer\LaravelNotification\Models\FcmDevice.` |
| `addWebPushes()` avec entrée non-`WebPushSubscription` | `InvalidArgumentException` | `Expected an instance of AndyDefer\LaravelNotification\Models\WebPushSubscription.` |
| `addPusherForModel()` avec modèle sans morph class | `InvalidArgumentException` | Levée par `PusherChannelNameVO::forModel()`. |
| `addPusherForModel()` avec modèle sans clé | `InvalidArgumentException` | Levée par `PusherChannelNameVO::forModel()`. |

## Intégration

Le builder s'utilise à l'intérieur de `NotifiableInterface::getNotificationChannels()`. Le résultat est consommé par `NotificationSenderProcessor`, qui itère sur chaque `NotificationRouteVO` pour résoudre le canal et le driver.

```
Modèle → getNotificationChannels()
    └── NotificationRouteBuilder
        └── NotificationRouteCollection
            └── NotificationSenderProcessor
                └── Channels + Drivers
```

## Performance

- Complexité : O(n) sur `has()` en fonction du nombre de routes déjà ajoutées. Pour une collection de moins de 100 routes, l'impact est négligeable.
- Aucun appel réseau, aucune requête base de données.
- Les `NotificationRouteVO` sont construits à la volée, sans cache.
- La déduplication évite la consommation superflue lors de l'envoi, ce qui est un gain net pour les envois massifs.

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1 | ⚠️ Non testé (propriétés `readonly`, enums) |
| PHP 8.0 | ❌ Incompatible |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Builders\NotificationRouteBuilder;
use AndyDefer\LaravelNotification\Models\FcmDevice;
use AndyDefer\LaravelNotification\Models\WebPushSubscription;

$deviceA = new FcmDevice([
    'token' => 'token-a',
    'device_id' => 'device-a',
]);

$deviceB = new FcmDevice([
    'token' => 'token-b',
    'device_id' => 'device-b',
]);

$subscription = new WebPushSubscription([
    'endpoint' => 'https://push.example.com/abc',
    'p256dh' => 'p256dh-key',
    'auth' => 'auth-secret',
]);

$collection = NotificationRouteBuilder::make()
    ->addMail('user@example.com', ['name' => 'John'])
    ->addMails(['secondary@example.com', 'archive@example.com'])
    ->addDatabase()
    ->addFcm($deviceA, ['user_id' => 42])
    ->addFcms([$deviceB], ['user_id' => 42])
    ->addWebPush($subscription, ['user_id' => 42])
    ->addPusher('announcements')
    ->addPusher('announcements') // ignoré (doublon)
    ->build();

foreach ($collection as $route) {
    echo $route->getChannelClass() . ' → ' . $route->getDestination() . PHP_EOL;
}

// AndyDefer\LaravelNotification\Channels\MailChannel → user@example.com
// AndyDefer\LaravelNotification\Channels\MailChannel → secondary@example.com
// AndyDefer\LaravelNotification\Channels\MailChannel → archive@example.com
// AndyDefer\LaravelNotification\Channels\DatabaseChannel → database
// AndyDefer\LaravelNotification\Channels\FirebaseCloudMessagingChannel → token-a
// AndyDefer\LaravelNotification\Channels\FirebaseCloudMessagingChannel → token-b
// AndyDefer\LaravelNotification\Channels\WebPushChannel → https://push.example.com/abc
// AndyDefer\LaravelNotification\Channels\PusherChannel → announcements
```