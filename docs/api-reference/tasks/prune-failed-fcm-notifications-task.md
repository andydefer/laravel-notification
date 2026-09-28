# PruneFailedFcmNotificationsTask - Référence Technique

## Description

Tâche récurrente qui scanne les notifications FCM échouées, résout leur notifiable, et planifie un ping/prune pour chaque appareil FCM associé.

## Hiérarchie

```
AbstractRecurringTask
    └── PruneFailedFcmNotificationsTask
```

## Rôle principal

Assurer un nettoyage périodique des appareils FCM dont l'envoi échoue de manière répétée. Le scan reste **rapide** : il ne fait aucune requête réseau. La vérification effective (ping) est déléguée à `PingAndPruneFcmDeviceTask`, une tâche unique qui se charge de l'appel FCM et de la suppression éventuelle.

## Prérequis

- Package `andydefer/laravel-task` installé et migrations exécutées.
- Package `andydefer/laravel-notification` installé et migrations exécutées.
- Le service `UniqueTaskServiceInterface` doit être résolu par le conteneur.
- Le modèle `Notification` doit exister avec les colonnes `channel`, `status`, `notifiable_type`, `notifiable_id`.
- Le modèle `FcmDevice` doit exister.
- La tâche `PingAndPruneFcmDeviceTask` doit exister.

## Constantes

| Constante | Valeur | Description |
|-----------|--------|-------------|
| `BATCH_SIZE` | `200` | Nombre de notifications traitées par chunk |

## API / Méthodes

### `process(): void` (protégée)

Scanne les notifications FCM au statut `FAILED`, résout le notifiable, et planifie un ping/prune par appareil associé.

**Effets de bord :** enregistrement de N tâches uniques (`PingAndPruneFcmDeviceTask`).

**Exemple :**
```php
// Appelé par le kernel de tâches
$task->process();
```

### `schedulePingAndPrune(UniqueTaskServiceInterface $uniqueTaskService, FcmDevice $device): void` (privée)

Enregistre une tâche unique `PingAndPruneFcmDeviceTask` pour l'appareil donné.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$uniqueTaskService` | `UniqueTaskServiceInterface` | Service d'enregistrement des tâches uniques |
| `$device` | `FcmDevice` | Appareil cible du ping/prune |

**Effet :** `register()` est appelé avec un payload `{device_id}` et une configuration standard.

## Comportement

### Ordre d'exécution

```
1. Log : "Scanning FAILED FCM notifications..."
2. Résolution de UniqueTaskServiceInterface
3. Parcours par chunks de 200 :
   └── Pour chaque Notification :
         ├── Résolution du notifiable
         │       └── null → skip
         ├── Récupération des FcmDevice du notifiable
         └── Pour chaque FcmDevice :
               └── schedulePingAndPrune(...)
4. Log : "Scheduled N ping/prune task(s)."
```

### Filtres appliqués

| Filtre | Valeur |
|--------|--------|
| `channel` | `FirebaseCloudMessagingChannel::class` |
| `status` | `NotificationStatus::FAILED` |
| Tri | `created_at` croissant |
| Batch | `BATCH_SIZE` = 200 |

## Cas d'utilisation

### Cas 1 : Enregistrement via directive

```bash
./vendor/bin/directive notification:register-prune-fcm
```

Enregistre la tâche récurrente avec un intervalle par défaut de 30 minutes.

### Cas 2 : Enregistrement programmatique

```php
<?php

declare(strict_types=1);

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Tasks\PruneFailedFcmNotificationsTask;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
use AndyDefer\Task\Records\RecurringTaskConfigRecord;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use AndyDefer\Task\ValueObjects\DurationVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
use AndyDefer\Task\ValueObjects\RecurringTaskFqcnVO;

/** @var RecurringTaskServiceInterface $tasks */
$tasks = app(RecurringTaskServiceInterface::class);

$alias = $tasks->register(
    new RecurringTaskFqcnVO(PruneFailedFcmNotificationsTask::class),
    StrictDataObject::from(['enabled' => true]),
    RecurringTaskConfigRecord::from([
        'description' => new DescriptionVO('Prune invalid FCM devices'),
        'interval_seconds' => new DurationVO(1800),
        'start_at' => new Iso8601DateTimeVO(now()->toIso8601String()),
        'max_attempts' => new MaxFailedAttemptsVO(3),
    ]),
);
```

### Cas 3 : Traitement d'un lot volumineux

Si 10 000 notifications échouées existent, le chunk de 200 évite de saturer la mémoire. La tâche unique planifiée pour chaque appareil est ensuite traitée de manière asynchrone par le worker de tâches.

```text
[recurring] Scanning FAILED FCM notifications...
[recurring] Scheduled 42 ping/prune task(s).
[unique]    Pinging FCM device 0192f3a1-... (token ...aBcDeFgHiJkL)
[unique]    Device 0192f3a1-... -> invalid
```

## Flux d'exécution

```
RecurringTaskService::process()
    ↓
PruneFailedFcmNotificationsTask::process()
    ├── UniqueTaskServiceInterface (résolution)
    ├── Notification::query()->chunk(200)
    │       └── Pour chaque Notification :
    │             ├── $notification->notifiable
    │             ├── FcmDevice::query()->where(notifiable_*)
    │             └── schedulePingAndPrune(...)
    ↓
N × PingAndPruneFcmDeviceTask (planifiées)
```

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Notification sans notifiable | Ignorée silencieusement (`continue`) |
| Notifiable sans appareil FCM | Ignorée silencieusement |
| Erreur dans `schedulePingAndPrune` | Remontée au kernel de tâches |
| Erreur dans une tâche unique planifiée | Gérée indépendamment par le worker |

## Tâches planifiées

Pour chaque appareil FCM associé à un notifiable ayant une notification échouée :

| Propriété | Valeur |
|-----------|--------|
| FQCN | `PingAndPruneFcmDeviceTask::class` |
| Payload | `['device_id' => (string) $device->id]` |
| Description | `Ping and prune FCM device <id>` |
| `scheduled_at` | `now()` |
| `max_attempts` | `3` |
| `grace_period` | `3600` |

## Intégration

| Composant | Rôle |
|-----------|------|
| `AbstractRecurringTask` | Contrat parent |
| `UniqueTaskServiceInterface` | Enregistrement des tâches uniques |
| `PingAndPruneFcmDeviceTask` | Tâche planifiée par ce scan |
| `FcmDevice` | Modèle source pour la planification |
| `Notification` | Table scannée |
| `DescriptionVO` | Journalisation structurée |

## Performance

- **Coût du scan** : N / 200 requêtes `chunk` + 1 requête par notifiable unique (N+1 potentiel sur `FcmDevice::query()`).
- **Latence** : pas d'appel réseau dans cette tâche.
- **Mémoire** : bornée par `BATCH_SIZE`.
- **Recommandation** : si le volume de notifications échouées est très important, ajouter un index sur `(channel, status)` pour accélérer le scan initial.
- **N+1** : le `FcmDevice::query()` par notification est un N+1 potentiel. Si plusieurs notifications partagent le même notifiable, les mêmes requêtes sont répétées.

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ |
| Laravel 12+ | ✅ |
| `andydefer/laravel-task` | ✅ Requis |
| `andydefer/laravel-notification` | ✅ Requis |

## Exemple complet

```bash
# 1. Enregistrer la tâche récurrente (intervalle par défaut 30 min)
./vendor/bin/directive notification:register-prune-fcm

# 2. Vérifier l'enregistrement
./vendor/bin/directive tasks:list

# 3. Exécuter manuellement le traitement
./bin/task tasks:process
```