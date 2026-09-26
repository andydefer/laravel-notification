# FirebaseCloudMessagingDriver - Référence Technique

## Description

Driver d'envoi de notifications push via Firebase Cloud Messaging (HTTP v1). Gère l'authentification OAuth 2 Google, la construction du payload et l'appel HTTP à l'API FCM.

## Hiérarchie / Implémentations

```
AbstractDriver
    └── FirebaseCloudMessagingDriver
```

## Rôle principal

Ce driver est instancié par `FirebaseCloudMessagingChannel::createDriver()`. Il :

- Vérifie que la configuration Firebase est complète.
- Génère (et met en cache) un access token OAuth 2 via un Service Account Google.
- Résout le `project_id` (depuis la config ou depuis le fichier credentials).
- Construit le payload FCM v1 (`token`, `notification`, `data`).
- Envoie la requête HTTP et interprète la réponse.
- Remonte les erreurs sous forme de `RuntimeException`.

## Installation

Le driver nécessite le SDK Google :

```bash
composer require google/apiclient
```

Configuration attendue dans `config/notification.php` :

```php
'firebase' => [
    'enabled' => true,
    'credentials_path' => env('FIREBASE_CREDENTIALS_PATH'),
    'project_id' => env('FIREBASE_PROJECT_ID'),
    'scope' => 'https://www.googleapis.com/auth/firebase.messaging',
    'timeout' => 30,
],
```

## API / Méthodes publiques

### `__construct(FirebaseConfigRecord $config)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$config` | `FirebaseConfigRecord` | Configuration Firebase |

**Retourne :** `void`

---

### `getChannel(): string`

Retourne le nom court du canal.

**Retourne :** `string` - Toujours `'firebase'`.

---

### `validateConfiguration(): bool`

Vérifie que le driver est prêt à envoyer.

**Retourne :** `bool` - `true` si :
- `enabled === true`
- `credentials_path` non vide
- Le fichier existe sur le disque

**Exemple :**

```php
if ($driver->validateConfiguration()) {
    // Prêt à envoyer.
}
```

---

### `send(NotificationMessageVO $message, NotificationRouteVO $route): SendResultRecord`

Héritée de `AbstractDriver`. Exécute `execute()` après validation.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `NotificationMessageVO` | Message à envoyer |
| `$route` | `NotificationRouteVO` | Destination + metadata |

**Retourne :** `SendResultRecord` - Résultat de l'envoi (success, channel, destination, error_message)

**Exceptions :** Le driver encapsule `execute()` et retourne un résultat d'échec plutôt que de propager. `execute()` peut lever :

| Exception | Cause |
|-----------|-------|
| `RuntimeException` | Configuration incomplète, token manquant, FCM HTTP non-2xx |

---

### `execute(NotificationMessageVO $message, NotificationRouteVO $route): bool` (protected)

Logique principale. Non appelable directement, mais c'est le cœur du driver.

**Payload FCM v1 envoyé :**

```json
{
    "message": {
        "token": "eQhyKer9t...",
        "notification": {
            "title": "Bonjour ! 👋",
            "body": "Ceci est un message envoyé depuis du PHP pur."
        },
        "data": {
            "screen": "profile",
            "user_id": "42"
        }
    }
}
```

**Règles de construction :**

- **Titre** : priorité à `route.metadata.title`, sinon `message.subject`, sinon `'Notification'`.
- **Corps** : `message.body`.
- **Data** : fusion `route.metadata.data` + `message.data`. Toutes les valeurs sont converties en **string** (les tableaux/objets sont encodés en JSON).
- **Token** : `route.destination`.

**Retourne :** `bool` - `true` si HTTP 2xx.

---

## Cas d'utilisation

### Cas 1 : Envoi d'une notification simple

```php
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\FirebaseCloudMessagingChannel;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

$message = new NotificationMessageVO(
    body: new MessageBodyVO('Bonjour depuis PHP.'),
    subject: new MessageSubjectVO('Salut !'),
);

$route = new NotificationRouteVO(
    channelClass: FirebaseCloudMessagingChannel::class,
    destination: $deviceToken,
    metadata: new StrictDataObject([
        'title' => 'Nouveau message',
        'data' => ['screen' => 'inbox'],
    ]),
);

$record = SendNowRecord::from([
    'channels' => [FirebaseCloudMessagingChannel::class],
    'limit_per_channel' => 1,
]);

app(NotificationService::class)->sendNow($user, $message, $record);
```

### Cas 2 : Envoi avec données riches

Les valeurs non scalaires sont automatiquement encodées en JSON :

```php
$route = new NotificationRouteVO(
    channelClass: FirebaseCloudMessagingChannel::class,
    destination: $deviceToken,
    metadata: new StrictDataObject([
        'title' => 'Commande',
        'data' => [
            'order_id' => '42',
            'items' => ['pizza', 'boisson'],
            'total' => 24.5,
        ],
    ]),
);
```

Payload `data` résultant :

```json
{
    "order_id": "42",
    "items": "[\"pizza\",\"boisson\"]",
    "total": "24.5"
}
```

## Flux d'exécution

```
execute($message, $route)
    → validateConfiguration()
    → resolveProjectId()
    → resolveTitle()
    → resolveData()
    → accessToken()
        → Google\Client::setAuthConfig()
        → Google\Client::fetchAccessTokenWithAssertion()
    → Http::withToken()->post(fcm.googleapis.com/v1/...)
        ├── 2xx → return true
        └── non-2xx → RuntimeException
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Config incomplète | `RuntimeException` | `Firebase configuration is incomplete.` |
| Token device vide | `RuntimeException` | `Firebase device token is missing.` |
| `project_id` absent | `RuntimeException` | `Firebase project_id is missing from credentials.` |
| Credentials illisibles | `RuntimeException` | `Unable to read Firebase credentials at "...".` |
| Credentials non-JSON | `RuntimeException` | `Firebase credentials file is not valid JSON.` |
| Auth Google échouée | `RuntimeException` | `Firebase authentication failed: {message}` |
| Pas d'access token | `RuntimeException` | `Firebase authentication returned no access token.` |
| FCM non-2xx | `RuntimeException` | `Firebase trigger failed (HTTP {status}): {body}` |

**Codes FCM courants :**

| HTTP | `errorCode` | Signification |
|------|-------------|---------------|
| 400 | `INVALID_ARGUMENT` | Payload malformé |
| 401 | `UNAUTHENTICATED` | Access token expiré |
| 403 | `SENDER_ID_MISMATCH` | Token appartient à un autre projet |
| 404 | `UNREGISTERED` | Token désenregistré (app désinstallée) |
| 429 | `QUOTA_EXCEEDED` | Trop de requêtes |
| 503 | `UNAVAILABLE` | FCM temporairement indisponible |

## Intégration

Le driver s'intègre avec :

- `FirebaseCloudMessagingChannel` — instancie le driver.
- `FirebaseConfigRecord` — transporte la config.
- `NotificationMessageVO` — corps, sujet, type, data.
- `NotificationRouteVO` — destination + metadata.
- `StrictDataObject` — metadata typées.
- `AbstractDriver::send()` — point d'entrée, produit un `SendResultRecord`.

## Performance

- **Access token** mis en cache dans la propriété `$accessToken` pour la durée de vie du driver (utile pour les envois en masse sur une même instance).
- **Appel HTTP** via `Illuminate\Support\Facades\Http` — compatible Guzzle, timeouts configurables.
- **Encodage data** : boucle O(n) sur les clés du payload, coût négligeable.
- Aucune requête DB dans ce driver.

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| Laravel 10+ | ✅ Complet |
| `google/apiclient` ^2.15 | ✅ Requis |
| FCM HTTP v1 | ✅ Requis (legacy non supportée) |

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

$message = new NotificationMessageVO(
    body: new MessageBodyVO('Ceci est un message envoyé depuis du PHP pur.'),
    subject: new MessageSubjectVO('Bonjour ! 👋'),
    type: 'test',
    data: new StrictDataObject([
        'screen' => 'profile',
        'user_id' => '42',
    ]),
);

$route = new NotificationRouteVO(
    channelClass: FirebaseCloudMessagingChannel::class,
    destination: 'eQhyKer9t_jperz7AYDDVB:APA91bFJTg_RGtoAIKIqj2Ps4ToXUWDua12kmAj8GD6W9UtELfheJLQNzhvl6Uc4Q7tdkT6FAS2GX4UdAwt4sv2nBn1nYN2Bl6rS5A_HbokZ3E7IaMlTM-s',
    metadata: new StrictDataObject([
        'title' => 'Test Title',
    ]),
);

$record = SendNowRecord::from([
    'channels' => [FirebaseCloudMessagingChannel::class],
    'limit_per_channel' => 1,
]);

$service = app(NotificationService::class);
$results = $service->sendNow($user, $message, $record);

if ($results->allSuccess()) {
    echo "✅ Notification envoyée.\n";
} else {
    foreach ($results->getFailures() as $failure) {
        echo "❌ " . $failure->error_message->getValue() . "\n";
    }
}
```