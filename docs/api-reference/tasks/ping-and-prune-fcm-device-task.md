# PingAndPruneFcmDeviceTask - Référence Technique

## Description

Tâche unique qui ping un appareil FCM et le supprime lorsqu'il est jugé invalide.

## Hiérarchie

```
AbstractUniqueTask
    └── PingAndPruneFcmDeviceTask
```

## Rôle principal

Vérifier qu'un appareil FCM identifié par son `device_id` est toujours joignable. Le ping est délégué à `FcmPingPong::pingOrPrune()`, qui se charge de la suppression en cas d'échec. La tâche journalise chaque étape via `DescriptionVO`.

## Prérequis

- Package `andydefer/laravel-task` installé et migrations exécutées.
- Le service `FcmPingPong` doit être résolu par le conteneur.
- Le modèle `FcmDevice` doit exister.
- Le payload doit contenir la clé `device_id` (UUID de l'appareil).

## Cycle de vie

La tâche étend `AbstractUniqueTask` et implémente les hooks :

| Hook | Rôle |
|------|------|
| `before(StrictDataObject $payload)` | Validation du payload |
| `process()` | Logique principale (ping + prune) |
| `after(bool $success, ?DescriptionVO $error)` | Journalisation finale |

## API / Méthodes

### `before(StrictDataObject $payload): void` (protégée)

Valide la présence et la non-vacuité de `device_id`.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$payload` | `StrictDataObject` | Payload de la tâche |

**Exceptions :**
- `InvalidArgumentException` — si `device_id` est absent : `"device_id is required."`
- `InvalidArgumentException` — si `device_id` est vide : `"device_id cannot be empty."`

**Exemple :**
```php
// Payload valide
$payload = StrictDataObject::from(['device_id' => '0192f3a1-...']);
```

### `process(): void` (protégée)

Charge l'appareil, exécute le ping via `FcmPingPong::pingOrPrune()`, et journalise le résultat.

**Exceptions :** `RuntimeException` si l'appareil correspondant au `device_id` n'existe pas.

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
$this->after(false, new DescriptionVO('FCM device not found: ...'));
```

## Payload attendu

| Clé | Type | Obligatoire | Description |
|-----|------|-------------|-------------|
| `device_id` | `string` | ✅ | UUID de l'appareil FCM à pinger |

## Comportement

### Ordre d'exécution

```
1. before($payload)
   ├── device_id absent → InvalidArgumentException
   └── device_id vide → InvalidArgumentException

2. process()
   ├── Chargement de FcmDevice via find($deviceId)
   │       └── Introuvable → RuntimeException
   ├── Résolution de FcmPingPong depuis le conteneur
   ├── Log : "Pinging FCM device <id> (token ...<last 12 chars>)"
   ├── pingOrPrune($device)
   │       ├── PONG  → appareil conservé, last_seen_at mis à jour
   │       └── INVALID → appareil supprimé
   ├── Log : "Device <id> -> <status>"
   └── Log conditionnel : "Device <id> is not reachable (<status>)"

3. after($success, $error)
   └── Échec → log d'erreur
```

## Cas d'utilisation

### Cas 1 : Planification manuelle

Un administrateur planifie un ping immédiat pour un appareil précis.

```php
<?php

declare(strict_types=1);

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Tasks\PingAndPruneFcmDeviceTask;
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
    new UniqueTaskFqcnVO(PingAndPruneFcmDeviceTask::class),
    StrictDataObject::from(['device_id' => '0192f3a1-...']),
    UniqueTaskConfigRecord::from([
        'description' => new DescriptionVO('Manual ping'),
        'scheduled_at' => new Iso8601DateTimeVO(now()->toIso8601String()),
        'max_attempts' => new MaxFailedAttemptsVO(3),
        'grace_period' => new DurationVO(3600),
    ]),
);
```

### Cas 2 : Planification par tâche récurrente

La tâche `PruneFailedFcmNotificationsTask` scanne les notifications échouées et planifie un `PingAndPruneFcmDeviceTask` pour chaque appareil détecté.

```php
$uniqueTaskService->register(
    new UniqueTaskFqcnVO(PingAndPruneFcmDeviceTask::class),
    StrictDataObject::from(['device_id' => (string) $device->id]),
    $config,
);
```

### Cas 3 : Nettoyage automatique

Un appareil dont le token FCM a été désenregistré est supprimé automatiquement.

```text
[ping] Pinging FCM device 0192f3a1-... (token ...aBcDeFgHiJkL)
[ping] Device 0192f3a1-... -> invalid
[ping] Device 0192f3a1-... is not reachable (invalid)
```

## Flux d'exécution

```
UniqueTaskService::process()
    ↓
PingAndPruneFcmDeviceTask::before($payload)
    ↓
PingAndPruneFcmDeviceTask::process()
    ├── FcmDevice::find($deviceId)
    ├── FcmPingPong::pingOrPrune($device)
    │       ├── PONG    → update last_seen_at
    │       └── INVALID → delete device
    └── Logs
    ↓
PingAndPruneFcmDeviceTask::after($success, $error)
```

## Gestion des erreurs

| Situation | Exception / Effet | Message |
|-----------|-------------------|---------|
| `device_id` absent | `InvalidArgumentException` | `device_id is required.` |
| `device_id` vide | `InvalidArgumentException` | `device_id cannot be empty.` |
| Appareil introuvable | `RuntimeException` | `FCM device not found: <id>` |
| Échec du ping | Log informatif | `Device <id> is not reachable (invalid)` |
| Échec global de la tâche | Log d'erreur | `Ping/prune failed: <reason>` |

## Intégration

| Composant | Rôle |
|-----------|------|
| `AbstractUniqueTask` | Contrat parent (cycle `before` → `process` → `after`) |
| `FcmPingPong` | Délègue le ping et la suppression |
| `FcmDevice` | Modèle cible |
| `UniqueTaskServiceInterface` | Enregistrement de la tâche |
| `DescriptionVO` | Journalisation structurée |
| `StrictDataObject` | Payload d'entrée |

## Performance

- **Coût** : 1 `find` + 1 appel réseau (via `FcmPingPong`).
- **Latence** : dominée par le réseau ; le timeout s'applique côté `FirebaseCloudMessagingDriver`.
- **Concurrence** : si plusieurs `PingAndPruneFcmDeviceTask` ciblent le même `device_id`, des appels redondants peuvent survenir. La clé naturelle de la table protège contre les doublons, mais pas contre les requêtes parallèles.
- **Idempotence** : un `pingOrPrune` sur un appareil déjà supprimé lèvera une `RuntimeException` à cause du `find`. La logique de planification doit éviter de replanifier un appareil supprimé.

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ |
| Laravel 12+ | ✅ |
| `andydefer/laravel-task` | ✅ Requis |
| `andydefer/laravel-notification` | ✅ Requis |

## Exemple complet

```bash
# Planifier manuellement la tâche via le service
php artisan tinker

>>> use AndyDefer\DomainStructures\Utils\StrictDataObject;
>>> use AndyDefer\LaravelNotification\Tasks\PingAndPruneFcmDeviceTask;
>>> use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
>>> use AndyDefer\Task\Records\UniqueTaskConfigRecord;
>>> use AndyDefer\Task\ValueObjects\DescriptionVO;
>>> use AndyDefer\Task\ValueObjects\DurationVO;
>>> use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
>>> use AndyDefer\Task\ValueObjects\MaxFailedAttemptsVO;
>>> use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;

>>> $alias = app(UniqueTaskServiceInterface::class)->register(
...     new UniqueTaskFqcnVO(PingAndPruneFcmDeviceTask::class),
...     StrictDataObject::from(['device_id' => '0192f3a1-...']),
...     UniqueTaskConfigRecord::from([
...         'description' => new DescriptionVO('Manual ping'),
...         'scheduled_at' => new Iso8601DateTimeVO(now()->toIso8601String()),
...         'max_attempts' => new MaxFailedAttemptsVO(3),
...         'grace_period' => new DurationVO(3600),
...     ]),
... );

>>> echo $alias->getValue();
```