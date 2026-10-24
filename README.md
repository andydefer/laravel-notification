# Laravel Notification

**Système de notifications multi-canaux pour Laravel. Persistance, traçabilité, multiples destinations, planification avancée - avec une architecture extensible.**

[![PHP Version](https://img.shields.io/badge/PHP-8.2%2B-blue)](https://php.net)
[![Laravel Version](https://img.shields.io/badge/Laravel-12.x%20%7C%2013.x%20%7C%2014.x%20%7C%2015.x-blue)](https://laravel.com)
[![License](https://img.shields.io/badge/License-MIT-green)](LICENSE)

---

## Table des matières

1. [Installation](#installation)
2. [Pourquoi Laravel Notification ?](#pourquoi-laravel-notification-)
3. [Architecture en un coup d'œil](#architecture-en-un-coup-dœil)
4. [Déclarer les canaux d'une entité](#déclarer-les-canaux-dune-entité)
5. [Envoyer une notification](#envoyer-une-notification)
   - [Envoi immédiat](#envoi-immédiat)
   - [Envoi différé](#envoi-différé)
   - [Envoi planifié](#envoi-planifié)
   - [Envoi récurrent](#envoi-récurrent)
6. [Séparation `data` / `metadata` dans le message](#séparation-data--metadata-dans-le-message)
7. [Filtrage des destinations avec SendOptions](#filtrage-des-destinations-avec-sendoptions)
8. [NotifiableBuilder - Envoi sans entité](#notifiablebuilder---envoi-sans-entité)
9. [MessageViewBodyVO - Corps de message basé sur une vue Laravel](#messageviewbodyvo---corps-de-message-basé-sur-une-vue-laravel)
10. [Gestion des tâches](#gestion-des-tâches)
11. [Statistiques et rapports](#statistiques-et-rapports)
12. [Canaux fonctionnels](#canaux-fonctionnels)
13. [Drivers fonctionnels](#drivers-fonctionnels)
14. [Canaux à implémenter](#canaux-à-implémenter)
15. [Ping/Pong — Vérification et nettoyage des cibles](#pingpong--vérification-et-nettoyage-des-cibles)
16. [Directives CLI](#directives-cli)
17. [Tâches internes](#tâches-internes)
18. [Créer un canal personnalisé](#créer-un-canal-personnalisé)
19. [Cas d'usage concrets](#cas-dusage-concrets)
20. [Bonnes pratiques](#bonnes-pratiques)
21. [Trait HasNotifications](#trait-hasnotifications)
22. [Référence de l'API](#référence-de-lapi)

---

## Installation

```bash
composer require andydefer/laravel-notification

php artisan vendor:publish --tag=notification-migrations
php artisan migrate

php artisan vendor:publish --tag=notification-config
```

---

## Pourquoi Laravel Notification ?

**Le problème :** Votre application doit notifier les utilisateurs par email, temps réel (Pusher), Web Push (navigateur), FCM (mobile) et dans la base de données. Chaque médecin a une adresse email professionnelle et une personnelle. Vous devez tracer **toutes** les notifications pour l'audit, savoir lesquelles ont échoué, et pouvoir consulter l'historique complet.

**La solution :** Laravel Notification. Un système complet qui orchestre l'envoi sur tous les canaux d'une entité, trace chaque tentative, et permet la planification avancée.

### Comparatif rapide

| Besoin | Laravel Notifications (natif) | Laravel Notification |
|--------|-------------------------------|----------------------|
| Plusieurs destinations par canal | ❌ | ✅ |
| Persistance automatique | ❌ (sauf database) | ✅ (tous les canaux) |
| Statut de l'envoi (SENT/FAILED) | ❌ | ✅ |
| Limitation par canal | ❌ | ✅ |
| Métadonnées par destination | ❌ | ✅ |
| Filtrage des destinations par canal | ❌ | ✅ |
| Séparation payload métier / options visuelles | ❌ | ✅ (`data` + `metadata`) |
| Envoi différé | ⚠️ (via queues) | ✅ (intégré) |
| Envoi récurrent | ⚠️ (via scheduler) | ✅ (intégré) |
| Gestion des tâches (pause/reprise) | ❌ | ✅ |
| Architecture extensible | ⚠️ (complexe) | ✅ (simple) |
| Envoi sans entité Notifiable | ❌ | ✅ (NotifiableBuilder) |
| Corps de message basé vue Laravel | ❌ | ✅ (MessageViewBodyVO) |
| Trait utilitaire pour les modèles | ❌ | ✅ (HasNotifications) |
| Temps réel (Pusher) | ❌ | ✅ |
| Web Push (VAPID) | ❌ | ✅ |
| Firebase Cloud Messaging | ❌ | ✅ |
| APNS (Apple Push Notification) | ❌ | 🚧 (à implémenter) |
| Ping/Pong (vérification de validité) | ❌ | ✅ |
| Pruning automatique des cibles invalides | ❌ | ✅ |

### En une phrase

> **Laravel Notifications envoie un message sur un canal défini. Laravel Notification orchestre l'envoi sur tous les canaux d'une entité, trace chaque tentative et permet la planification avancée.**

---

## Architecture en un coup d'œil

```
┌─────────────────────────────────────────────────────────────────┐
│                    NotificationService                          │
│          (Point d'entrée principal de l'API)                    │
└────────────────────────┬────────────────────────────────────────┘
                         │
         ┌───────────────┼───────────────┐
         │               │               │
         ▼               ▼               ▼
┌─────────────────┐┌─────────────────┐┌─────────────────────────┐
│ Notifiable      ││ Notifiable      ││    NotificationSender   │
│ Builder         ││ Service         ││    Processor            │
│ (API fluente)   ││ (API standard)  ││    (Orchestrateur)      │
└─────────────────┘└─────────────────┘└─────────────────────────┘
                                                 │
                                                 ▼
                                    ┌─────────────────────────┐
                                    │  Channels (résolution)  │
                                    │  Mail / Database /      │
                                    │  Pusher / WebPush /     │
                                    │  FirebaseCloudMessaging │
                                    └────────────┬────────────┘
                                                 │
                                                 ▼
                                    ┌─────────────────────────┐
                                    │  Drivers (exécution)    │
                                    │  MailDriver /           │
                                    │  DatabaseDriver /       │
                                    │  PusherDriver /         │
                                    │  WebPushDriver /        │
                                    │  FirebaseCloudMessaging │
                                    │  Driver                 │
                                    └─────────────────────────┘
```

### Composants principaux

| Composant | Rôle |
|-----------|------|
| `NotificationService` | Service principal, point d'entrée de l'API |
| `NotifiableBuilder` | Builder fluide pour envoyer des notifications sans entité |
| `NotificationSenderProcessor` | Orchestre l'envoi : résolution des routes, filtres, limites |
| `SendOptions` | Configuration fluide des options d'envoi (canaux, limites, filtres) |
| `NotificationMessageVO` | Value Object du message (`body`, `subject`, `type`, `data`, `metadata`) |
| `NotificationRouteVO` | Value Object définissant un canal + destination + métadonnées device |
| `AbstractChannel` | Classe de base pour les canaux |
| `AbstractDriver` | Classe de base pour les drivers |
| `SendDelayedNotificationTask` | Tâche unique pour les envois différés/planifiés |
| `SendRecurringNotificationTask` | Tâche récurrente pour les envois périodiques |
| `MessageViewBodyVO` | Value Object pour corps de message basé sur vue Laravel |
| `HasNotifications` | Trait utilitaire pour les modèles Eloquent recevant des notifications |
| `HasPingPong` | Trait utilitaire implémentant `PingableInterface` |
| `PingPongAdapter` | Résout le helper ping/pong par modèle |
| `FcmPingPong` | Helper de vérification pour les appareils FCM |
| `WebPushPingPong` | Helper de vérification pour les souscriptions Web Push |
| `PingStatus` | Enum du résultat d'un ping (`PONG`, `INVALID`) |

---

## Déclarer les canaux d'une entité

Pour qu'une entité (User, Order, Doctor, etc.) puisse recevoir des notifications, elle doit implémenter l'interface `NotifiableInterface`.

```php
<?php

namespace App\Models;

use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\Collections\NotificationRouteCollection;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\PusherChannel;
use AndyDefer\LaravelNotification\Channels\WebPushChannel;
use AndyDefer\LaravelNotification\Channels\FirebaseCloudMessagingChannel;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use Illuminate\Database\Eloquent\Model;

class User extends Model implements NotifiableInterface
{
    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;

        // ✅ Email principal
        if ($this->email_primary) {
            $collection->add(new NotificationRouteVO(
                channelClass: MailChannel::class,
                destination: $this->email_primary,
                metadata: new StrictDataObject(['type' => 'primary'])
            ));
        }

        // ✅ Email secondaire
        if ($this->email_secondary) {
            $collection->add(new NotificationRouteVO(
                channelClass: MailChannel::class,
                destination: $this->email_secondary,
                metadata: new StrictDataObject(['type' => 'secondary'])
            ));
        }

        // ✅ Pusher (temps réel) — la destination est le canal Pusher
        $collection->add(new NotificationRouteVO(
            channelClass: PusherChannel::class,
            destination: "private-user.{$this->id}"
        ));

        // ✅ Web Push (navigateur) — les identifiants device vont dans metadata
        foreach ($this->webPushSubscriptions as $subscription) {
            $collection->add(new NotificationRouteVO(
                channelClass: WebPushChannel::class,
                destination: $subscription->endpoint,
                metadata: new StrictDataObject([
                    'endpoint' => $subscription->endpoint,
                    'p256dh' => $subscription->p256dh,
                    'auth' => $subscription->auth,
                ])
            ));
        }

        // ✅ FCM (mobile) — le token est la destination
        foreach ($this->fcmDevices as $device) {
            $collection->add(new NotificationRouteVO(
                channelClass: FirebaseCloudMessagingChannel::class,
                destination: $device->token
            ));
        }

        // ✅ Base de données (traçabilité)
        $collection->add(new NotificationRouteVO(
            channelClass: DatabaseChannel::class,
            destination: 'database'
        ));

        return $collection;
    }

    public function getMorphClass(): string
    {
        return 'user';
    }

    public function getKey(): int
    {
        return $this->id;
    }
}
```

### NotificationRouteVO — uniquement l'identifiant device

Le `NotificationRouteVO` définit **où envoyer** (identifiant du device), pas **quoi envoyer** :

```php
new NotificationRouteVO(
    channelClass: MailChannel::class,
    destination: 'user@example.com',
    metadata: new StrictDataObject(['type' => 'primary'])   // metadata = identifiants device
);
```

**Règle** : la route ne porte que ce qui est **spécifique au device** (email, token FCM, endpoint Web Push, canal Pusher). Tout le reste (icône, image, canal Android, TTL Web Push, event Pusher, cc/bcc Mail) vient du **message** via `metadata`.

---

## Envoyer une notification

### Envoi immédiat

```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\PusherChannel;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

class UserController extends Controller
{
    public function __construct(
        private readonly NotificationService $service
    ) {}

    public function welcome(User $user)
    {
        $message = new NotificationMessageVO(
            body: new MessageBodyVO('<h1>Bonjour !</h1><p>Bienvenue sur notre plateforme.</p>'),
            subject: new MessageSubjectVO('Bienvenue !'),
            type: 'welcome',
            data: new StrictDataObject(['user_id' => $user->id]),
            metadata: new StrictDataObject([
                'event' => 'notification.welcome',   // Pusher
                'icon' => 'ic_notification',         // FCM
            ])
        );

        $record = SendNowRecord::from([
            'channels' => [MailChannel::class, PusherChannel::class],
            'limit_per_channel' => 1,
        ]);

        $results = $this->service->sendNow($user, $message, $record);

        return response()->json([
            'success' => $results->allSuccess(),
            'sent' => $results->getSuccessCount(),
            'failed' => $results->getFailureCount(),
        ]);
    }
}
```

---

### Envoi différé

```php
use AndyDefer\LaravelNotification\Records\SendLaterRecord;

$record = SendLaterRecord::from([
    'delay_seconds' => 1800,
    'channels' => [MailChannel::class, PusherChannel::class],
    'limit_per_channel' => 1,
]);

$alias = $this->service->sendLater($user, $message, $record);
```

---

### Envoi planifié

```php
use AndyDefer\LaravelNotification\Records\SendAtRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationDateTimeVO;

$record = SendAtRecord::from([
    'scheduled_at' => new NotificationDateTimeVO($scheduledAt->toIso8601String()),
    'channels' => [MailChannel::class],
    'limit_per_channel' => 1,
]);

$alias = $this->service->sendAt($user, $message, $record);
```

---

### Envoi récurrent

```php
use AndyDefer\LaravelNotification\Records\SendRecurringRecord;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;

$record = SendRecurringRecord::from([
    'interval_seconds' => 604800,
    'start_at' => new NotificationDateTimeVO('2026-07-08 09:00:00'),
    'end_at' => new NotificationDateTimeVO('2026-12-31 09:00:00'),
    'channels' => [MailChannel::class],
    'limit_per_channel' => 1,
    'max_attempts' => new MaxFailedAttemptsVO(3),
]);

$alias = $this->service->sendRecurring($user, $message, $record);
```

---

## Séparation `data` / `metadata` dans le message

Depuis la v2, `NotificationMessageVO` distingue **deux `StrictDataObject`** :

| Propriété | Rôle | Destination côté driver |
|---|---|---|
| `data` | **Payload métier** — IDs, URL, données métier | `data.payload` (FCM), payload Pusher, payload Web Push |
| `metadata` | **Options visuelles/transport** — icône, image, canal, cc/bcc, TTL, event… | `notification.*` (FCM), `android.notification.*`, headers Web Push, event Pusher |

### Exemple

```php
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;

$message = new NotificationMessageVO(
    body: new MessageBodyVO('Votre profil a été mis à jour.'),
    subject: new MessageSubjectVO('Profil mis à jour'),
    type: 'profile_updated',

    // ⬇️ Payload métier — part dans `data.payload` de FCM
    data: new StrictDataObject([
        'user_id' => '61',
        'updated_fields' => ['currency'],
    ]),

    // ⬇️ Options visuelles/transport — partent dans `notification.*` de FCM
    metadata: new StrictDataObject([
        'icon' => 'ic_notification',
        'image' => 'https://cdn.afya.com/banner.png',
        'channel_id' => 'afya_payments',
        'sound' => 'default',
        'tag' => 'profile',
    ]),
);
```

### ⚠️ Ne jamais `json_encode()` manuellement

Le driver fait `json_encode()` **une seule fois** sur les valeurs non scalaires. Si tu pré-encodes, tu obtiens une **double stringification** côté Android.

```php
// ❌ JAMAIS
'updated_fields' => json_encode(['currency']),

// ✅ TOUJOURS
'updated_fields' => ['currency'],
```

---

## Filtrage des destinations avec SendOptions

```php
use AndyDefer\LaravelNotification\Options\SendOptions;

$options = SendOptions::init()
    ->withChannel(MailChannel::class)
    ->withDestinationFilter(MailChannel::class, 'user@example.com')
    ->withLimitPerChannel(1);

$results = $notificationService
    ->withOptions($options)
    ->sendNow($user, $message);
```

---

## NotifiableBuilder - Envoi sans entité

```php
use AndyDefer\LaravelNotification\Builders\NotifiableBuilder;

$results = NotifiableBuilder::create()
    ->to(MailChannel::class, 'user@example.com')
    ->to(PusherChannel::class, 'private-user.42')
    ->subject('Notification importante')
    ->body('Votre commande a été expédiée.')
    ->data(['order_id' => 12345])
    ->sendNow();
```

---

## MessageViewBodyVO - Corps de message basé sur une vue Laravel

```php
use AndyDefer\LaravelNotification\ValueObjects\MessageViewBodyVO;

$body = MessageViewBodyVO::html(
    view: 'emails.welcome',
    data: ['user' => $user]
);

$finalBody = $body->withData(['extra' => 'value']);
```

---

## Gestion des tâches

```php
class TaskManager
{
    public function __construct(
        private readonly NotificationService $service
    ) {}

    public function pause(string $alias): bool
    {
        return $this->service->pause($alias);
    }

    public function resume(string $alias): bool
    {
        return $this->service->resume($alias);
    }

    public function changeInterval(string $alias, int $newIntervalSeconds): bool
    {
        return $this->service->changeInterval($alias, $newIntervalSeconds);
    }

    public function cancel(string $alias): bool
    {
        return $this->service->cancel($alias);
    }
}
```

---

## Statistiques et rapports

```php
$stats = $this->service->getStats($user);

return response()->json([
    'total' => $stats->total,
    'sent' => $stats->sent,
    'failed' => $stats->failed,
    'delivered' => $stats->delivered,
    'pending' => $stats->pending,
    'success_rate' => $stats->success_rate . '%',
]);
```

---

## Canaux fonctionnels

| Canal | Nom | Icône | Description | Actif par défaut |
|-------|-----|-------|-------------|------------------|
| **MailChannel** | Email | 📧 | Envoi d'emails via Laravel Mail | ✅ |
| **DatabaseChannel** | Base de données | 💾 | Persistance pour la traçabilité | ✅ |
| **PusherChannel** | Pusher | 📡 | Diffusion temps réel via Pusher | ❌ |
| **WebPushChannel** | Web Push (VAPID) | 🌐 | Notifications navigateur (W3C Push API) | ❌ |
| **FirebaseCloudMessagingChannel** | FCM | 🔥 | Notifications mobiles Android/iOS (via FCM) | ❌ |

### Installation de chaque canal

#### 1. MailChannel — Email

**Prérequis :** `laravel/mail` configuré.

```php
// config/notification.php
'channels' => [
    'mail' => [
        'enabled' => true,
        'default_from' => env('MAIL_FROM_ADDRESS'),
        'default_from_name' => env('MAIL_FROM_NAME'),
    ],
],
```

**Options supportées via `$message->getMetadata()`** :

| Clé | Type | Description |
|-----|------|-------------|
| `attachments` | `string\|array` | Pièces jointes (chemins, array, ou objets `{path, as, mime}`) |
| `cc` | `string\|string[]` | Copie |
| `bcc` | `string\|string[]` | Copie cachée |
| `reply_to` | `string` | Adresse de réponse |
| `priority` | `int` | Priorité `1` (haute), `3` (normale), `5` (basse) |
| `from` / `from_name` | `string` | Override expéditeur |

**Utilisation :**

```php
use AndyDefer\DomainStructures\Utils\StrictDataObject;

$message = new NotificationMessageVO(
    body: new MessageBodyVO('<h1>Votre facture</h1>'),
    subject: new MessageSubjectVO('Facture #1234'),
    type: 'invoice',
    metadata: new StrictDataObject([
        'attachments' => [
            '/storage/invoices/1234.pdf',
            ['path' => '/storage/summary.pdf', 'as' => 'résumé.pdf'],
        ],
        'cc' => ['comptable@afya.com'],
        'priority' => 1,
        'from' => 'facturation@afya.com',
    ]),
);

$record = SendNowRecord::from([
    'channels' => [MailChannel::class],
    'limit_per_channel' => 1,
]);

$results = $service->sendNow($user, $message, $record);
```

---

#### 2. DatabaseChannel — Traçabilité

**Prérequis :** migrations publiées et exécutées.

```bash
php artisan vendor:publish --tag=notification-migrations
php artisan migrate
```

**Utilisation :**

```php
$collection->add(new NotificationRouteVO(
    DatabaseChannel::class,
    'database'
));
```

**Bénéfice :** une ligne est insérée pour chaque tentative, avec `status`, `channel`, `destination`, `error_message`.

---

#### 3. PusherChannel — Temps réel

**Configuration :**

```php
// config/notification.php
'pusher' => [
    'enabled' => env('PUSHER_NOTIFICATION_ENABLED', false),
    'app_id' => env('PUSHER_APP_ID'),
    'key' => env('PUSHER_APP_KEY'),
    'secret' => env('PUSHER_APP_SECRET'),
    'cluster' => env('PUSHER_APP_CLUSTER', 'eu'),
    'use_tls' => env('PUSHER_USE_TLS', true),
    'timeout' => env('PUSHER_TIMEOUT', 30),
    'default_channel' => env('PUSHER_NOTIFICATION_CHANNEL', 'notifications'),
],
```

**Utilisation :**

```php
// Route = channel Pusher
$collection->add(new NotificationRouteVO(
    PusherChannel::class,
    "private-user.{$this->id}"
));

// Message = event (dans metadata) + data (dans data)
$message = new NotificationMessageVO(
    body: new MessageBodyVO('Votre commande est confirmée.'),
    subject: new MessageSubjectVO('Commande confirmée'),
    type: 'order_confirmed',
    data: new StrictDataObject(['order_id' => 42]),
    metadata: new StrictDataObject([
        'event' => 'notification.received',   // ⬅️ event dans le message
    ]),
);
```

**Côté client (Laravel Echo) :**

```ts
Echo.private(`user.${userId}`)
    .listen('.notification.received', (payload) => {
        console.log(payload.body, payload.subject, payload.data);
    });
```

---

#### 4. WebPushChannel — Notifications navigateur

**Génération des clés VAPID :**

```bash
./vendor/bin/directive notification:generate-vapid
```

Cette directive écrit dans `.env` :

```env
WEBPUSH_PUBLIC_KEY=BDw92Y6vnPXYNN90QwiqmLAnnEX9...
WEBPUSH_PRIVATE_KEY=m7lTHzndL5P7QL0be8a0l1whYsy8...
WEBPUSH_NOTIFICATION_ENABLED=true
WEBPUSH_SUBJECT="mailto:contact@afya-medical.com"
```

**Configuration :**

```php
// config/notification.php
'webpush' => [
    'enabled' => env('WEBPUSH_NOTIFICATION_ENABLED', false),
    'subject' => env('WEBPUSH_SUBJECT'),
    'public_key' => env('WEBPUSH_PUBLIC_KEY'),
    'private_key' => env('WEBPUSH_PRIVATE_KEY'),
    'ttl' => env('WEBPUSH_TTL', 3600),
    'urgency' => env('WEBPUSH_URGENCY', 'normal'),
    'topic' => env('WEBPUSH_TOPIC', 'notification'),
],
```

**Modèle `WebPushSubscription` :**

```php
final class WebPushSubscription extends Model implements NotifiableInterface, PingableInterface
{
    use HasPingPong;
    use HasUuids;

    protected $table = 'web_push_subscriptions';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'endpoint', 'p256dh', 'auth', 'browser',
        'user_agent', 'last_seen_at', 'notifiable_type', 'notifiable_id',
    ];

    protected $casts = ['last_seen_at' => 'immutable_datetime'];

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;

        $collection->add(new NotificationRouteVO(
            channelClass: WebPushChannel::class,
            destination: (string) $this->endpoint,
            metadata: new StrictDataObject([
                'endpoint' => (string) $this->endpoint,
                'p256dh' => (string) $this->p256dh,
                'auth' => (string) $this->auth,
            ]),
        ));

        return $collection;
    }
}
```

**Options de transport via `$message->getMetadata()`** :

| Clé | Type | Valeurs | Fallback config |
|-----|------|---------|-----------------|
| `ttl` | `int` | Secondes `>= 0` | `config->ttl` |
| `urgency` | `string` | `very-low`, `low`, `normal`, `high` | `config->urgency` |
| `topic` | `string` | Non vide | `config->topic` |

**Utilisation :**

```php
$message = new NotificationMessageVO(
    body: new MessageBodyVO('Alerte critique : intervention requise.'),
    subject: new MessageSubjectVO('Alerte'),
    type: 'critical_alert',
    data: new StrictDataObject(['alert_id' => 42]),
    metadata: new StrictDataObject([
        'ttl' => 120,
        'urgency' => 'high',
        'topic' => 'alerts',
    ]),
);

$record = SendNowRecord::from([
    'channels' => [WebPushChannel::class],
    'limit_per_channel' => 1,
]);

$results = $service->sendNow($subscription, $message, $record);
```

**Service worker côté navigateur :**

```js
self.addEventListener('push', (event) => {
    const payload = event.data ? event.data.json() : {};

    self.clients.matchAll({ type: 'window', includeUncontrolled: true })
        .then((wins) => {
            for (const win of wins) {
                win.postMessage({ type: 'WEBPUSH_RECEIVED', payload });
            }
        });

    event.waitUntil(self.registration.showNotification(
        payload.subject ?? 'Notification',
        {
            body: payload.body ?? '',
            data: payload.data ?? {},
            icon: '/icon.png',
            badge: '/badge.png',
        }
    ));
});
```

---

#### 5. FirebaseCloudMessagingChannel — Mobile

**Prérequis :**

```bash
composer require google/auth guzzlehttp/guzzle
```

**Configuration :**

```php
// config/notification.php
'firebase' => [
    'enabled' => env('FIREBASE_NOTIFICATION_ENABLED', false),
    'credentials_path' => env('FIREBASE_CREDENTIALS_PATH'),
    'project_id' => env('FIREBASE_PROJECT_ID'),
    'scope' => env('FIREBASE_SCOPE', 'https://www.googleapis.com/auth/firebase.messaging'),
    'timeout' => env('FIREBASE_TIMEOUT', 30),
],
```

**Modèle `FcmDevice` :**

```php
final class FcmDevice extends Model implements NotifiableInterface, PingableInterface
{
    use HasPingPong;
    use HasUuids;

    protected $table = 'fcm_devices';
    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'id', 'device_id', 'token', 'platform', 'user_agent',
        'last_seen_at', 'notifiable_type', 'notifiable_id',
    ];

    protected $casts = ['last_seen_at' => 'immutable_datetime'];

    public function notifiable(): MorphTo
    {
        return $this->morphTo();
    }

    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;

        $collection->add(new NotificationRouteVO(
            channelClass: FirebaseCloudMessagingChannel::class,
            destination: (string) $this->token,
        ));

        return $collection;
    }
}
```

**Options visuelles via `$message->getMetadata()`** :

| Clé | Type | Description |
|-----|------|-------------|
| `icon` | `string` | Nom de drawable Android (ex : `ic_notification`) |
| `image` | `string` | URL HTTPS de l'image grande (BigPicture) |
| `channel_id` | `string` | Canal Android (ex : `afya_payments`) |
| `sound` | `string` | Son (ex : `default`) |
| `tag` | `string` | Tag de regroupement |
| `click_action` | `string` | Action au clic |

**Utilisation :**

```php
$message = new NotificationMessageVO(
    body: new MessageBodyVO('Votre profil a été mis à jour avec succès.'),
    subject: new MessageSubjectVO('Profil mis à jour'),
    type: 'profile_updated',
    data: new StrictDataObject([
        'user_id' => '61',
        'updated_fields' => ['currency'],
    ]),
    metadata: new StrictDataObject([
        'icon' => 'ic_notification',
        'image' => 'https://cdn.afya.com/banner.png',
        'channel_id' => 'afya_payments',
        'sound' => 'default',
        'tag' => 'profile',
        'click_action' => 'OPEN_PROFILE',
    ]),
);

$record = SendNowRecord::from([
    'channels' => [FirebaseCloudMessagingChannel::class],
    'limit_per_channel' => 1,
]);

$results = $service->sendNow($device, $message, $record);
```

---

## Drivers fonctionnels

| Driver | Canal | Dépendance externe | Comportement |
|--------|-------|--------------------|--------------|
| **MailDriver** | `MailChannel` | Laravel Mail | `Mail::send()` avec options (cc, bcc, attachments…) |
| **DatabaseDriver** | `DatabaseChannel` | Base de données | Insère une ligne par notification |
| **PusherDriver** | `PusherChannel` | `pusher/pusher-php-server` | `Pusher::trigger()` avec event depuis message |
| **WebPushDriver** | `WebPushChannel` | `minishlink/web-push` | W3C Push API + VAPID + TTL/urgency/topic |
| **FirebaseCloudMessagingDriver** | `FirebaseCloudMessagingChannel` | HTTP client + OAuth 2.0 | API HTTP v1 FCM avec `notification`, `android.notification`, `data` |

### Points clés par driver

#### MailDriver

- **Route** : `destination` (email)
- **Message `data`** : payload métier
- **Message `metadata`** : `attachments`, `cc`, `bcc`, `reply_to`, `priority`, `from`, `from_name`

#### PusherDriver

- **Route** : `destination` (canal Pusher) + override `metadata.channel`
- **Message `metadata`** : `event` (nom d'événement, fallback `'notification'`)
- **Message `data`** : payload métier (dans `$message->toArray()`)

#### WebPushDriver

- **Route `metadata`** : `endpoint`, `p256dh`, `auth` (identifiants device)
- **Message `metadata`** : `ttl`, `urgency`, `topic` (options transport)
- **Payload** : `$message->toArray()` (body, subject, type, data)

#### FirebaseCloudMessagingDriver

- **Route** : `destination` (token FCM)
- **Message `metadata`** : `icon`, `image`, `channel_id`, `sound`, `tag`, `click_action` → `android.notification.*`
- **Message `data`** : `type` + `payload` (JSON string, `json_encode` **une seule fois** par le driver)
- **Bloc `notification`** : `title` (depuis `subject`) + `body` (depuis `body`)

---

## Canaux à implémenter

| Canal | Driver | Statut | Note |
|---|---|---|---|
| **ApnsChannel** | `ApnsDriver` | 🚧 **À implémenter** | Squelette — lève une `RuntimeException` explicite. Pour iOS, utiliser `FirebaseCloudMessagingDriver` en attendant une implémentation native APNS. |

L'ancien `PushDriver` (qui prétendait supporter FCM **et** APNS sans rien faire) a été **supprimé et renommé `ApnsDriver`** avec un statut explicite "à implémenter". Plus de fausse promesse.

---

## Ping/Pong — Vérification et nettoyage des cibles

Le module **Ping/Pong** permet de vérifier qu'un appareil FCM ou une souscription Web Push est toujours valide, et de le supprimer automatiquement sinon.

### Principe

1. Une notification légère (`ping`) est envoyée via le canal cible.
2. Le résultat est interprété :
   - **Succès** → la cible est valide → `PingStatus::PONG`, `last_seen_at` mis à jour.
   - **Échec** → la cible est invalide → `PingStatus::INVALID` → suppression.
3. Aucun parsing de message d'erreur n'est effectué : tout échec = invalide.

### Contrat `PingPongInterface`

```php
interface PingPongInterface
{
    public function ping(Model $notifiable): PingStatus;
    public function isAlive(Model $notifiable): bool;
    public function pingOrPrune(Model $notifiable): PingStatus;
}
```

### Enum `PingStatus`

```php
enum PingStatus: string
{
    case PONG = 'pong';
    case INVALID = 'invalid';

    public function isPong(): bool;
    public function isInvalid(): bool;
}
```

### Trait `HasPingPong`

À utiliser dans tous les modèles notifiables (FCM, Web Push) :

```php
final class FcmDevice extends Model implements NotifiableInterface, PingableInterface
{
    use HasPingPong;
    // ...
}

final class WebPushSubscription extends Model implements NotifiableInterface, PingableInterface
{
    use HasPingPong;
    // ...
}
```

### Utilisation

```php
if ($subscription->isAlive()) {
    // Toujours joignable
}

$status = $subscription->pingOrPrune();

if ($status->isInvalid()) {
    logger()->info('Subscription pruned', ['id' => $subscription->id]);
}
```

### Helpers disponibles

| Helper | Canal cible | Suppression automatique |
|--------|-------------|-------------------------|
| `FcmPingPong` | FCM | ✅ via `pingOrPrune` |
| `WebPushPingPong` | Web Push | ✅ via `pingOrPrune` |

---

## Directives CLI

### `notification:generate-vapid`

Génère une paire de clés VAPID et l'écrit dans un fichier `.env`.

```bash
./vendor/bin/directive notification:generate-vapid
./vendor/bin/directive notification:generate-vapid .env.production
./vendor/bin/directive notification:generate-vapid --force
```

**Alias :** `notification:gvk`, `n:gvk`

| Argument / Option | Description |
|-------------------|-------------|
| `env` | Chemin du fichier env (défaut : `.env`) |
| `--force` | Écrase les clés existantes |

---

### `notification:register-prune-fcm`

Enregistre la tâche récurrente de nettoyage des appareils FCM invalides.

```bash
./vendor/bin/directive notification:register-prune-fcm
./vendor/bin/directive notification:register-prune-fcm 3600 5
./vendor/bin/directive notification:register-prune-fcm --force
```

**Alias :** `notification:rpf`, `n:rpf`

---

### `notification:register-prune-webpush`

Enregistre la tâche récurrente de nettoyage des souscriptions Web Push invalides.

```bash
./vendor/bin/directive notification:register-prune-webpush
./vendor/bin/directive notification:register-prune-webpush 3600 5
./vendor/bin/directive notification:register-prune-webpush --force
```

**Alias :** `notification:rpwp`, `n:rpwp`

---

## Tâches internes

| Tâche | Type | Rôle |
|-------|------|------|
| `SendDelayedNotificationTask` | Unique | Envoi différé/planifié |
| `SendRecurringNotificationTask` | Récurrente | Envoi périodique |
| `PingAndPruneFcmDeviceTask` | Unique | Ping + suppression d'un appareil FCM |
| `PingAndPruneWebPushSubscriptionTask` | Unique | Ping + suppression d'une souscription Web Push |
| `PruneFailedFcmNotificationsTask` | Récurrente | Scan des notifications FCM échouées → planification des ping/prune |
| `PruneFailedWebPushNotificationsTask` | Récurrente | Scan des notifications Web Push échouées → planification des ping/prune |

---

## Créer un canal personnalisé

### 1. Créer le Driver

```php
class DiscordDriver extends AbstractDriver
{
    public function __construct(private readonly array $config) {}

    public function getChannel(): string { return 'discord'; }

    public function validateConfiguration(): bool
    {
        return !empty($this->config['webhook_url']);
    }

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        $webhookUrl = $route->getMetadata()?->get('webhook_url')
            ?? $this->config['webhook_url'];

        $response = Http::post($webhookUrl, [
            'content' => $message->getBodyValue(),
        ]);

        if (!$response->successful()) {
            throw new RuntimeException('Discord API error: ' . $response->body());
        }

        return true;
    }
}
```

### 2. Créer le Channel

```php
class DiscordChannel extends AbstractChannel
{
    public function getName(): string { return 'discord'; }
    public function getLabel(): string { return 'Discord'; }
    public function getIcon(): string { return '🎮'; }

    public function isEnabled(): bool
    {
        return $this->configRepository->get('notification.channels.discord.enabled', false);
    }

    public function getConfig(): AbstractRecord
    {
        return DiscordConfigRecord::from(
            $this->configRepository->get('notification.channels.discord', [])
        );
    }

    public function createDriver(): AbstractDriver
    {
        return new DiscordDriver($this->getConfig()->toArray());
    }

    public static function validateDestination(string $destination): bool
    {
        return filter_var($destination, FILTER_VALIDATE_URL) !== false
            && str_contains($destination, 'discord.com/api/webhooks');
    }
}
```

### 3. Créer le Record de Configuration

```php
final class DiscordConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $enabled = false,
        public readonly ?string $webhook_url = null,
    ) {}
}
```

---

## Cas d'usage concrets

### 1. Application médicale

```php
class Doctor extends Model implements NotifiableInterface
{
    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;

        if ($this->email_professional) {
            $collection->add(new NotificationRouteVO(
                MailChannel::class,
                $this->email_professional,
                new StrictDataObject(['priority' => 'high'])
            ));
        }

        if ($this->email_personal) {
            $collection->add(new NotificationRouteVO(
                MailChannel::class,
                $this->email_personal,
                new StrictDataObject(['priority' => 'low'])
            ));
        }

        $collection->add(new NotificationRouteVO(
            DatabaseChannel::class,
            'database'
        ));

        return $collection;
    }
}
```

### 2. E-commerce multi-canal

```php
class Order extends Model implements NotifiableInterface
{
    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;

        $collection->add(new NotificationRouteVO(MailChannel::class, $this->customer_email));
        $collection->add(new NotificationRouteVO(PusherChannel::class, "private-order.{$this->id}"));

        foreach ($this->customer->webPushSubscriptions as $subscription) {
            $collection->add(new NotificationRouteVO(
                WebPushChannel::class,
                $subscription->endpoint,
                new StrictDataObject([
                    'endpoint' => $subscription->endpoint,
                    'p256dh' => $subscription->p256dh,
                    'auth' => $subscription->auth,
                ])
            ));
        }

        foreach ($this->customer->fcmDevices as $device) {
            $collection->add(new NotificationRouteVO(
                FirebaseCloudMessagingChannel::class,
                $device->token
            ));
        }

        return $collection;
    }
}
```

### 3. Notification multi-canal avec `data` / `metadata`

```php
$message = new NotificationMessageVO(
    body: new MessageBodyVO('Votre commande a été expédiée.'),
    subject: new MessageSubjectVO('Commande expédiée'),
    type: 'order_shipped',

    // ⬇️ Payload métier — part dans data.payload (FCM), data Pusher, payload Web Push
    data: new StrictDataObject(['order_id' => 42, 'url' => '/orders/42']),

    // ⬇️ Options visuelles/transport — chaque driver lit ce qui le concerne
    metadata: new StrictDataObject([
        // FCM
        'icon' => 'ic_notification',
        'channel_id' => 'afya_orders',
        'click_action' => 'OPEN_ORDERS',
        // Pusher
        'event' => 'notification.order_shipped',
        // Web Push
        'ttl' => 3600,
        'urgency' => 'normal',
        // Mail
        'cc' => ['support@afya.com'],
    ]),
);

$record = SendNowRecord::from([
    'channels' => [
        PusherChannel::class,
        WebPushChannel::class,
        FirebaseCloudMessagingChannel::class,
        MailChannel::class,
        DatabaseChannel::class,
    ],
    'limit_per_channel' => 1,
]);

$results = $service->sendNow($order->customer, $message, $record);
```

**Résultat** :

- **Pusher** : event `notification.order_shipped`, payload avec `data`
- **Web Push** : en-têtes `TTL: 3600`, `Urgency: normal`, payload avec `data`
- **FCM** : `android.notification.icon = ic_notification`, `android.notification.channel_id = afya_orders`, `data.payload = {"order_id":42,"url":"/orders/42"}`
- **Mail** : cc `support@afya.com`, body + subject
- **Database** : 5 lignes de traçabilité (une par canal)

---

## Bonnes pratiques

### ✅ Injecter le service via le constructeur

```php
class UserController extends Controller
{
    public function __construct(
        private readonly NotificationService $service
    ) {}
}
```

### ✅ Valider les destinations dans l'entité

```php
if ($this->email) {
    $collection->add(new NotificationRouteVO(MailChannel::class, $this->email));
}
```

### ✅ Séparer `data` (métier) et `metadata` (visuel/transport)

```php
// ✅ BON
data: new StrictDataObject(['order_id' => 42]),
metadata: new StrictDataObject(['icon' => 'ic_notification']),

// ❌ MAUVAIS
data: new StrictDataObject([
    'order_id' => 42,
    'icon' => 'ic_notification',  // ⬅️ ne va pas dans data.payload
]),
```

### ✅ Ne jamais pré-encoder en JSON dans `data`

Le driver fait `json_encode()` **une seule fois**. Sinon → double stringification.

```php
// ❌ JAMAIS
'updated_fields' => json_encode(['currency']),

// ✅ TOUJOURS
'updated_fields' => ['currency'],
```

### ✅ Toujours inclure le canal Database

```php
$collection->add(new NotificationRouteVO(
    DatabaseChannel::class,
    'database'
));
```

### ✅ Utiliser les limites par canal

```php
$record = SendNowRecord::from([
    'channels' => [MailChannel::class, PusherChannel::class],
    'limit_per_channel' => 1,
]);
```

### ✅ Vérifier périodiquement les cibles push

```bash
./vendor/bin/directive notification:register-prune-fcm
./vendor/bin/directive notification:register-prune-webpush
```

### ✅ Gérer les erreurs proprement

```php
$results = $this->service->sendNow($user, $message, $record);

if (!$results->allSuccess()) {
    foreach ($results->getFailures() as $failure) {
        Log::error('Notification failed', [
            'channel' => $failure->channel->getValue(),
            'destination' => $failure->destination,
            'error' => $failure->error_message->getValue(),
        ]);
    }
}
```

---

## Trait HasNotifications

Le trait `HasNotifications` fournit une API riche pour les modèles Eloquent qui reçoivent des notifications.

### Installation

```php
final class User extends Authenticatable
{
    use HasNotifications;
}
```

### Attributs calculés

| Attribut | Type | Description |
|----------|------|-------------|
| `unread_notifications_count` | `int` | Notifications non lues |
| `unread_notifications` | `Collection` | Notifications non lues |
| `read_notifications` | `Collection` | Notifications lues |
| `sent_notifications` | `Collection` | Notifications envoyées |
| `pending_notifications` | `Collection` | Notifications en attente |
| `failed_notifications` | `Collection` | Notifications échouées |
| `latest_notifications` | `Collection` | 10 dernières notifications |
| `database_notifications` | `Collection` | Notifications du canal `DatabaseChannel` |
| `has_unread_notifications` | `bool` | Y a-t-il des non lues ? |
| `has_notifications` | `bool` | Y a-t-il au moins une notification ? |

### Méthodes d'action

| Méthode | Description | Retour |
|---------|-------------|--------|
| `markNotificationAsRead(string $notificationId): bool` | Marque comme lue | `bool` |
| `markAllNotificationsAsRead(): int` | Marque toutes comme lues | `int` |
| `deleteNotification(string $notificationId): bool` | Soft delete | `bool` |
| `deleteAllNotifications(): int` | Supprime tout | `int` |
| `deleteReadNotifications(): int` | Supprime les lues | `int` |
| `countNotificationsByStatus(NotificationStatus $status): int` | Compte par statut | `int` |
| `notificationsByChannel(string $channel, int $limit = 10): Collection` | Par canal | `Collection` |

---

## Référence de l'API

### NotificationMessageVO

| Propriété | Type | Description |
|-----------|------|-------------|
| `body` | `MessageBodyVO` | Corps du message |
| `subject` | `MessageSubjectVO` | Sujet (non vide) |
| `type` | `string` | Discriminant métier |
| `data` | `StrictDataObject` | Payload métier |
| `metadata` | `?StrictDataObject` | Options visuelles/transport (nullable) |

| Méthode | Description |
|---------|-------------|
| `getBody(): MessageBodyVO` | Retourne le VO corps |
| `getSubject(): MessageSubjectVO` | Retourne le VO sujet |
| `getType(): string` | Retourne le type |
| `getData(): StrictDataObject` | Retourne le payload métier |
| `getMetadata(): ?StrictDataObject` | Retourne les options visuelles/transport |
| `getBodyValue(): string` | Corps en string |
| `getSubjectValue(): string` | Sujet en string |
| `with(string $key, mixed $value): self` | Nouvelle instance avec `data[key]` modifié |
| `withMetadata(string $key, mixed $value): self` | Nouvelle instance avec `metadata[key]` modifié |
| `toArray(): array` | Sérialisation complète |
| `has(string $key): bool` | Vérifie `data[key]` |
| `get(string $key, mixed $default = null): mixed` | Récupère `data[key]` |

### NotificationService

| Méthode | Description | Retour |
|---------|-------------|--------|
| `withOptions(SendOptions $options): self` | Définit les options | `self` |
| `resetOptions(): self` | Réinitialise les options | `self` |
| `sendNow(NotifiableInterface, NotificationMessageVO, ?SendNowRecord): SendResultCollection` | Envoi immédiat | `SendResultCollection` |
| `sendLater(NotifiableInterface, NotificationMessageVO, ?SendLaterRecord): TaskAliasVO` | Envoi différé | `TaskAliasVO` |
| `sendAt(NotifiableInterface, NotificationMessageVO, ?SendAtRecord): TaskAliasVO` | Envoi planifié | `TaskAliasVO` |
| `sendRecurring(NotifiableInterface, NotificationMessageVO, ?SendRecurringRecord): TaskAliasVO` | Envoi récurrent | `TaskAliasVO` |
| `cancel(string $signature): bool` | Annuler une tâche | `bool` |
| `pause(string $signature): bool` | Pause | `bool` |
| `resume(string $signature): bool` | Reprise | `bool` |
| `changeInterval(string $signature, int $newIntervalSeconds): bool` | Change l'intervalle | `bool` |
| `getStats(NotifiableInterface&Model): NotificationStatsVO` | Statistiques | `NotificationStatsVO` |

### SendResultCollection

| Méthode | Description |
|---------|-------------|
| `getSuccessCount(): int` | Nombre de réussis |
| `getFailureCount(): int` | Nombre d'échecs |
| `allSuccess(): bool` | Tous réussis ? |
| `hasFailures(): bool` | Au moins un échec ? |
| `filterBySuccess(): self` | Filtre réussis |
| `filterByFailure(): self` | Filtre échecs |
| `filterByChannel(string $channelClass): self` | Filtre par canal |
| `getSuccessfulDestinations(): array` | Destinations réussies |
| `getFailedDestinations(): array` | Destinations échouées |

### NotificationStatsVO

| Propriété | Description |
|-----------|-------------|
| `total: int` | Total |
| `sent: int` | Nombre de `SENT` |
| `failed: int` | Nombre de `FAILED` |
| `delivered: int` | Nombre de `DELIVERED` |
| `pending: int` | Nombre de `PENDING` |
| `success_rate: float` | Taux de succès (%) |

### PingStatus

| Case | Description |
|------|-------------|
| `PONG` | La cible est valide |
| `INVALID` | La cible est invalide (à supprimer) |

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)

---
