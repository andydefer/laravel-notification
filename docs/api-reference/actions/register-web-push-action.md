# RegisterWebPushSubscriptionAction - Référence Technique

## Description

Enregistre ou met à jour une souscription Web Push pour le notifiable actuellement authentifié via Nemesis.

## Hiérarchie

```
AbstractAction
    └── RegisterWebPushSubscriptionAction
```

## Rôle principal

Point d'entrée métier de la route `POST /notification/register-webpush-subscription`. L'action associe l'endpoint Web Push fourni au notifiable courant, en déléguant la persistance au repository via une opération d'upsert idempotente basée sur l'endpoint.

## Prérequis

- Route protégée par le middleware `nemesis.token`.
- La requête entrante doit être une instance de `RegisterWebPushSubscriptionRequest`.
- Le record produit doit être un `RegisterWebPushSubscriptionRecord`.

## API / Méthodes publiques

### `__construct(NemesisHelper $helper, WebPushSubscriptionRepositoryInterface $subscriptions)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$helper` | `NemesisHelper` | Fournit l'accès au notifiable authentifié |
| `$subscriptions` | `WebPushSubscriptionRepositoryInterface` | Couche de persistance des souscriptions |

**Retourne :** `void` (constructeur)

**Exceptions :** Aucune

**Exemple :**
```php
$action = new RegisterWebPushSubscriptionAction(
    $helper,
    $subscriptions,
);
```

### `handle(AbstractRecord $request): ResponseFactory`

Méthode protégée exécutée par le cycle de vie de `AbstractAction`. Enregistre ou met à jour la souscription, puis retourne la ressource sérialisée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$request` | `AbstractRecord` | Doit être une instance de `RegisterWebPushSubscriptionRecord` |

**Retourne :** `ResponseFactory` - Réponse JSON contenant les champs `id`, `endpoint`, `browser`, `lastSeenAt`

**Exceptions :** Aucune n'est levée explicitement. Les erreurs d'authentification sont retournées sous forme de `ResponseFactory` via `ErrorCode`.

**Exemple :**
```php
$record = RegisterWebPushSubscriptionRecord::from([
    'endpoint' => 'https://jmt17.google.com/fcm/send/...',
    'p256dh' => 'BGtKsvWiILk_...',
    'auth' => 'hv_Nwds7IHarbxh2KChgEw',
]);

$response = $action->run($record);
```

## Cas d'utilisation

### Cas 1 : Première souscription d'un utilisateur

Un utilisateur navigue sur le site depuis Chrome. Le service worker enregistre la souscription et envoie ses clés.

```php
POST /notification/register-webpush-subscription
Authorization: Bearer <token>
Content-Type: application/json

{
    "endpoint": "https://jmt17.google.com/fcm/send/c9ptagJdvGY:...",
    "p256dh": "BGtKsvWiILk_...",
    "auth": "hv_Nwds7IHarbxh2KChgEw"
}
```

Réponse :
```json
{
    "id": "0192f3...",
    "endpoint": "https://jmt17.google.com/fcm/send/c9ptagJdvGY:...",
    "browser": "Chrome",
    "lastSeenAt": "2026-09-28T12:00:00Z"
}
```

### Cas 2 : Re-souscription avec le même endpoint

Le même utilisateur rafraîchit son navigateur. Le service worker renvoie la même souscription. L'upsert détecte l'endpoint existant et met à jour la ligne au lieu d'en créer une nouvelle.

```php
POST /notification/register-webpush-subscription
Authorization: Bearer <token>

{
    "endpoint": "https://jmt17.google.com/fcm/send/c9ptagJdvGY:...",
    "p256dh": "BGtKsvWiILk_...",
    "auth": "hv_Nwds7IHarbxh2KChgEw"
}
```

Réponse : même `id` que la première souscription, `lastSeenAt` rafraîchi.

### Cas 3 : Requête sans token valide

```php
POST /notification/register-webpush-subscription
(no Authorization header)
```

Réponse :
```json
{
    "errorCode": "MISSING_TOKEN",
    "message": "Token not provided",
    "status": 401
}
```

## Flux d'exécution

```
Requête HTTP (middleware nemesis.token)
    ↓
RegisterWebPushSubscriptionRequest::rules()    → validation
    ↓
RegisterWebPushSubscriptionRequest::getRecord() → RegisterWebPushSubscriptionRecord
    ↓
RegisterWebPushSubscriptionAction::handle()
    ├── $helper->isAuthenticated()  → sinon MISSING_TOKEN (401)
    ├── $helper->getCurrentAuthenticatable()
    │       └── sinon AUTHENTICATABLE_NOT_FOUND
    ├── $subscriptions->upsertFor(WebPushSubscriptionRecord)
    └── ResponseFactory::json(WebPushSubscriptionData)
    ↓
Réponse JSON 200
```

## Gestion des erreurs

| Situation | Réponse HTTP | ErrorCode |
|-----------|--------------|-----------|
| Aucun token fourni | 401 | `MISSING_TOKEN` |
| Token invalide ou expiré | 401 | `INVALID_TOKEN` |
| Le notifiable courant n'est pas un `Model` Eloquent | 422 | `AUTHENTICATABLE_NOT_FOUND` |
| `endpoint`, `p256dh` ou `auth` manquant | 422 | (erreurs de validation Laravel) |

## Intégration

| Composant | Rôle |
|-----------|------|
| `NemesisHelper` | Accès au notifiable authentifié |
| `WebPushSubscriptionRepositoryInterface` | Persistance via `upsertFor()` |
| `RegisterWebPushSubscriptionRequest` | Validation et construction du record |
| `RegisterWebPushSubscriptionRecord` | DTO d'entrée |
| `WebPushSubscriptionRecord` | DTO interne du repository |
| `WebPushSubscriptionData` | DTO de sortie (sérialisation JSON) |

## Performance

- **Idempotence** : `upsertFor()` effectue un `SELECT` puis `INSERT` ou `UPDATE`, soit 2 requêtes dans le pire cas.
- **Unicité** : l'endpoint possède un index unique en base, ce qui évite les doublons et permet une résolution rapide.
- Aucune opération bloquante (pas d'appel réseau).

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ |
| Laravel 12+ | ✅ |
| `andydefer/laravel-actions` | ✅ |
| `andydefer/laravel-nemesis` | ✅ |
| `andydefer/laravel-repository` | ✅ |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Actions\RegisterWebPushSubscriptionAction;
use AndyDefer\LaravelNotification\Contracts\Repositories\WebPushSubscriptionRepositoryInterface;
use AndyDefer\LaravelNotification\Records\RegisterWebPushSubscriptionRecord;
use AndyDefer\Nemesis\Helpers\NemesisHelper;

$helper = app(NemesisHelper::class);
$subscriptions = app(WebPushSubscriptionRepositoryInterface::class);

$action = new RegisterWebPushSubscriptionAction($helper, $subscriptions);

$record = RegisterWebPushSubscriptionRecord::from([
    'endpoint' => 'https://jmt17.google.com/fcm/send/c9ptagJdvGY:...',
    'p256dh' => 'BGtKsvWiILk_...',
    'auth' => 'hv_Nwds7IHarbxh2KChgEw',
    'browser' => 'Chrome',
    'user_agent' => 'Mozilla/5.0 ...',
]);

$response = $action->run($record);
```
