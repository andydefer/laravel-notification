# SendDelayedNotificationTask - Référence Technique

## Description

Tâche unique (`AbstractUniqueTask`) pour l'envoi de notifications différées. Récupère l'entité notifiable, construit le message et exécute l'envoi via `NotificationSenderProcessor`.

## Hiérarchie / Implémentations

```
AbstractUniqueTask
    └── SendDelayedNotificationTask (final)
```

## Rôle principal

**Point d'entrée pour l'envoi de notifications planifiées :**

1. **Validation** (`before()`) : Vérifie l'intégrité du payload
2. **Exécution** (`process()`) : Récupère l'entité et envoie la notification
3. **Finalisation** (`after()`) : Loggue le résultat

---

## API / Méthodes publiques

### `before(StrictDataObject $payload): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$payload` | `StrictDataObject` | Le payload de la tâche |

**Retourne :** `void`

**Exceptions :** `InvalidArgumentException` si le payload est invalide

**Exemple :**
```php
protected function before(StrictDataObject $payload): void
{
    // Validation automatique via NotificationTaskPayloadRecord
    // Lance une exception si un champ requis est manquant
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
    if ($success) {
        $this->info(new DescriptionVO('Notification envoyée avec succès'));
    } else {
        $this->error(new DescriptionVO('Échec de l\'envoi: ' . $error?->getValue()));
    }
}
```

---

## Cas d'utilisation

### Cas 1 : Envoi d'un email 30 minutes après l'inscription

```php
<?php

use AndyDefer\LaravelNotification\Tasks\SendDelayedNotificationTask;
use AndyDefer\LaravelNotification\Records\NotificationTaskPayloadRecord;
use AndyDefer\LaravelNotification\Records\SendLaterRecord;
use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;

$payload = NotificationTaskPayloadRecord::from([
    'notifiable_type' => App\Models\User::class,
    'notifiable_id' => $user->id,
    'body' => 'Bienvenue sur notre plateforme !',
    'subject' => 'Bienvenue',
    'type' => 'email',
    'data' => ['user_id' => $user->id],
    'channels' => [App\Notifications\Channels\EmailChannel::class],
    'limit_per_channel' => 1,
]);

$config = UniqueTaskConfigRecord::from([
    'description' => 'Email de bienvenue - ' . $user->email,
    'scheduled_at' => new Iso8601DateTimeVO(now()->addMinutes(30)->toIso8601String()),
    'max_attempts' => 3,
    'grace_period' => 86400,
]);

$task = new SendDelayedNotificationTask();
$alias = $task->register(
    new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
    StrictDataObject::from($payload->toArray()),
    $config
);
```

---

### Cas 2 : Utilisation via NotificationService

```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendLaterRecord;

$service = app(NotificationService::class);

$record = SendLaterRecord::from([
    'delay_seconds' => 900, // 15 minutes
    'channels' => [EmailChannel::class, SmsChannel::class],
    'limit_per_channel' => 1,
]);

$message = new NotificationMessageVO(
    subject: 'Rappel de rendez-vous',
    body: 'Votre rendez-vous est dans 15 minutes.'
);

$alias = $service->sendLater($user, $message, $record);
// La tâche SendDelayedNotificationTask est automatiquement planifiée
```

---

## Flux d'exécution

```
1. Tâche planifiée (par cron)
    ↓
2. before() → Validation du payload
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
| Entité non trouvée | `RuntimeException` | `Notifiable not found: {type} #{id}` |

---

## Intégration

### Dans le service provider

```php
<?php

namespace App\Providers;

use AndyDefer\LaravelNotification\Tasks\SendDelayedNotificationTask;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use Illuminate\Support\ServiceProvider;

final class NotificationTaskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // La tâche est automatiquement disponible via le conteneur
        $this->app->bind(SendDelayedNotificationTask::class);
    }
}
```

### Dans une commande

```bash
# Exécuter manuellement une tâche planifiée
./vendor/bin/directive process-tasks --unique-only
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `before()` | O(1) | Validation simple |
| `process()` | O(n) | n = nombre de routes |
| `findNotifiable()` | O(1) | Requête Eloquent par ID |
| `sendNotification()` | O(n) | n = nombre de routes |

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

use AndyDefer\LaravelNotification\Tasks\SendDelayedNotificationTask;
use AndyDefer\LaravelNotification\Records\NotificationTaskPayloadRecord;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;
use AndyDefer\Task\ValueObjects\UniqueTaskConfigRecord;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use App\Models\User;
use App\Notifications\Channels\EmailChannel;

// 1. Création du payload
$payload = NotificationTaskPayloadRecord::from([
    'notifiable_type' => User::class,
    'notifiable_id' => 123,
    'body' => '<h1>Bonjour !</h1><p>Votre compte a été créé avec succès.</p>',
    'subject' => 'Bienvenue sur notre plateforme',
    'type' => 'welcome',
    'data' => ['user_id' => 123, 'welcome_code' => 'ABC-123'],
    'channels' => [EmailChannel::class],
    'limit_per_channel' => 1,
]);

// 2. Création de la configuration
$config = UniqueTaskConfigRecord::from([
    'description' => 'Email de bienvenue pour user #123',
    'scheduled_at' => new Iso8601DateTimeVO('2026-07-07 10:00:00'),
    'max_attempts' => 3,
    'grace_period' => 86400,
]);

// 3. Enregistrement de la tâche
$taskService = app(UniqueTaskServiceInterface::class);
$alias = $taskService->register(
    new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
    StrictDataObject::from($payload->toArray()),
    $config
);

echo "Tâche planifiée avec l'alias : " . $alias->getValue() . "\n";

// 4. Exécution de la tâche (lorsque la date est atteinte)
// La tâche sera exécutée automatiquement par process-tasks

// 5. Vérification du résultat
$debug = $taskService->getDebug($alias);
echo "Statut : " . $debug->status . "\n";
```