# WebPushDriver - Référence Technique

## Description

Driver d'envoi Web Push utilisant le protocole W3C Push API avec authentification VAPID, ciblant l'API Push native des navigateurs. Les **identifiants device** (`endpoint`, `p256dh`, `auth`) restent dans la **route**, tandis que les **options de transport** (`ttl`, `urgency`, `topic`) viennent du **message** via `metadata`.

## Hiérarchie

```
AbstractDriver
    └── WebPushDriver
```

## Rôle principal

Traduire un message de notification en un payload Web Push et le livrer à un endpoint de souscription via la bibliothèque `minishlink/web-push`. Ce driver diffère du `FirebaseCloudMessagingDriver` en ce qu'il cible directement les services Push des navigateurs (Google, Mozilla, Apple) plutôt que l'API FCM.

**Séparation stricte route / message** :

| Champ | Source | Raison |
|---|---|---|
| `endpoint` | `$route->getDestination()` ou `$route->metadata->get('endpoint')` | Spécifique au device |
| `p256dh` | `$route->metadata->get('p256dh')` | Spécifique au device |
| `auth` | `$route->metadata->get('auth')` | Spécifique au device |
| `ttl` | `$message->getMetadata()->get('ttl')` | Commun à tous les devices d'un envoi |
| `urgency` | `$message->getMetadata()->get('urgency')` | Commun à tous les devices d'un envoi |
| `topic` | `$message->getMetadata()->get('topic')` | Commun à tous les devices d'un envoi |

## Prérequis

- Package `minishlink/web-push` installé.
- Clés VAPID configurées (`subject`, `public_key`, `private_key`).
- Chaque route doit fournir `endpoint`, `p256dh` et `auth` dans ses métadonnées.

## API / Méthodes publiques

### `__construct(WebPushConfigRecord $config)`

Injecte le record de configuration du canal Web Push.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$config` | `WebPushConfigRecord` | Contient `enabled`, `subject`, `public_key`, `private_key`, `ttl`, `urgency`, `topic` (valeurs par défaut) |

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

Construit la souscription, initialise le client Web Push, résout les options de transport, puis envoie la notification.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `NotificationMessageVO` | Corps, sujet, type, données, options de transport (`ttl`, `urgency`, `topic`) |
| `$route` | `NotificationRouteVO` | Destination + métadonnées (`endpoint`, `p256dh`, `auth`) |

**Retourne :** `bool` - `true` en cas de succès.

**Exceptions :**
- `RuntimeException` — configuration invalide, endpoint manquant, `p256dh` manquant, `auth` manquant, échec d'envoi.
- `JsonException` — échec d'encodage JSON du payload.

## Métadonnées supportées

### Côté route (device-spécifique) — obligatoire

| Clé | Obligatoire | Description |
|-----|-------------|-------------|
| `endpoint` | ✅ (fallback sur `destination`) | URL de l'endpoint Push |
| `p256dh` | ✅ | Clé publique du client (base64url) |
| `auth` | ✅ | Secret d'authentification du client (base64url) |

### Côté message (transport) — optionnel, fallback sur `$config`

| Clé | Type | Valeurs | Fallback config |
|-----|------|---------|-----------------|
| `ttl` | `int` | Secondes, `>= 0` | `config->ttl` |
| `urgency` | `string` | `very-low`, `low`, `normal`, `high` | `config->urgency` |
| `topic` | `string` | Non vide | `config->topic` |

**Règles de validation** :
- `ttl` invalide (négatif, non-`int`) → fallback config.
- `urgency` invalide (pas dans la liste) → fallback config.
- `topic` vide ou non-string → fallback config.

## Payload envoyé au navigateur

```json
{
    "body": "Ceci est un test",
    "subject": "Test Web Push",
    "type": "test",
    "data": {
        "url": "/",
        "screen": "home"
    },
    "metadata": null
}
```

Le payload est **`$message->toArray()`** — il inclut `body`, `subject`, `type`, `data`, `metadata`. Les options de transport (`ttl`, `urgency`, `topic`) sont passées **séparément** via le 3ᵉ argument de `sendOneNotification()` (en-têtes HTTP), pas dans le payload JSON.

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

### Cas 2 : Options de transport personnalisées

`ttl`, `urgency`, `topic` surchargés **par message**.

```php
use AndyDefer\DomainStructures\Utils\StrictDataObject;

$message = new NotificationMessageVO(
    body: new MessageBodyVO('Alerte critique : intervention requise.'),
    subject: new MessageSubjectVO('Alerte'),
    type: 'critical_alert',
    data: new StrictDataObject(['alert_id' => 42]),
    metadata: new StrictDataObject([
        'ttl' => 120,          // 2 minutes (au lieu de la valeur config)
        'urgency' => 'high',   // priorité haute
        'topic' => 'alerts',   // remplace les notifs précédentes de même topic
    ]),
);

$driver->send($message, $route);
```

**Comportement** :
- `TTL: 120` (en-tête HTTP)
- `Urgency: high` (en-tête HTTP)
- `Topic: alerts` (en-tête HTTP)
- Payload JSON inchangé (contient `body`, `subject`, `type`, `data`)

### Cas 3 : Fallback sur config si options absentes

```php
$config = new WebPushConfigRecord(
    enabled: true,
    subject: 'mailto:contact@afya-medical.com',
    public_key: '...',
    private_key: '...',
    ttl: 3600,          // fallback si message->metadata['ttl'] absent
    urgency: 'normal',  // fallback si message->metadata['urgency'] absent
    topic: 'notification', // fallback si message->metadata['topic'] absent
);

$message = new NotificationMessageVO(
    body: new MessageBodyVO('Simple notification.'),
    subject: new MessageSubjectVO('Simple'),
    type: 'simple',
    // pas de metadata → tous les fallbacks config utilisés
);

$driver->send($message, $route);
// TTL=3600, Urgency=normal, Topic=notification (depuis config)
```

### Cas 4 : Gestion d'un endpoint expiré

Le driver lève une `RuntimeException` quand `$report->isSuccess()` retourne `false`. L'appelant peut interpréter l'erreur pour supprimer la souscription.

```php
$result = $driver->send($message, $route);

if (! $result->success) {
    $error = $result->error_message?->getValue() ?? '';

    // Endpoint expiré (HTTP 410 Gone) → supprimer la souscription
    if (str_contains($error, '410') || str_contains($error, 'Gone')) {
        $subscription->delete();
    }

    logger()->warning('Web Push failed', [
        'endpoint' => $result->destination,
        'error' => $error,
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
    ├── buildSubscription($destination, $route->getMetadata())
    │       ├── Résolution endpoint (metadata.endpoint ou destination)
    │       ├── Vérification p256dh
    │       ├── Vérification auth
    │       └── Subscription::create(...)
    ├── createClient()
    │       ├── Instanciation de WebPush avec VAPID
    │       └── Échec → RuntimeException
    ├── resolveOptions($message)                    ⬅️ NOUVEAU
    │       ├── ttl     (message.metadata ?? config)
    │       ├── urgency (message.metadata ?? config, validé)
    │       └── topic   (message.metadata ?? config)
    ├── $webPush->sendOneNotification($sub, $json, $options)
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

**Options invalides** (ttl négatif, urgency inconnue, topic vide) → **silencieusement** remplacées par les valeurs de `$config`. Pas d'exception.

## Intégration

| Composant | Rôle |
|-----------|------|
| `AbstractDriver` | Contrat parent (cycle `send()` → `execute()`) |
| `WebPushConfigRecord` | Configuration VAPID et options par défaut (`ttl`, `urgency`, `topic`) |
| `Minishlink\WebPush\WebPush` | Client Web Push |
| `Minishlink\WebPush\Subscription` | Représentation de la souscription |
| `WebPushChannel` | Canal qui instancie ce driver |
| `NotificationRouteVO` | Source des identifiants device (`endpoint`, `p256dh`, `auth`) |
| `NotificationMessageVO` | Source des options de transport (`ttl`, `urgency`, `topic`) |

## Performance

- **Coût** : un appel HTTP sortant vers le service Push (Google, Mozilla, Apple).
- **Latence** : dominée par le réseau ; le timeout du client `minishlink/web-push` s'applique.
- **Payload** : sérialisation JSON en O(n) sur la taille des données.
- **Options** : résolution en O(1) — 3 accès `StrictDataObject::get()`.
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
- Le payload peut contenir des données sensibles : filtrer `metadata` et `data` avant envoi.
- ⚠️ **`metadata` du message contient les options de transport** — vérifier qu'il n'y a pas de fuite de données sensibles dans `ttl`/`urgency`/`topic` (ce sont des types simples, donc sûr).

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

// 1. Configuration — valeurs par défaut
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

// 2. Route — identifiants device
$route = new NotificationRouteVO(
    channelClass: WebPushChannel::class,
    destination: 'https://jmt17.google.com/fcm/send/c9ptagJdvGY:...',
    metadata: new StrictDataObject([
        'endpoint' => 'https://jmt17.google.com/fcm/send/c9ptagJdvGY:...',
        'p256dh' => 'BGtKsvWiILk_CxgTlaz1ajvawoh2FtUIiUUIPMyQ_wG6yS-9SwG3bK4KqusuVDmftoZqIz8LnWRl5tJPs053iYI',
        'auth' => 'hv_Nwds7IHarbxh2KChgEw',
    ]),
);

// 3. Message — contenu + options de transport
$message = new NotificationMessageVO(
    body: new MessageBodyVO('Votre commande a été expédiée.'),
    subject: new MessageSubjectVO('Commande expédiée'),
    type: 'order_shipped',
    data: new StrictDataObject(['order_id' => 42, 'url' => '/orders/42']),
    metadata: new StrictDataObject([
        'ttl' => 300,           // 5 minutes (override config)
        'urgency' => 'high',    // important
        'topic' => 'orders',    // regroupe les notifs de commandes
    ]),
);

// 4. Envoi
$result = $driver->send($message, $route);

if ($result->success) {
    echo 'Notification envoyée.';
} else {
    echo 'Erreur : '.$result->error_message?->getValue();
}
```

---

## Ce qui change dans cette référence

| Section | Changement |
|---|---|
| **Description** | Ajout de la séparation route/message |
| **Rôle principal** | Tableau récapitulatif 6 champs : 3 device (route) + 3 transport (message) |
| **API `execute()`** | Mentionne les options de transport lues depuis le message |
| **Nouvelle section** | **Métadonnées supportées** : tableau route (device) + tableau message (transport) avec règles de validation |
| **Payload** | Changé : c'est `$message->toArray()` (plus de fusion `metadata.data` / `message.data` — `data` seul est dans le message) |
| **Cas 2** | **Nouveau** : options de transport (`ttl`, `urgency`, `topic`) via message metadata |
| **Cas 3** | **Nouveau** : fallback config quand options absentes |
| **Cas 4** | Endpoint expiré (extrait de l'ancien cas 3) |
| **Flux d'exécution** | Ajout de `resolveOptions($message)` avant `sendOneNotification` |
| **Gestion des erreurs** | Ajout : "Options invalides → fallback silencieux" |
| **Intégration** | Précise route = device, message = transport |
| **Performance** | Ajout : "Options en O(1)" |
| **Sécurité** | Note sur les options de transport (types simples, sûr) |
| **Exemple complet** | `metadata` du message ajouté avec `ttl`, `urgency`, `topic` |