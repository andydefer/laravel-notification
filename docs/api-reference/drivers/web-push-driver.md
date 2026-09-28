# WebPushDriver - Référence Technique

## Description

Driver d'envoi Web Push utilisant le protocole W3C Push API avec authentification VAPID, ciblant l'API Push native des navigateurs.

## Hiérarchie

```
AbstractDriver
    └── WebPushDriver
```

## Rôle principal

Traduire un message de notification en un payload Web Push et le livrer à un endpoint de souscription via la bibliothèque `minishlink/web-push`. Ce driver diffère du `FirebaseCloudMessagingDriver` en ce qu'il cible directement les services Push des navigateurs (Google, Mozilla, Apple) plutôt que l'API FCM.

## Prérequis

- Package `minishlink/web-push` installé.
- Clés VAPID configurées (`subject`, `public_key`, `private_key`).
- Chaque route doit fournir `endpoint`, `p256dh` et `auth` dans ses métadonnées.

## API / Méthodes publiques

### `__construct(WebPushConfigRecord $config)`

Injecte le record de configuration du canal Web Push.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$config` | `WebPushConfigRecord` | Contient `enabled`, `subject`, `public_key`, `private_key`, `ttl`, `urgency`, `topic` |

**Exemple :**
```php
$driver = new WebPushDriver($webPushConfig);
```

### `getChannel(): string`

Retourne l'identifiant du canal.

**Retourne :** `string` - Toujours `"webpush"`.

### `validateConfiguration(): bool`

Vérifie que la configuration VAPID est complète.

**Retourne :** `bool` - `true` si `enabled = true` et que `subject`, `public_key`, `private_key` sont tous non vides.

**Exemple :**
```php
if (! $driver->validateConfiguration()) {
    throw new RuntimeException('Web Push is not configured.');
}
```

### `send(NotificationMessageVO $message, NotificationRouteVO $route): SendResultRecord` (héritée)

Envoie la notification via `execute()`. Le résultat est encapsulé dans un `SendResultRecord`.

### `execute(NotificationMessageVO $message, NotificationRouteVO $route): bool` (protégée)

Construit la souscription, initialise le client Web Push, construit le payload et envoie la notification.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `NotificationMessageVO` | Corps, sujet, type, données du message |
| `$route` | `NotificationRouteVO` | Destination + métadonnées (`endpoint`, `p256dh`, `auth`, `title`, `data`) |

**Retourne :** `bool` - `true` en cas de succès.

**Exceptions :**
- `RuntimeException` — configuration invalide, endpoint manquant, `p256dh` manquant, `auth` manquant, échec d'envoi.
- `JsonException` — échec d'encodage JSON du payload.

## Métadonnées de route supportées

| Clé | Obligatoire | Description |
|-----|-------------|-------------|
| `endpoint` | ✅ (fallback sur `destination`) | URL de l'endpoint Push |
| `p256dh` | ✅ | Clé publique du client (base64url) |
| `auth` | ✅ | Secret d'authentification du client (base64url) |
| `title` | ❌ | Titre affiché dans la notification (fallback : `$message->getSubjectValue()`) |
| `data` | ❌ | Données additionnelles fusionnées avec `$message->getData()` |

## Payload envoyé au navigateur

```json
{
    "title": "Test Web Push",
    "body": "Ceci est un test",
    "data": {
        "url": "/",
        "screen": "home"
    }
}
```

- `title` provient de `metadata.title`, sinon du sujet du message.
- `body` provient de `MessageBodyVO`.
- `data` est la fusion de `metadata.data` et `message.data`, avec `message.data` prioritaire en cas de collision.

## Cas d'utilisation

### Cas 1 : Envoi d'une notification standard

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Drivers\WebPushDriver;
use AndyDefer\LaravelNotification\Records\WebPushConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use AndyDefer\LaravelNotification\Channels\WebPushChannel;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

$driver = new WebPushDriver($webPushConfig);

$message = new NotificationMessageVO(
    body: new MessageBodyVO('Votre commande a été expédiée.'),
    subject: new MessageSubjectVO('Commande expédiée'),
    type: 'order_shipped',
    data: new StrictDataObject(['order_id' => 42]),
);

$route = new NotificationRouteVO(
    channelClass: WebPushChannel::class,
    destination: 'https://jmt17.google.com/fcm/send/...',
    metadata: new StrictDataObject([
        'endpoint' => 'https://jmt17.google.com/fcm/send/...',
        'p256dh' => 'BGtKsvWiILk_...',
        'auth' => 'hv_Nwds7IHarbxh2KChgEw',
    ]),
);

$result = $driver->send($message, $route);
```

### Cas 2 : Titre personnalisé

```php
$route = new NotificationRouteVO(
    channelClass: WebPushChannel::class,
    destination: $endpoint,
    metadata: new StrictDataObject([
        'endpoint' => $endpoint,
        'p256dh' => $p256dh,
        'auth' => $auth,
        'title' => 'Alerte critique',
        'data' => ['priority' => 'high'],
    ]),
);
```

Le navigateur affichera `Alerte critique` comme titre.

### Cas 3 : Gestion d'un endpoint expiré

Le driver lève une `RuntimeException` quand `$report->isSuccess()` retourne `false`. L'appelant peut interpréter l'erreur pour supprimer la souscription.

```php
$result = $driver->send($message, $route);

if (! $result->success) {
    // Le message d'erreur contient la raison retournée par le service Push
    logger()->warning('Web Push failed', [
        'error' => $result->error_message?->getValue(),
    ]);
}
```

## Flux d'exécution

```
send($message, $route)
    ↓
execute($message, $route)
    ├── validateConfiguration()
    │       └── Échec → RuntimeException
    ├── buildSubscription($destination, $metadata)
    │       ├── Résolution endpoint (metadata.endpoint ou destination)
    │       ├── Vérification p256dh
    │       ├── Vérification auth
    │       └── Subscription::create(...)
    ├── createClient()
    │       ├── Instanciation de WebPush avec VAPID
    │       └── Échec → RuntimeException
    ├── buildPayload($message, $metadata)
    │       ├── Fusion title / body
    │       └── Fusion metadata.data + message.data
    ├── $webPush->sendOneNotification(...)
    │       ├── Succès → true
    │       └── Échec → RuntimeException
    ↓
SendResultRecord
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Configuration VAPID incomplète | `RuntimeException` | `Web Push configuration is incomplete.` |
| Endpoint absent ou vide | `RuntimeException` | `Web Push endpoint is missing.` |
| `p256dh` absent ou vide | `RuntimeException` | `Web Push p256dh key is missing.` |
| `auth` absent ou vide | `RuntimeException` | `Web Push auth key is missing.` |
| Échec d'initialisation du client | `RuntimeException` | `Web Push client initialization failed: <reason>` |
| Échec d'envoi | `RuntimeException` | `Web Push trigger failed: <reason>` |
| Payload non sérialisable | `JsonException` | Message dépendant |

## Intégration

| Composant | Rôle |
|-----------|------|
| `AbstractDriver` | Contrat parent (cycle `send()` → `execute()`) |
| `WebPushConfigRecord` | Configuration VAPID et options d'envoi |
| `Minishlink\WebPush\WebPush` | Client Web Push |
| `Minishlink\WebPush\Subscription` | Représentation de la souscription |
| `WebPushChannel` | Canal qui instancie ce driver |
| `NotificationRouteVO` | Source des métadonnées d'envoi |

## Performance

- **Coût** : un appel HTTP sortant vers le service Push (Google, Mozilla, Apple).
- **Latence** : dominée par le réseau ; le timeout du client `minishlink/web-push` s'applique.
- **Payload** : sérialisation JSON en O(n) sur la taille des données.
- **Connexions** : `WebPush` peut maintenir des connexions HTTP multiples ; `sendOneNotification()` gère une seule souscription.
- Aucune mise en cache.

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ |
| Laravel 12+ | ✅ |
| `minishlink/web-push` | ✅ Requis |
| `andydefer/php-records` | ✅ |
| `andydefer/laravel-notification` | ✅ |

## Sécurité

- Les clés VAPID (`public_key`, `private_key`) doivent être stockées dans `.env`.
- Ne jamais exposer la clé privée dans les logs.
- Le payload peut contenir des données sensibles : filtrer `metadata.data` et `message.data` avant envoi.

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\WebPushChannel;
use AndyDefer\LaravelNotification\Drivers\WebPushDriver;
use AndyDefer\LaravelNotification\Records\WebPushConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

$config = new WebPushConfigRecord(
    enabled: true,
    subject: 'mailto:contact@afya-medical.com',
    public_key: 'BDw92Y6vnPXYNN90QwiqmLAnnEX9...',
    private_key: 'm7lTHzndL5P7QL0be8a0l1whYsy8...',
    ttl: 3600,
    urgency: 'normal',
    topic: 'notification',
);

$driver = new WebPushDriver($config);

$message = new NotificationMessageVO(
    body: new MessageBodyVO('Votre commande a été expédiée.'),
    subject: new MessageSubjectVO('Commande expédiée'),
    type: 'order_shipped',
    data: new StrictDataObject(['order_id' => 42, 'url' => '/orders/42']),
);

$route = new NotificationRouteVO(
    channelClass: WebPushChannel::class,
    destination: 'https://jmt17.google.com/fcm/send/c9ptagJdvGY:...',
    metadata: new StrictDataObject([
        'endpoint' => 'https://jmt17.google.com/fcm/send/c9ptagJdvGY:...',
        'p256dh' => 'BGtKsvWiILk_CxgTlaz1ajvawoh2FtUIiUUIPMyQ_wG6yS-9SwG3bK4KqusuVDmftoZqIz8LnWRl5tJPs053iYI',
        'auth' => 'hv_Nwds7IHarbxh2KChgEw',
        'title' => 'Commande expédiée',
        'data' => ['priority' => 'normal'],
    ]),
);

$result = $driver->send($message, $route);

if ($result->success) {
    echo 'Notification envoyée.';
} else {
    echo 'Erreur : '.$result->error_message?->getValue();
}
```