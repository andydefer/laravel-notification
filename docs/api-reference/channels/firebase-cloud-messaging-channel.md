# FirebaseCloudMessagingChannel - Référence Technique

## Description

Canal de notification pour Firebase Cloud Messaging (FCM). Enregistre les tokens d'enregistrement Firebase et délègue l'envoi effectif au driver `FirebaseCloudMessagingDriver`.

## Hiérarchie / Implémentations

```
AbstractChannel
    └── FirebaseCloudMessagingChannel
```

Interfaces héritées :
- `ChannelInterface` (via `AbstractChannel`)
- `DestinationValidatable` (via `AbstractChannel`)

## Rôle principal

Dans l'architecture du package, ce canal :

- Identifie le canal par son nom court `firebase`.
- Vérifie que FCM est activé dans la configuration globale.
- Valide les tokens d'enregistrement avant toute tentative d'envoi.
- Instancie le driver FCM avec la configuration extraite du conteneur.

Il sert de **point d'entrée côté Notification** : l'utilisateur ajoute un `NotificationRouteVO` avec `FirebaseCloudMessagingChannel::class` comme `channelClass`, et le reste est géré par le canal et son driver.

## Installation

Le canal est disponible dès que le package est installé :

```bash
composer require andydefer/laravel-notification
```

Prérequis pour l'activer :

- Fichier de credentials Firebase (`service-account.json`).
- Variable `FIREBASE_NOTIFICATION_ENABLED=true`.
- Chemin `FIREBASE_CREDENTIALS_PATH` vers le fichier.
- `FIREBASE_PROJECT_ID` renseigné.

## API / Méthodes publiques

### `__construct(NotificationConfigInterface $config)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$config` | `NotificationConfigInterface` | Configuration globale du package |

**Retourne :** `void`

**Exceptions :** Aucune.

---

### `getName(): string`

Retourne le nom court du canal, utilisé comme identifiant interne.

**Retourne :** `string` - Toujours `'firebase'`.

**Exemple :**

```php
$channel->getName(); // 'firebase'
```

---

### `getLabel(): string`

Retourne le libellé humain du canal.

**Retourne :** `string` - Toujours `'Firebase Cloud Messaging'`.

---

### `getIcon(): string`

Retourne l'icône emoji associée au canal.

**Retourne :** `string` - Toujours `'🔥'`.

---

### `isEnabled(): bool`

Indique si le canal est activé dans la configuration.

**Retourne :** `bool` - `true` si `FIREBASE_NOTIFICATION_ENABLED=true`, `false` sinon.

**Exemple :**

```php
if ($channel->isEnabled()) {
    // Le canal peut envoyer des notifications.
}
```

---

### `validateDestination(string $destination): bool` (static)

Valide un token d'enregistrement FCM.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$destination` | `string` | Token FCM à valider |

**Retourne :** `bool` - `true` si le token est syntaxiquement valide, `false` sinon.

**Règles de validation :**
- Longueur comprise entre **100 et 512 caractères**.
- Uniquement des caractères `[A-Za-z0-9_\-:.]`.

**Exceptions :** Aucune.

**Exemple :**

```php
FirebaseCloudMessagingChannel::validateDestination(
    'eQhyKer9t_jperz7AYDDVB:APA91bFJTg_RGtoAIKIqj2Ps4ToXUWDua12kmAj8GD6W9UtELfheJLQNzhvl6Uc4Q7tdkT6FAS2GX4UdAwt4sv2nBn1nYN2Bl6rS5A_HbokZ3E7IaMlTM-s'
); // true

FirebaseCloudMessagingChannel::validateDestination('short'); // false
```

---

### `createDriver(): AbstractDriver`

Instancie le driver FCM avec la configuration extraite du conteneur.

**Retourne :** `AbstractDriver` - Une instance de `FirebaseCloudMessagingDriver`.

**Exceptions :** Aucune (l'erreur de configuration est levée par le driver lors de `validateConfiguration()`).

**Exemple :**

```php
$driver = $channel->createDriver();
```

---

## Cas d'utilisation

### Cas 1 : Envoi d'une notification push à un appareil

Un utilisateur s'est abonné aux notifications push via un navigateur ou une app mobile. Son token FCM est stocké en base. On souhaite lui envoyer une notification.

```php
use AndyDefer\LaravelNotification\Channels\FirebaseCloudMessagingChannel;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

$message = new NotificationMessageVO(
    body: new MessageBodyVO('Votre commande #123 est expédiée.'),
    subject: new MessageSubjectVO('Commande expédiée'),
    type: 'order_shipped',
    data: new StrictDataObject([
        'order_id' => '123',
        'screen' => 'orders',
    ]),
);

$route = new NotificationRouteVO(
    channelClass: FirebaseCloudMessagingChannel::class,
    destination: $user->fcm_token,
    metadata: new StrictDataObject([
        'title' => 'Commande expédiée',
    ]),
);

$record = SendNowRecord::from([
    'channels' => [FirebaseCloudMessagingChannel::class],
    'limit_per_channel' => 1,
]);

$service = app(NotificationService::class);
$service->sendNow($user, $message, $record);
```

### Cas 2 : Envoi à plusieurs appareils d'un même utilisateur

Un utilisateur s'est connecté sur plusieurs navigateurs. Chaque navigateur a son propre token FCM. On veut envoyer à tous ses appareils.

```php
foreach ($user->fcm_tokens as $token) {
    $route = new NotificationRouteVO(
        channelClass: FirebaseCloudMessagingChannel::class,
        destination: $token,
        metadata: new StrictDataObject(['title' => 'Alerte sécurité']),
    );

    // Chaque route est traitée indépendamment.
}
```

## Flux d'exécution

```
NotificationService.sendNow()
    → NotificationSenderProcessor
        → FirebaseCloudMessagingChannel::validateDestination($token)
        → FirebaseCloudMessagingChannel::createDriver()
            → FirebaseCloudMessagingDriver::send()
                → FirebaseCloudMessagingDriver::validateConfiguration()
                → FirebaseCloudMessagingDriver::execute()
                    → HTTP POST fcm.googleapis.com/v1/projects/{projectId}/messages:send
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Token invalide | Aucune (retourne `false`) | — |
| Canal désactivé | Aucune (retourne `false` via `isEnabled()`) | — |
| Credentials manquants | `RuntimeException` (levée par le driver) | `Firebase configuration is incomplete.` |
| Fichier credentials introuvable | `RuntimeException` (levée par le driver) | `Firebase credentials file not found at "...".` |

## Intégration

Le canal s'intègre avec :

- `NotificationRouteCollection` — pour déclarer la destination dans `getNotificationChannels()`.
- `NotificationService` — pour déclencher l'envoi.
- `NotificationSenderProcessor` — pour orchestrer.
- `FirebaseCloudMessagingDriver` — pour l'appel HTTP vers FCM.
- `FirebaseConfigRecord` — pour transporter la configuration.

## Performance

- `validateDestination()` : **O(n)** sur la longueur du token, avec une regex rapide. Négligeable.
- `createDriver()` : instantiation légère, pas d'appel réseau.
- Le coût réel est dans le driver (appel HTTP OAuth + FCM), pas dans le canal.

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| Laravel 10+ | ✅ Complet |
| Firebase HTTP v1 | ✅ Requis (v1 legacy non supportée) |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\FirebaseCloudMessagingChannel;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

// 1. Construire le message
$message = new NotificationMessageVO(
    body: new MessageBodyVO('Ceci est un message envoyé depuis du PHP pur.'),
    subject: new MessageSubjectVO('Bonjour ! 👋'),
    type: 'test',
    data: new StrictDataObject([
        'screen' => 'profile',
        'user_id' => '42',
    ]),
);

// 2. Construire la route
$route = new NotificationRouteVO(
    channelClass: FirebaseCloudMessagingChannel::class,
    destination: 'eQhyKer9t_jperz7AYDDVB:APA91bFJTg_RGtoAIKIqj2Ps4ToXUWDua12kmAj8GD6W9UtELfheJLQNzhvl6Uc4Q7tdkT6FAS2GX4UdAwt4sv2nBn1nYN2Bl6rS5A_HbokZ3E7IaMlTM-s',
    metadata: new StrictDataObject([
        'title' => 'Test Title',
    ]),
);

// 3. Configurer l'envoi
$record = SendNowRecord::from([
    'channels' => [FirebaseCloudMessagingChannel::class],
    'limit_per_channel' => 1,
]);

// 4. Envoyer
$service = app(NotificationService::class);
$results = $service->sendNow($user, $message, $record);

// 5. Vérifier le résultat
if ($results->allSuccess()) {
    echo "Notification envoyée avec succès.\n";
} else {
    foreach ($results->getFailures() as $failure) {
        echo "Échec : " . $failure->error_message->getValue() . "\n";
    }
}
```
