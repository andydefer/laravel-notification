# RegisterPruneFailedWebPushNotificationsDirective - Référence Technique

## Description

Directive CLI enregistrant la tâche récurrente qui scanne les notifications Web Push échouées et planifie le nettoyage des souscriptions invalides.

## Hiérarchie

```
AbstractDirective
    └── RegisterPruneFailedWebPushNotificationsDirective
```

## Rôle principal

Fournir une commande d'installation pour enregistrer la tâche `PruneFailedWebPushNotificationsTask` auprès du service de tâches récurrentes. L'opération est idempotente : si la tâche existe déjà, elle est ignorée sauf si `--force` est fourni.

## Prérequis

- Package `andydefer/laravel-task` installé et migrations exécutées.
- Package `andydefer/laravel-directive` installé et kernel configuré.
- Le service `RecurringTaskServiceInterface` doit être résolu par le conteneur.
- La tâche `PruneFailedWebPushNotificationsTask` doit exister dans le package.

## Signature

```
notification:register-prune-webpush {interval=1800}#"Interval in seconds"
    {maxAttempts=3}#"Maximum number of attempts"
    {--force}#"Re-register even if the task already exists"
```

| Argument / Option | Type | Défaut | Description |
|-------------------|------|--------|-------------|
| `interval` | `int` | `1800` | Intervalle en secondes entre deux exécutions |
| `maxAttempts` | `int` | `3` | Nombre maximum de tentatives en cas d'échec |
| `--force` | `bool` | `false` | Ré-enregistre la tâche même si elle existe |

## Alias

- `notification:rpwp`
- `n:rpwp`

## API / Méthodes publiques

### `getSignature(): string`

Retourne la signature CLI complète.

**Retourne :** `string`

### `getDescription(): string`

Retourne la description affichée dans l'aide CLI.

**Retourne :** `string` - `"Register the recurring task that prunes invalid Web Push subscriptions"`

### `getAliases(): StringTypedCollection`

Retourne les alias acceptés.

**Retourne :** `StringTypedCollection` - Contient `notification:rpwp` et `n:rpwp`.

### `beforeExecute(): void` (protégée)

Valide les arguments avant l'exécution.

**Exceptions :**
- `InvalidArgumentException` si `interval < 60`.
- `InvalidArgumentException` si `maxAttempts < 1`.

### `execute(): ExitCode` (protégée)

Enregistre la tâche récurrente via `RecurringTaskServiceInterface::register()`.

**Retourne :**
- `ExitCode::SUCCESS` — tâche enregistrée, ou déjà existante sans `--force`.
- `ExitCode::FAILURE` — non utilisé dans cette implémentation (les erreurs de validation sont levées avant).

**Exceptions :** Aucune propagation manuelle — les erreurs d'argument sont levées dans `beforeExecute()` et gérées par le kernel.

### `recurringTaskExists(RecurringTaskServiceInterface $service, RecurringTaskFqcnVO $fqcn): bool` (privée)

Vérifie si une tâche récurrente avec le même FQCN est déjà enregistrée (peu importe son statut : waiting, playing, paused).

**Retourne :** `bool` - `true` si une tâche active ou en pause correspond au FQCN.

## Comportement

### Ordre d'exécution

```
1. beforeExecute()
   ├── Interval < 60 → InvalidArgumentException
   └── maxAttempts < 1 → InvalidArgumentException

2. execute()
   ├── Lecture des arguments et flags
   ├── Résolution de RecurringTaskServiceInterface
   ├── Vérification de l'existence (sauf --force)
   │     └── Existante → avertissement + ExitCode::SUCCESS
   ├── Construction du payload et de la config
   ├── register() auprès du service
   └── Retour ExitCode::SUCCESS
```

## Cas d'utilisation

### Cas 1 : Enregistrement initial

```bash
./vendor/bin/directive notification:register-prune-webpush
```

Sortie :
```
Recurring task registered: 0192f3a1-...
```

### Cas 2 : Ré-enregistrement forcé

```bash
./vendor/bin/directive notification:register-prune-webpush --force
```

Enregistre une nouvelle tâche même si une existe déjà.

### Cas 3 : Intervalle personnalisé

```bash
./vendor/bin/directive notification:register-prune-webpush 3600 5
```

Intervalle de 1 heure, 5 tentatives maximum.

### Cas 4 : Alias court

```bash
./vendor/bin/directive n:rpwp
```

Équivalent à `notification:register-prune-webpush`.

### Cas 5 : Argument invalide

```bash
./vendor/bin/directive notification:register-prune-webpush 30 3
```

Sortie :
```
Interval must be at least 60 seconds.
```

## Gestion des erreurs

| Situation | Exception / ExitCode | Message |
|-----------|---------------------|---------|
| `interval < 60` | `InvalidArgumentException` | `Interval must be at least 60 seconds.` |
| `maxAttempts < 1` | `InvalidArgumentException` | `maxAttempts must be at least 1.` |
| Tâche existante sans `--force` | `ExitCode::SUCCESS` | `Recurring task ... is already registered. Use --force to override.` |
| Enregistrement réussi | `ExitCode::SUCCESS` | `Recurring task registered: <alias>` |

## Config de la tâche enregistrée

| Propriété | Valeur |
|-----------|--------|
| `description` | `Prune invalid Web Push subscriptions from failed notifications` |
| `interval_seconds` | Valeur de l'argument `interval` |
| `start_at` | `now()` au format ISO 8601 |
| `max_attempts` | Valeur de l'argument `maxAttempts` |
| `payload.enabled` | `true` |
| `fqcn` | `PruneFailedWebPushNotificationsTask::class` |

## Intégration

| Composant | Rôle |
|-----------|------|
| `RecurringTaskServiceInterface` | Enregistrement de la tâche récurrente |
| `PruneFailedWebPushNotificationsTask` | Tâche récurrente cible |
| `RecurringTaskConfigRecord` | Config passée au service |
| `RecurringTaskFqcnVO` | Value Object du FQCN |
| `AbstractDirective` | Contrat parent de la CLI |

## Performance

- **Coût** : 1 lecture des arguments, 1 à 3 appels `find*()` au service, puis 1 `register()`.
- **Base de données** : les appels `findWaiting`, `findPlaying`, `findPaused` peuvent générer plusieurs requêtes si le service ne les optimise pas.
- Aucun appel réseau.

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ |
| Laravel 12+ | ✅ |
| `andydefer/laravel-task` | ✅ Requis |
| `andydefer/laravel-directive` | ✅ Requis |

## Exemple complet

```bash
# 1. Enregistrer la tâche (intervalle de 30 min par défaut)
./vendor/bin/directive notification:register-prune-webpush

# 2. Vérifier l'enregistrement
./vendor/bin/directive tasks:list

# 3. Ré-enregistrer avec un intervalle d'une heure
./vendor/bin/directive notification:register-prune-webpush 3600 3 --force
```