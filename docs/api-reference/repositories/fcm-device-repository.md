# FcmDeviceRepositoryInterface - Référence Technique

## Description

Contrat de persistance dédié aux appareils Firebase Cloud Messaging (FCM), exposant une opération d'upsert basée sur la clé naturelle `(notifiable_type, notifiable_id, device_id)`.

## Hiérarchie

```
AbstractRepositoryInterface<FcmDevice, FcmDeviceRecord>
    └── FcmDeviceRepositoryInterface
            └── FcmDeviceRepository (implémentation)
```

## Rôle principal

Définir le contrat de persistance des appareils FCM. L'interface hérite de toute l'API générique de `AbstractRepositoryInterface` (create, update, find, delete, paginate, etc.) et ajoute une méthode dédiée `upsertFor()` qui exploite la clé naturelle d'un appareil (un utilisateur + un device_id).

## Prérequis

- L'implémentation concrète (`FcmDeviceRepository`) doit être liée à cette interface dans le conteneur d'injection.
- Le modèle `FcmDevice` doit exister et utiliser une clé primaire UUID.
- La table `fcm_devices` doit être créée par migration.
- L'index unique `fcm_devices_owner_device_unique` doit exister sur `(notifiable_type, notifiable_id, device_id)`.

## API / Méthodes publiques

### `upsertFor(FcmDeviceRecord $record): FcmDevice`

Crée ou met à jour un appareil FCM à partir de son record. Si un appareil correspondant à la clé naturelle existe, son token et ses métadonnées sont rafraîchis. Sinon, une nouvelle ligne est créée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `FcmDeviceRecord` | Données de l'appareil à persister |

**Retourne :** `FcmDevice` - L'instance persistée (créée ou mise à jour)

**Exceptions :** Aucune n'est définie par le contrat. L'implémentation peut lever `Illuminate\Database\QueryException` en cas d'erreur base de données (contrainte unique, connexion, etc.).

**Exemple :**
```php
$device = $repository->upsertFor(FcmDeviceRecord::from([
    'device_id' => '550e8400-e29b-41d4-a716-446655440000',
    'token' => 'eQhyKer9t_jperz7AYDDVB:APA91b...',
    'platform' => FcmPlatform::WEB->value,
    'user_agent' => 'Mozilla/5.0 ...',
    'notifiable_type' => $user->getMorphClass(),
    'notifiable_id' => (string) $user->getKey(),
]));
```

## Cas d'utilisation

### Cas 1 : Enregistrement initial d'un appareil

Un utilisateur se connecte pour la première fois sur l'application mobile.

```php
$repository->upsertFor(FcmDeviceRecord::from([
    'device_id' => $deviceId,
    'token' => $fcmToken,
    'platform' => FcmPlatform::ANDROID->value,
    'notifiable_type' => $user->getMorphClass(),
    'notifiable_id' => (string) $user->getKey(),
]));
// → INSERT en base, retourne la nouvelle ligne
```

### Cas 2 : Rafraîchissement du token FCM

FCM renouvelle le token d'un appareil déjà connu. Le device_id reste identique, seul le token change.

```php
$repository->upsertFor(FcmDeviceRecord::from([
    'device_id' => $deviceId, // même device_id
    'token' => $newFcmToken,  // nouveau token
    'platform' => FcmPlatform::ANDROID->value,
    'notifiable_type' => $user->getMorphClass(),
    'notifiable_id' => (string) $user->getKey(),
]));
// → UPDATE de la ligne existante, même id, token rafraîchi
```

### Cas 3 : Réaffectation d'un appareil à un autre utilisateur

Un même appareil physique est utilisé par deux comptes à des moments différents.

```php
$repository->upsertFor(FcmDeviceRecord::from([
    'device_id' => $deviceId,
    'token' => $newToken,
    'platform' => FcmPlatform::ANDROID->value,
    'notifiable_type' => $anotherUser->getMorphClass(),
    'notifiable_id' => (string) $anotherUser->getKey(),
]));
// → INSERT d'une nouvelle ligne : la clé naturelle
//   (notifiable_type, notifiable_id, device_id) a changé
```

### Cas 4 : Détection du renouvellement de token

Lorsqu'un même token apparaît pour un autre device_id, le repository peut réaffecter la ligne existante pour éviter les doublons côté FCM.

```php
// Le token X existe déjà pour un autre device_id
$repository->upsertFor(FcmDeviceRecord::from([
    'device_id' => $newDeviceId,
    'token' => $existingToken, // token déjà connu
    'notifiable_type' => $user->getMorphClass(),
    'notifiable_id' => (string) $user->getKey(),
]));
// → La ligne est réaffectée au nouveau device_id
```

## Flux d'exécution

```
Appel upsertFor($record)
    ↓
SELECT * FROM fcm_devices WHERE token = ?
    ├── Ligne trouvée avec ce token
    │     → UPDATE : device_id, notifiable_*, platform,
    │                user_agent, last_seen_at
    │
    └── Aucune ligne pour ce token
          ↓
          SELECT * FROM fcm_devices
              WHERE notifiable_type = ?
                AND notifiable_id = ?
                AND device_id = ?
          ├── Ligne trouvée → UPDATE : token, platform,
          │                              user_agent, last_seen_at
          └── Aucune ligne → INSERT
    ↓
Retourne FcmDevice
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Contrainte unique violée (token ou clé naturelle) | `Illuminate\Database\QueryException` | Message dépendant du SGBD |
| Enregistrement sans device_id | `Illuminate\Database\QueryException` | `NOT NULL constraint failed` |
| Erreur de connexion | `Illuminate\Database\QueryException` | Message dépendant du SGBD |

## Intégration

| Composant | Composant lié |
|-----------|--------------|
| `AbstractRepositoryInterface` | Contrat parent fournissant l'API générique |
| `FcmDeviceRepository` | Implémentation concrète |
| `FcmDeviceRecord` | DTO interne utilisé par `upsertFor()` |
| `FcmDevice` | Modèle Eloquent retourné |
| `RegisterFcmDeviceAction` | Consommateur principal via injection |

## Performance

- **Coût** : jusqu'à 3 requêtes dans le pire cas (SELECT token, SELECT clé naturelle, puis INSERT/UPDATE).
- **Indexation** :
  - `fcm_devices_token_unique` rend la recherche par token O(log n).
  - `fcm_devices_owner_device_unique` rend la recherche par clé naturelle O(log n).
  - `fcm_devices_notifiable_index` accélère les scans par utilisateur.
- **Concurrence** : deux appels simultanés avec la même clé naturelle peuvent déclencher une `QueryException` sur l'index unique. Un `try/catch` est recommandé dans les contextes hautement concurrents.
- **Idempotence** : N appels avec la même clé naturelle ne produisent qu'une seule ligne.

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ |
| Laravel 12+ | ✅ |
| `andydefer/laravel-repository` | ✅ |
| `andydefer/php-records` | ✅ |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Contracts\Repositories\FcmDeviceRepositoryInterface;
use AndyDefer\LaravelNotification\Enums\FcmPlatform;
use AndyDefer\LaravelNotification\Records\FcmDeviceRecord;

/** @var FcmDeviceRepositoryInterface $repository */
$repository = app(FcmDeviceRepositoryInterface::class);

$device = $repository->upsertFor(FcmDeviceRecord::from([
    'device_id' => '550e8400-e29b-41d4-a716-446655440000',
    'token' => 'eQhyKer9t_jperz7AYDDVB:APA91bFJTg_RGtoAIKIqj...',
    'platform' => FcmPlatform::WEB->value,
    'user_agent' => 'Mozilla/5.0 ...',
    'notifiable_type' => 'user',
    'notifiable_id' => '42',
]));

echo $device->id;         // UUID
echo $device->device_id;  // 550e8400-e29b-41d4-a716-446655440000
echo $device->token;      // token FCM
echo $device->platform;   // "web"
```