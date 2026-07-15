# SendDelayedNotificationTask - Référence Technique

## Description

`SendDelayedNotificationTask` est une tâche unique qui exécute l'envoi d'une notification différée. Elle est créée automatiquement par le service de notification lorsqu'un envoi différé ou planifié est demandé, et s'exécute à la date/heure prévue.

## Hiérarchie / Implémentations

```
AbstractUniqueTask
    └── SendDelayedNotificationTask (final)
```

**Classe parente :** `AbstractUniqueTask` - Classe de base pour les tâches uniques

## Rôle principal

Cette tâche agit comme le pont entre le système de planification et le processeur de notifications :

1. **Validation du payload** - Vérifie l'intégrité des données avant exécution
2. **Récupération de l'entité** - Trouve le notifiable (User, Order, etc.) en base de données
3. **Construction du message** - Reconstruit le message de notification à partir du payload
4. **Envoi de la notification** - Délègue l'envoi au `NotificationSenderProcessor`
5. **Journalisation** - Log le succès ou l'échec de l'exécution

## API / Méthodes publiques

La classe hérite de `AbstractUniqueTask` et n'ajoute pas de méthodes publiques supplémentaires. L'exécution est déclenchée par le système de tâches via la méthode `run()`.

## Flux d'exécution

```
SendDelayedNotificationTask::run()
    │
    ▼
┌─────────────────────────────────────────────────────────────────┐
│ 1. before() - Validation du payload                            │
│    ├── notifiable_type requis                                  │
│    ├── notifiable_id requis                                    │
│    ├── body requis                                             │
│    ├── subject requis                                          │
│    └── channels non vides                                      │
└────────────────────────┬────────────────────────────────────────┘
                         │ (si valide)
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. process() - Exécution principale                            │
│    ├── findNotifiable() → récupère l'entité                    │
│    ├── createNotificationMessage() → construit le message      │
│    ├── createProcessRecord() → configure l'envoi               │
│    └── sendNotification() → délègue au processor               │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. after() - Post-exécution                                    │
│    ├── Succès → log info                                       │
│    └── Échec → log error avec le message d'erreur              │
└─────────────────────────────────────────────────────────────────┘
```

## Cas d'utilisation

### Cas 1 : Envoi d'email de bienvenue 5 minutes après l'inscription

```php
<?php

use AndyDefer\LaravelNotification\Tasks\SendDelayedNotificationTask;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;

// La tâche est créée automatiquement par NotificationService::sendLater()
// Mais voici ce qui se passe en interne :

$payload = StrictDataObject::from([
    'notifiable_type' => User::class,
    'notifiable_id' => $user->id,
    'body' => 'Bienvenue sur notre plateforme !',
    'subject' => 'Bienvenue',
    'type' => 'welcome',
    'data' => [],
    'channels' => [MailChannel::class],
    'limit_per_channel' => 1,
]);

$config = UniqueTaskConfigRecord::from([
    'description' => 'Delayed notification: Bienvenue',
    'scheduled_at' => new Iso8601DateTimeVO(now()->addMinutes(5)),
    'max_attempts' => 3,
    'grace_period' => 86400,
]);

// Enregistrement de la tâche
$alias = $uniqueTaskService->register(
    new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
    $payload,
    $config
);

// La tâche s'exécutera automatiquement dans 5 minutes
```

### Cas 2 : Rappel de rendez-vous 24h à l'avance

```php
<?php

use AndyDefer\LaravelNotification\Tasks\SendDelayedNotificationTask;

// Création d'un rappel de rendez-vous
$payload = StrictDataObject::from([
    'notifiable_type' => Patient::class,
    'notifiable_id' => $patient->id,
    'body' => 'Rappel : Rendez-vous demain à 10h00.',
    'subject' => 'Rappel de rendez-vous',
    'type' => 'appointment_reminder',
    'data' => ['appointment_id' => $appointment->id],
    'channels' => [MailChannel::class, SmsChannel::class],
    'limit_per_channel' => 1,
]);

// La tâche est programmée pour s'exécuter 24h avant le rendez-vous
$scheduledAt = $appointment->start_at->subDay();

$config = UniqueTaskConfigRecord::from([
    'description' => 'Appointment reminder for patient #' . $patient->id,
    'scheduled_at' => new Iso8601DateTimeVO($scheduledAt->toIso8601String()),
    'max_attempts' => 3,
    'grace_period' => 3600, // 1h de grâce
]);

$alias = $uniqueTaskService->register(
    new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
    $payload,
    $config
);
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| `notifiable_type` manquant | `InvalidArgumentException` | `Notifiable type is required` |
| `notifiable_id` manquant | `InvalidArgumentException` | `Notifiable ID is required` |
| `body` manquant | `InvalidArgumentException` | `Notification body is required` |
| `subject` manquant | `InvalidArgumentException` | `Notification subject is required` |
| Canaux vides | `InvalidArgumentException` | `At least one notification channel is required` |
| Notifiable introuvable | `RuntimeException` | `Notifiable not found: {type} #{id}` |

## Intégration

### Dépendances injectées via le contexte

```
SendDelayedNotificationTask
    └── AbstractUniqueTask
        └── Contexte de tâche (RecurringTaskContext)
            ├── getPayload() → StrictDataObject
            ├── getAlias() → TaskAliasVO
            └── getLaravelApp() → Application
```

### Relations avec les autres composants

```
┌─────────────────────────────────────────────────────────────────┐
│                    UniqueTaskService                            │
│              (gestionnaire de tâches)                           │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│              SendDelayedNotificationTask                       │
│              (tâche de notification différée)                  │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│              NotificationSenderProcessor                       │
│              (processeur d'envoi)                              │
└─────────────────────────────────────────────────────────────────┘
```

### NotificationTaskPayloadRecord

Le payload stocké dans la tâche contient toutes les informations nécessaires :

```php
NotificationTaskPayloadRecord::from([
    'notifiable_type' => 'App\\Models\\User',
    'notifiable_id' => 123,
    'body' => new MessageBodyVO('Message body'),
    'subject' => new MessageSubjectVO('Subject'),
    'type' => 'welcome',
    'data' => new StrictDataObject(['user_id' => 123]),
    'channels' => FqcnChannelCollection,
    'limit_per_channel' => 1,
    'destination_filter' => StrictAssociative,
]);
```

## Performance

| Opération | Complexité | Description |
|-----------|-----------|-------------|
| `before()` | O(1) | Validation des données |
| `findNotifiable()` | O(1) | Recherche Eloquent par ID |
| `createNotificationMessage()` | O(1) | Construction du message |
| `sendNotification()` | O(n) | n = nombre de routes de notification |
| `after()` | O(1) | Journalisation |

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

use AndyDefer\LaravelNotification\Tasks\SendDelayedNotificationTask;
use AndyDefer\LaravelNotification\Records\NotificationTaskPayloadRecord;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\Task\ValueObjects\TaskAliasVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;

class DelayedNotificationService
{
    public function scheduleNotification(
        User $user,
        string $body,
        string $subject,
        int $delaySeconds,
        array $channels = [MailChannel::class]
    ): TaskAliasVO {
        // 1. Construction du payload
        $channelsCollection = new FqcnChannelCollection;
        foreach ($channels as $channel) {
            $channelsCollection->add(new FqcnChannelVO($channel));
        }

        $payload = StrictDataObject::from([
            'notifiable_type' => get_class($user),
            'notifiable_id' => $user->id,
            'body' => new MessageBodyVO($body),
            'subject' => new MessageSubjectVO($subject),
            'type' => 'custom',
            'data' => new StrictDataObject(['user_id' => $user->id]),
            'channels' => $channelsCollection,
            'limit_per_channel' => 1,
        ]);

        // 2. Configuration de la tâche
        $scheduledAt = new Iso8601DateTimeVO(now()->addSeconds($delaySeconds));

        $config = UniqueTaskConfigRecord::from([
            'description' => 'Delayed notification: ' . $subject,
            'scheduled_at' => $scheduledAt,
            'max_attempts' => 3,
            'grace_period' => 86400,
        ]);

        // 3. Enregistrement de la tâche
        $alias = $this->uniqueTaskService->register(
            new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
            $payload,
            $config
        );

        return $alias;
    }

    public function cancelDelayedNotification(string $alias): bool
    {
        return $this->uniqueTaskService->cancel(new TaskAliasVO($alias));
    }

    public function getTaskStatus(string $alias): array
    {
        $task = $this->uniqueTaskRepository->findByAlias(new TaskAliasVO($alias));

        if (!$task) {
            return ['status' => 'not_found'];
        }

        return [
            'status' => $task->getStatus()->value,
            'scheduled_at' => $task->getScheduledAt()->getValue(),
            'attempts' => $task->getAttempts()->getValue(),
            'max_attempts' => $task->getMaxAttempts()->getValue(),
        ];
    }
}

// Utilisation
$service = new DelayedNotificationService($uniqueTaskService, $uniqueTaskRepository);

// Planifier un email dans 30 minutes
$alias = $service->scheduleNotification(
    $user,
    'Votre rapport est prêt.',
    'Rapport disponible',
    1800,
    [MailChannel::class]
);

echo "📅 Notification planifiée\n";
echo "🔑 Alias : " . $alias->getValue() . "\n";

// Vérifier le statut
$status = $service->getTaskStatus($alias->getValue());
echo "📊 Statut : " . $status['status'] . "\n";

// Annuler si nécessaire
if ($shouldCancel) {
    $service->cancelDelayedNotification($alias->getValue());
    echo "🚫 Tâche annulée\n";
}
```

## Voir aussi
- `AbstractUniqueTask` - Classe parente pour les tâches uniques
- `NotificationTaskPayloadRecord` - Record du payload
- `NotificationSenderProcessor` - Processeur d'envoi
- `UniqueTaskService` - Service de gestion des tâches uniques
- `SendDelayedNotificationTask` - Cette classe
- `SendRecurringNotificationTask` - Tâche récurrente similaire
- `NotificationMessageVO` - Value Object du message