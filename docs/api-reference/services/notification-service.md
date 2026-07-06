# NotificationService - Référence Technique

## Description

Service central de gestion des notifications. Orchestre l'envoi immédiat, différé, planifié ou récurrent des notifications en s'appuyant sur le système de tâches (UniqueTask et RecurringTask).

## Hiérarchie / Implémentations

```
NotificationServiceInterface
    └── NotificationService (final)
```

## Rôle principal

**Orchestrateur des envois de notifications :**

1. **Envoi immédiat** (`sendNow`) - Envoi synchrone
2. **Envoi différé** (`sendLater`) - Planification dans X secondes
3. **Envoi planifié** (`sendAt`) - Planification à une date/heure spécifique
4. **Envoi récurrent** (`sendRecurring`) - Envoi périodique
5. **Gestion des tâches** - Pause, reprise, annulation, modification d'intervalle
6. **Statistiques** - Consultation des statistiques par notifiable ou session

---

## API / Méthodes publiques

### `sendNow(NotifiableInterface&Model $notifiable, NotificationMessageVO $message, SendNowRecord $record): SendResultCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface&Model` | L'entité à notifier |
| `$message` | `NotificationMessageVO` | Le message à envoyer |
| `$record` | `SendNowRecord` | Configuration d'envoi immédiat |

**Retourne :** `SendResultCollection` - Résultats de l'envoi

**Exceptions :** Aucune (déléguée au processor)

**Exemple :**
```php
$record = SendNowRecord::from([
    'channels' => [EmailChannel::class],
    'limit_per_channel' => 1,
]);

$results = $service->sendNow($user, $message, $record);
```

---

### `sendLater(NotifiableInterface&Model $notifiable, NotificationMessageVO $message, SendLaterRecord $record): TaskAliasVO`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface&Model` | L'entité à notifier |
| `$message` | `NotificationMessageVO` | Le message à envoyer |
| `$record` | `SendLaterRecord` | Configuration d'envoi différé |

**Retourne :** `TaskAliasVO` - Alias de la tâche créée

**Exceptions :** 
- `InvalidArgumentException` si `delay_seconds <= 0`

**Exemple :**
```php
$record = SendLaterRecord::from([
    'delay_seconds' => 300, // 5 minutes
    'channels' => [EmailChannel::class],
    'limit_per_channel' => 1,
]);

$alias = $service->sendLater($user, $message, $record);
```

---

### `sendAt(NotifiableInterface&Model $notifiable, NotificationMessageVO $message, SendAtRecord $record): TaskAliasVO`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface&Model` | L'entité à notifier |
| `$message` | `NotificationMessageVO` | Le message à envoyer |
| `$record` | `SendAtRecord` | Configuration d'envoi planifié |

**Retourne :** `TaskAliasVO` - Alias de la tâche créée

**Exceptions :** 
- `InvalidArgumentException` si la date est dans le passé

**Exemple :**
```php
$record = SendAtRecord::from([
    'scheduled_at' => new NotificationDateTimeVO('2026-07-07 10:00:00'),
    'channels' => [EmailChannel::class],
    'limit_per_channel' => 1,
]);

$alias = $service->sendAt($user, $message, $record);
```

---

### `sendRecurring(NotifiableInterface&Model $notifiable, NotificationMessageVO $message, SendRecurringRecord $record): TaskAliasVO`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface&Model` | L'entité à notifier |
| `$message` | `NotificationMessageVO` | Le message à envoyer |
| `$record` | `SendRecurringRecord` | Configuration d'envoi récurrent |

**Retourne :** `TaskAliasVO` - Alias de la tâche créée

**Exceptions :** 
- `InvalidArgumentException` si `interval_seconds < 1`

**Exemple :**
```php
$record = SendRecurringRecord::from([
    'interval_seconds' => 86400, // Tous les jours
    'start_at' => new NotificationDateTimeVO('2026-07-06 08:00:00'),
    'end_at' => new NotificationDateTimeVO('2026-07-31 23:59:59'),
    'channels' => [EmailChannel::class],
    'limit_per_channel' => 1,
]);

$alias = $service->sendRecurring($user, $message, $record);
```

---

### `cancel(string $signature): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la tâche à annuler |

**Retourne :** `bool` - `true` si annulée, `false` si non trouvée

**Exceptions :** Aucune (les exceptions sont capturées et loggées)

**Exemple :**
```php
$service->cancel('unique@abc-123');
```

---

### `pause(string $signature): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la tâche récurrente à mettre en pause |

**Retourne :** `bool` - `true` si mise en pause, `false` si non trouvée

**Exceptions :** Aucune

**Exemple :**
```php
$service->pause('recurring@def-456');
```

---

### `resume(string $signature): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la tâche récurrente à reprendre |

**Retourne :** `bool` - `true` si reprise, `false` si non trouvée

**Exceptions :** Aucune

**Exemple :**
```php
$service->resume('recurring@def-456');
```

---

### `changeInterval(string $signature, int $newIntervalSeconds): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la tâche récurrente |
| `$newIntervalSeconds` | `int` | Nouvel intervalle en secondes |

**Retourne :** `bool` - `true` si modifié, `false` si non trouvée

**Exceptions :** 
- `InvalidArgumentException` si `newIntervalSeconds < 1`

**Exemple :**
```php
$service->changeInterval('recurring@def-456', 3600); // Toutes les heures
```

---

### `getStats(NotifiableInterface&Model $notifiable): NotificationStatsVO`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface&Model` | L'entité à notifier |

**Retourne :** `NotificationStatsVO` - Statistiques des notifications

**Exceptions :** Aucune

**Exemple :**
```php
$stats = $service->getStats($user);
echo "Taux de succès : " . $stats->success_rate . "%";
```

---

### `getSessionStats(string $sessionId): SessionStatsRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$sessionId` | `string` | ID de la session |

**Retourne :** `SessionStatsRecord` - Statistiques de la session

**Exceptions :** Aucune

**Exemple :**
```php
$sessionStats = $service->getSessionStats('session-123');
echo "En attente : " . $sessionStats->pending;
```

---

## Cas d'utilisation

### Cas 1 : Envoi immédiat après action utilisateur

```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;

// Inscription d'un nouvel utilisateur
public function register(Request $request)
{
    $user = User::create($request->validated());
    
    // Envoi immédiat d'un email de bienvenue
    $message = new NotificationMessageVO(
        subject: 'Bienvenue !',
        content: '<h1>Bonjour ' . $user->name . ' !</h1>...'
    );
    
    $record = SendNowRecord::from([
        'channels' => [EmailChannel::class],
        'limit_per_channel' => 1,
    ]);
    
    $service = app(NotificationService::class);
    $results = $service->sendNow($user, $message, $record);
    
    return response()->json(['user' => $user]);
}
```

---

### Cas 2 : Email de relance 30 minutes après abandon de panier

```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendLaterRecord;

public function abandonCart(Cart $cart)
{
    $user = $cart->user;
    
    $message = new NotificationMessageVO(
        subject: 'Votre panier vous attend !',
        content: '<p>Vous avez des articles dans votre panier...</p>'
    );
    
    $record = SendLaterRecord::from([
        'delay_seconds' => 1800, // 30 minutes
        'channels' => [EmailChannel::class],
        'limit_per_channel' => 1,
    ]);
    
    $service = app(NotificationService::class);
    $alias = $service->sendLater($user, $message, $record);
    
    // Stocker l'alias pour annulation si le panier est validé
    $cart->notification_task = $alias->getValue();
    $cart->save();
}
```

---

### Cas 3 : Newsletter hebdomadaire sur 4 semaines

```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendRecurringRecord;

public function scheduleNewsletter(User $user)
{
    $message = new NotificationMessageVO(
        subject: 'Votre newsletter hebdomadaire',
        content: '<p>Voici les dernières actualités...</p>'
    );
    
    $record = SendRecurringRecord::from([
        'interval_seconds' => 604800, // 7 jours
        'start_at' => new NotificationDateTimeVO('2026-07-08 09:00:00'),
        'end_at' => new NotificationDateTimeVO('2026-07-29 09:00:00'), // 4 semaines
        'channels' => [EmailChannel::class],
        'limit_per_channel' => 1,
    ]);
    
    $service = app(NotificationService::class);
    $alias = $service->sendRecurring($user, $message, $record);
    
    return $alias;
}
```

---

### Cas 4 : Gestion des notifications récurrentes

```php
<?php

// Admin : Pause des notifications
public function pauseNewsletter(string $alias)
{
    $service = app(NotificationService::class);
    
    if ($service->pause($alias)) {
        return response()->json(['message' => 'Newsletter mise en pause']);
    }
    
    return response()->json(['error' => 'Tâche non trouvée'], 404);
}

// Admin : Reprise
public function resumeNewsletter(string $alias)
{
    $service = app(NotificationService::class);
    
    if ($service->resume($alias)) {
        return response()->json(['message' => 'Newsletter reprise']);
    }
    
    return response()->json(['error' => 'Tâche non trouvée'], 404);
}

// Admin : Modification de la fréquence
public function changeNewsletterFrequency(string $alias, int $days)
{
    $service = app(NotificationService::class);
    $intervalSeconds = $days * 86400;
    
    if ($service->changeInterval($alias, $intervalSeconds)) {
        return response()->json(['message' => "Fréquence modifiée à {$days} jours"]);
    }
    
    return response()->json(['error' => 'Tâche non trouvée'], 404);
}
```

---

### Cas 5 : Statistiques des notifications

```php
<?php

public function showStats(User $user)
{
    $service = app(NotificationService::class);
    $stats = $service->getStats($user);
    
    return response()->json([
        'total' => $stats->total,
        'sent' => $stats->sent,
        'failed' => $stats->failed,
        'delivered' => $stats->delivered,
        'pending' => $stats->pending,
        'success_rate' => $stats->success_rate . '%',
    ]);
}
```

---

## Flux d'exécution

### Envoi immédiat

```
sendNow(Notifiable, Message, Record)
    ↓
Création de ProcessNotificationRecord
    ↓
Log 'Sending notification immediately'
    ↓
senderProcessor->send()
    ↓
SendResultCollection
```

### Envoi différé / planifié

```
sendLater/sendAt(Notifiable, Message, Record)
    ↓
Validation (delay > 0 ou date future)
    ↓
Création du payload
    ↓
Création de la configuration UniqueTask
    ↓
uniqueTaskService->register()
    ↓
TaskAliasVO
```

### Envoi récurrent

```
sendRecurring(Notifiable, Message, Record)
    ↓
Validation (interval >= 1)
    ↓
Création du payload
    ↓
Création de la configuration RecurringTask
    ↓
recurringTaskService->register()
    ↓
TaskAliasVO
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Delay <= 0 | `InvalidArgumentException` | `Delay seconds must be greater than 0.` |
| Date dans le passé | `InvalidArgumentException` | `Scheduled date must be in the future.` |
| Interval < 1 | `InvalidArgumentException` | `Interval seconds must be at least 1 second.` |
| Tâche non trouvée | `false` (retourné, non exception) | - |

---

## Intégration

### Avec le service provider

```php
<?php

namespace App\Providers;

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Contracts\Services\NotificationServiceInterface;
use Illuminate\Support\ServiceProvider;

final class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            NotificationServiceInterface::class,
            function ($app) {
                return new NotificationService(
                    $app->make(NotificationRepositoryInterface::class),
                    $app->make(NotificationSenderProcessorInterface::class),
                    $app->make(UniqueTaskServiceInterface::class),
                    $app->make(RecurringTaskServiceInterface::class),
                    $app->make(LoggerInterface::class),
                    $app->make(HydrationService::class),
                );
            }
        );
    }
}
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `sendNow()` | O(n) | n = nombre de routes |
| `sendLater()` | O(1) | + insertion base de données |
| `sendAt()` | O(1) | + insertion base de données |
| `sendRecurring()` | O(1) | + insertion base de données |
| `cancel()` | O(1) | Recherche + mise à jour |
| `pause()` | O(1) | Mise à jour statut |
| `resume()` | O(1) | Mise à jour statut |
| `changeInterval()` | O(1) | Mise à jour intervalle |
| `getStats()` | O(1) | 4 requêtes count |
| `getSessionStats()` | O(1) | 3 requêtes count |

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| Laravel 10.x | ✅ Complet |
| Laravel 11.x | ✅ Complet |
| Laravel 12.x | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\Records\SendLaterRecord;
use AndyDefer\LaravelNotification\Records\SendRecurringRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationDateTimeVO;
use App\Models\User;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\SmsChannel;

$service = app(NotificationService::class);
$user = User::find(1);

// 1. Envoi immédiat
$message = new NotificationMessageVO(
    subject: 'Bienvenue !',
    content: '<h1>Bonjour !</h1><p>Bienvenue sur notre plateforme.</p>'
);

$nowRecord = SendNowRecord::from([
    'channels' => [EmailChannel::class, SmsChannel::class],
    'limit_per_channel' => 1,
]);

$results = $service->sendNow($user, $message, $nowRecord);

// 2. Envoi différé (15 minutes)
$laterRecord = SendLaterRecord::from([
    'delay_seconds' => 900,
    'channels' => [EmailChannel::class],
    'limit_per_channel' => 1,
]);

$alias = $service->sendLater($user, $message, $laterRecord);

// 3. Envoi récurrent (hebdomadaire)
$recurringRecord = SendRecurringRecord::from([
    'interval_seconds' => 604800,
    'start_at' => new NotificationDateTimeVO('2026-07-08 09:00:00'),
    'end_at' => new NotificationDateTimeVO('2026-07-29 09:00:00'),
    'channels' => [EmailChannel::class],
    'limit_per_channel' => 1,
]);

$recurringAlias = $service->sendRecurring($user, $message, $recurringRecord);

// 4. Gestion
$service->pause($recurringAlias->getValue());
$service->resume($recurringAlias->getValue());
$service->changeInterval($recurringAlias->getValue(), 86400); // Tous les jours
$service->cancel($alias->getValue());

// 5. Statistiques
$stats = $service->getStats($user);

echo "📊 Statistiques des notifications\n";
echo "Total : " . $stats->total . "\n";
echo "✅ Envoyées : " . $stats->sent . "\n";
echo "❌ Échouées : " . $stats->failed . "\n";
echo "📬 Délivrées : " . $stats->delivered . "\n";
echo "⏳ En attente : " . $stats->pending . "\n";
echo "📈 Taux de succès : " . $stats->success_rate . "%\n";
```