# FirebaseCloudMessagingDriver - Référence Technique

## Description

Driver d'envoi de notifications push via **Firebase Cloud Messaging HTTP v1**. Il transforme un `NotificationMessageVO` en payload FCM conforme et l'envoie à l'API Google avec authentification OAuth 2.0.

## Hiérarchie / Implémentations

```
AbstractDriver
    └── FirebaseCloudMessagingDriver
```

## Rôle principal

Ce driver est le **maillon d'exécution** du canal `FirebaseCloudMessagingChannel`. Il :

1. Résout le `project_id` Firebase (config ou credentials)
2. Obtient un access token OAuth 2.0 via un service account Google
3. Construit le payload FCM HTTP v1 (**3 blocs** : `notification`, `android.notification`, `data`)
4. Envoie la requête HTTP POST vers `https://fcm.googleapis.com/v1/projects/{projectId}/messages:send`
5. Lève une `RuntimeException` si l'envoi échoue

Il **ne contient aucune logique métier** — il lit le message (`NotificationMessageVO`) et le transforme. La route (`NotificationRouteVO`) ne porte que le **token** du device.

## Installation

```bash
composer require google/auth guzzlehttp/guzzle
```

Variables `.env` requises :

```env
FIREBASE_NOTIFICATION_ENABLED=true
FIREBASE_CREDENTIALS_PATH=/path/to/service-account.json
FIREBASE_PROJECT_ID=my-project-id
```

## API / Méthodes publiques

### `getChannel(): string`

Retourne le nom canonique du canal.

**Retourne :** `string` — Toujours `'firebase'`.

**Exemple :**
```php
$driver->getChannel(); // 'firebase'
```

---

### `validateConfiguration(): bool`

Vérifie que la configuration Firebase est complète et que le fichier de credentials existe.

**Retourne :** `bool` — `true` si `enabled=true`, `credentials_path` non vide et fichier existant.

**Exemple :**
```php
if (! $driver->validateConfiguration()) {
    throw new RuntimeException('Firebase non configuré');
}
```

---

### `getFirebaseCredentialsFilePath(): string`

Résout le chemin absolu du fichier de credentials depuis `Paths::projectRoot()`.

**Retourne :** `string` — Chemin absolu.

**Exceptions :** `RuntimeException` si `credentials_path` est vide.

**Exemple :**
```php
$path = $driver->getFirebaseCredentialsFilePath();
// '/var/www/app/firebase-credentials.json'
```

---

### `execute(NotificationMessageVO $message, NotificationRouteVO $route): bool`

**Méthode protégée** héritée d'`AbstractDriver`, exposée via `send()`. Effectue l'envoi réel.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `NotificationMessageVO` | Message portant titre, corps, type, data, metadata |
| `$route` | `NotificationRouteVO` | Route portant le token du device |

**Retourne :** `bool` — `true` si Firebase répond avec un statut HTTP 2xx.

**Exceptions :**
- `RuntimeException` — Configuration invalide
- `RuntimeException` — Token device manquant ou non-string
- `RuntimeException` — `project_id` introuvable
- `RuntimeException` — Authentification Google échouée
- `RuntimeException` — Réponse HTTP non-2xx de Firebase

**Exemple :**
```php
$route = new NotificationRouteVO(
    channelClass: FirebaseCloudMessagingChannel::class,
    destination: 'eQhyKer9t_jperz7AYDDVB:APA91b...',
);

$message = new NotificationMessageVO(
    body: new MessageBodyVO('Votre profil a été mis à jour.'),
    subject: new MessageSubjectVO('Profil mis à jour'),
    type: 'profile_updated',
    data: new StrictDataObject(['user_id' => '61']),
    metadata: new StrictDataObject([
        'icon'       => 'ic_notification',
        'channel_id' => 'afya_payments',
    ]),
);

$result = $driver->send($message, $route);

if (! $result->success) {
    Log::error($result->error_message?->getValue());
}
```

## Cas d'utilisation

### Cas 1 : Notification simple (titre + corps)

Envoi sans métadonnées visuelles — Firebase utilise le canal par défaut.

```php
$message = new NotificationMessageVO(
    body: new MessageBodyVO('Bonjour depuis PHP.'),
    subject: new MessageSubjectVO('Test'),
    type: 'test',
);

$driver->send($message, $route);
```

**Payload produit :**
```json
{
  "message": {
    "token": "...",
    "notification": { "title": "Test", "body": "Bonjour depuis PHP." },
    "data": { "type": "test", "payload": "{}" }
  }
}
```

---

### Cas 2 : Notification avec métadonnées visuelles Android

Icône, image, canal, son, tag, click_action.

```php
$message = new NotificationMessageVO(
    body: new MessageBodyVO('Votre commande a été expédiée.'),
    subject: new MessageSubjectVO('Commande expédiée'),
    type: 'order_shipped',
    data: new StrictDataObject(['order_id' => 42]),
    metadata: new StrictDataObject([
        'icon'         => 'ic_notification',
        'image'        => 'https://cdn.afya.com/banner.png',
        'channel_id'   => 'afya_orders',
        'sound'        => 'default',
        'tag'          => 'orders',
        'click_action' => 'OPEN_ORDERS',
    ]),
);

$driver->send($message, $route);
```

**Payload produit :**
```json
{
  "message": {
    "token": "...",
    "notification": {
      "title": "Commande expédiée",
      "body": "Votre commande a été expédiée."
    },
    "android": {
      "notification": {
        "icon": "ic_notification",
        "image": "https://cdn.afya.com/banner.png",
        "channel_id": "afya_orders",
        "sound": "default",
        "tag": "orders",
        "click_action": "OPEN_ORDERS"
      }
    },
    "data": {
      "type": "order_shipped",
      "payload": "{\"order_id\":42}"
    }
  }
}
```

---

### Cas 3 : Payload métier complexe avec tableaux

Les tableaux PHP sont automatiquement `json_encode()`-és **une seule fois** par le driver.

```php
$message = new NotificationMessageVO(
    body: new MessageBodyVO('Profil mis à jour.'),
    subject: new MessageSubjectVO('Profil'),
    type: 'profile_updated',
    data: new StrictDataObject([
        'user_id'        => '61',
        'updated_fields' => ['currency', 'phone'], // ← array natif
        'roles'          => ['user', 'admin'],
    ]),
);

$driver->send($message, $route);
```

**Payload produit :**
```json
"data": {
  "type": "profile_updated",
  "payload": "{\"user_id\":\"61\",\"updated_fields\":[\"currency\",\"phone\"],\"roles\":[\"user\",\"admin\"]}"
}
```

⚠️ **Ne jamais** pré-encoder avec `json_encode()` dans `data` → double stringification.

## Flux d'exécution

```
execute()
  │
  ├─→ validateConfiguration()   [runtime]
  │     └─ si false → RuntimeException
  │
  ├─→ resolveProjectId()
  │     ├─ config->project_id ?? credentials->project_id
  │     └─ si vide → RuntimeException
  │
  ├─→ route->getDestination()
  │     └─ si vide/non-string → RuntimeException
  │
  ├─→ resolveData($message)
  │     └─ data->toArray() → json_encode(tableaux)
  │
  ├─→ resolveTitle($message)
  │     └─ subject ?? DEFAULT_TITLE ('Notification')
  │
  ├─→ resolveAndroidNotification($message)
  │     └─ metadata->get('icon'|'image'|'channel_id'|...)
  │
  ├─→ array_filter($fcmPayload)  [retire 'android' si vide]
  │
  ├─→ accessToken()
  │     ├─ cache ?? nouveau GoogleClient
  │     ├─ credentials path + scope
  │     └─ fetchAccessTokenWithAssertion()
  │
  └─→ Http::withToken($token)->post(fcm.googleapis.com/...)
        ├─ 2xx → return true
        └─ non-2xx → RuntimeException (HTTP status + body)
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Config Firebase incomplète | `RuntimeException` | `Firebase configuration is incomplete.` |
| Token device manquant | `RuntimeException` | `Firebase device token is missing.` |
| `project_id` absent | `RuntimeException` | `Firebase project_id is missing from credentials.` |
| Auth Google échouée | `RuntimeException` | `Firebase authentication failed: {msg}` |
| Pas d'access_token retourné | `RuntimeException` | `Firebase authentication returned no access token.` |
| Credentials file introuvable | `RuntimeException` | `Firebase credentials file not found at "{path}".` |
| Credentials file illisible | `RuntimeException` | `Unable to read Firebase credentials at "{path}".` |
| Credentials JSON invalide | `RuntimeException` | `Firebase credentials file is not valid JSON.` |
| `credentials_path` vide | `RuntimeException` | `Firebase credentials path is not configured.` |
| Réponse HTTP non-2xx | `RuntimeException` | `Firebase trigger failed (HTTP {code}): {body}` |

## Intégration

```
FirebaseCloudMessagingChannel
    └── createDriver() → FirebaseCloudMessagingDriver
            ├── dépend de FirebaseConfigRecord (hydraté depuis .env)
            ├── consomme NotificationMessageVO (body, subject, type, data, metadata)
            ├── consomme NotificationRouteVO (destination = token)
            └── retourne SendResultRecord (success/error/channel/destination)
```

Le driver est invoqué par `NotificationSenderProcessor` pour chaque `NotificationRouteVO` dont le canal est `FirebaseCloudMessagingChannel`.

## Performance

| Aspect | Détail |
|---|---|
| **Cache access token** | `$this->accessToken` — un seul appel Google par instance |
| **Latence réseau** | 1 requête HTTP POST par device token (pas de batch natif FCM HTTP v1) |
| **`json_encode`** | Une seule passe sur les valeurs non scalaires de `data` |
| **`array_filter`** | O(n) sur 4 clés racines — négligeable |
| **Credentials load** | 1 seule fois par `resolveProjectId()` si `project_id` absent de config |

Pour envoyer à **N devices**, itérer sur N `NotificationRouteVO` — chaque appel est indépendant. Le package fournit `PingPong` pour nettoyer les tokens invalides (voir guide `laravel-notification` §5.3).

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.1+ | ✅ Complet (readonly, enums, match) |
| PHP 8.0 | ✅ Complet (promotion, named args) |
| PHP 7.4 | ❌ Non supporté (readonly, promotion) |

| Version Firebase | Support |
|---|---|
| HTTP v1 (depuis 2023) | ✅ Seule API supportée par Google |
| Legacy FCM API | ❌ Décommissionnée par Google (2024) |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\FirebaseCloudMessagingChannel;
use AndyDefer\LaravelNotification\Drivers\FirebaseCloudMessagingDriver;
use AndyDefer\LaravelNotification\Records\FirebaseConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

// 1. Configuration (généralement depuis .env via FirebaseConfigRecord::from(...))
$config = new FirebaseConfigRecord(
    enabled: true,
    credentials_path: 'storage/firebase/service-account.json',
    project_id: 'afya-medical',
    scope: 'https://www.googleapis.com/auth/firebase.messaging',
    timeout: 30,
);

// 2. Driver
$driver = new FirebaseCloudMessagingDriver($config);

// 3. Route — uniquement le token
$route = new NotificationRouteVO(
    channelClass: FirebaseCloudMessagingChannel::class,
    destination: 'eQhyKer9t_jperz7AYDDVB:APA91bH...',
);

// 4. Message — séparation stricte `data` (métier) vs `metadata` (visuel)
$message = new NotificationMessageVO(
    body: new MessageBodyVO('Votre profil a été mis à jour avec succès.'),
    subject: new MessageSubjectVO('Profil mis à jour'),
    type: 'profile_updated',
    data: new StrictDataObject([
        'user_id'        => '61',
        'updated_fields' => ['currency'], // array natif → json_encode unique
    ]),
    metadata: new StrictDataObject([
        'icon'         => 'ic_notification',
        'image'        => 'https://cdn.afya.com/banner.png',
        'channel_id'   => 'afya_payments',
        'sound'        => 'default',
        'tag'          => 'profile',
        'click_action' => 'OPEN_PROFILE',
    ]),
);

// 5. Envoi
$result = $driver->send($message, $route);

if (! $result->success) {
    Log::error('Firebase send failed', [
        'channel'     => $result->channel->getValue(),
        'destination' => $result->destination,
        'error'       => $result->error_message?->getValue(),
    ]);

    return;
}

Log::info('Notification FCM envoyée', [
    'destination' => $result->destination,
    'type'        => $message->getType(),
]);
```