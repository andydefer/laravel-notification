# NotificationRepository - Référence Technique

## Description

Repository gérant le stockage, la récupération et les mises à jour des notifications. Fournit une API dédiée pour les opérations courantes sur les notifications (marquage, comptage, filtrage par session).

## Hiérarchie / Implémentations

```
AbstractRepository<Notification, NotificationRecord>
    └── NotificationRepository (final)
         └── NotificationRepositoryInterface
```

## Rôle principal

- Stockage et récupération des notifications
- Mise à jour des statuts (SENT, DELIVERED, FAILED)
- Marquage des notifications comme lues
- Opérations par session (session_id)
- Filtrage avancé via `NotificationFilterRecord`

---

## API / Méthodes publiques

### `__construct()`

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
$repository = new NotificationRepository();
```

---

### `markAsRead(string $id): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `string` | UUID de la notification |

**Retourne :** `bool` - `true` si la notification existe et a été marquée comme lue

**Exceptions :** Aucune

**Exemple :**
```php
$repository->markAsRead('550e8400-e29b-41d4-a716-446655440000');
```

---

### `markAsDelivered(string $id): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `string` | UUID de la notification |

**Retourne :** `bool` - `true` si la notification existe et a été marquée comme délivrée

**Exceptions :** Aucune

**Exemple :**
```php
$repository->markAsDelivered('550e8400-e29b-41d4-a716-446655440000');
```

---

### `markAsSent(string $id): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `string` | UUID de la notification |

**Retourne :** `bool` - `true` si la notification existe et a été marquée comme envoyée

**Exceptions :** Aucune

**Exemple :**
```php
$repository->markAsSent('550e8400-e29b-41d4-a716-446655440000');
```

---

### `markAsFailed(string $id, string $error): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$id` | `string` | UUID de la notification |
| `$error` | `string` | Message d'erreur |

**Retourne :** `bool` - `true` si la notification existe et a été marquée comme échouée

**Exceptions :** Aucune

**Exemple :**
```php
$repository->markAsFailed('550e8400-e29b-41d4-a716-446655440000', 'SMTP connection timeout');
```

---

### `markAsReadBySession(string $sessionId): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$sessionId` | `string` | Session UUID |

**Retourne :** `int` - Nombre de notifications marquées comme lues

**Exceptions :** Aucune

**Exemple :**
```php
$count = $repository->markAsReadBySession('session-123');
```

---

### `countByNotifiable(Model $notifiable): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `Model` | L'entité notifiable (User, Order, etc.) |

**Retourne :** `int` - Nombre total de notifications pour cette entité

**Exceptions :** Aucune

**Exemple :**
```php
$user = User::find(1);
$count = $repository->countByNotifiable($user);
```

---

### `countByStatus(Model $notifiable, NotificationStatus $status): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `Model` | L'entité notifiable |
| `$status` | `NotificationStatus` | Statut à compter |

**Retourne :** `int` - Nombre de notifications avec ce statut

**Exceptions :** Aucune

**Exemple :**
```php
$user = User::find(1);
$failed = $repository->countByStatus($user, NotificationStatus::FAILED);
```

---

### `countBySession(string $sessionId): int`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$sessionId` | `string` | Session UUID |

**Retourne :** `int` - Nombre de notifications dans la session

**Exceptions :** Aucune

**Exemple :**
```php
$count = $repository->countBySession('session-123');
```

---

### `findBySession(string $sessionId): Builder`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$sessionId` | `string` | Session UUID |

**Retourne :** `Builder` - Query Builder pour la session

**Exceptions :** Aucune

**Exemple :**
```php
$notifications = $repository->findBySession('session-123')
    ->orderBy('created_at', 'desc')
    ->get();
```

---

### `applyFilters(Builder $query, AbstractRecord $filters): void` (protégé)

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$query` | `Builder` | La requête Eloquent |
| `$filters` | `AbstractRecord` | Les filtres à appliquer |

**Retourne :** `void`

**Exceptions :** Aucune (si `$filters` n'est pas un `NotificationFilterRecord`, la méthode ne fait rien)

---

## Cas d'utilisation

### Cas 1 : Suivi d'une notification

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Repositories\NotificationRepository;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;

$repository = new NotificationRepository();

// 1. Création
$notification = $repository->create($record);

// 2. Envoi
$driver->send($message, $route);

// 3. Marquage
if ($success) {
    $repository->markAsSent($notification->getId());
} else {
    $repository->markAsFailed($notification->getId(), 'SMTP timeout');
}

// 4. Vérification du statut
$updated = $repository->find($notification->getId());
echo $updated->getStatus()->value; // 'sent' ou 'failed'
```

---

### Cas 2 : Affichage des notifications d'un utilisateur

```php
<?php

use AndyDefer\LaravelNotification\Repositories\NotificationRepository;
use AndyDefer\LaravelNotification\Records\NotificationFilterRecord;

$repository = new NotificationRepository();

$user = auth()->user();

// Notifications non lues
$filter = NotificationFilterRecord::from([
    'notifiable_type' => $user->getMorphClass(),
    'notifiable_id' => $user->id,
    'read' => false,
]);

$unread = $repository->findBy($filter);

// Toutes les notifications
$filter = NotificationFilterRecord::from([
    'notifiable_type' => $user->getMorphClass(),
    'notifiable_id' => $user->id,
]);

$all = $repository->findBy($filter);

echo "Non lues : " . $unread->count() . "\n";
echo "Total : " . $all->count() . "\n";
```

---

### Cas 3 : Gestion des sessions

```php
<?php

use AndyDefer\LaravelNotification\Repositories\NotificationRepository;

$repository = new NotificationRepository();

// Création d'une session
$sessionId = UuidVO::generate();

// Envoi de notifications groupées (ex: batch)
foreach ($users as $user) {
    $record = NotificationRecord::from([
        'session_id' => $sessionId,
        'channel' => $channel,
        'destination' => $user->email,
        'notifiable_type' => $user->getMorphClass(),
        'notifiable_id' => $user->id,
        'message' => $message,
    ]);
    
    $repository->create($record);
}

// Vérification du nombre
$count = $repository->countBySession($sessionId->getValue());

// Marquer toutes les notifications de la session comme lues
$repository->markAsReadBySession($sessionId->getValue());
```

---

### Cas 4 : Statistiques par statut

```php
<?php

use AndyDefer\LaravelNotification\Repositories\NotificationRepository;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;

$repository = new NotificationRepository();
$user = User::find(1);

$stats = [
    'total' => $repository->countByNotifiable($user),
    'sent' => $repository->countByStatus($user, NotificationStatus::SENT),
    'delivered' => $repository->countByStatus($user, NotificationStatus::DELIVERED),
    'failed' => $repository->countByStatus($user, NotificationStatus::FAILED),
    'pending' => $repository->countByStatus($user, NotificationStatus::PENDING),
];

echo "📊 Statistiques des notifications\n";
echo "Total : {$stats['total']}\n";
echo "✅ Envoyées : {$stats['sent']}\n";
echo "📬 Délivrées : {$stats['delivered']}\n";
echo "❌ Échouées : {$stats['failed']}\n";
echo "⏳ En attente : {$stats['pending']}\n";
```

---

## Flux d'exécution

### Marquage d'une notification

```
markAsSent(id)
    ↓
find(id)
    ↓
    ├── null → false
    └── model → update(['status' => 'sent', 'sent_at' => now()])
         ↓
         true
```

### Comptage par statut

```
countByStatus(notifiable, status)
    ↓
Création du filtre
    ↓
count(filters)
    ↓
applyFilters() → where statut
    ↓
count() → retourne int
```

---

## Gestion des erreurs

| Situation | Retour | Message |
|-----------|--------|---------|
| Notification introuvable | `false` | - |
| Filtre invalide | Aucune erreur | - |

---

## Intégration

### Avec le service provider

```php
<?php

namespace App\Providers;

use AndyDefer\LaravelNotification\Repositories\NotificationRepository;
use AndyDefer\LaravelNotification\Contracts\Repositories\NotificationRepositoryInterface;
use Illuminate\Support\ServiceProvider;

final class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            NotificationRepositoryInterface::class,
            function () {
                return new NotificationRepository();
            }
        );
    }
}
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `markAsRead()` | O(1) | Update par ID |
| `markAsSent()` | O(1) | Update par ID |
| `markAsFailed()` | O(1) | Update par ID |
| `markAsReadBySession()` | O(n) | n = nombre de notifications dans la session |
| `countByNotifiable()` | O(1) | Count avec index |
| `countByStatus()` | O(1) | Count avec index |
| `findBySession()` | O(1) | Query Builder |

**Optimisations :**
- Index sur `session_id`
- Index composite sur (`notifiable_type`, `notifiable_id`)
- Index sur `status` pour les comptages rapides

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

use AndyDefer\LaravelNotification\Repositories\NotificationRepository;
use AndyDefer\LaravelNotification\Records\NotificationFilterRecord;
use AndyDefer\LaravelNotification\Records\NotificationRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\UuidVO;
use App\Models\User;

// 1. Création du repository
$repository = new NotificationRepository();

// 2. Création d'une notification
$message = new NotificationMessageVO(
    'Bienvenue',
    'Bienvenue sur notre plateforme'
);

$record = NotificationRecord::from([
    'id' => UuidVO::generate(),
    'session_id' => UuidVO::generate(),
    'channel' => 'email',
    'destination' => 'john@example.com',
    'notifiable_type' => User::class,
    'notifiable_id' => 1,
    'message' => $message,
    'status' => 'pending',
]);

$notification = $repository->create($record);

// 3. Envoi (simulé)
$success = true;

// 4. Mise à jour du statut
if ($success) {
    $repository->markAsSent($notification->getId());
} else {
    $repository->markAsFailed($notification->getId(), 'SMTP error');
}

// 5. Consultation
$user = User::find(1);
$filter = NotificationFilterRecord::from([
    'notifiable_type' => $user->getMorphClass(),
    'notifiable_id' => $user->id,
    'status' => 'sent',
]);

$sentNotifications = $repository->findBy($filter);

echo "Notifications envoyées : " . $sentNotifications->count() . "\n";

foreach ($sentNotifications as $notif) {
    echo "- " . $notif->getMessage()->getSubject() . "\n";
    echo "  Délivré le : " . $notif->getSentAt() . "\n";
}
```