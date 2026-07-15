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
8. [Gestion des tâches](#gestion-des-tâches)
9. [Statistiques et rapports](#statistiques-et-rapports)
10. [Canaux disponibles](#canaux-disponibles)
11. [Créer un canal personnalisé](#créer-un-canal-personnalisé)
12. [Cas d'usage concrets](#cas-dusage-concrets)
13. [Bonnes pratiques](#bonnes-pratiques)
14. [Référence de l'API](#référence-de-lapi)

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
| Filtrage des destinations par canal | ❌ | ✅ |
| Envoi différé | ⚠️ (via queues) | ✅ (intégré) |
| Envoi récurrent | ⚠️ (via scheduler) | ✅ (intégré) |
| Gestion des tâches (pause/reprise) | ❌ | ✅ |
| Architecture extensible | ⚠️ (complexe) | ✅ (simple) |
| Envoi sans entité Notifiable | ❌ | ✅ (NotifiableBuilder) |

### En une phrase

> **Laravel Notifications envoie un message sur un canal défini. Laravel Notification orchestre l'envoi sur tous les canaux d'une entité, trace chaque tentative et permet la planification avancée.**

---

## Architecture en un coup d'œil

L'architecture du package repose sur plusieurs composants clés :

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
```

### Composants principaux

| Composant | Rôle |
|-----------|------|
| `NotificationService` | Service principal, point d'entrée de l'API |
| `NotifiableBuilder` | Builder fluide pour envoyer des notifications sans entité |
| `NotificationSenderProcessor` | Orchestre l'envoi : résolution des routes, filtres, limites |
| `SendOptions` | Configuration fluide des options d'envoi (canaux, limites, filtres) |
| `NotificationRouteVO` | Value Object définissant un canal + destination + métadonnées |
| `AbstractDriver` | Classe de base pour les drivers d'envoi (Mail, SMS, Slack, etc.) |
| `AbstractChannel` | Classe de base pour les canaux de notification |
| `SendDelayedNotificationTask` | Tâche unique pour les envois différés/planifiés |
| `SendRecurringNotificationTask` | Tâche récurrente pour les envois périodiques |

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
use AndyDefer\LaravelNotification\Channels\SlackChannel;
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
        
        // ✅ Slack (ex: pour les admins)
        if ($this->is_admin) {
            $collection->add(new NotificationRouteVO(
                channelClass: SlackChannel::class,
                destination: '#admin-notifications',
                metadata: new StrictDataObject(['webhook_url' => env('SLACK_ADMIN_WEBHOOK')])
            ));
        }
        
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

Le `NotificationRouteVO` est le Value Object qui définit une route de notification :

```php
new NotificationRouteVO(
    channelClass: MailChannel::class,      // Le canal à utiliser
    destination: 'user@example.com',       // La destination (email, téléphone, etc.)
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
use AndyDefer\LaravelNotification\Channels\SmsChannel;

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
            'channels' => [MailChannel::class, SmsChannel::class],
            'limit_per_channel' => 1, // Un seul email, un seul SMS
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
- L'email est envoyé à l'adresse primaire (limit_per_channel = 1)
- Le SMS est envoyé au numéro primaire
- Les deux notifications sont persistées en base de données
- Le statut de chaque envoi est enregistré (SENT ou FAILED)
- Une session ID est générée pour tracer le lot d'envois

---

### Envoi différé

```php
<?php

use AndyDefer\LaravelNotification\Records\SendLaterRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationDateTimeVO;

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
            'interval_seconds' => 604800, // 7 jours
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

**Résultat :**
- Une tâche `SendRecurringNotificationTask` est créée
- Elle s'exécute toutes les semaines à 9h
- Elle s'arrête automatiquement le 31 décembre 2026
- En cas d'échec, elle fait jusqu'à 3 tentatives

---

## Filtrage des destinations avec SendOptions

Le package introduit `SendOptions` pour un contrôle précis des destinations par canal. Cette approche fluide permet de filtrer dynamiquement les destinations sans modifier les records.

### Utilisation de base

```php
<?php

use AndyDefer\LaravelNotification\Options\SendOptions;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;

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
<?php

// ✅ Envoyer à plusieurs emails spécifiques
$options = SendOptions::init()
    ->withChannel(MailChannel::class)
    ->withDestinationFilter(MailChannel::class, [
        'user@example.com',
        'admin@example.com',
        'support@example.com',
    ]);

$results = $notificationService
    ->withOptions($options)
    ->sendNow($user, $message);
```

### Filtres sur plusieurs canaux

```php
<?php

// ✅ Email uniquement à l'email pro, SMS uniquement au téléphone pro
$options = SendOptions::init()
    ->withChannels([MailChannel::class, SmsChannel::class])
    ->withDestinationFilter(MailChannel::class, 'pro@example.com')
    ->withDestinationFilter(SmsChannel::class, '+33123456789');

$results = $notificationService
    ->withOptions($options)
    ->sendNow($user, $message);
```

---

## NotifiableBuilder - Envoi sans entité

Le `NotifiableBuilder` est un builder fluide qui permet d'envoyer des notifications **sans avoir à implémenter l'interface `NotifiableInterface`**. C'est idéal pour les cas où vous voulez envoyer directement à une adresse email, un numéro de téléphone, ou toute autre destination, sans passer par une entité.

### Utilisation de base

```php
<?php

use AndyDefer\LaravelNotification\Builders\NotifiableBuilder;
use AndyDefer\LaravelNotification\Channels\MailChannel;

$results = NotifiableBuilder::create()
    ->to(MailChannel::class, 'user@example.com')
    ->subject('Bienvenue')
    ->body('<h1>Bienvenue sur notre plateforme</h1>')
    ->sendNow();

if ($results->allSuccess()) {
    echo "✅ Email envoyé avec succès";
}
```

### Envoi multi-canaux

```php
<?php

use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;

$results = NotifiableBuilder::create()
    ->to(MailChannel::class, 'user@example.com')
    ->to(SmsChannel::class, '+33123456789')
    ->subject('Notification importante')
    ->body('Votre commande a été expédiée.')
    ->data(['order_id' => 12345])
    ->sendNow();
```

### Envoi à plusieurs destinations sur le même canal

```php
<?php

$results = NotifiableBuilder::create()
    ->to(MailChannel::class, [
        'user1@example.com',
        'user2@example.com',
        'user3@example.com',
    ])
    ->subject('Newsletter')
    ->body('Contenu de la newsletter')
    ->limit(3)  // Limite à 3 destinataires
    ->sendNow();
```

### Envoi différé avec le builder

```php
<?php

$alias = NotifiableBuilder::create()
    ->to(MailChannel::class, 'user@example.com')
    ->subject('Rappel')
    ->body('N\'oubliez pas votre rendez-vous demain.')
    ->sendLater(1800); // Dans 30 minutes
```

### Envoi récurrent avec le builder

```php
<?php

use AndyDefer\LaravelNotification\ValueObjects\NotificationDateTimeVO;

$alias = NotifiableBuilder::create()
    ->to(MailChannel::class, 'user@example.com')
    ->subject('Newsletter hebdomadaire')
    ->body('Voici les dernières actualités...')
    ->limit(1)
    ->sendRecurring(
        604800, // 7 jours
        new NotificationDateTimeVO(now()->startOfWeek()->toIso8601String()),
        new NotificationDateTimeVO(now()->addWeeks(4)->toIso8601String())
    );
```

### Avec filtres et métadonnées

```php
<?php

use AndyDefer\DomainStructures\Utils\StrictDataObject;

$results = NotifiableBuilder::create()
    ->to(MailChannel::class, ['user@example.com', 'admin@example.com'])
    ->subject('Offre spéciale')
    ->body('Profitez de notre offre exclusive.')
    ->filter(MailChannel::class, 'user@example.com')
    ->metadata(MailChannel::class, new StrictDataObject([
        'priority' => 'high',
        'name' => 'John Doe',
    ]))
    ->limit(1)
    ->sendNow();
```

### Avec traçage

```php
<?php

$results = NotifiableBuilder::create()
    ->to(MailChannel::class, 'user@example.com')
    ->subject('Notification tracée')
    ->body('Cette notification est tracée.')
    ->as('external_user', 12345)  // Définit la classe morph et la clé
    ->sendNow();
```

### API du NotifiableBuilder

| Méthode | Description | Retour |
|---------|-------------|--------|
| `static create(?NotificationService $service): self` | Crée une nouvelle instance | `self` |
| `to(string $channelClass, string|array $destination): self` | Définit la destination pour un canal | `self` |
| `body(string $body): self` | Définit le corps du message | `self` |
| `subject(string $subject): self` | Définit le sujet du message | `self` |
| `type(string $type): self` | Définit le type du message | `self` |
| `data(array $data): self` | Définit les données supplémentaires | `self` |
| `limit(int $limit): self` | Définit la limite par canal | `self` |
| `filter(string $channelClass, string|array $destinations): self` | Ajoute un filtre de destination | `self` |
| `filters(array $filters): self` | Remplace tous les filtres | `self` |
| `options(SendOptions $options): self` | Définit les options d'envoi | `self` |
| `metadata(string $channelClass, StrictDataObject $metadata): self` | Ajoute des métadonnées | `self` |
| `metadataAll(StrictDataObject $metadata): self` | Ajoute des métadonnées à tous les canaux | `self` |
| `as(string $morphClass, int|string $key): self` | Définit la classe morph et la clé | `self` |
| `sendNow(?SendNowRecord $record): SendResultCollection` | Envoi immédiat | `SendResultCollection` |
| `sendLater(int $delaySeconds): TaskAliasVO` | Envoi différé | `TaskAliasVO` |
| `sendAt(NotificationDateTimeVO $scheduledAt): TaskAliasVO` | Envoi planifié | `TaskAliasVO` |
| `sendRecurring(int $intervalSeconds, NotificationDateTimeVO $startAt, ?NotificationDateTimeVO $endAt): TaskAliasVO` | Envoi récurrent | `TaskAliasVO` |
| `reset(): self` | Réinitialise le builder | `self` |

---

## Gestion des tâches

Le `NotificationService` expose une API complète pour gérer les tâches de notification.

### Pause, reprise, annulation

```php
<?php

namespace App\Services;

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\Task\ValueObjects\TaskAliasVO;

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

    // ✅ Vérifier l'existence d'une tâche
    public function exists(string $alias): bool
    {
        $taskAlias = new TaskAliasVO($alias);
        
        return $this->uniqueTaskService->exists($taskAlias)
            || $this->recurringTaskService->exists($taskAlias);
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

    public function cancelCampaign(string $alias)
    {
        if ($this->service->cancel($alias)) {
            return response()->json(['message' => 'Campagne annulée']);
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
use AndyDefer\LaravelNotification\ValueObjects\NotificationStatsVO;

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

### NotificationStatsVO

Le `NotificationStatsVO` propose plusieurs méthodes utiles :

```php
$stats = $service->getStats($user);

// Taux de succès
$successRate = $stats->success_rate;           // 75.5

// Pourcentages
$percentageSent = $stats->getPercentageSent();     // 60.0%
$percentageFailed = $stats->getPercentageFailed(); // 40.0%

// Vérifications
if ($stats->isSuccess()) {
    echo "✅ Toutes les notifications ont réussi";
}

if ($stats->hasFailures()) {
    echo "⚠️ Des échecs ont été détectés";
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

### 4. Utiliser le canal personnalisé

```php
<?php

use App\Notifications\Channels\DiscordChannel;

class OrderController extends Controller
{
    public function notifyAdmin(Order $order)
    {
        $user = User::find(1); // Admin
        
        $message = new NotificationMessageVO(
            body: new MessageBodyVO("La commande #{$order->id} a été passée."),
            subject: new MessageSubjectVO('Nouvelle commande !'),
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

// ✅ Envoi avec filtrage
$options = SendOptions::init()
    ->withChannels([MailChannel::class, SmsChannel::class])
    ->withDestinationFilter(MailChannel::class, $doctor->email_professional)
    ->withDestinationFilter(SmsChannel::class, $doctor->phone)
    ->withLimitPerChannel(1);
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

### 3. Envoi direct avec NotifiableBuilder (SaaS)

```php
<?php

use AndyDefer\LaravelNotification\Builders\NotifiableBuilder;
use AndyDefer\LaravelNotification\Channels\MailChannel;

class InvitationService
{
    public function sendInvitation(string $email, string $name): void
    {
        $results = NotifiableBuilder::create()
            ->to(MailChannel::class, $email)
            ->subject('Vous êtes invité !')
            ->body("<h1>Bonjour {$name}</h1><p>Rejoignez notre plateforme.</p>")
            ->metadata(MailChannel::class, new StrictDataObject([
                'recipient_name' => $name,
                'type' => 'invitation',
            ]))
            ->sendNow();

        if (!$results->allSuccess()) {
            Log::error('Échec de l\'envoi de l\'invitation', [
                'email' => $email,
                'errors' => $results->getFailures()->toArray(),
            ]);
        }
    }

    public function sendBulkInvitations(array $emails, string $message): void
    {
        $results = NotifiableBuilder::create()
            ->to(MailChannel::class, $emails)
            ->subject('Invitation collective')
            ->body($message)
            ->limit(count($emails))
            ->sendNow();

        echo "✅ " . $results->getSuccessCount() . " invitations envoyées\n";
        echo "❌ " . $results->getFailureCount() . " échecs\n";
    }
}
```

### 4. Notifications d'urgence avec escalade

```php
// ✅ Système d'escalade : tenter 3 fois avec délai croissant
$attempts = [
    ['delay' => 300, 'channel' => SmsChannel::class, 'destination' => $user->phone],
    ['delay' => 600, 'channel' => WhatsAppChannel::class, 'destination' => $user->phone],
    ['delay' => 900, 'channel' => MailChannel::class, 'destination' => $user->email_professional],
];

foreach ($attempts as $attempt) {
    $options = SendOptions::init()
        ->withChannel($attempt['channel'])
        ->withDestinationFilter($attempt['channel'], $attempt['destination'])
        ->withLimitPerChannel(1);

    $record = SendLaterRecord::from([
        'delay_seconds' => $attempt['delay'],
        'channels' => [$attempt['channel']],
        'limit_per_channel' => 1,
    ]);
    
    $service
        ->withOptions($options)
        ->sendLater($user, $message, $record);
}
// → SMS dans 5 min, WhatsApp dans 10 min, Email dans 15 min
```

### 5. Audit et conformité

```php
// ✅ Récupération de toutes les notifications d'un utilisateur
$stats = $service->getStats($user);

// ✅ Vérification de conformité
if ($stats->failed > 0) {
    Log::warning('Des notifications ont échoué pour l\'utilisateur ' . $user->id);
}

// ✅ Rapport mensuel avec analyse
$users = User::where('created_at', '>=', now()->subMonth())->get();
$report = [];
foreach ($users as $user) {
    $stats = $service->getStats($user);
    $report[$user->id] = [
        'total' => $stats->total,
        'success_rate' => $stats->success_rate,
        'sent' => $stats->sent,
        'failed' => $stats->failed,
        'pending' => $stats->pending,
        'has_failures' => $stats->hasFailures(),
    ];
}

// ✅ Exporter le rapport
Storage::put('reports/notification_report_' . now()->format('Y-m') . '.json', json_encode($report));
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

### ✅ Utiliser NotifiableBuilder pour les envois directs

```php
// ✅ BON - Envoi direct sans entité
$results = NotifiableBuilder::create()
    ->to(MailChannel::class, 'user@example.com')
    ->subject('Test')
    ->body('Contenu')
    ->sendNow();

// ❌ ÉVITER - Créer une entité fictive
class FakeUser extends Model implements NotifiableInterface { ... }
$fakeUser = new FakeUser();
$service->sendNow($fakeUser, $message);
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
$options = SendOptions::init()
    ->withChannels([MailChannel::class, SmsChannel::class])
    ->withLimitPerChannel(1);

// ✅ Pour les notifications critiques, tout envoyer
$options = SendOptions::init()
    ->withChannels([]) // Tous
    ->withLimitPerChannel(null); // Pas de limite
```

### ✅ Utiliser les filtres de destination pour le contrôle précis

```php
// ✅ Envoyer uniquement à des emails spécifiques
$options = SendOptions::init()
    ->withChannel(MailChannel::class)
    ->withDestinationFilter(MailChannel::class, [
        $user->email_primary,
        $user->email_secondary,
    ]);

// ✅ Filtres multiples par canal
$options = SendOptions::init()
    ->withChannels([MailChannel::class, SmsChannel::class])
    ->withDestinationFilter(MailChannel::class, $user->email_professional)
    ->withDestinationFilter(SmsChannel::class, $user->phone_professional);
```

### ✅ Auto-reset des options

Les options sont automatiquement réinitialisées après chaque envoi :

```php
// ✅ Premier envoi avec options
$service->withOptions($options)->sendNow($user, $message);

// ✅ Second envoi sans options (utilise les canaux par défaut)
$service->sendNow($user, $message);
```

---

## Référence de l'API

### NotificationService

| Méthode | Description | Retour |
|---------|-------------|--------|
| `withOptions(SendOptions $options): self` | Définit les options pour le prochain envoi | `self` |
| `resetOptions(): self` | Réinitialise les options en attente | `self` |
| `sendNow(NotifiableInterface, NotificationMessageVO, ?SendNowRecord): SendResultCollection` | Envoi immédiat | `SendResultCollection` |
| `sendLater(NotifiableInterface, NotificationMessageVO, ?SendLaterRecord): TaskAliasVO` | Envoi différé | `TaskAliasVO` |
| `sendAt(NotifiableInterface, NotificationMessageVO, ?SendAtRecord): TaskAliasVO` | Envoi planifié | `TaskAliasVO` |
| `sendRecurring(NotifiableInterface, NotificationMessageVO, ?SendRecurringRecord): TaskAliasVO` | Envoi récurrent | `TaskAliasVO` |
| `cancel(string $signature): bool` | Annuler une tâche | `bool` |
| `pause(string $signature): bool` | Mettre en pause | `bool` |
| `resume(string $signature): bool` | Reprendre | `bool` |
| `changeInterval(string $signature, int $newIntervalSeconds): bool` | Modifier l'intervalle | `bool` |
| `getStats(NotifiableInterface&Model $notifiable): NotificationStatsVO` | Statistiques globales | `NotificationStatsVO` |
| `getSessionStats(string $sessionId): SessionStatsRecord` | Statistiques d'une session | `SessionStatsRecord` |

### NotifiableBuilder

| Méthode | Description | Retour |
|---------|-------------|--------|
| `static create(?NotificationService $service): self` | Crée une nouvelle instance | `self` |
| `to(string $channelClass, string|array $destination): self` | Définit la destination pour un canal | `self` |
| `body(string $body): self` | Définit le corps du message | `self` |
| `subject(string $subject): self` | Définit le sujet du message | `self` |
| `type(string $type): self` | Définit le type du message | `self` |
| `data(array $data): self` | Définit les données supplémentaires | `self` |
| `limit(int $limit): self` | Définit la limite par canal | `self` |
| `filter(string $channelClass, string|array $destinations): self` | Ajoute un filtre de destination | `self` |
| `filters(array $filters): self` | Remplace tous les filtres | `self` |
| `options(SendOptions $options): self` | Définit les options d'envoi | `self` |
| `metadata(string $channelClass, StrictDataObject $metadata): self` | Ajoute des métadonnées | `self` |
| `metadataAll(StrictDataObject $metadata): self` | Ajoute des métadonnées à tous les canaux | `self` |
| `as(string $morphClass, int|string $key): self` | Définit la classe morph et la clé | `self` |
| `sendNow(?SendNowRecord $record): SendResultCollection` | Envoi immédiat | `SendResultCollection` |
| `sendLater(int $delaySeconds): TaskAliasVO` | Envoi différé | `TaskAliasVO` |
| `sendAt(NotificationDateTimeVO $scheduledAt): TaskAliasVO` | Envoi planifié | `TaskAliasVO` |
| `sendRecurring(int $intervalSeconds, NotificationDateTimeVO $startAt, ?NotificationDateTimeVO $endAt): TaskAliasVO` | Envoi récurrent | `TaskAliasVO` |
| `reset(): self` | Réinitialise le builder | `self` |

### SendOptions

| Méthode | Description | Retour |
|---------|-------------|--------|
| `static init(): self` | Crée une nouvelle instance | `self` |
| `withChannel(string $channelClass): self` | Ajoute un canal | `self` |
| `withChannels(array $channelClasses): self` | Ajoute plusieurs canaux | `self` |
| `withLimitPerChannel(int $limit): self` | Définit la limite par canal | `self` |
| `withDestinationFilter(string $channelClass, string|array $destinations): self` | Ajoute un filtre de destination | `self` |
| `withDestinationFilters(array $filters): self` | Remplace tous les filtres | `self` |
| `getDestinationFilters(): ?StrictAssociative` | Récupère les filtres | `?StrictAssociative` |

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
| `getPercentageSent(): float` | Pourcentage envoyé |
| `getPercentageFailed(): float` | Pourcentage échoué |
| `isSuccess(): bool` | Tout a réussi |
| `hasFailures(): bool` | Au moins un échec |

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