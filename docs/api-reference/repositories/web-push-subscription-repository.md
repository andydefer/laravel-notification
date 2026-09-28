# WebPushSubscriptionRepositoryInterface - Référence Technique

## Description

Contrat de persistance dédié aux souscriptions Web Push, exposant une opération d'upsert idempotente basée sur l'endpoint.

## Hiérarchie

```
AbstractRepositoryInterface
    └── WebPushSubscriptionRepositoryInterface
            └── WebPushSubscriptionRepository (implémentation)
```

## Rôle principal

Définir le contrat de persistance des souscriptions Web Push. L'interface hérite de toute l'API générique de `AbstractRepositoryInterface` (create, update, find, delete, paginate, etc.) et ajoute une méthode dédiée `upsertFor()` qui exploite l'unicité fonctionnelle de l'endpoint.

## Prérequis

- L'implémentation concrète (`WebPushSubscriptionRepository`) doit être liée à cette interface dans le conteneur d'injection.
- Le modèle `WebPushSubscription` doit exister et utiliser une clé primaire UUID.
- La table `web_push_subscriptions` doit être créée par migration.

## API / Méthodes publiques

### `upsertFor(WebPushSubscriptionRecord $record): WebPushSubscription`

Persiste une souscription identifiée par son endpoint unique. Si une ligne existe déjà avec le même endpoint, elle est mise à jour ; sinon, une nouvelle ligne est créée.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `WebPushSubscriptionRecord` | Données de la souscription à persister |

**Retourne :** `WebPushSubscription` - L'instance persistée (créée ou mise à jour)

**Exceptions :** Aucune n'est définie par le contrat. L'implémentation peut lever `QueryException` en cas d'erreur base de données.

**Exemple :**
```php
$subscription = $repository->upsertFor(WebPushSubscriptionRecord::from([
    'endpoint' => 'https://jmt17.google.com/fcm/send/...',
    'p256dh' => 'BGtKsvWiILk_...',
    'auth' => 'hv_Nwds7IHarbxh2KChgEw',
    'browser' => 'Chrome',
    'user_agent' => 'Mozilla/5.0 ...',
    'notifiable_type' => $user->getMorphClass(),
    'notifiable_id' => (string) $user->getKey(),
]));
```

## Cas d'utilisation

### Cas 1 : Enregistrement initial d'une souscription

Un utilisateur active les notifications pour la première fois.

```php
$repository->upsertFor(WebPushSubscriptionRecord::from([
    'endpoint' => $endpoint,
    'p256dh' => $p256dh,
    'auth' => $auth,
    'notifiable_type' => $user->getMorphClass(),
    'notifiable_id' => (string) $user->getKey(),
]));
// → INSERT en base, retourne la nouvelle ligne
```

### Cas 2 : Re-souscription avec le même endpoint

Le service worker envoie à nouveau la même souscription après un rafraîchissement de page.

```php
$repository->upsertFor(WebPushSubscriptionRecord::from([
    'endpoint' => $endpoint, // même endpoint qu'avant
    'p256dh' => $p256dh,
    'auth' => $auth,
    'notifiable_type' => $user->getMorphClass(),
    'notifiable_id' => (string) $user->getKey(),
]));
// → UPDATE de la ligne existante, même id
```

### Cas 3 : Réaffectation d'une souscription à un autre utilisateur

Un même appareil navigue avec un autre compte utilisateur.

```php
$repository->upsertFor(WebPushSubscriptionRecord::from([
    'endpoint' => $endpoint,
    'p256dh' => $p256dh,
    'auth' => $auth,
    'notifiable_type' => $anotherUser->getMorphClass(),
    'notifiable_id' => (string) $anotherUser->getKey(),
]));
// → UPDATE de la ligne : notifiable_id change, endpoint reste identique
```

## Flux d'exécution

```
Appel upsertFor($record)
    ↓
SELECT * FROM web_push_subscriptions WHERE endpoint = ?
    ├── Ligne trouvée → UPDATE (notifiable_type, notifiable_id,
    │                          p256dh, auth, browser, user_agent, last_seen_at)
    └── Aucune ligne → INSERT
    ↓
Retourne WebPushSubscription
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Contrainte SQL violée (endpoint dupliqué en concurrence) | `Illuminate\Database\QueryException` | Message dépendant du SGBD |
| Enregistrement sans endpoint | `Illuminate\Database\QueryException` | `NOT NULL constraint failed` |
| Erreur de connexion | `Illuminate\Database\QueryException` | Message dépendant du SGBD |

## Intégration

| Composant | Rôle |
|-----------|------|
| `AbstractRepositoryInterface` | Contrat parent fournissant l'API générique |
| `WebPushSubscriptionRepository` | Implémentation concrète |
| `WebPushSubscriptionRecord` | DTO interne utilisé par `upsertFor()` |
| `WebPushSubscription` | Modèle Eloquent retourné |
| `RegisterWebPushSubscriptionAction` | Consommateur principal via l'injection de dépendances |

## Performance

- **Coût** : 1 `SELECT` suivi d'un `INSERT` ou `UPDATE`. Deux requêtes dans le pire cas.
- **Indexation** : l'endpoint possède un index unique, ce qui rend la recherche O(log n).
- **Concurrence** : deux appels simultanés avec le même endpoint peuvent déclencher une `QueryException` sur l'index unique. Un `try/catch` autour de `upsertFor()` est recommandé dans les contextes hautement concurrents.
- **Idempotence** : garantir que N appels avec le même endpoint ne produisent qu'une seule ligne.

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

use AndyDefer\LaravelNotification\Contracts\Repositories\WebPushSubscriptionRepositoryInterface;
use AndyDefer\LaravelNotification\Records\WebPushSubscriptionRecord;

/** @var WebPushSubscriptionRepositoryInterface $repository */
$repository = app(WebPushSubscriptionRepositoryInterface::class);

$subscription = $repository->upsertFor(WebPushSubscriptionRecord::from([
    'endpoint' => 'https://jmt17.google.com/fcm/send/c9ptagJdvGY:APA91b...',
    'p256dh' => 'BGtKsvWiILk_CxgTlaz1ajvawoh2FtUIiUUIPMyQ_wG6yS-9SwG3bK4KqusuVDmftoZqIz8LnWRl5tJPs053iYI',
    'auth' => 'hv_Nwds7IHarbxh2KChgEw',
    'browser' => 'Chrome',
    'user_agent' => 'Mozilla/5.0 ...',
    'notifiable_type' => 'user',
    'notifiable_id' => '42',
]));

echo $subscription->id;        // UUID
echo $subscription->endpoint;  // URL de l'endpoint
```