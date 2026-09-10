# HasNotifications - Référence Technique

## Description

Trait fournissant des utilitaires pour les modèles Eloquent qui peuvent recevoir des notifications. Encapsule le `NotificationRepositoryInterface` pour éviter la duplication de logique.

## Hiérarchie / Implémentations

```
HasNotifications (trait)
    └── Utilisé par tout Model qui reçoit des notifications
```

## Rôle principal

Exposer une API riche et idiomatique pour manipuler les notifications d'un modèle, sans dupliquer la logique d'accès aux données. Le trait s'appuie sur `NotificationRepositoryInterface` et sur les attributs Eloquent (`Attribute`) pour fournir :

- Des **attributs calculés** (`$user->unread_notifications_count`, `$user->database_notifications`, etc.)
- Des **méthodes d'action** (`markNotificationAsRead()`, `deleteAllNotifications()`, etc.)
- Une **relation morphMany** native vers le modèle `Notification`

## Prérequis

- Le modèle doit étendre `Illuminate\Database\Eloquent\Model`
- Le modèle doit utiliser le package `andydefer/laravel-notification`
- Le modèle doit implémenter une colonne polymorphe compatible avec la table `notifications`

## API / Méthodes publiques

### `notifications(): MorphMany`

Retourne la relation polymorphe vers les notifications du modèle.

**Retourne :** `MorphMany` - La relation Eloquent

**Exemple :**
```php
foreach ($user->notifications as $notification) {
    echo $notification->getSubject();
}
```

---

### `markNotificationAsRead(string $notificationId): bool`

Marque une notification spécifique comme lue.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notificationId` | `string` | L'UUID de la notification |

**Retourne :** `bool` - `true` si la mise à jour a réussi, `false` sinon

**Exemple :**
```php
$user->markNotificationAsRead('550e8400-e29b-41d4-a716-446655440000');
```

---

### `markAllNotificationsAsRead(): int`

Marque toutes les notifications non lues comme lues.

**Retourne :** `int` - Nombre de notifications mises à jour

**Exemple :**
```php
$count = $user->markAllNotificationsAsRead();
```

---

### `deleteNotification(string $notificationId): bool`

Supprime (soft delete) une notification spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notificationId` | `string` | L'UUID de la notification |

**Retourne :** `bool` - `true` si la suppression a réussi

**Exemple :**
```php
$user->deleteNotification('550e8400-e29b-41d4-a716-446655440000');
```

---

### `deleteAllNotifications(): int`

Supprime toutes les notifications du modèle (soft delete).

**Retourne :** `int` - Nombre de notifications supprimées

**Exemple :**
```php
$deleted = $user->deleteAllNotifications();
```

---

### `deleteReadNotifications(): int`

Supprime uniquement les notifications déjà lues.

**Retourne :** `int` - Nombre de notifications supprimées

**Exemple :**
```php
$deleted = $user->deleteReadNotifications();
```

---

### `countNotificationsByStatus(NotificationStatus $status): int`

Compte les notifications ayant un statut spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$status` | `NotificationStatus` | Le statut cible |

**Retourne :** `int` - Nombre de notifications

**Exemple :**
```php
$sent = $user->countNotificationsByStatus(NotificationStatus::SENT);
```

---

### `notificationsByChannel(string $channel, int $limit = 10): Collection`

Récupère les notifications d'un canal spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$channel` | `string` | Le FQCN du canal (ex: `MailChannel::class`) |
| `$limit` | `int` | Nombre maximum de résultats (défaut: 10) |

**Retourne :** `Collection<int, Notification>` - Collection de notifications triées par date de création décroissante

**Exemple :**
```php
$mails = $user->notificationsByChannel(MailChannel::class, 5);
```

---

## Attributs calculés

Le trait expose les attributs suivants via la méthode `Attribute` d'Eloquent. Ils sont accessibles directement comme des propriétés du modèle.

| Attribut | Type | Description |
|----------|------|-------------|
| `unread_notifications_count` | `int` | Nombre de notifications non lues |
| `unread_notifications` | `Collection<int, Notification>` | Collection des notifications non lues |
| `read_notifications` | `Collection<int, Notification>` | Collection des notifications lues |
| `sent_notifications` | `Collection<int, Notification>` | Collection des notifications envoyées |
| `pending_notifications` | `Collection<int, Notification>` | Collection des notifications en attente |
| `failed_notifications` | `Collection<int, Notification>` | Collection des notifications échouées |
| `latest_notifications` | `Collection<int, Notification>` | Les 10 dernières notifications (tous canaux confondus) |
| `database_notifications` | `Collection<int, Notification>` | Toutes les notifications du canal `DatabaseChannel` |
| `latest_database_notifications` | `Collection<int, Notification>` | Les 10 dernières notifications du canal `DatabaseChannel` |
| `has_unread_notifications` | `bool` | Indique s'il y a des notifications non lues |
| `has_notifications` | `bool` | Indique s'il y a au moins une notification |

## Cas d'utilisation

### Cas 1 : Afficher le badge de notifications non lues

**Problème :** Afficher le nombre de notifications non lues dans la navigation.

```php
// Dans un middleware Inertia
public function share(Request $request): array
{
    $user = auth()->user();

    return [
        'unreadNotificationsCount' => $user?->unread_notifications_count ?? 0,
    ];
}
```

### Cas 2 : Afficher les notifications en base de données pour un panneau dédié

**Problème :** Récupérer uniquement les notifications persistées en base (`DatabaseChannel`) pour un panneau d'historique.

```php
// Dans un contrôleur
public function databaseNotifications(Request $request)
{
    $notifications = $request->user()->database_notifications;

    return NotificationData::collect($notifications);
}
```

### Cas 3 : Afficher les dernières notifications en base pour un widget

**Problème :** Récupérer les 10 dernières notifications du canal `DatabaseChannel` pour un widget de notifications récentes.

```php
// Dans un composant Inertia
$latest = $request->user()->latest_database_notifications;
```

### Cas 4 : Marquer toutes les notifications comme lues à la fermeture du panneau

**Problème :** Quand l'utilisateur ouvre son panneau de notifications, tout marquer comme lu.

```php
// Dans un contrôleur
public function markAllAsRead(Request $request)
{
    $count = $request->user()->markAllNotificationsAsRead();

    return response()->json(['marked' => $count]);
}
```

### Cas 5 : Afficher les dernières notifications d'un canal spécifique

**Problème :** Récupérer uniquement les notifications par email pour un affichage ciblé.

```php
// Dans un contrôleur
public function mailNotifications(Request $request)
{
    $notifications = $request->user()->notificationsByChannel(
        MailChannel::class,
        limit: 20
    );

    return NotificationData::collect($notifications);
}
```

## Flux d'exécution

### Attribut simple

```
$user->unread_notifications_count
    → notificationRepository()->count()
        → notificationFilter(['read' => false])
            → NotificationRepository::count($filter)
                → SELECT COUNT(*) FROM notifications WHERE notifiable_type = ? AND notifiable_id = ? AND read_at IS NULL
```

### Attribut filtré par canal

```
$user->latest_database_notifications
    → notificationRepository()->findBy(FindByRecord)
        → notificationFilter(['channel' => DatabaseChannel::class])
            → NotificationRepository::findBy()
                → SELECT * FROM notifications WHERE notifiable_type = ? AND notifiable_id = ? AND channel = ? ORDER BY created_at DESC LIMIT 10
```

## Gestion des erreurs

| Situation | Comportement |
|-----------|--------------|
| Repository non disponible | `app()` lève une exception Laravel standard |
| `NotificationFilterRecord::from()` avec données invalides | `InvalidArgumentException` de `AbstractRecord` |
| Modèle n'implémentant pas `Model` | Erreur PHPStan (déclaré via `@phpstan-require-extends Model`) |
| Notification introuvable | Les méthodes `markAsRead()` retournent `false` |

## Intégration

### Dépendances

| Dépendance | Utilisation |
|-----------|-------------|
| `NotificationRepositoryInterface` | Accès aux données |
| `NotificationFilterRecord` | Construction des filtres |
| `FindByRecord` | Requêtes de recherche |
| `SortColumns` | Tri des résultats |
| `Attribute` | Attributs calculés Eloquent |
| `MorphMany` | Relation polymorphe |
| `DatabaseChannel` | Filtre spécifique pour les notifications en base |

### Dépendances externes

- `andydefer/laravel-repository` (pour `FindByRecord`, `SortColumns`)

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `notifications()` | O(1) | Relation Eloquent paresseuse |
| `unread_notifications_count` | 1 requête `COUNT` | Peut être mis en cache |
| `unread_notifications` | 1 requête `SELECT` | Utiliser `latest_notifications` pour limiter |
| `database_notifications` | 1 requête `SELECT` filtrée par canal | Index sur `channel` recommandé |
| `latest_database_notifications` | 1 requête `SELECT` avec `LIMIT` | Optimisé |
| `markAllNotificationsAsRead()` | N requêtes `UPDATE` | **Optimisable** en 1 requête `UPDATE` groupée |
| `notificationsByChannel()` | 1 requête `SELECT` avec `LIMIT` | Optimisé |

**Optimisation recommandée** : pour `markAllNotificationsAsRead()`, remplacer la boucle par un appel direct au repository avec `updateBulk()` si disponible.

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.2+ | ✅ Complet |
| PHP 8.1 | ✅ Complet |
| PHP 8.0 | ⚠️ Non testé |

| Version Laravel | Support |
|-----------------|---------|
| Laravel 11+ | ✅ |
| Laravel 10+ | ✅ |

## Exemple complet

```php
<?php

declare(strict_types=1);

namespace App\Models;

use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\LaravelNotification\Traits\HasNotifications;
use Illuminate\Foundation\Auth\User as Authenticatable;

final class User extends Authenticatable
{
    use HasNotifications;
}

// Utilisation
$user = User::find(1);

// Attributs calculés
echo $user->unread_notifications_count;      // 3
$unread = $user->unread_notifications;        // Collection
$latest = $user->latest_notifications;        // Collection (10 items)

// Attributs spécifiques au canal Database
$dbNotifications = $user->database_notifications;         // Collection
$latestDb = $user->latest_database_notifications;         // Collection (10 items)

if ($user->has_unread_notifications) {
    // Afficher un badge
}

// Actions
$user->markNotificationAsRead($notificationId);
$user->markAllNotificationsAsRead();
$user->deleteReadNotifications();

// Par canal (méthode générique)
$mails = $user->notificationsByChannel(MailChannel::class, limit: 5);
$db = $user->notificationsByChannel(DatabaseChannel::class, limit: 20);

// Compter par statut
$sentCount = $user->countNotificationsByStatus(NotificationStatus::SENT);
```

## Voir aussi

- `NotificationRepositoryInterface` - Repository sous-jacent
- `NotificationFilterRecord` - Record de filtres
- `Notification` - Modèle Eloquent
- `NotificationData` - DTO de sortie API
- `DatabaseChannel` - Canal de persistance en base
- `MailChannel` - Canal d'envoi par email
```