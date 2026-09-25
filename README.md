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
6. [Filtrage des destinations avec SendOptions](#filtrage-des-destinations-avec-sendoptions)
7. [NotifiableBuilder - Envoi sans entité](#notifiablebuilder---envoi-sans-entité)
8. [MessageViewBodyVO - Corps de message basé sur une vue Laravel](#messageviewbodyvo---corps-de-message-basé-sur-une-vue-laravel)
9. [Gestion des tâches](#gestion-des-tâches)
10. [Statistiques et rapports](#statistiques-et-rapports)
11. [Canaux fonctionnels](#canaux-fonctionnels)
12. [Drivers fonctionnels](#drivers-fonctionnels)
13. [Créer un canal personnalisé](#créer-un-canal-personnalisé)
14. [Cas d'usage concrets](#cas-dusage-concrets)
15. [Bonnes pratiques](#bonnes-pratiques)
16. [Trait HasNotifications](#trait-hasnotifications)
17. [Référence de l'API](#référence-de-lapi)

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

**Le problème :** Votre application doit notifier les utilisateurs par email, temps réel (Pusher) et dans la base de données. Chaque médecin a une adresse email professionnelle et une personnelle. Vous devez tracer **toutes** les notifications pour l'audit, savoir lesquelles ont échoué, et pouvoir consulter l'historique complet.

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
| Envoi différé | ⚠️ (via queues) | ✅ (intégré) |
| Envoi récurrent | ⚠️ (via scheduler) | ✅ (intégré) |
| Gestion des tâches (pause/reprise) | ❌ | ✅ |
| Architecture extensible | ⚠️ (complexe) | ✅ (simple) |
| Envoi sans entité Notifiable | ❌ | ✅ (NotifiableBuilder) |
| Corps de message basé vue Laravel | ❌ | ✅ (MessageViewBodyVO) |
| Trait utilitaire pour les modèles | ❌ | ✅ (HasNotifications) |
| Temps réel (Pusher) | ❌ | ✅ |

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
                                    │  Pusher                 │
                                    └────────────┬────────────┘
                                                 │
                                                 ▼
                                    ┌─────────────────────────┐
                                    │  Drivers (exécution)    │
                                    │  MailDriver /           │
                                    │  DatabaseDriver /       │
                                    │  PusherDriver           │
                                    └─────────────────────────┘
```

### Composants principaux

| Composant | Rôle |
|-----------|------|
| `NotificationService` | Service principal, point d'entrée de l'API |
| `NotifiableBuilder` | Builder fluide pour envoyer des notifications sans entité |
| `NotificationSenderProcessor` | Orchestre l'envoi : résolution des routes, filtres, limites |
| `SendOptions` | Configuration fluide des options d'envoi (canaux, limites, filtres) |
| `NotificationRouteVO` | Value Object définissant un canal + destination + métadonnées |
| `AbstractChannel` | Classe de base pour les canaux (`MailChannel`, `DatabaseChannel`, `PusherChannel`) |
| `AbstractDriver` | Classe de base pour les drivers (`MailDriver`, `DatabaseDriver`, `PusherDriver`) |
| `SendDelayedNotificationTask` | Tâche unique pour les envois différés/planifiés |
| `SendRecurringNotificationTask` | Tâche récurrente pour les envois périodiques |
| `MessageViewBodyVO` | Value Object pour corps de message basé sur vue Laravel |
| `HasNotifications` | Trait utilitaire pour les modèles Eloquent recevant des notifications |

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

        // ✅ Pusher (temps réel vers l'app mobile / web)
        $collection->add(new NotificationRouteVO(
            channelClass: PusherChannel::class,
            destination: "private-user.{$this->id}",
            metadata: new StrictDataObject(['event' => 'notification.received'])
        ));

        // ✅ Base de données (toujours disponible pour la traçabilité)
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

### NotificationRouteVO

Le `NotificationRouteVO` définit une route de notification :

```php
new NotificationRouteVO(
    channelClass: MailChannel::class,      // Canal
    destination: 'user@example.com',       // Destination
    metadata: new StrictDataObject([       // Métadonnées optionnelles
        'type' => 'primary',
        'name' => 'John Doe',
    ])
);
```

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
            data: new StrictDataObject(['user_id' => $user->id])
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
            'details' => $results->map(fn($r) => [
                'channel' => $r->channel->getValue(),
                'destination' => $r->destination,
                'success' => $r->success,
            ])->toArray(),
        ]);
    }
}
```

**Résultat :**
- L'email est envoyé à l'adresse primaire (`limit_per_channel = 1`).
- L'événement Pusher est diffusé sur `private-user.{id}`.
- Les notifications sont persistées en base.
- Le statut de chaque envoi est enregistré (`SENT` ou `FAILED`).

---

### Envoi différé

```php
<?php

use AndyDefer\LaravelNotification\Records\SendLaterRecord;

class CartController extends Controller
{
    public function abandonCart(Cart $cart)
    {
        $user = $cart->user;

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Vous avez des articles dans votre panier...'),
            subject: new MessageSubjectVO('Votre panier vous attend !'),
            type: 'abandoned_cart',
            data: new StrictDataObject([
                'cart_id' => $cart->id,
                'items_count' => $cart->items->count(),
            ])
        );

        $record = SendLaterRecord::from([
            'delay_seconds' => 1800,
            'channels' => [MailChannel::class, PusherChannel::class],
            'limit_per_channel' => 1,
        ]);

        $alias = $this->service->sendLater($user, $message, $record);

        $cart->notification_task = $alias->getValue();
        $cart->save();

        return response()->json([
            'message' => 'Rappel planifié dans 30 minutes',
            'task_alias' => $alias->getValue(),
        ]);
    }
}
```

---

### Envoi planifié

```php
<?php

use AndyDefer\LaravelNotification\Records\SendAtRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationDateTimeVO;

class AppointmentController extends Controller
{
    public function scheduleReminder(Appointment $appointment)
    {
        $user = $appointment->user;

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Votre rendez-vous est dans 24h.'),
            subject: new MessageSubjectVO('Rappel de rendez-vous'),
            type: 'appointment_reminder',
            data: new StrictDataObject([
                'appointment_id' => $appointment->id,
                'start_at' => $appointment->start_at->toIso8601String(),
            ])
        );

        $scheduledAt = $appointment->start_at->subDay();

        $record = SendAtRecord::from([
            'scheduled_at' => new NotificationDateTimeVO(
                $scheduledAt->toIso8601String()
            ),
            'channels' => [MailChannel::class, PusherChannel::class],
            'limit_per_channel' => 1,
        ]);

        $alias = $this->service->sendAt($user, $message, $record);

        return response()->json([
            'message' => 'Rappel planifié pour le ' . $scheduledAt->format('d/m/Y H:i'),
            'task_alias' => $alias->getValue(),
        ]);
    }
}
```

---

### Envoi récurrent

```php
<?php

use AndyDefer\LaravelNotification\Records\SendRecurringRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationDateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;

class NewsletterController extends Controller
{
    public function scheduleNewsletter(User $user)
    {
        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Voici les dernières actualités...'),
            subject: new MessageSubjectVO('Votre newsletter hebdomadaire'),
            type: 'newsletter',
            data: new StrictDataObject(['user_id' => $user->id])
        );

        $record = SendRecurringRecord::from([
            'interval_seconds' => 604800,
            'start_at' => new NotificationDateTimeVO('2026-07-08 09:00:00'),
            'end_at' => new NotificationDateTimeVO('2026-12-31 09:00:00'),
            'channels' => [MailChannel::class],
            'limit_per_channel' => 1,
            'max_attempts' => new MaxFailedAttemptsVO(3),
        ]);

        $alias = $this->service->sendRecurring($user, $message, $record);

        return response()->json([
            'message' => 'Newsletter planifiée chaque lundi à 9h',
            'task_alias' => $alias->getValue(),
        ]);
    }
}
```

---

## Filtrage des destinations avec SendOptions

`SendOptions` permet un contrôle précis des destinations par canal.

```php
<?php

use AndyDefer\LaravelNotification\Options\SendOptions;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\PusherChannel;

$options = SendOptions::init()
    ->withChannel(MailChannel::class)
    ->withDestinationFilter(MailChannel::class, 'user@example.com')
    ->withLimitPerChannel(1);

$results = $notificationService
    ->withOptions($options)
    ->sendNow($user, $message);
```

### Filtres multiples par canal

```php
$options = SendOptions::init()
    ->withChannel(MailChannel::class)
    ->withDestinationFilter(MailChannel::class, [
        'user@example.com',
        'admin@example.com',
        'support@example.com',
    ]);
```

### Filtres sur plusieurs canaux

```php
$options = SendOptions::init()
    ->withChannels([MailChannel::class, PusherChannel::class])
    ->withDestinationFilter(MailChannel::class, 'pro@example.com')
    ->withDestinationFilter(PusherChannel::class, 'private-user.42');
```

---

## NotifiableBuilder - Envoi sans entité

Le `NotifiableBuilder` permet d'envoyer des notifications **sans implémenter `NotifiableInterface`**.

```php
<?php

use AndyDefer\LaravelNotification\Builders\NotifiableBuilder;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\PusherChannel;

$results = NotifiableBuilder::create()
    ->to(MailChannel::class, 'user@example.com')
    ->to(PusherChannel::class, 'private-user.42')
    ->subject('Notification importante')
    ->body('Votre commande a été expédiée.')
    ->data(['order_id' => 12345])
    ->sendNow();
```

### Envoi à plusieurs destinations

```php
$results = NotifiableBuilder::create()
    ->to(MailChannel::class, [
        'user1@example.com',
        'user2@example.com',
    ])
    ->subject('Newsletter')
    ->body('Contenu de la newsletter')
    ->limit(2)
    ->sendNow();
```

### Envoi différé

```php
$alias = NotifiableBuilder::create()
    ->to(MailChannel::class, 'user@example.com')
    ->subject('Rappel')
    ->body('N\'oubliez pas votre rendez-vous demain.')
    ->sendLater(1800);
```

### API du NotifiableBuilder

| Méthode | Description | Retour |
|---------|-------------|--------|
| `static create(?NotificationService $service): self` | Crée une instance | `self` |
| `to(string $channelClass, string|array $destination): self` | Définit la destination | `self` |
| `body(string|MessageBodyVO $body): self` | Corps du message | `self` |
| `subject(string $subject): self` | Sujet du message | `self` |
| `type(string $type): self` | Type du message | `self` |
| `data(array $data): self` | Données supplémentaires | `self` |
| `limit(int $limit): self` | Limite par canal | `self` |
| `filter(string $channelClass, string|array $destinations): self` | Filtre de destination | `self` |
| `metadata(string $channelClass, StrictDataObject $metadata): self` | Métadonnées par canal | `self` |
| `as(string $morphClass, int|string $key): self` | Classe morph + clé | `self` |
| `sendNow(?SendNowRecord $record): SendResultCollection` | Envoi immédiat | `SendResultCollection` |
| `sendLater(int $delaySeconds): TaskAliasVO` | Envoi différé | `TaskAliasVO` |
| `sendAt(NotificationDateTimeVO $scheduledAt): TaskAliasVO` | Envoi planifié | `TaskAliasVO` |
| `sendRecurring(int $intervalSeconds, NotificationDateTimeVO $startAt, ?NotificationDateTimeVO $endAt): TaskAliasVO` | Envoi récurrent | `TaskAliasVO` |
| `reset(): self` | Réinitialise le builder | `self` |

---

## MessageViewBodyVO - Corps de message basé sur une vue Laravel

`MessageViewBodyVO` étend `MessageBodyVO` et permet de définir le corps d'un message à partir d'une vue Laravel.

### Constructeur

```php
public function __construct(
    string $view,
    StrictAssociative|array $data = [],
    StrictAssociative|array $mergeData = [],
    bool $plainText = false,
)
```

### Création HTML (Email)

```php
use AndyDefer\LaravelNotification\ValueObjects\MessageViewBodyVO;

$body = MessageViewBodyVO::from([
    'view' => 'emails.welcome',
    'data' => ['user' => $user],
]);

// Ou via helper
$body = MessageViewBodyVO::html(
    view: 'emails.welcome',
    data: ['user' => $user]
);
```

### Conversion (immuable)

```php
$htmlBody = MessageViewBodyVO::html('notifications.reminder', ['user' => $user]);
$finalBody = $htmlBody->withData(['extra' => 'value']);
```

---

## Gestion des tâches

```php
<?php

namespace App\Services;

use AndyDefer\LaravelNotification\Services\NotificationService;

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
<?php

class StatsController extends Controller
{
    public function __construct(
        private readonly NotificationService $service
    ) {}

    public function userStats(User $user)
    {
        $stats = $this->service->getStats($user);

        return response()->json([
            'total' => $stats->total,
            'sent' => $stats->sent,
            'failed' => $stats->failed,
            'delivered' => $stats->delivered,
            'pending' => $stats->pending,
            'success_rate' => $stats->success_rate . '%',
            'percentage_sent' => $stats->getPercentageSent(),
            'percentage_failed' => $stats->getPercentageFailed(),
        ]);
    }

    public function sessionStats(string $sessionId)
    {
        $sessionStats = $this->service->getSessionStats($sessionId);

        return response()->json([
            'session_id' => $sessionStats->session_id,
            'total' => $sessionStats->total,
            'sent' => $sessionStats->sent,
            'failed' => $sessionStats->failed,
            'pending' => $sessionStats->pending,
        ]);
    }
}
```

### NotificationStatsVO

```php
$stats = $service->getStats($user);

$stats->success_rate;              // 75.5
$stats->getPercentageSent();       // 60.0
$stats->getPercentageFailed();     // 40.0
$stats->isSuccess();               // true
$stats->hasFailures();             // false
```

---

## Canaux fonctionnels

| Canal | Nom | Icône | Description | Actif par défaut |
|-------|-----|-------|-------------|------------------|
| **MailChannel** | Email | 📧 | Envoi d'emails via Laravel Mail | ✅ |
| **DatabaseChannel** | Base de données | 💾 | Persistance pour la traçabilité | ✅ |
| **PusherChannel** | Pusher | 📡 | Diffusion temps réel via Pusher | ❌ |

### Configuration des canaux

```php
// config/notification.php
return [
    'channels' => [
        'mail' => [
            'enabled' => true,
            'default_from' => env('MAIL_FROM_ADDRESS'),
            'default_from_name' => env('MAIL_FROM_NAME'),
        ],
        'database' => [
            'driver' => 'database',
            'table' => 'notifications',
        ],
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
    ],
];
```

---

## Drivers fonctionnels

Les drivers sont responsables de l'exécution réelle de l'envoi. Chaque canal possède son driver.

| Driver | Canal | Dépendance externe | Comportement |
|--------|-------|--------------------|--------------|
| **MailDriver** | `MailChannel` | Laravel Mail | Utilise `Mail::to(...)->send(...)`. Supporte `from` / `from_name` via metadata de route. |
| **DatabaseDriver** | `DatabaseChannel` | Base de données | Insère une ligne par notification. Table configurable via `notification.channels.database.table`. |
| **PusherDriver** | `PusherChannel` | `pusher/pusher-php-server` | Publie un événement via `Pusher::trigger()`. Supporte `channel` et `event` via metadata de route. |

### MailDriver — points clés

- Configuration : `MailConfigRecord` (enabled, default_from, default_from_name).
- Surcharge possible par route via metadata `from` et `from_name`.
- Le rendu HTML est géré par `MessageBodyVO` ou `MessageViewBodyVO`.
- Les erreurs SMTP sont capturées et transformées en `error_message` du `SendResultRecord`.

### DatabaseDriver — points clés

- Configuration : `DatabaseConfigRecord` (driver, table).
- Toujours actif, garantit la traçabilité.
- Une ligne par notification envoyée, avec statut `SENT`, `FAILED`, `DELIVERED` ou `PENDING`.
- Recommandé dans **tous** les modèles `NotifiableInterface`.

### PusherDriver — points clés

- Configuration : `PusherConfigRecord` (enabled, app_id, key, secret, cluster, use_tls, timeout, default_channel).
- Résolution du channel : `metadata['channel']` > `destination` > `default_channel`.
- Résolution de l'événement : `metadata['event']` > `notification`.
- Payload diffusé : `body`, `subject`, `type`, `data`, `sent_at`.
- Client Pusher instancié en lazy et mémoïsé.

### Exemple d'envoi multi-drivers

```php
<?php

use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\PusherChannel;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;

$message = new NotificationMessageVO(
    body: new MessageBodyVO('<h1>Commande confirmée</h1>'),
    subject: new MessageSubjectVO('Commande #42 confirmée'),
    type: 'order_confirmation',
);

$record = SendNowRecord::from([
    'channels' => [
        MailChannel::class,
        PusherChannel::class,
        DatabaseChannel::class,
    ],
    'limit_per_channel' => 1,
]);

$results = $service->sendNow($user, $message, $record);

// → Mail envoyé
// → Événement Pusher diffusé
// → Notification tracée en base
```

---

## Créer un canal personnalisé

### 1. Créer le Driver

```php
<?php

namespace App\Notifications\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class DiscordDriver extends AbstractDriver
{
    public function __construct(
        private readonly array $config
    ) {}

    public function getChannel(): string
    {
        return 'discord';
    }

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

        if (!$webhookUrl) {
            throw new RuntimeException('Discord webhook URL not specified.');
        }

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
<?php

namespace App\Notifications\Channels;

use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use App\Notifications\Drivers\DiscordDriver;
use App\Notifications\Records\DiscordConfigRecord;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

class DiscordChannel extends AbstractChannel
{
    public function getName(): string
    {
        return 'discord';
    }

    public function getLabel(): string
    {
        return 'Discord';
    }

    public function getIcon(): string
    {
        return '🎮';
    }

    public function isEnabled(): bool
    {
        return $this->configRepository->get('notification.channels.discord.enabled', false);
    }

    public function getConfig(): AbstractRecord
    {
        $config = $this->configRepository->get('notification.channels.discord', [
            'enabled' => false,
            'webhook_url' => env('DISCORD_WEBHOOK_URL'),
        ]);

        return DiscordConfigRecord::from($config);
    }

    public function createDriver(): AbstractDriver
    {
        /** @var DiscordConfigRecord $config */
        $config = $this->getConfig();

        return new DiscordDriver($config->toArray());
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
<?php

namespace App\Notifications\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

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

### 2. E-commerce

```php
class Order extends Model implements NotifiableInterface
{
    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;

        $collection->add(new NotificationRouteVO(
            MailChannel::class,
            $this->customer_email
        ));

        $collection->add(new NotificationRouteVO(
            PusherChannel::class,
            "private-order.{$this->id}"
        ));

        return $collection;
    }
}
```

### 3. Temps réel mobile / web avec Pusher

```php
use AndyDefer\LaravelNotification\Channels\PusherChannel;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

// Modèle
$collection->add(new NotificationRouteVO(
    PusherChannel::class,
    "private-user.{$this->id}",
    new StrictDataObject(['event' => 'notification.received'])
));

// Envoi
$service->sendNow($user, $message, SendNowRecord::from([
    'channels' => [PusherChannel::class],
    'limit_per_channel' => 1,
]));
```

```ts
// Frontend Laravel Echo + Pusher
Echo.private(`user.${userId}`)
    .listen('.notification.received', (payload) => {
        console.log(payload.body, payload.subject, payload.data);
    });
```

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

### ✅ Utiliser NotifiableBuilder pour les envois directs

```php
$results = NotifiableBuilder::create()
    ->to(MailChannel::class, 'user@example.com')
    ->subject('Test')
    ->body('Contenu')
    ->sendNow();
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
<?php

namespace App\Models;

use AndyDefer\LaravelNotification\Traits\HasNotifications;
use Illuminate\Foundation\Auth\User as Authenticatable;

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
| `latest_database_notifications` | `Collection` | 10 dernières notifications du canal `DatabaseChannel` |
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
| `getStats(NotifiableInterface&Model $notifiable): NotificationStatsVO` | Statistiques | `NotificationStatsVO` |
| `getSessionStats(string $sessionId): SessionStatsRecord` | Stats de session | `SessionStatsRecord` |

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
| `getPercentageSent(): float` | % envoyé |
| `getPercentageFailed(): float` | % échoué |
| `isSuccess(): bool` | Tout a réussi |
| `hasFailures(): bool` | Au moins un échec |

### SessionStatsRecord

| Propriété | Description |
|-----------|-------------|
| `session_id: string` | ID de session |
| `total: int` | Total |
| `sent: int` | Nombre de `SENT` |
| `failed: int` | Nombre de `FAILED` |
| `pending: int` | Nombre de `PENDING` |

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)
