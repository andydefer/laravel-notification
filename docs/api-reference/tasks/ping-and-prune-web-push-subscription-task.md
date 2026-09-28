# PingAndPruneWebPushSubscriptionTask - Référence Technique

## Description

Tâche unique qui ping une souscription Web Push et la supprime lorsqu'elle est jugée invalide.

## Hiérarchie

```
AbstractUniqueTask
    └── PingAndPruneWebPushSubscriptionTask
```

## Rôle principal

Vérifier qu'une souscription Web Push identifiée par son `subscription_id` est toujours joignable. Le ping est délégué à `WebPushPingPong::pingOrPrune()`, qui se charge de la suppression en cas d'échec. La tâche journalise chaque étape via `DescriptionVO`.

## Prérequis

- Package `andydefer/laravel-task` installé et migrations exécutées.
- Le service `WebPushPingPong` doit être résolu par le conteneur.
- Le modèle `WebPushSubscription` doit exister.
- Le payload doit contenir la clé `subscription_id` (UUID de la souscription).

## Cycle de vie

La tâche étend `AbstractUniqueTask` et implémente les hooks :

| Hook | Rôle |
|------|------|
| `before(StrictDataObject $payload)` | Validation du payload |
| `process()` | Logique principale (ping + prune) |
| `after(bool $success, ?DescriptionVO $error)` | Journalisation finale |

## API / Méthodes

### `before(StrictDataObject $payload): void` (protégée)

Valide la présence et la non-vacuité de `subscription_id`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$payload` | `StrictDataObject` | Payload de la tâche |

**Exceptions :**
- `InvalidArgumentException` — si `subscription_id` est absent : `"subscription_id is required."`
- `InvalidArgumentException` — si `subscription_id` est vide : `"subscription_id cannot be empty."`

**Exemple :**
```php
// Payload valide
$payload = StrictDataObject::from(['subscription_id' => '0192f3a1-...']);
```

### `process(): void` (protégée)

Charge la souscription, exécute le ping via `WebPushPingPong::pingOrPrune()`, et journalise le résultat.

**Exceptions :** `RuntimeException` si la souscription correspondant au `subscription_id` n'existe pas.

**Exemple :**
```php
// Appelé par le kernel de tâches
$task->process();
```

### `after(bool $success, ?DescriptionVO $error = null): void` (protégée)

Journalise une erreur si la tâche a échoué.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$success` | `bool` | Résultat global de l'exécution |
| `$error` | `?DescriptionVO` | Description de l'erreur, si présente |

**Exemple :**
```php
// Si une exception est levée dans process()
$this->after(false, new DescriptionVO('Web Push subscription not found: ...'));
```

## Payload attendu

| Clé | Type | Obligatoire | Description |
|-----|------|-------------|-------------|
| `subscription_id` | `string` | ✅ | UUID de la souscription Web Push à pinger |

## Comportement

### Ordre d'exécution

```
1. before($payload)
   ├── subscription_id absent → InvalidArgumentException
   └── subscription_id vide → InvalidArgumentException

2. process()
   ├── Chargement de WebPushSubscription via find($subscriptionId)
   │       └── Introuvable → RuntimeException
   ├── Résolution de WebPushPingPong depuis le conteneur
   ├── Log : "Pinging Web Push subscription <id> (endpoint ...<last 12 chars>)"
   ├── pingOrPrune($subscription)
   │       ├── PONG  → souscription conservée, last_seen_at mis à jour
   │       └── INVALID → souscription supprimée
   ├── Log : "Subscription <id> -> <status>"
   └── Log conditionnel : "Subscription <id> is not reachable (<status>)"

3. after($success, $error)
   └── Échec → log d'erreur
```

## Cas d'utilisation

### Cas 1 : Planification manuelle

Un administrateur planifie un ping immédiat pour une souscription précise.

```php
<?php

declare(strict_types=1);

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Tasks\PingAndPruneWebPushSubscriptionTask;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use AndyDefer\Task\Records\UniqueTaskConfigRecord;
use AndyDefer\Task\ValueObjects\DescriptionVO;
use AndyDefer\Task\ValueObjects\DurationVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;

/** @var UniqueTaskServiceInterface $tasks */
$tasks = app(UniqueTaskServiceInterface::class);

$alias = $tasks->register(
    new UniqueTaskFqcnVO(PingAndPruneWebPushSubscriptionTask::class),
    StrictDataObject::from(['subscription_id' => '0192f3a1-...']),
    UniqueTaskConfigRecord::from([
        'description' => new DescriptionVO('Manual ping'),
        'scheduled_at' => new Iso8601DateTimeVO(now()->toIso8601String()),
        'max_attempts' => new MaxFailedAttemptsVO(3),
        'grace_period' => new DurationVO(3600),
    ]),
);
```

### Cas 2 : Planification par tâche récurrente

La tâche `PruneFailedWebPushNotificationsTask` scanne les notifications échouées et planifie un `PingAndPruneWebPushSubscriptionTask` pour chaque souscription détectée.

```php
$uniqueTaskService->register(
    new UniqueTaskFqcnVO(PingAndPruneWebPushSubscriptionTask::class),
    StrictDataObject::from(['subscription_id' => (string) $subscription->id]),
    $config,
);
```

### Cas 3 : Nettoyage automatique

Une souscription dont l'endpoint a expiré côté navigateur est supprimée automatiquement.

```text
[ping] Pinging Web Push subscription 0192f3a1-... (endpoint ...gAAAAABquZBj)
[ping] Subscription 0192f3a1-... -> invalid
[ping] Subscription 0192f3a1-... is not reachable (invalid)
```

## Flux d'exécution

```
UniqueTaskService::process()
    ↓
PingAndPruneWebPushSubscriptionTask::before($payload)
    ↓
PingAndPruneWebPushSubscriptionTask::process()
    ├── WebPushSubscription::find($subscriptionId)
    ├── WebPushPingPong::pingOrPrune($subscription)
    │       ├── PONG    → update last_seen_at
    │       └── INVALID → delete subscription
    └── Logs
    ↓
PingAndPruneWebPushSubscriptionTask::after($success, $error)
```

## Gestion des erreurs

| Situation | Exception / Effet | Message |
|-----------|-------------------|---------|
| `subscription_id` absent | `InvalidArgumentException` | `subscription_id is required.` |
| `subscription_id` vide | `InvalidArgumentException` | `subscription_id cannot be empty.` |
| Souscription introuvable | `RuntimeException` | `Web Push subscription not found: <id>` |
| Échec du ping | Log informatif | `Subscription <id> is not reachable (invalid)` |
| Échec global de la tâche | Log d'erreur | `Ping/prune failed: <reason>` |

## Intégration

| Composant | Rôle |
|-----------|------|
| `AbstractUniqueTask` | Contrat parent (cycle `before` → `process` → `after`) |
| `WebPushPingPong` | Délègue le ping et la suppression |
| `WebPushSubscription` | Modèle cible |
| `UniqueTaskServiceInterface` | Enregistrement de la tâche |
| `DescriptionVO` | Journalisation structurée |
| `StrictDataObject` | Payload d'entrée |

## Performance

- **Coût** : 1 `find` + 1 appel réseau (via `WebPushPingPong`).
- **Latence** : dominée par le réseau ; le timeout s'applique côté `WebPushDriver`.
- **Concurrence** : si plusieurs `PingAndPruneWebPushSubscriptionTask` ciblent la même `subscription_id`, des appels redondants peuvent survenir. L'index unique sur l'endpoint protège contre les doublons, mais pas contre les requêtes parallèles.
- **Idempotence** : un `pingOrPrune` sur une souscription déjà supprimée lèvera une `RuntimeException` à cause du `find`. La logique de planification doit éviter de replanifier une souscription supprimée.

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ |
| Laravel 12+ | ✅ |
| `andydefer/laravel-task` | ✅ Requis |
| `andydefer/laravel-notification` | ✅ Requis |

## Exemple complet

```bash
# Planifier manuellement la tâche via tinker
php artisan tinker

>>> use AndyDefer\DomainStructures\Utils\StrictDataObject;
>>> use AndyDefer\LaravelNotification\Tasks\PingAndPruneWebPushSubscriptionTask;
>>> use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
>>> use AndyDefer\Task\Records\UniqueTaskConfigRecord;
>>> use AndyDefer\Task\ValueObjects\DescriptionVO;
>>> use AndyDefer\Task\ValueObjects\DurationVO;
>>> use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
>>> use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
>>> use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;

>>> $alias = app(UniqueTaskServiceInterface::class)->register(
...     new UniqueTaskFqcnVO(PingAndPruneWebPushSubscriptionTask::class),
...     StrictDataObject::from(['subscription_id' => '0192f3a1-...']),
...     UniqueTaskConfigRecord::from([
...         'description' => new DescriptionVO('Manual ping'),
...         'scheduled_at' => new Iso8601DateTimeVO(now()->toIso8601String()),
...         'max_attempts' => new MaxFailedAttemptsVO(3),
...         'grace_period' => new DurationVO(3600),
...     ]),
... );

>>> echo $alias->getValue();
```