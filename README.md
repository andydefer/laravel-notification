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
6. [Gestion des tâches](#gestion-des-tâches)
7. [Statistiques et rapports](#statistiques-et-rapports)
8. [Canaux disponibles](#canaux-disponibles)
9. [Créer un canal personnalisé](#créer-un-canal-personnalisé)
10. [Cas d'usage concrets](#cas-dusage-concrets)
11. [Bonnes pratiques](#bonnes-pratiques)
12. [Référence de l'API](#référence-de-lapi)

---

## Installation

```bash
composer require andydefer/laravel-notification

php artisan vendor:publish --tag=notification-migrations
php artisan migrate

php artisan vendor:publish --tag=notification-config
```

**Prérequis :** PHP 8.2+ | Laravel 12.x, 13.x, 14.x ou 15.x

---

## Pourquoi Laravel Notification ?

**Le problème :** Votre application doit notifier les utilisateurs par email, SMS, WhatsApp, Slack, et dans la base de données. Chaque médecin a une adresse email professionnelle et une personnelle. Chaque client a un numéro de téléphone principal et un secondaire. Vous devez tracer **toutes** les notifications pour l'audit, savoir lesquelles ont échoué, et pouvoir consulter l'historique complet.

**La solution :** Laravel Notification. Un système complet qui orchestre l'envoi sur tous les canaux d'une entité, trace chaque tentative, et permet la planification avancée.

### Comparatif rapide

| Besoin | Laravel Notifications (natif) | Laravel Notification |
|--------|-------------------------------|----------------------|
| Plusieurs destinations par canal | ❌ | ✅ |
| Persistance automatique | ❌ (sauf database) | ✅ (tous les canaux) |
| Statut de l'envoi (SENT/FAILED) | ❌ | ✅ |
| Limitation par canal | ❌ | ✅ |
| Métadonnées par destination | ❌ | ✅ |
| Envoi différé | ⚠️ (via queues) | ✅ (intégré) |
| Envoi récurrent | ⚠️ (via scheduler) | ✅ (intégré) |
| Gestion des tâches (pause/reprise) | ❌ | ✅ |
| Architecture extensible | ⚠️ (complexe) | ✅ (simple) |

### En une phrase

> **Laravel Notifications envoie un message sur un canal défini. Laravel Notification orchestre l'envoi sur tous les canaux d'une entité, trace chaque tentative et permet la planification avancée.**

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
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
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
        
        // ✅ SMS principal
        if ($this->phone_primary) {
            $collection->add(new NotificationRouteVO(
                channelClass: SmsChannel::class,
                destination: $this->phone_primary
            ));
        }
        
        // ✅ SMS secondaire
        if ($this->phone_secondary) {
            $collection->add(new NotificationRouteVO(
                channelClass: SmsChannel::class,
                destination: $this->phone_secondary
            ));
        }
        
        // ✅ Base de données (toujours disponible)
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

---

## Envoyer une notification

### Envoi immédiat

```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;

class UserController extends Controller
{
    public function __construct(
        private readonly NotificationService $service
    ) {}

    public function welcome(User $user)
    {
        $message = new NotificationMessageVO(
            subject: 'Bienvenue !',
            body: '<h1>Bonjour !</h1><p>Bienvenue sur notre plateforme.</p>'
        );

        $record = SendNowRecord::from([
            'channels' => [MailChannel::class, SmsChannel::class],
            'limit_per_channel' => 1, // Un seul email, un seul SMS
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

**Résultat :**
- L'email est envoyé à l'adresse primaire (limit_per_channel = 1)
- Le SMS est envoyé au numéro primaire
- Les deux notifications sont persistées en base de données
- Le statut de chaque envoi est enregistré (SENT ou FAILED)

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
            subject: 'Votre panier vous attend !',
            body: '<p>Vous avez des articles dans votre panier...</p>'
        );

        $record = SendLaterRecord::from([
            'delay_seconds' => 1800, // 30 minutes
            'channels' => [MailChannel::class, SmsChannel::class],
            'limit_per_channel' => 1,
        ]);

        $alias = $this->service->sendLater($user, $message, $record);

        // ✅ Stocker l'alias pour annulation si le panier est validé
        $cart->notification_task = $alias->getValue();
        $cart->save();

        return response()->json([
            'message' => 'Rappel planifié dans 30 minutes',
            'task_alias' => $alias->getValue(),
        ]);
    }
}
```

**Résultat :**
- Une tâche `SendDelayedNotificationTask` est créée
- La tâche s'exécute dans 30 minutes
- À l'exécution, la notification est envoyée sur les canaux configurés
- Tout est tracé en base de données

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
            subject: 'Rappel de rendez-vous',
            body: '<p>Votre rendez-vous est dans 24h.</p>'
        );

        $scheduledAt = $appointment->start_at->subDay();

        $record = SendAtRecord::from([
            'scheduled_at' => new NotificationDateTimeVO(
                $scheduledAt->toIso8601String()
            ),
            'channels' => [MailChannel::class, SmsChannel::class],
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

class NewsletterController extends Controller
{
    public function scheduleNewsletter(User $user)
    {
        $message = new NotificationMessageVO(
            subject: 'Votre newsletter hebdomadaire',
            body: '<p>Voici les dernières actualités...</p>'
        );

        $record = SendRecurringRecord::from([
            'interval_seconds' => 604800, // 7 jours
            'start_at' => new NotificationDateTimeVO('2026-07-08 09:00:00'),
            'end_at' => new NotificationDateTimeVO('2026-12-31 09:00:00'),
            'channels' => [MailChannel::class],
            'limit_per_channel' => 1,
            'max_attempts' => 3,
        ]);

        $alias = $this->service->sendRecurring($user, $message, $record);

        return response()->json([
            'message' => 'Newsletter planifiée chaque lundi à 9h',
            'task_alias' => $alias->getValue(),
        ]);
    }
}
```

**Résultat :**
- Une tâche `SendRecurringNotificationTask` est créée
- Elle s'exécute toutes les semaines à 9h
- Elle s'arrête automatiquement le 31 décembre 2026
- En cas d'échec, elle fait jusqu'à 3 tentatives

---

## Gestion des tâches

Le `NotificationService` expose une API complète pour gérer les tâches de notification.

### Pause, reprise, annulation

```php
<?php

namespace App\Services;

use AndyDefer\LaravelNotification\Services\NotificationService;

class TaskManager
{
    public function __construct(
        private readonly NotificationService $service
    ) {}

    // ✅ Mettre en pause une tâche récurrente
    public function pause(string $alias): bool
    {
        return $this->service->pause($alias);
        // La tâche ne sera plus exécutée jusqu'à reprise
    }

    // ✅ Reprendre une tâche mise en pause
    public function resume(string $alias): bool
    {
        return $this->service->resume($alias);
    }

    // ✅ Changer l'intervalle d'une tâche récurrente
    public function changeInterval(string $alias, int $newIntervalSeconds): bool
    {
        return $this->service->changeInterval($alias, $newIntervalSeconds);
    }

    // ✅ Annuler une tâche (unique ou récurrente)
    public function cancel(string $alias): bool
    {
        return $this->service->cancel($alias);
        // La tâche est définitivement supprimée
    }
}
```

**Exemple d'utilisation :**

```php
// Dans un contrôleur Admin
class AdminController extends Controller
{
    public function pauseNewsletter(string $alias)
    {
        if ($this->service->pause($alias)) {
            return response()->json(['message' => 'Newsletter mise en pause']);
        }
        
        return response()->json(['error' => 'Tâche non trouvée'], 404);
    }

    public function changeFrequency(string $alias, int $days)
    {
        $intervalSeconds = $days * 86400;
        
        if ($this->service->changeInterval($alias, $intervalSeconds)) {
            return response()->json(['message' => "Fréquence modifiée à {$days} jours"]);
        }
        
        return response()->json(['error' => 'Tâche non trouvée'], 404);
    }
}
```

---

## Statistiques et rapports

```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;

class StatsController extends Controller
{
    public function __construct(
        private readonly NotificationService $service
    ) {}

    public function userStats(User $user)
    {
        // ✅ Statistiques globales de l'utilisateur
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
        // ✅ Statistiques d'une session d'envoi
        $sessionStats = $this->service->getSessionStats($sessionId);

        return response()->json([
            'session_id' => $sessionStats->session_id,
            'total' => $sessionStats->total,
            'sent' => $sessionStats->sent,
            'failed' => $sessionStats->failed,
            'pending' => $sessionStats->pending,
        ]);
    }

    public function dashboard()
    {
        $users = User::all();
        $globalStats = [
            'total_notifications' => 0,
            'total_sent' => 0,
            'total_failed' => 0,
            'users' => [],
        ];

        foreach ($users as $user) {
            $stats = $this->service->getStats($user);
            $globalStats['total_notifications'] += $stats->total;
            $globalStats['total_sent'] += $stats->sent;
            $globalStats['total_failed'] += $stats->failed;
            $globalStats['users'][] = [
                'user_id' => $user->id,
                'stats' => $stats->toArray(),
            ];
        }

        return view('admin.dashboard', $globalStats);
    }
}
```

---

## Canaux disponibles

| Canal | Nom | Icône | Description | Actif par défaut |
|-------|-----|-------|-------------|------------------|
| **MailChannel** | Email | 📧 | Envoi d'emails via Laravel Mail | ✅ |
| **DatabaseChannel** | Base de données | 💾 | Stockage en base de données | ✅ |
| **SmsChannel** | SMS | 📱 | Envoi de SMS (Twilio, Vonage) | ❌ |
| **WhatsAppChannel** | WhatsApp | 💬 | Envoi via Meta Business API | ❌ |
| **SlackChannel** | Slack | 💼 | Envoi via Webhook | ❌ |
| **TelegramChannel** | Telegram | ✈️ | Envoi via Bot API | ❌ |
| **PushChannel** | Push Notification | 🔔 | Notifications push (FCM, APNS) | ❌ |

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
        'sms' => [
            'enabled' => env('SMS_ENABLED', false),
            'driver' => 'twilio',
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
        'slack' => [
            'enabled' => env('SLACK_ENABLED', false),
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
        ],
        'telegram' => [
            'enabled' => env('TELEGRAM_ENABLED', false),
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_CHAT_ID'),
        ],
        'whatsapp' => [
            'enabled' => env('WHATSAPP_ENABLED', false),
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        ],
        'push' => [
            'enabled' => env('PUSH_ENABLED', false),
            'platform' => 'fcm',
            'fcm_api_key' => env('FCM_API_KEY'),
            'fcm_project_id' => env('FCM_PROJECT_ID'),
            'apns_key_path' => env('APNS_KEY_PATH'),
            'apns_key_id' => env('APNS_KEY_ID'),
            'apns_team_id' => env('APNS_TEAM_ID'),
            'apns_bundle_id' => env('APNS_BUNDLE_ID'),
            'default_sound' => 'default',
            'default_tokens' => [],
        ],
    ],
];
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
use App\Notifications\Drivers\DiscordDriver;
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

        return new DiscordDriver($config);
    }

    public static function validateDestination(string $destination): bool
    {
        return filter_var($destination, FILTER_VALIDATE_URL) !== false
            && str_contains($destination, 'discord.com/api/webhooks');
    }
}
```

### 3. Utiliser le canal personnalisé

```php
<?php

use App\Notifications\Channels\DiscordChannel;

class OrderController extends Controller
{
    public function notifyAdmin(Order $order)
    {
        $user = User::find(1); // Admin
        
        $message = new NotificationMessageVO(
            subject: 'Nouvelle commande !',
            body: "La commande #{$order->id} a été passée."
        );

        $record = SendNowRecord::from([
            'channels' => [DiscordChannel::class],
            'limit_per_channel' => 1,
        ]);

        $results = $this->service->sendNow($user, $message, $record);

        // ✅ La notification est envoyée sur Discord ET tracée en base de données
    }
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
        
        // ✅ Email professionnel (priorité haute)
        if ($this->email_professional) {
            $collection->add(new NotificationRouteVO(
                MailChannel::class,
                $this->email_professional,
                new StrictDataObject(['priority' => 'high'])
            ));
        }
        
        // ✅ Email personnel (priorité basse)
        if ($this->email_personal) {
            $collection->add(new NotificationRouteVO(
                MailChannel::class,
                $this->email_personal,
                new StrictDataObject(['priority' => 'low'])
            ));
        }
        
        // ✅ SMS d'urgence
        if ($this->phone) {
            $collection->add(new NotificationRouteVO(
                SmsChannel::class,
                $this->phone,
                new StrictDataObject(['type' => 'emergency'])
            ));
        }
        
        // ✅ WhatsApp pour les rappels
        if ($this->phone) {
            $collection->add(new NotificationRouteVO(
                WhatsAppChannel::class,
                $this->phone
            ));
        }
        
        // ✅ Traçabilité
        $collection->add(new NotificationRouteVO(
            DatabaseChannel::class,
            'database'
        ));
        
        return $collection;
    }
}

// ✅ Alerte d'urgence : tous les canaux, sans limite
$record = SendNowRecord::from([
    'channels' => [], // Tous les canaux
    'limit_per_channel' => null, // Pas de limite
]);

// ✅ Rappel normal : uniquement email et WhatsApp, limité à 1
$record = SendNowRecord::from([
    'channels' => [MailChannel::class, WhatsAppChannel::class],
    'limit_per_channel' => 1,
]);
```

### 2. E-commerce

```php
class Order extends Model implements NotifiableInterface
{
    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;
        
        // ✅ Client
        $collection->add(new NotificationRouteVO(
            MailChannel::class,
            $this->customer_email
        ));
        
        if ($this->customer_phone) {
            $collection->add(new NotificationRouteVO(
                SmsChannel::class,
                $this->customer_phone
            ));
        }
        
        // ✅ Admin
        $collection->add(new NotificationRouteVO(
            MailChannel::class,
            'admin@shop.com',
            new StrictDataObject(['role' => 'admin'])
        ));
        
        // ✅ Équipe Slack
        $collection->add(new NotificationRouteVO(
            SlackChannel::class,
            '#orders',
            new StrictDataObject(['webhook_url' => env('SLACK_ORDERS_WEBHOOK')])
        ));
        
        return $collection;
    }
}

// ✅ Après validation de commande
$service->sendNow($order, $message, $record);
// → Client reçoit un email + SMS
// → Admin reçoit un email
// → Slack #orders reçoit une notification
// → Tout est tracé en base de données
```

### 3. Newsletter et campagnes marketing

```php
// ✅ Newsletter hebdomadaire sur 4 semaines
$record = SendRecurringRecord::from([
    'interval_seconds' => 604800, // 7 jours
    'start_at' => new NotificationDateTimeVO('2026-07-08 09:00:00'),
    'end_at' => new NotificationDateTimeVO('2026-07-29 09:00:00'),
    'channels' => [MailChannel::class],
    'limit_per_channel' => 1,
]);

$alias = $service->sendRecurring($user, $message, $record);

// ✅ Gestion de la campagne
$service->pause($alias);      // Pause si désabonnement
$service->resume($alias);     // Reprise si réabonnement
$service->changeInterval($alias, 86400); // Tous les jours
$service->cancel($alias);     // Annulation définitive
```

### 4. Relance après abandon de panier

```php
// ✅ Email de relance 30 minutes après abandon
$record = SendLaterRecord::from([
    'delay_seconds' => 1800,
    'channels' => [MailChannel::class, SmsChannel::class],
    'limit_per_channel' => 1,
]);

$alias = $service->sendLater($user, $message, $record);

// ✅ Si le panier est validé, annuler la tâche
$service->cancel($alias);
```

### 5. Notifications d'urgence avec escalade

```php
// ✅ Système d'escalade : tenter 3 fois avec délai croissant
$attempts = [
    ['delay' => 300, 'channel' => SmsChannel::class],
    ['delay' => 600, 'channel' => WhatsAppChannel::class],
    ['delay' => 900, 'channel' => MailChannel::class],
];

foreach ($attempts as $attempt) {
    $record = SendLaterRecord::from([
        'delay_seconds' => $attempt['delay'],
        'channels' => [$attempt['channel']],
        'limit_per_channel' => 1,
    ]);
    
    $service->sendLater($user, $message, $record);
}
// → SMS dans 5 min, WhatsApp dans 10 min, Email dans 15 min
```

### 6. Audit et conformité

```php
// ✅ Récupération de toutes les notifications d'un utilisateur
$stats = $service->getStats($user);

// ✅ Vérification de conformité
if ($stats->failed > 0) {
    Log::warning('Des notifications ont échoué pour l\'utilisateur ' . $user->id);
}

// ✅ Rapport mensuel
$users = User::where('created_at', '>=', now()->subMonth())->get();
$report = [];
foreach ($users as $user) {
    $report[$user->id] = $service->getStats($user)->toArray();
}
```

---

## Bonnes pratiques

### ✅ Injecter le service via le constructeur

```php
// BON
class UserController extends Controller
{
    public function __construct(
        private readonly NotificationService $service
    ) {}
}

// ÉVITER (facade)
use AndyDefer\LaravelNotification\Facades\Notification;
Notification::sendNow(...);
```

### ✅ Valider les destinations dans l'entité

```php
// BON
public function getNotificationChannels(): NotificationRouteCollection
{
    $collection = new NotificationRouteCollection;
    
    if ($this->email) {
        $collection->add(new NotificationRouteVO(
            MailChannel::class,
            $this->email
        ));
    }
    
    return $collection;
}

// ÉVITER (laisser les destinations vides)
public function getNotificationChannels(): NotificationRouteCollection
{
    return NotificationRouteCollection::from([
        new NotificationRouteVO(MailChannel::class, $this->email), // Peut être null
    ]);
}
```

### ✅ Utiliser les métadonnées pour le contexte

```php
// BON
$collection->add(new NotificationRouteVO(
    MailChannel::class,
    $this->email,
    new StrictDataObject([
        'name' => $this->name,
        'locale' => $this->locale,
        'timezone' => $this->timezone,
    ])
));
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

### ✅ Toujours inclure le canal Database

```php
public function getNotificationChannels(): NotificationRouteCollection
{
    $collection = new NotificationRouteCollection;
    
    // ... autres canaux ...
    
    // ✅ Toujours présent pour la traçabilité
    $collection->add(new NotificationRouteVO(
        DatabaseChannel::class,
        'database'
    ));
    
    return $collection;
}
```

### ✅ Utiliser des limites par canal

```php
// ✅ Pour éviter les spams, limiter à 1 par canal
$record = SendNowRecord::from([
    'channels' => [MailChannel::class, SmsChannel::class],
    'limit_per_channel' => 1,
]);

// ✅ Pour les notifications critiques, tout envoyer
$record = SendNowRecord::from([
    'channels' => [], // Tous
    'limit_per_channel' => null, // Pas de limite
]);
```

---

## Référence de l'API

### NotificationService

| Méthode | Description | Retour |
|---------|-------------|--------|
| `sendNow(NotifiableInterface, NotificationMessageVO, SendNowRecord)` | Envoi immédiat | `SendResultCollection` |
| `sendLater(NotifiableInterface, NotificationMessageVO, SendLaterRecord)` | Envoi différé | `TaskAliasVO` |
| `sendAt(NotifiableInterface, NotificationMessageVO, SendAtRecord)` | Envoi planifié | `TaskAliasVO` |
| `sendRecurring(NotifiableInterface, NotificationMessageVO, SendRecurringRecord)` | Envoi récurrent | `TaskAliasVO` |
| `cancel(string $signature)` | Annuler une tâche | `bool` |
| `pause(string $signature)` | Mettre en pause | `bool` |
| `resume(string $signature)` | Reprendre | `bool` |
| `changeInterval(string $signature, int $newIntervalSeconds)` | Modifier l'intervalle | `bool` |
| `getStats(NotifiableInterface&Model $notifiable)` | Statistiques globales | `NotificationStatsVO` |
| `getSessionStats(string $sessionId)` | Statistiques d'une session | `SessionStatsRecord` |

### SendResultCollection

| Méthode | Description |
|---------|-------------|
| `getSuccessCount(): int` | Nombre d'envois réussis |
| `getFailureCount(): int` | Nombre d'échecs |
| `allSuccess(): bool` | Tous les envois ont réussi ? |
| `hasFailures(): bool` | Au moins un échec ? |
| `filterBySuccess(): self` | Filtre les réussis |
| `filterByFailure(): self` | Filtre les échecs |
| `filterByChannel(string $channelClass): self` | Filtre par canal |
| `getSuccessfulDestinations(): array` | Destinations réussies |
| `getFailedDestinations(): array` | Destinations échouées |

### NotificationStatsVO

| Propriété | Description |
|-----------|-------------|
| `total: int` | Total des notifications |
| `sent: int` | Nombre de SENT |
| `failed: int` | Nombre de FAILED |
| `delivered: int` | Nombre de DELIVERED |
| `pending: int` | Nombre de PENDING |
| `success_rate: float` | Taux de succès (%) |

### SessionStatsRecord

| Propriété | Description |
|-----------|-------------|
| `session_id: string` | ID de la session |
| `total: int` | Total des notifications |
| `sent: int` | Nombre de SENT |
| `failed: int` | Nombre de FAILED |
| `pending: int` | Nombre de PENDING |

---

## Licence

MIT © [Andy Defer](https://github.com/andydefer)