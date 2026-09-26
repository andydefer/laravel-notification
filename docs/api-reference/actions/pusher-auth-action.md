# PusherAuthAction - Référence Technique

## Description

Action HTTP qui autorise un client Pusher à s'abonner à un canal privé. Vérifie l'authentification via Nemesis, valide que le canal appartient bien à l'utilisateur, puis génère la signature Pusher attendue par le SDK client.

## Hiérarchie / Implémentations

```
AbstractAction
    └── PusherAuthAction
```

## Rôle principal

Dans l'architecture du package, cette action :

- Expose le point d'entrée `/pusher/auth` utilisé automatiquement par `pusher-js` lors de `pusher.subscribe('private-...')`.
- Authentifie l'appelant via **Laravel Nemesis** (middleware `nemesis.token`).
- Vérifie que le canal demandé suit strictement la convention `private-user-{morphType}-{id}` ou `private-user-{morphType}-{id}-device-{deviceId}`.
- Génère la signature Pusher côté serveur (le `secret` n'est jamais exposé au front).
- Retourne une réponse typée via `PusherAuthData`.

Elle ne déclenche **aucune** notification : elle autorise uniquement l'abonnement WebSocket.

## Installation

Aucune commande dédiée. La route est chargée automatiquement par le `NotificationServiceProvider` :

```bash
php artisan vendor:publish --tag=notification-routes
```

Fichier publié : `routes/notification.php`.

Prérequis :

- `andydefer/laravel-actions` installé.
- `andydefer/laravel-nemesis` installé.
- `pusher/pusher-php-server` installé.
- Config Pusher renseignée dans `config/notification.php` :

```php
'pusher' => [
    'enabled' => true,
    'app_id' => env('PUSHER_APP_ID'),
    'key' => env('PUSHER_APP_KEY'),
    'secret' => env('PUSHER_APP_SECRET'),
    'cluster' => env('PUSHER_APP_CLUSTER'),
    'use_tls' => true,
    'timeout' => 30,
],
```

## API / Méthodes publiques

### `__construct(NemesisHelper $helper, NotificationConfigInterface $notificationConfig)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$helper` | `NemesisHelper` | Helper Nemesis pour l'authentification |
| `$notificationConfig` | `NotificationConfigInterface` | Config du package (pour Pusher) |

**Retourne :** `void`

---

### `handle(AbstractRecord $request): ResponseFactory` (protected)

Point d'entrée réel. Reçoit un `PusherAuthRecord` déjà validé par `PusherAuthRequest`.

**Retourne :** `ResponseFactory` - Une réponse JSON :

```json
{
    "auth": "f1bc1733c3f2f2e30cdd:8a7c8b...",
    "channelData": null
}
```

**Cas d'erreur :** voir section *Gestion des erreurs*.

---

### Méthodes privées

#### `authorizeChannel(string $channelName, string $socketId): PusherAuthData`

Génère la signature Pusher via `pusher/pusher-php-server`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$channelName` | `string` | Nom du canal privé |
| `$socketId` | `string` | Socket ID fourni par Pusher.js |

**Retourne :** `PusherAuthData` - Objet typé contenant `auth` et `channel_data`.

**Exceptions :** `PusherException` si l'appel Pusher échoue.

---

#### `isChannelAllowedForUser(string $channelName, Model $user): bool`

Vérifie que le canal demandé correspond au couple `(morphType, id)` du modèle authentifié.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$channelName` | `string` | Nom du canal demandé |
| `$user` | `Model` | Modèle authentifié via Nemesis |

**Retourne :** `bool` - `true` si autorisé, `false` sinon.

**Canaux autorisés :**

- `private-user-{morphType}-{id}`
- `private-user-{morphType}-{id}-device-{deviceId}`

**Exemple :**

```php
// Pour App\Models\User avec id = 42
// Autorisé :   private-user-App_Models_User-42
// Autorisé :   private-user-App_Models_User-42-device-abc123
// Refusé :     private-user-App_Models_Admin-42
// Refusé :     private-user-App_Models_User-99
// Refusé :     public-channel
```

---

#### `sanitizeChannelSegment(string $value): string`

Remplace les caractères non autorisés par Pusher (`\`, espaces, etc.) par `_`. Pusher n'accepte que `[A-Za-z0-9_\-=@,.;]` dans les noms de canal.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$value` | `string` | Segment de nom de canal |

**Retourne :** `string` - Segment assaini.

**Exemple :**

```php
$this->sanitizeChannelSegment('App\\Models\\User');
// → 'App_Models_User'
```

## Cas d'utilisation

### Cas 1 : Abonnement d'un utilisateur à son canal privé

Le front appelle `pusher.subscribe('private-user-App_Models_User-42')`. `pusher-js` envoie automatiquement une requête `POST /pusher/auth` avec `socket_id` et `channel_name`, accompagnée du header `Authorization: Bearer {nemesis_token}`.

```bash
curl -X POST https://api.example.com/pusher/auth \
  -H "Authorization: Bearer 3|abcdef..." \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{"socket_id":"1234.5678","channel_name":"private-user-App_Models_User-42"}'
```

Réponse :

```json
{
    "auth": "f1bc1733c3f2f2e30cdd:abc123...",
    "channelData": null
}
```

### Cas 2 : Abonnement d'un appareil spécifique

Pour cibler une machine précise, le front utilise `private-user-{morphType}-{id}-device-{deviceId}`. Le deviceId est stocké côté client (localStorage / uuid).

```bash
curl -X POST https://api.example.com/pusher/auth \
  -H "Authorization: Bearer 3|abcdef..." \
  -H "Accept: application/json" \
  -d '{"socket_id":"1234.5678","channel_name":"private-user-App_Models_User-42-device-abc123"}'
```

### Cas 3 : Tentative d'abonnement à un canal d'un autre utilisateur

Un utilisateur 42 tente de s'abonner au canal de l'utilisateur 99. Le driver refuse.

```json
{
    "errorCode": "ORIGIN_NOT_ALLOWED",
    "message": "Forbidden channel",
    "status": 403,
    "errors": null
}
```

## Flux d'exécution

```
POST /pusher/auth
    → middleware nemesis.token
        → NemesisHelper::isAuthenticated()
    → PusherAuthRequest::rules()
        → socket_id (required, string)
        → channel_name (required, string)
    → PusherAuthRequest::getRecord()
        → PusherAuthRecord
    → PusherAuthAction::handle(PusherAuthRecord)
        → NemesisHelper::getCurrentAuthenticatable()
        → isChannelAllowedForUser()
        → authorizeChannel()
            → Pusher::authorizeChannel()
        → ResponseFactory::json(PusherAuthData)
```

## Gestion des erreurs

| Situation | HTTP | `errorCode` | Message |
|-----------|------|-------------|---------|
| Aucun token fourni | 401 | `MISSING_TOKEN` | `Token not provided` |
| Token invalide / expiré | 401 | `INVALID_TOKEN` | `Invalid token` / `Token has expired` |
| Utilisateur introuvable | 401 | `AUTHENTICATABLE_NOT_FOUND` | `User not found` |
| Canal non autorisé | 403 | `ORIGIN_NOT_ALLOWED` | `Forbidden channel` |
| Erreur Pusher | 401 | `INVALID_TOKEN` | `Pusher authentication failed` |
| `socket_id` manquant | 422 | — | Erreur de validation standard Laravel |
| `channel_name` manquant | 422 | — | Erreur de validation standard Laravel |

**Payload d'erreur typique :**

```json
{
    "message": "Forbidden channel",
    "status": 403,
    "errorCode": "ORIGIN_NOT_ALLOWED",
    "errors": null
}
```

## Intégration

L'action s'intègre avec :

- **Laravel Actions** — cycle de vie `before` / `handle` / `after`.
- **Laravel Nemesis** — `NemesisHelper` + middleware `nemesis.token`.
- **Pusher PHP SDK** — génération de signature via `Pusher::authorizeChannel()`.
- **NotificationConfig** — accès à la config Pusher (`getPusherConfig()`).
- **ErrorCode** (Nemesis) — réponses d'erreur standardisées.

**Côté front (rappel) :**

```ts
const pusher = new Pusher('f1bc1733c3f2f2e30cdd', {
    cluster: 'ap2',
    forceTLS: true,
    authEndpoint: 'https://api.example.com/pusher/auth',
    auth: {
        headers: { Authorization: `Bearer ${token}` },
    },
});
```

## Performance

- **O(n)** sur la longueur du nom de canal (validation + sanitization).
- **1 appel HTTP sortant** vers l'API Pusher (`authorizeChannel`) par requête.
- **Aucune requête DB** au-delà de la résolution du token Nemesis (déjà faite par le middleware).
- **Aucun cache** : la signature Pusher est unique par `(socket_id, channel_name)`.

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| Laravel 10+ | ✅ Complet |
| `pusher/pusher-php-server` ^7 | ✅ Requis |
| `andydefer/laravel-nemesis` | ✅ Requis |
| `andydefer/laravel-actions` | ✅ Requis |

## Exemple complet

### Backend — Route

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Actions\PusherAuthAction;
use AndyDefer\LaravelNotification\Http\Requests\PusherAuthRequest;
use Illuminate\Support\Facades\Route;
use function action_route;

Route::post('/pusher/auth', action_route(PusherAuthRequest::class, PusherAuthAction::class))
    ->name('laravel-notification.pusher.auth')
    ->middleware('nemesis.token');
```

### Frontend — Abonnement

```ts
import Pusher from 'pusher-js';

const pusher = new Pusher('f1bc1733c3f2f2e30cdd', {
    cluster: 'ap2',
    forceTLS: true,
    authEndpoint: 'https://api.example.com/pusher/auth',
    auth: {
        headers: {
            Authorization: `Bearer ${localStorage.getItem('nemesis_token') ?? ''}`,
        },
    },
});

const channelName = 'private-user-App_Models_User-42';
const channel = pusher.subscribe(channelName);

channel.bind('pusher:subscription_succeeded', () => {
    console.log('✅ Abonné à', channelName);
});

channel.bind('pusher:subscription_error', (err: unknown) => {
    console.error('❌ Erreur :', err);
});

channel.bind('notification', (payload: unknown) => {
    console.log('📩 Notification reçue :', payload);
});
```

### Backend — Envoi vers ce canal

Depuis le `PusherDriver`, la destination du `NotificationRouteVO` doit correspondre au nom de canal :

```php
use AndyDefer\LaravelNotification\Channels\PusherChannel;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

$route = new NotificationRouteVO(
    channelClass: PusherChannel::class,
    destination: 'private-user-App_Models_User-42',
    metadata: new StrictDataObject([
        'channel' => 'private-user-App_Models_User-42',
        'event' => 'notification',
    ]),
);
```
