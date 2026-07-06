# SendRecurringNotificationTask - Référence Technique

## Description

Tâche récurrente (`AbstractRecurringTask`) pour l'envoi périodique de notifications. S'exécute à intervalles réguliers pour envoyer des notifications à une entité notifiable.

## Hiérarchie / Implémentations

```
AbstractRecurringTask
    └── SendRecurringNotificationTask (final)
```

## Rôle principal

**Point d'entrée pour les notifications périodiques :**

1. **Validation** (`before()`) : Vérifie l'intégrité du payload et l'intervalle
2. **Exécution** (`process()`) : Récupère l'entité et envoie la notification
3. **Finalisation** (`after()`) : Loggue le résultat avec gestion d'erreur structurée

---

## API / Méthodes publiques

### `before(StrictDataObject $payload): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$payload` | `StrictDataObject` | Le payload de la tâche |

**Retourne :** `void`

**Exceptions :** 
- `InvalidArgumentException` si le payload est invalide
- `InvalidArgumentException` si l'intervalle est inférieur à 60 secondes

**Exemple :**
```php
protected function before(StrictDataObject $payload): void
{
    // Validation automatique via NotificationTaskPayloadRecord
    // Vérification supplémentaire : interval >= 60 secondes
}
```

---

### `process(): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `void`

**Exceptions :** `RuntimeException` si l'entité notifiable n'est pas trouvée

**Exemple :**
```php
protected function process(): void
{
    $payload = NotificationTaskPayloadRecord::from($this->context->getPayload());
    $notifiable = $this->findNotifiable($payload);
    // ... envoi de la notification
}
```

---

### `after(bool $success, ?DescriptionVO $error = null): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$success` | `bool` | Indique si la tâche a réussi |
| `$error` | `?DescriptionVO` | Description de l'erreur (si échec) |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
protected function after(bool $success, ?DescriptionVO $error = null): void
{
    // Log du succès ou de l'échec
    // En cas d'échec, log structuré avec LoggerInterface
}
```

---

## Cas d'utilisation

### Cas 1 : Newsletter hebdomadaire

```php
<?php

use AndyDefer\LaravelNotification\Tasks\SendRecurringNotificationTask;
use AndyDefer\LaravelNotification\Records\NotificationTaskPayloadRecord;
use AndyDefer\Task\ValueObjects\RecurringTaskFqcnVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\DurationVO;
use App\Models\User;
use App\Notifications\Channels\EmailChannel;

$payload = NotificationTaskPayloadRecord::from([
    'notifiable_type' => User::class,
    'notifiable_id' => 123,
    'body' => '<h1>Votre newsletter</h1><p>Les dernières actualités...</p>',
    'subject' => 'Newsletter hebdomadaire',
    'type' => 'newsletter',
    'data' => ['user_id' => 123, 'edition' => '2026-W27'],
    'channels' => [EmailChannel::class],
    'limit_per_channel' => 1,
]);

$config = RecurringTaskConfigRecord::from([
    'description' => 'Newsletter hebdomadaire pour user #123',
    'interval_seconds' => 604800, // 7 jours
    'start_at' => new Iso8601DateTimeVO('2026-07-08 09:00:00'),
    'end_at' => new Iso8601DateTimeVO('2026-07-29 09:00:00'),
    'max_attempts' => 3,
]);

$taskService = app(RecurringTaskServiceInterface::class);
$alias = $taskService->register(
    new RecurringTaskFqcnVO(SendRecurringNotificationTask::class),
    StrictDataObject::from($payload->toArray()),
    $config
);
```

---

### Cas 2 : Utilisation via NotificationService

```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendRecurringRecord;

$service = app(NotificationService::class);

$record = SendRecurringRecord::from([
    'interval_seconds' => 86400, // Tous les jours
    'start_at' => new NotificationDateTimeVO('2026-07-06 08:00:00'),
    'end_at' => new NotificationDateTimeVO('2026-07-31 23:59:59'),
    'channels' => [EmailChannel::class, SmsChannel::class],
    'limit_per_channel' => 1,
    'max_attempts' => 2,
]);

$message = new NotificationMessageVO(
    subject: 'Rappel quotidien',
    body: 'N\'oubliez pas de consulter vos notifications.'
);

$alias = $service->sendRecurring($user, $message, $record);
// La tâche SendRecurringNotificationTask est automatiquement planifiée
```

---

### Cas 3 : Gestion des tâches récurrentes

```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;

$service = app(NotificationService::class);
$alias = 'recurring@abc-123';

// Pause de la tâche
$service->pause($alias);

// Reprise
$service->resume($alias);

// Modification de l'intervalle (toutes les 12 heures)
$service->changeInterval($alias, 43200);

// Annulation définitive
$service->cancel($alias);
```

---

## Flux d'exécution

```
1. Tâche déclenchée par cron (intervalle défini)
    ↓
2. before() → Validation du payload + intervalle >= 60s
    ↓
3. process() → Récupération du payload
    ↓
4. findNotifiable() → Recherche de l'entité
    ↓
5. createNotificationMessage() → Construction du message
    ↓
6. createProcessRecord() → Construction du record
    ↓
7. sendNotification() → Envoi via NotificationSenderProcessor
    ↓
8. after() → Logging du résultat
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Notifiable type manquant | `InvalidArgumentException` | `Notifiable type is required` |
| Notifiable ID manquant | `InvalidArgumentException` | `Notifiable ID is required` |
| Body manquant | `InvalidArgumentException` | `Notification body is required` |
| Subject manquant | `InvalidArgumentException` | `Notification subject is required` |
| Aucun canal | `InvalidArgumentException` | `At least one notification channel is required` |
| Interval < 60s | `InvalidArgumentException` | `Interval must be at least 60 seconds for recurring notifications` |
| Entité non trouvée | `RuntimeException` | `Notifiable not found: {type} #{id}` |

---

## Intégration

### Dans le service provider

```php
<?php

namespace App\Providers;

use AndyDefer\LaravelNotification\Tasks\SendRecurringNotificationTask;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
use Illuminate\Support\ServiceProvider;

final class NotificationTaskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SendRecurringNotificationTask::class);
    }
}
```

### Dans une commande

```bash
# Exécuter toutes les tâches récurrentes
./vendor/bin/directive process-tasks --recurring-only

# Surveillance continue
./vendor/bin/directive tasks-watch --recurring-only --interval=60
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `before()` | O(1) | Validation simple |
| `process()` | O(n) | n = nombre de routes |
| `findNotifiable()` | O(1) | Requête Eloquent par ID |
| `sendNotification()` | O(n) | n = nombre de routes |
| `after()` | O(1) | Logging |

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

use AndyDefer\LaravelNotification\Tasks\SendRecurringNotificationTask;
use AndyDefer\LaravelNotification\Records\NotificationTaskPayloadRecord;
use AndyDefer\Task\ValueObjects\RecurringTaskFqcnVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\DurationVO;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
use App\Models\User;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\SmsChannel;

// 1. Création du payload
$payload = NotificationTaskPayloadRecord::from([
    'notifiable_type' => User::class,
    'notifiable_id' => 456,
    'body' => '<h1>Bonjour !</h1><p>Ceci est votre notification quotidienne.</p>',
    'subject' => 'Votre notification quotidienne',
    'type' => 'daily_reminder',
    'data' => [
        'user_id' => 456,
        'day' => '2026-07-06',
        'reminder_type' => 'daily',
    ],
    'channels' => [EmailChannel::class, SmsChannel::class],
    'limit_per_channel' => 1,
]);

// 2. Création de la configuration
$config = RecurringTaskConfigRecord::from([
    'description' => 'Notification quotidienne pour user #456',
    'interval_seconds' => 86400, // Tous les jours
    'start_at' => new Iso8601DateTimeVO('2026-07-06 08:00:00'),
    'end_at' => new Iso8601DateTimeVO('2026-07-31 08:00:00'),
    'max_attempts' => 3,
]);

// 3. Enregistrement de la tâche
$taskService = app(RecurringTaskServiceInterface::class);
$alias = $taskService->register(
    new RecurringTaskFqcnVO(SendRecurringNotificationTask::class),
    StrictDataObject::from($payload->toArray()),
    $config
);

echo "Tâche récurrente planifiée : " . $alias->getValue() . "\n";

// 4. Gestion de la tâche
$taskService->pause($alias);
$taskService->changeInterval($alias, new DurationVO(43200)); // 12 heures
$taskService->resume($alias);

// 5. Vérification de l'état
$debug = $taskService->getDebug($alias);
echo "Statut : " . $debug->status . "\n";
echo "Dernière exécution : " . $debug->last_run_at . "\n";
echo "Prochaine exécution : " . $debug->next_run_at . "\n";
```