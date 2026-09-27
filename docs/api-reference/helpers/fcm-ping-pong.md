# FcmPingPong - Référence Technique

## Description

Helper de ping/pong pour les devices FCM. Envoie une notification légère à un token FCM afin de vérifier qu'il est toujours enregistré et joignable. Distingue un token définitivement invalide (`UNREGISTERED`, `INVALID_ARGUMENT`, `SENDER_ID_MISMATCH`) d'un token simplement injoignable (réseau, FCM indisponible).

## Hiérarchie / Implémentations

```
FcmPingPong (final class)
```

Aucune interface implémentée, aucune classe parente. Classe `final` non extensible.

## Rôle principal

Ce helper sert de **sonde de santé** pour les tokens FCM stockés en base. Dans l'architecture du package `laravel-notification`, il s'insère entre la couche métier (qui gère les devices) et le `NotificationServiceInterface` (qui envoie réellement les notifications via les channels).

Son rôle est triple :
1. **Tester la vivacité** d'un token en envoyant un message minimal (`ping`).
2. **Qualifier l'échec** : invalide vs injoignable, pour éviter de supprimer un device à cause d'une panne réseau transitoire.
3. **Nettoyer automatiquement** les tokens morts via `pingOrPrune()`.

## Installation

Aucune installation spécifique. Le helper est fourni par le package `andydefer/laravel-notification`.

Prérequis :
- PHP 8.1+
- Le service `NotificationServiceInterface` doit être résolu par le container.
- Le channel `FirebaseCloudMessagingChannel` doit être enregistré.
- Les devices doivent implémenter `NotifiableInterface` et `PingableInterface`.

## API / Méthodes publiques

### Constantes publiques

| Constante | Valeur | Description |
|-----------|--------|-------------|
| `PING_TYPE` | `'ping'` | Type de notification envoyé |
| `PING_SUBJECT` | `'ping'` | Sujet du message |
| `PING_BODY` | `'ping'` | Corps du message |

---

### `ping(Model $device): PingStatus`

Envoie un ping à un device et retourne le statut résultant.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$device` | `Model&NotifiableInterface&PingableInterface` | Device à tester. Doit être un Model Eloquent **et** implémenter les deux interfaces |

**Retourne :** `PingStatus` - `PONG` si succès, `INVALID` si token définitivement invalide, `UNREACHABLE` sinon.

**Exceptions :** `RuntimeException` - Levée si `$device` n'implémente pas `NotifiableInterface`.

**Effets de bord :** En cas de succès, met à jour `last_seen_at` à `now()` et sauvegarde le Model.

**Exemple :**
```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Helpers\FcmPingPong;
use AndyDefer\LaravelNotification\Enums\PingStatus;

/** @var FcmPingPong $ping */
$status = $ping->ping($device);

match ($status) {
    PingStatus::PONG => logger()->info('Device joignable'),
    PingStatus::INVALID => logger()->warning('Token invalide'),
    PingStatus::UNREACHABLE => logger()->notice('Device injoignable'),
};
```

---

### `isAlive(Model $device): bool`

Retourne `true` si le device répond par un pong.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$device` | `Model&NotifiableInterface&PingableInterface` | Device à tester |

**Retourne :** `bool` - `true` uniquement si le ping a réussi.

**Exceptions :** `RuntimeException` - Propagée depuis `ping()`.

**Effets de bord :** Identiques à `ping()` en cas de succès.

**Exemple :**
```php
if ($ping->isAlive($device)) {
    // Le token est valide et joignable
}
```

---

### `pingOrPrune(Model $device): PingStatus`

Ping le device et le supprime s'il est définitivement invalide.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$device` | `Model&NotifiableInterface&PingableInterface` | Device à tester et éventuellement purger |

**Retourne :** `PingStatus` - Statut du ping.

**Exceptions :** `RuntimeException` - Propagée depuis `ping()`.

**Effets de bord :**
- Si succès : `last_seen_at` mis à jour.
- Si `INVALID` : suppression (`delete()`) du Model.

**Exemple :**
```php
$status = $ping->pingOrPrune($device);

if ($status === PingStatus::INVALID) {
    // Le device a été supprimé
}
```

---

### `__construct(NotificationServiceInterface $service)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$service` | `NotificationServiceInterface` | Service d'envoi de notifications injecté |

**Exemple :**
```php
$ping = app(FcmPingPong::class); // Résolution automatique via le container
```

## Cas d'utilisation

### Cas 1 : Vérification ponctuelle avant envoi critique

Avant d'envoyer une notification importante, vérifier que le device répond.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Helpers\FcmPingPong;

/** @var FcmPingPong $ping */
if (! $ping->isAlive($device)) {
    throw new RuntimeException('Device injoignable, envoi annulé.');
}

$service->sendNow($device, $message, $record);
```

### Cas 2 : Nettoyage périodique via commande Artisan

Purger tous les tokens définitivement invalides.

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Helpers\FcmPingPong;
use AndyDefer\LaravelNotification\Enums\PingStatus;
use Illuminate\Console\Command;

final class PruneDevicesCommand extends Command
{
    protected $signature = 'fcm:prune-devices';

    public function handle(FcmPingPong $ping): int
    {
        $pruned = 0;

        foreach (Device::cursor() as $device) {
            if ($ping->pingOrPrune($device) === PingStatus::INVALID) {
                $pruned++;
            }
        }

        $this->info("Devices purgés : {$pruned}");

        return self::SUCCESS;
    }
}
```

### Cas 3 : Diagnostic sans suppression

Lister les devices injoignables sans les purger.

```php
$unreachable = Device::all()->reject(
    fn ($device) => $ping->ping($device)->isPong()
);
```

## Flux d'exécution

```
ping($device)
  ├── Vérification instanceof NotifiableInterface → RuntimeException si KO
  ├── Construction NotificationMessageVO (body/subject/type/data)
  ├── Construction FqcnChannelCollection [FirebaseCloudMessagingChannel]
  ├── Construction SendNowRecord (limit_per_channel: 1)
  ├── service->sendNow($device, $message, $record)
  │
  ├── allSuccess() ?
  │     ├── OUI → last_seen_at = now() + save() → PingStatus::PONG
  │     └── NON → extraction error_message
  │           ├── isDefinitelyInvalid() ? → PingStatus::INVALID
  │           └── sinon → PingStatus::UNREACHABLE
  │
pingOrPrune($device)
  └── ping() → si INVALID → $device->delete()
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Device n'implémente pas `NotifiableInterface` | `RuntimeException` | `Device must implement NotifiableInterface.` |
| Échec d'envoi avec code FCM invalide | Aucune (retourne `PingStatus::INVALID`) | — |
| Échec d'envoi non qualifié (réseau, timeout) | Aucune (retourne `PingStatus::UNREACHABLE`) | — |
| Succès d'envoi | Aucune (retourne `PingStatus::PONG`) | — |

**Codes FCM traités comme invalidants** (constante `INVALID_ERROR_CODES`) :
- `UNREGISTERED`
- `INVALID_ARGUMENT`
- `SENDER_ID_MISMATCH`

La détection se fait par `str_contains()` sur le message d'erreur retourné par FCM.

## Intégration

`FcmPingPong` dépend de :

- `NotificationServiceInterface` — injecté au constructeur, exécute l'envoi réel.
- `FirebaseCloudMessagingChannel` — channel cible, référencé via `FqcnChannelVO`.
- `NotificationMessageVO`, `MessageBodyVO`, `MessageSubjectVO` — construction du message.
- `FqcnChannelCollection`, `FqcnChannelVO` — liste des channels.
- `SendNowRecord` — options d'envoi (`limit_per_channel: 1`).
- `StrictDataObject` — payload `data` (device_id, token_id).
- `PingStatus` — enum de retour.
- `NotifiableInterface` et `PingableInterface` — contrats attendus côté device.

Côté consommateur, le helper est typiquement injecté dans :
- Une commande Artisan de purge (`fcm:prune-devices`).
- Un job planifié (cron quotidien/hebdomadaire).
- Un listener qui vérifie la santé avant un envoi critique.

## Performance

- **Coût principal** : un aller-retour réseau vers FCM par appel à `ping()`. Cette opération est **I/O bound**.
- **Complexité interne** : O(n) sur `INVALID_ERROR_CODES` (3 entrées constantes) — négligeable.
- **Écriture DB** : un `save()` par ping réussi (mise à jour `last_seen_at`).
- **Recommandations** :
  - Pour purger un grand volume de devices, utiliser `cursor()` plutôt que `all()` afin d'éviter de charger toute la table en mémoire.
  - Envisager un rate limiting / batch si FCM limite les requêtes.
  - Envisager une queue pour les purges massives (job dispatché plutôt que synchrone).

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1 | ✅ Complet (readonly properties, enums, match) |
| PHP 8.2 | ✅ Complet |
| PHP 8.3 | ✅ Complet |
| PHP 8.0 | ❌ Non supporté (`readonly` properties) |
| Laravel 9 | ⚠️ À vérifier selon la version du package parent |
| Laravel 10+ | ✅ Recommandé |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Enums\PingStatus;
use AndyDefer\LaravelNotification\Helpers\FcmPingPong;
use AndyDefer\LaravelNotification\Models\Device;

/** @var FcmPingPong $ping */
$ping = app(FcmPingPong::class);

// Cas 1 : vérifier un device
$device = Device::findOrFail(1);
$status = $ping->ping($device);

match ($status) {
    PingStatus::PONG => logger()->info("Device {$device->id} OK"),
    PingStatus::INVALID => logger()->warning("Device {$device->id} invalide"),
    PingStatus::UNREACHABLE => logger()->notice("Device {$device->id} injoignable"),
};

// Cas 2 : booléen simple
if ($ping->isAlive($device)) {
    // envoyer une notification
}

// Cas 3 : purge automatique
$status = $ping->pingOrPrune($device);
// Si INVALID → $device supprimé de la base

// Cas 4 : purge en masse
foreach (Device::cursor() as $device) {
    $ping->pingOrPrune($device);
}
```