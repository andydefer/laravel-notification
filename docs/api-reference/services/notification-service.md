# NotificationService - Référence Technique

## Description

`NotificationService` est le service principal du package Laravel Notification. Il orchestre l'envoi de notifications immédiates, différées, planifiées ou récurrentes, en utilisant le système de tâches pour les envois asynchrones.

## Hiérarchie / Implémentations

```
NotificationService (final)
    └── NotificationServiceInterface
```

**Interfaces implémentées :**
- `NotificationServiceInterface` - Contrat définissant toutes les opérations de notification

## Rôle principal

Ce service agit comme le point d'entrée unique pour toutes les opérations de notification :

1. **Envoi immédiat** - `sendNow()` - Envoi synchrone instantané
2. **Envoi différé** - `sendLater()` - Envoi après un délai configurable
3. **Envoi planifié** - `sendAt()` - Envoi à une date/heure précise
4. **Envoi récurrent** - `sendRecurring()` - Envoi à intervalles réguliers
5. **Gestion des tâches** - `cancel()`, `pause()`, `resume()`, `changeInterval()`
6. **Statistiques** - `getStats()`, `getSessionStats()`
7. **Configuration fluide** - `withOptions()` pour définir des options temporaires

## API / Méthodes publiques

### `withOptions(SendOptions $options): self`

Définit les options pour le prochain envoi.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$options` | `SendOptions` | Options de configuration |

**Retourne :** `self` - Instance du service pour le chaînage

**Exemple :**
```php
$results = $notificationService
    ->withOptions($options)
    ->sendNow($user, $message);
```

---

### `resetOptions(): self`

Réinitialise les options en attente.

**Retourne :** `self` - Instance du service pour le chaînage

**Exemple :**
```php
$notificationService->resetOptions();
```

---

### `sendNow(NotifiableInterface&Model $notifiable, NotificationMessageVO $message, ?SendNowRecord $record = null): SendResultCollection`

Envoie une notification immédiatement (mode synchrone).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface&Model` | Entité recevant la notification |
| `$message` | `NotificationMessageVO` | Message à envoyer |
| `$record` | `SendNowRecord|null` | Configuration d'envoi (facultatif) |

**Retourne :** `SendResultCollection` - Collection des résultats d'envoi

**Exceptions :** 
- `RuntimeException` si aucun canal disponible
- `RuntimeException` si les filtres ne correspondent à aucune destination

**Exemple :**
```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;

$service = app(NotificationService::class);

$message = new NotificationMessageVO(
    body: new MessageBodyVO('Bienvenue sur notre plateforme'),
    subject: new MessageSubjectVO('Bienvenue'),
);

$record = new SendNowRecord;

$results = $service->sendNow($user, $message, $record);

echo "✅ Envoyé : " . $results->getSuccessCount() . "\n";
echo "❌ Échecs : " . $results->getFailureCount() . "\n";
```

---

### `sendLater(NotifiableInterface&Model $notifiable, NotificationMessageVO $message, ?SendLaterRecord $record = null): TaskAliasVO`

Planifie un envoi différé après un délai.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface&Model` | Entité recevant la notification |
| `$message` | `NotificationMessageVO` | Message à envoyer |
| `$record` | `SendLaterRecord|null` | Configuration d'envoi (facultatif) |

**Retourne :** `TaskAliasVO` - Alias de la tâche créée

**Exceptions :** 
- `InvalidArgumentException` si `delay_seconds <= 0`

**Exemple :**
```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendLaterRecord;

$service = app(NotificationService::class);

$record = new SendLaterRecord(delay_seconds: 300); // 5 minutes

$alias = $service->sendLater($user, $message, $record);

echo "📅 Notification planifiée dans 5 minutes\n";
echo "🔑 Alias : " . $alias->getValue() . "\n";
```

---

### `sendAt(NotifiableInterface&Model $notifiable, NotificationMessageVO $message, ?SendAtRecord $record = null): TaskAliasVO`

Planifie un envoi à une date/heure précise.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface&Model` | Entité recevant la notification |
| `$message` | `NotificationMessageVO` | Message à envoyer |
| `$record` | `SendAtRecord|null` | Configuration d'envoi (facultatif) |

**Retourne :** `TaskAliasVO` - Alias de la tâche créée

**Exceptions :** 
- `InvalidArgumentException` si `scheduled_at` est dans le passé

**Exemple :**
```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendAtRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationDateTimeVO;

$service = app(NotificationService::class);

$scheduledAt = new NotificationDateTimeVO(now()->addHours(2)->toIso8601String());

$record = new SendAtRecord(scheduled_at: $scheduledAt);

$alias = $service->sendAt($user, $message, $record);

echo "📅 Notification planifiée à " . $scheduledAt->forDisplay() . "\n";
```

---

### `sendRecurring(NotifiableInterface&Model $notifiable, NotificationMessageVO $message, ?SendRecurringRecord $record = null): TaskAliasVO`

Crée une tâche récurrente pour envoyer des notifications à intervalles réguliers.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface&Model` | Entité recevant la notification |
| `$message` | `NotificationMessageVO` | Message à envoyer |
| `$record` | `SendRecurringRecord|null` | Configuration d'envoi (facultatif) |

**Retourne :** `TaskAliasVO` - Alias de la tâche créée

**Exceptions :** 
- `InvalidArgumentException` si `interval_seconds < 1`

**Exemple :**
```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendRecurringRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationDateTimeVO;

$service = app(NotificationService::class);

$record = new SendRecurringRecord(
    interval_seconds: 86400, // 1 jour
    start_at: new NotificationDateTimeVO(now()->toIso8601String()),
);

$alias = $service->sendRecurring($user, $message, $record);

echo "🔄 Notification récurrente créée\n";
echo "🔑 Alias : " . $alias->getValue() . "\n";
```

---

### `cancel(string $signature): bool`

Annule une tâche de notification (unique ou récurrente).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Alias de la tâche à annuler |

**Retourne :** `bool` - `true` si la tâche a été annulée

**Exemple :**
```php
if ($service->cancel($alias)) {
    echo "✅ Tâche annulée\n";
}
```

---

### `pause(string $signature): bool`

Met en pause une tâche récurrente.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Alias de la tâche à mettre en pause |

**Retourne :** `bool` - `true` si la tâche a été mise en pause

**Exemple :**
```php
if ($service->pause($alias)) {
    echo "⏸️ Tâche mise en pause\n";
}
```

---

### `resume(string $signature): bool`

Reprend une tâche récurrente mise en pause.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Alias de la tâche à reprendre |

**Retourne :** `bool` - `true` si la tâche a été reprise

**Exemple :**
```php
if ($service->resume($alias)) {
    echo "▶️ Tâche reprise\n";
}
```

---

### `changeInterval(string $signature, int $newIntervalSeconds): bool`

Modifie l'intervalle d'une tâche récurrente.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Alias de la tâche |
| `$newIntervalSeconds` | `int` | Nouvel intervalle en secondes |

**Retourne :** `bool` - `true` si l'intervalle a été modifié

**Exceptions :** 
- `InvalidArgumentException` si `newIntervalSeconds < 1`

**Exemple :**
```php
if ($service->changeInterval($alias, 3600)) { // Toutes les heures
    echo "✅ Intervalle modifié\n";
}
```

---

### `getStats(NotifiableInterface&Model $notifiable): NotificationStatsVO`

Récupère les statistiques de notification pour une entité.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface&Model` | Entité cible |

**Retourne :** `NotificationStatsVO` - Statistiques agrégées

**Exemple :**
```php
$stats = $service->getStats($user);

echo "📊 Statistiques :\n";
echo "   Total : " . $stats->total . "\n";
echo "   Envoyées : " . $stats->sent . "\n";
echo "   Échouées : " . $stats->failed . "\n";
echo "   Taux de succès : " . $stats->success_rate . "%\n";
```

---

### `getSessionStats(string $sessionId): SessionStatsRecord`

Récupère les statistiques d'une session d'envoi.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$sessionId` | `string` | ID de la session |

**Retourne :** `SessionStatsRecord` - Statistiques de la session

**Exemple :**
```php
$sessionId = '550e8400-e29b-41d4-a716-446655440000';
$stats = $service->getSessionStats($sessionId);

echo "📊 Session : $sessionId\n";
echo "   Total : " . $stats->total . "\n";
echo "   Envoyées : " . $stats->sent . "\n";
echo "   Échouées : " . $stats->failed . "\n";
echo "   En attente : " . $stats->pending . "\n";
```

## Cas d'utilisation

### Cas 1 : Envoi de bienvenue avec options fluentes

```php
<?php

use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Options\SendOptions;
use AndyDefer\LaravelNotification\Services\NotificationService;

class UserController extends Controller
{
    public function register(Request $request)
    {
        $user = User::create($request->validated());

        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Bienvenue sur notre plateforme !'),
            subject: new MessageSubjectVO('Bienvenue'),
        );

        $options = SendOptions::init()
            ->withChannel(MailChannel::class)
            ->withDestinationFilter(MailChannel::class, $user->email);

        $results = $this->service
            ->withOptions($options)
            ->sendNow($user, $message);

        if ($results->allSuccess()) {
            return response()->json(['message' => 'Email de bienvenue envoyé']);
        }

        return response()->json(['message' => 'Erreur lors de l\'envoi'], 500);
    }
}
```

### Cas 2 : Rappel différé avec gestion de tâche

```php
<?php

use AndyDefer\LaravelNotification\Records\SendLaterRecord;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;

class AppointmentController extends Controller
{
    public function scheduleReminder(Appointment $appointment)
    {
        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Rappel : Rendez-vous dans 24h.'),
            subject: new MessageSubjectVO('Rappel de rendez-vous'),
        );

        $options = SendOptions::init()
            ->withChannels([MailChannel::class, SmsChannel::class])
            ->withDestinationFilter(MailChannel::class, $appointment->user->email)
            ->withDestinationFilter(SmsChannel::class, $appointment->user->phone)
            ->withLimitPerChannel(1);

        $record = new SendLaterRecord(delay_seconds: 86400); // 24h

        $alias = $this->service
            ->withOptions($options)
            ->sendLater($appointment->user, $message, $record);

        // ✅ Stocker l'alias pour annulation si le rendez-vous est annulé
        $appointment->notification_task = $alias->getValue();
        $appointment->save();

        return response()->json([
            'message' => 'Rappel planifié',
            'task_alias' => $alias->getValue(),
        ]);
    }

    public function cancelAppointment(Appointment $appointment)
    {
        // ✅ Annuler la notification
        if ($appointment->notification_task) {
            $this->service->cancel($appointment->notification_task);
        }

        $appointment->delete();

        return response()->json(['message' => 'Rendez-vous annulé']);
    }
}
```

### Cas 3 : Newsletter récurrente avec gestion

```php
<?php

use AndyDefer\LaravelNotification\Records\SendRecurringRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationDateTimeVO;
use AndyDefer\LaravelNotification\Channels\MailChannel;

class NewsletterController extends Controller
{
    public function subscribe(User $user)
    {
        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Voici votre newsletter hebdomadaire.'),
            subject: new MessageSubjectVO('Newsletter #' . now()->weekOfYear),
        );

        $options = SendOptions::init()
            ->withChannel(MailChannel::class)
            ->withDestinationFilter(MailChannel::class, $user->email);

        $record = new SendRecurringRecord(
            interval_seconds: 604800, // 7 jours
            start_at: new NotificationDateTimeVO(now()->startOfWeek()->toIso8601String()),
            end_at: new NotificationDateTimeVO(now()->addWeeks(4)->toIso8601String()),
        );

        $alias = $this->service
            ->withOptions($options)
            ->sendRecurring($user, $message, $record);

        $user->newsletter_task = $alias->getValue();
        $user->save();

        return response()->json([
            'message' => 'Newsletter hebdomadaire activée',
            'task_alias' => $alias->getValue(),
        ]);
    }

    public function unsubscribe(User $user)
    {
        if ($user->newsletter_task) {
            $this->service->pause($user->newsletter_task);
            $user->newsletter_task = null;
            $user->save();
        }

        return response()->json(['message' => 'Désabonnement effectué']);
    }
}
```

### Cas 4 : Monitoring des notifications

```php
<?php

class AdminController extends Controller
{
    public function dashboard()
    {
        $users = User::all();
        $globalStats = [
            'total' => 0,
            'sent' => 0,
            'failed' => 0,
            'users' => [],
        ];

        foreach ($users as $user) {
            $stats = $this->service->getStats($user);
            $globalStats['total'] += $stats->total;
            $globalStats['sent'] += $stats->sent;
            $globalStats['failed'] += $stats->failed;

            $globalStats['users'][] = [
                'user_id' => $user->id,
                'total' => $stats->total,
                'sent' => $stats->sent,
                'failed' => $stats->failed,
                'success_rate' => $stats->success_rate,
            ];
        }

        return view('admin.dashboard', $globalStats);
    }
}
```

## Flux d'exécution

```
withOptions($options)
    │
    └── $this->pendingOptions = $options
            │
            ▼
sendNow($notifiable, $message, $record)
    │
    ├── $record = $record ?? new SendNowRecord
    ├── $options = $pendingOptions ?? new SendOptions
    ├── $this->resetOptions()
    │
    ├── ProcessNotificationRecord
    │   ├── channels = $options->channels ?? $record->channels
    │   └── limit_per_channel = $options->limitPerChannel ?? $record->limit_per_channel
    │
    ├── logInfo()
    │
    └── $this->senderProcessor->send($notifiable, $message, $processRecord, $options->destinationFilters)
            │
            ├── Résolution des routes
            ├── Application des filtres
            ├── Application de la limite
            ├── Création des notifications
            ├── Dispatching vers les drivers
            └── Retourne SendResultCollection
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| `sendLater` avec delay ≤ 0 | `InvalidArgumentException` | `Delay seconds must be greater than 0.` |
| `sendAt` avec date passée | `InvalidArgumentException` | `Scheduled date must be in the future.` |
| `sendRecurring` avec interval < 1 | `InvalidArgumentException` | `Interval seconds must be at least 1 second.` |
| `changeInterval` avec interval < 1 | `InvalidArgumentException` | `Interval seconds must be at least 1 second.` |
| Aucun canal disponible | `RuntimeException` | `No available channels for notifiable {type}#{id}` |
| Filtre sans correspondance | `RuntimeException` | `No routes after applying destination filters...` |

## Performance

| Opération | Complexité | Description |
|-----------|-----------|-------------|
| `sendNow()` | O(n) | n = nombre de routes |
| `sendLater()` | O(1) | Création d'une tâche unique |
| `sendAt()` | O(1) | Création d'une tâche unique |
| `sendRecurring()` | O(1) | Création d'une tâche récurrente |
| `cancel()` | O(1) | Annulation d'une tâche |
| `pause()` / `resume()` | O(1) | Modification d'état |
| `getStats()` | O(1) | Requêtes COUNT |
| `getSessionStats()` | O(1) | Requêtes COUNT |

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| Laravel 12.x | ✅ Complet |
| Laravel 13.x | ✅ Complet |
| Laravel 14.x | ✅ Complet |
| Laravel 15.x | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\Options\SendOptions;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\Records\SendLaterRecord;
use AndyDefer\LaravelNotification\Records\SendRecurringRecord;
use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationDateTimeVO;

class OrderService
{
    public function __construct(
        private readonly NotificationService $service,
    ) {}

    public function processOrder(Order $order): void
    {
        $user = $order->user;

        // 1. ✅ Confirmation immédiate
        $this->sendConfirmation($user, $order);

        // 2. ✅ Rappel de livraison J-1
        $this->scheduleDeliveryReminder($user, $order);

        // 3. ✅ Newsletter post-achat
        $this->schedulePostPurchaseNewsletter($user, $order);
    }

    private function sendConfirmation(User $user, Order $order): void
    {
        $message = new NotificationMessageVO(
            body: new MessageBodyVO("Votre commande #{$order->id} a été confirmée."),
            subject: new MessageSubjectVO('Commande confirmée'),
        );

        $options = SendOptions::init()
            ->withChannels([MailChannel::class, SmsChannel::class])
            ->withDestinationFilter(MailChannel::class, $user->email)
            ->withDestinationFilter(SmsChannel::class, $user->phone)
            ->withLimitPerChannel(1);

        $results = $this->service
            ->withOptions($options)
            ->sendNow($user, $message);

        if (!$results->allSuccess()) {
            Log::warning('Confirmation d\'ordre partiellement échouée', [
                'order_id' => $order->id,
                'success' => $results->getSuccessCount(),
                'failed' => $results->getFailureCount(),
            ]);
        }
    }

    private function scheduleDeliveryReminder(User $user, Order $order): void
    {
        $message = new NotificationMessageVO(
            body: new MessageBodyVO("Votre commande #{$order->id} sera livrée demain."),
            subject: new MessageSubjectVO('Livraison prévue demain'),
        );

        $options = SendOptions::init()
            ->withChannel(MailChannel::class)
            ->withDestinationFilter(MailChannel::class, $user->email);

        $record = new SendLaterRecord(
            delay_seconds: 86400, // 24h
            channels: $options->channels,
            limit_per_channel: 1,
        );

        $alias = $this->service->sendLater($user, $message, $record);

        $order->reminder_task = $alias->getValue();
        $order->save();
    }

    private function schedulePostPurchaseNewsletter(User $user, Order $order): void
    {
        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Découvrez nos offres exclusives.'),
            subject: new MessageSubjectVO('Offres exclusives'),
        );

        $options = SendOptions::init()
            ->withChannel(MailChannel::class)
            ->withDestinationFilter(MailChannel::class, $user->email)
            ->withLimitPerChannel(1);

        $record = new SendRecurringRecord(
            interval_seconds: 604800, // 7 jours
            start_at: new NotificationDateTimeVO(now()->addWeek()->toIso8601String()),
            end_at: new NotificationDateTimeVO(now()->addWeeks(4)->toIso8601String()),
        );

        $alias = $this->service
            ->withOptions($options)
            ->sendRecurring($user, $message, $record);

        $user->newsletter_task = $alias->getValue();
        $user->save();
    }

    public function cancelOrder(Order $order): void
    {
        // ✅ Annuler les notifications planifiées
        if ($order->reminder_task) {
            $this->service->cancel($order->reminder_task);
        }

        if ($order->user->newsletter_task) {
            $this->service->pause($order->user->newsletter_task);
        }

        $order->delete();
    }
}
```

## Voir aussi
- `NotificationServiceInterface` - Interface du service
- `SendOptions` - Options de configuration
- `NotificationMessageVO` - Message de notification
- `SendNowRecord` - Record d'envoi immédiat
- `SendLaterRecord` - Record d'envoi différé
- `SendAtRecord` - Record d'envoi planifié
- `SendRecurringRecord` - Record d'envoi récurrent
- `NotificationSenderProcessor` - Processeur d'envoi
- `NotificationStatsVO` - Statistiques de notification
- `SessionStatsRecord` - Statistiques de session