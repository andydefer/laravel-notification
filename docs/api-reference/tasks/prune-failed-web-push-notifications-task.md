# PruneFailedWebPushNotificationsTask - Référence Technique

## Description

Tâche récurrente qui scanne les notifications Web Push échouées, résout leur notifiable, et planifie un ping/prune pour chaque souscription Web Push associée.

## Hiérarchie

```
AbstractRecurringTask
    └── PruneFailedWebPushNotificationsTask
```

## Rôle principal

Assurer un nettoyage périodique des souscriptions Web Push dont l'envoi échoue de manière répétée. Le scan reste **rapide** : il ne fait aucune requête réseau. La vérification effective (ping) est déléguée à `PingAndPruneWebPushSubscriptionTask`, une tâche unique qui se charge de l'appel au service Push et de la suppression éventuelle.

## Prérequis

- Package `andydefer/laravel-task` installé et migrations exécutées.
- Package `andydefer/laravel-notification` installé et migrations exécutées.
- Le service `UniqueTaskServiceInterface` doit être résolu par le conteneur.
- Le modèle `Notification` doit exister avec les colonnes `channel`, `status`, `notifiable_type`, `notifiable_id`.
- Le modèle `WebPushSubscription` doit exister.
- La tâche `PingAndPruneWebPushSubscriptionTask` doit exister.

## Constantes

| Constante | Valeur | Description |
|-----------|--------|-------------|
| `BATCH_SIZE` | `200` | Nombre de notifications traitées par chunk |

## API / Méthodes

### `process(): void` (protégée)

Scanne les notifications Web Push au statut `FAILED`, résout le notifiable, et planifie un ping/prune par souscription associée.

**Effets de bord :** enregistrement de N tâches uniques (`PingAndPruneWebPushSubscriptionTask`).

**Exemple :**
```php
// Appelé par le kernel de tâches
$task->process();
```

### `schedulePingAndPrune(UniqueTaskServiceInterface $uniqueTaskService, WebPushSubscription $subscription): void` (privée)

Enregistre une tâche unique `PingAndPruneWebPushSubscriptionTask` pour la souscription donnée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$uniqueTaskService` | `UniqueTaskServiceInterface` | Service d'enregistrement des tâches uniques |
| `$subscription` | `WebPushSubscription` | Souscription cible du ping/prune |

**Effet :** `register()` est appelé avec un payload `{subscription_id}` et une configuration standard.

## Comportement

### Ordre d'exécution

```
1. Log : "Scanning FAILED Web Push notifications..."
2. Résolution de UniqueTaskServiceInterface
3. Parcours par chunks de 200 :
   └── Pour chaque Notification :
         ├── Résolution du notifiable
         │       └── null → skip
         ├── Récupération des WebPushSubscription du notifiable
         └── Pour chaque WebPushSubscription :
               └── schedulePingAndPrune(...)
4. Log : "Scheduled N ping/prune task(s)."
```

### Filtres appliqués

| Filtre | Valeur |
|--------|--------|
| `channel` | `WebPushChannel::class` |
| `status` | `NotificationStatus::FAILED` |
| Tri | `created_at` croissant |
| Batch | `BATCH_SIZE` = 200 |

## Cas d'utilisation

### Cas 1 : Enregistrement via directive

```bash
./vendor/bin/directive notification:register-prune-webpush
```

Enregistre la tâche récurrente avec un intervalle par défaut de 30 minutes.

### Cas 2 : Enregistrement programmatique

```php
<?php

declare(strict_types=1);

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Tasks\PruneFailedWebPushNotificationsTask;
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
    new RecurringTaskFqcnVO(PruneFailedWebPushNotificationsTask::class),
    StrictDataObject::from(['enabled' => true]),
    RecurringTaskConfigRecord::from([
        'description' => new DescriptionVO('Prune invalid Web Push subscriptions'),
        'interval_seconds' => new DurationVO(1800),
        'start_at' => new Iso8601DateTimeVO(now()->toIso8601String()),
        'max_attempts' => new MaxFailedAttemptsVO(3),
    ]),
);
```

### Cas 3 : Traitement d'un lot volumineux

Si 10 000 notifications échouées existent, le chunk de 200 évite de saturer la mémoire. La tâche unique planifiée pour chaque souscription est ensuite traitée de manière asynchrone par le worker de tâches.

```text
[recurring] Scanning FAILED Web Push notifications...
[recurring] Scheduled 42 ping/prune task(s).
[unique]    Pinging Web Push subscription 0192f3a1-... (endpoint ...gAAAAABquZBj)
[unique]    Subscription 0192f3a1-... -> invalid
```

## Flux d'exécution

```
RecurringTaskService::process()
    ↓
PruneFailedWebPushNotificationsTask::process()
    ├── UniqueTaskServiceInterface (résolution)
    ├── Notification::query()->chunk(200)
    │       └── Pour chaque Notification :
    │             ├── $notification->notifiable
    │             ├── WebPushSubscription::query()->where(notifiable_*)
    │             └── schedulePingAndPrune(...)
    ↓
N × PingAndPruneWebPushSubscriptionTask (planifiées)
```

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Notification sans notifiable | Ignorée silencieusement (`continue`) |
| Notifiable sans souscription Web Push | Ignorée silencieusement |
| Erreur dans `schedulePingAndPrune` | Remontée au kernel de tâches |
| Erreur dans une tâche unique planifiée | Gérée indépendamment par le worker |

## Tâches planifiées

Pour chaque souscription Web Push associée à un notifiable ayant une notification échouée :

| Propriété | Valeur |
|-----------|--------|
| FQCN | `PingAndPruneWebPushSubscriptionTask::class` |
| Payload | `['subscription_id' => (string) $subscription->id]` |
| Description | `Ping and prune Web Push subscription <id>` |
| `scheduled_at` | `now()` |
| `max_attempts` | `3` |
| `grace_period` | `3600` |

## Intégration

| Composant | Rôle |
|-----------|------|
| `AbstractRecurringTask` | Contrat parent |
| `UniqueTaskServiceInterface` | Enregistrement des tâches uniques |
| `PingAndPruneWebPushSubscriptionTask` | Tâche planifiée par ce scan |
| `WebPushSubscription` | Modèle source pour la planification |
| `Notification` | Table scannée |
| `DescriptionVO` | Journalisation structurée |

## Performance

- **Coût du scan** : N / 200 requêtes `chunk` + 1 requête par notifiable unique (N+1 potentiel sur `WebPushSubscription::query()`).
- **Latence** : pas d'appel réseau dans cette tâche.
- **Mémoire** : bornée par `BATCH_SIZE`.
- **Recommandation** : si le volume de notifications échouées est très important, ajouter un index sur `(channel, status)` pour accélérer le scan initial.
- **N+1** : le `WebPushSubscription::query()` par notification est un N+1 potentiel. Si plusieurs notifications partagent le même notifiable, les mêmes requêtes sont répétées.

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
./vendor/bin/directive notification:register-prune-webpush

# 2. Vérifier l'enregistrement
./vendor/bin/directive tasks:list

# 3. Exécuter manuellement le traitement
./bin/task tasks:process

```