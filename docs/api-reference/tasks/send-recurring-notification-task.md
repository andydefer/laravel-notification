# SendRecurringNotificationTask - Référence Technique

## Description

`SendRecurringNotificationTask` est une tâche récurrente qui exécute l'envoi périodique de notifications à intervalles réguliers. Elle est créée automatiquement par le service de notification lorsqu'un envoi récurrent est demandé, et s'exécute à chaque intervalle défini.

## Hiérarchie / Implémentations

```
AbstractRecurringTask
    └── SendRecurringNotificationTask (final)
```

**Classe parente :** `AbstractRecurringTask` - Classe de base pour les tâches récurrentes

## Rôle principal

Cette tâche agit comme le pont entre le système de planification récurrente et le processeur de notifications :

1. **Validation du payload** - Vérifie l'intégrité des données avant exécution
2. **Validation de l'intervalle** - S'assure que l'intervalle est ≥ 60 secondes
3. **Récupération de l'entité** - Trouve le notifiable (User, Order, etc.) en base de données
4. **Construction du message** - Reconstruit le message de notification à partir du payload
5. **Envoi de la notification** - Délègue l'envoi au `NotificationSenderProcessor`
6. **Journalisation avancée** - Log le succès ou l'échec avec un payload structuré

## API / Méthodes publiques

La classe hérite de `AbstractRecurringTask` et n'ajoute pas de méthodes publiques supplémentaires. L'exécution est déclenchée par le système de tâches récurrentes via la méthode `run()`.

## Cas d'utilisation

### Cas 1 : Newsletter hebdomadaire

```php
<?php

use AndyDefer\LaravelNotification\Tasks\SendRecurringNotificationTask;
use AndyDefer\Task\Records\RecurringTaskConfigRecord;
use AndyDefer\Task\ValueObjects\RecurringTaskFqcnVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;

// La tâche est créée automatiquement par NotificationService::sendRecurring()
// Mais voici ce qui se passe en interne :

$payload = StrictDataObject::from([
    'notifiable_type' => User::class,
    'notifiable_id' => $user->id,
    'body' => 'Votre newsletter hebdomadaire.',
    'subject' => 'Newsletter #' . now()->weekOfYear,
    'type' => 'newsletter',
    'data' => ['user_id' => $user->id],
    'channels' => [MailChannel::class],
    'limit_per_channel' => 1,
]);

$config = RecurringTaskConfigRecord::from([
    'description' => 'Weekly newsletter for user #' . $user->id,
    'interval_seconds' => 604800, // 7 jours
    'start_at' => new Iso8601DateTimeVO(now()->startOfWeek()->toIso8601String()),
    'end_at' => new Iso8601DateTimeVO(now()->addWeeks(4)->toIso8601String()),
    'max_attempts' => 3,
]);

// Enregistrement de la tâche récurrente
$alias = $recurringTaskService->register(
    new RecurringTaskFqcnVO(SendRecurringNotificationTask::class),
    $payload,
    $config
);

// La tâche s'exécutera toutes les semaines pendant 4 semaines
```

### Cas 2 : Rappel quotidien avec pause

```php
<?php

use AndyDefer\LaravelNotification\Tasks\SendRecurringNotificationTask;

// Création d'un rappel quotidien
$payload = StrictDataObject::from([
    'notifiable_type' => Patient::class,
    'notifiable_id' => $patient->id,
    'body' => 'N\'oubliez pas de prendre votre traitement.',
    'subject' => 'Rappel de traitement',
    'type' => 'medication_reminder',
    'data' => ['medication_id' => $medication->id],
    'channels' => [SmsChannel::class],
    'limit_per_channel' => 1,
]);

$config = RecurringTaskConfigRecord::from([
    'description' => 'Daily medication reminder for patient #' . $patient->id,
    'interval_seconds' => 86400, // 1 jour
    'start_at' => new Iso8601DateTimeVO(now()->setTime(8, 0)->toIso8601String()),
    'end_at' => new Iso8601DateTimeVO(now()->addMonths(1)->toIso8601String()),
    'max_attempts' => 3,
]);

$alias = $recurringTaskService->register(
    new RecurringTaskFqcnVO(SendRecurringNotificationTask::class),
    $payload,
    $config
);

// Mise en pause si le patient arrête le traitement
$recurringTaskService->pause($alias);

// Reprise si le traitement reprend
$recurringTaskService->resume($alias);

// Annulation définitive
$recurringTaskService->cancel($alias);
```

### Cas 3 : Rapport hebdomadaire avec modification d'intervalle

```php
<?php

class WeeklyReportService
{
    public function scheduleReport(User $user): TaskAliasVO
    {
        $payload = StrictDataObject::from([
            'notifiable_type' => User::class,
            'notifiable_id' => $user->id,
            'body' => 'Voici votre rapport d\'activité hebdomadaire.',
            'subject' => 'Rapport d\'activité',
            'type' => 'weekly_report',
            'data' => ['user_id' => $user->id],
            'channels' => [MailChannel::class],
            'limit_per_channel' => 1,
        ]);

        $config = RecurringTaskConfigRecord::from([
            'description' => 'Weekly report for user #' . $user->id,
            'interval_seconds' => 604800, // 7 jours
            'start_at' => new Iso8601DateTimeVO(now()->next(Carbon::MONDAY)->setTime(9, 0)->toIso8601String()),
            'end_at' => null, // Illimité
            'max_attempts' => 5,
        ]);

        return $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(SendRecurringNotificationTask::class),
            $payload,
            $config
        );
    }

    public function updateFrequency(TaskAliasVO $alias, int $newInterval): bool
    {
        // Passer de 7 jours à 14 jours
        return $this->recurringTaskService->changeInterval(
            $alias,
            new DurationVO($newInterval)
        );
    }
}
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| `notifiable_type` manquant | `InvalidArgumentException` | `Notifiable type is required` |
| `notifiable_id` manquant | `InvalidArgumentException` | `Notifiable ID is required` |
| `body` manquant | `InvalidArgumentException` | `Notification body is required` |
| `subject` manquant | `InvalidArgumentException` | `Notification subject is required` |
| Canaux vides | `InvalidArgumentException` | `At least one notification channel is required` |
| Intervalle < 60 secondes | `InvalidArgumentException` | `Interval must be at least 60 seconds for recurring notifications` |
| Notifiable introuvable | `RuntimeException` | `Notifiable not found: {type} #{id}` |

## Intégration

### Dépendances injectées via le contexte

```
SendRecurringNotificationTask
    └── AbstractRecurringTask
        └── Contexte de tâche (RecurringTaskContext)
            ├── getPayload() → StrictDataObject
            ├── getAlias() → TaskAliasVO
            ├── getIntervalSeconds() → CounterVO
            ├── getStartAt() → Iso8601DateTimeVO
            ├── getEndAt() → Iso8601DateTimeVO
            ├── getLastRunAt() → Iso8601DateTimeVO
            └── getLaravelApp() → Application
```

### Relations avec les autres composants

```
┌─────────────────────────────────────────────────────────────────┐
│                 RecurringTaskService                            │
│              (gestionnaire de tâches)                           │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│              SendRecurringNotificationTask                     │
│              (tâche de notification récurrente)               │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│              NotificationSenderProcessor                       │
│              (processeur d'envoi)                              │
└─────────────────────────────────────────────────────────────────┘
```

### Journalisation en cas d'échec

```php
// Payload de log structuré
$logPayload = StrictDataObject::from([
    'event' => 'recurring_notification_task_failed',
    'notifiable_type' => 'App\\Models\\User',
    'notifiable_id' => 123,
    'error' => 'Connection timeout',
    'alias' => 'recurring@550e8400-e29b-41d4-a716-446655440000',
]);

$this->logger->error(new LogDataRecord(
    type: 'recurring_notification_task',
    payload: $logPayload
));
```

## Performance

| Opération | Complexité | Description |
|-----------|-----------|-------------|
| `before()` | O(1) | Validation des données |
| `findNotifiable()` | O(1) | Recherche Eloquent par ID |
| `createNotificationMessage()` | O(1) | Construction du message |
| `sendNotification()` | O(n) | n = nombre de routes de notification |
| `after()` | O(1) | Journalisation |

**Intervalle minimum :** 60 secondes (pour éviter la surcharge du système)

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

use AndyDefer\LaravelNotification\Tasks\SendRecurringNotificationTask;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\Task\ValueObjects\TaskAliasVO;
use AndyDefer\Task\ValueObjects\DurationVO;

class RecurringNotificationService
{
    public function __construct(
        private readonly RecurringTaskServiceInterface $recurringTaskService,
        private readonly RecurringTaskRepository $recurringTaskRepository,
    ) {}

    public function scheduleWeeklyDigest(User $user): TaskAliasVO
    {
        $payload = StrictDataObject::from([
            'notifiable_type' => get_class($user),
            'notifiable_id' => $user->id,
            'body' => 'Voici votre résumé hebdomadaire.',
            'subject' => 'Résumé hebdomadaire',
            'type' => 'weekly_digest',
            'data' => new StrictDataObject(['user_id' => $user->id]),
            'channels' => [MailChannel::class],
            'limit_per_channel' => 1,
        ]);

        $config = RecurringTaskConfigRecord::from([
            'description' => 'Weekly digest for user #' . $user->id,
            'interval_seconds' => 604800, // 7 jours
            'start_at' => new Iso8601DateTimeVO(now()->next(Carbon::MONDAY)->setTime(8, 0)->toIso8601String()),
            'end_at' => null,
            'max_attempts' => 3,
        ]);

        return $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(SendRecurringNotificationTask::class),
            $payload,
            $config
        );
    }

    public function scheduleDailyReminder(Patient $patient, Medication $medication): TaskAliasVO
    {
        $payload = StrictDataObject::from([
            'notifiable_type' => get_class($patient),
            'notifiable_id' => $patient->id,
            'body' => "Rappel : Prenez votre traitement {$medication->name}.",
            'subject' => 'Rappel de traitement',
            'type' => 'medication_reminder',
            'data' => new StrictDataObject([
                'medication_id' => $medication->id,
                'patient_id' => $patient->id,
            ]),
            'channels' => [MailChannel::class, SmsChannel::class],
            'limit_per_channel' => 1,
        ]);

        $config = RecurringTaskConfigRecord::from([
            'description' => "Daily reminder for patient #{$patient->id} - {$medication->name}",
            'interval_seconds' => 86400, // 1 jour
            'start_at' => new Iso8601DateTimeVO(now()->setTime(8, 0)->toIso8601String()),
            'end_at' => new Iso8601DateTimeVO(now()->addMonths(1)->toIso8601String()),
            'max_attempts' => 5,
        ]);

        return $this->recurringTaskService->register(
            new RecurringTaskFqcnVO(SendRecurringNotificationTask::class),
            $payload,
            $config
        );
    }

    public function updateInterval(TaskAliasVO $alias, int $newInterval): bool
    {
        return $this->recurringTaskService->changeInterval(
            $alias,
            new DurationVO($newInterval)
        );
    }

    public function pauseTask(TaskAliasVO $alias): bool
    {
        return $this->recurringTaskService->pause($alias);
    }

    public function resumeTask(TaskAliasVO $alias): bool
    {
        return $this->recurringTaskService->resume($alias);
    }

    public function cancelTask(TaskAliasVO $alias): bool
    {
        return $this->recurringTaskService->cancel($alias);
    }

    public function getTaskStatus(TaskAliasVO $alias): array
    {
        $task = $this->recurringTaskRepository->findByAlias($alias);

        if (!$task) {
            return ['status' => 'not_found'];
        }

        return [
            'status' => $task->getStatus()->value,
            'interval_seconds' => $task->getIntervalSeconds()->getValue(),
            'start_at' => $task->getStartAt()->getValue(),
            'end_at' => $task->getEndAt()?->getValue(),
            'last_run_at' => $task->getLastRunAt()?->getValue(),
            'failed_attempts' => $task->getFailedAttempts()->getValue(),
            'max_failed_attempts' => $task->getMaxFailedAttempts()->getValue(),
        ];
    }
}

// Utilisation
$service = new RecurringNotificationService($recurringTaskService, $recurringTaskRepository);

// Planifier un digest hebdomadaire
$alias = $service->scheduleWeeklyDigest($user);
echo "📅 Digest hebdomadaire planifié\n";
echo "🔑 Alias : " . $alias->getValue() . "\n";

// Modifier l'intervalle (tous les 14 jours)
$service->updateInterval($alias, 1209600);

// Mettre en pause temporairement
$service->pauseTask($alias);

// Reprendre
$service->resumeTask($alias);

// Vérifier le statut
$status = $service->getTaskStatus($alias);
echo "📊 Statut : " . $status['status'] . "\n";
echo "   Intervalle : " . $status['interval_seconds'] . "s\n";
echo "   Dernière exécution : " . $status['last_run_at'] . "\n";

// Annuler définitivement
$service->cancelTask($alias);
echo "🚫 Tâche annulée\n";
```

## Voir aussi
- `AbstractRecurringTask` - Classe parente pour les tâches récurrentes
- `NotificationTaskPayloadRecord` - Record du payload
- `NotificationSenderProcessor` - Processeur d'envoi
- `RecurringTaskService` - Service de gestion des tâches récurrentes
- `SendRecurringNotificationTask` - Cette classe
- `SendDelayedNotificationTask` - Tâche unique similaire
- `NotificationMessageVO` - Value Object du message
- `RecurringTaskConfigRecord` - Configuration de la tâche récurrente