# NotificationSenderProcessor - Référence Technique

## Description

Le `NotificationSenderProcessor` est le moteur d'exécution des notifications. Il orchestre l'intégralité du processus d'envoi : résolution des routes, application des filtres, limitation des canaux, création des notifications en base de données et dispatching vers les drivers appropriés.

## Hiérarchie / Implémentations

```
NotificationSenderProcessor (final)
    └── NotificationSenderProcessorInterface
```

**Interfaces implémentées :**
- `NotificationSenderProcessorInterface` - Contrat principal du processeur

## Rôle principal

Ce processeur agit comme l'orchestrateur central de l'envoi des notifications :

1. **Résolution des routes** - Sélectionne les canaux disponibles pour une entité
2. **Application des filtres** - Filtre les destinations par canal
3. **Limitation des canaux** - Applique `limit_per_channel` pour contrôler le nombre de destinations
4. **Création des notifications** - Persiste chaque notification en base de données avec statut PENDING
5. **Dispatching vers les drivers** - Exécute chaque driver et met à jour le statut (SENT/FAILED)
6. **Journalisation** - Log les échecs avec le contexte complet

## API / Méthodes publiques

### `send(NotifiableInterface&Model $notifiable, NotificationMessageVO $message, ProcessNotificationRecord $processRecord, ?array $destinationFilters = null): SendResultCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface&Model` | Entité recevant la notification |
| `$message` | `NotificationMessageVO` | Message à envoyer |
| `$processRecord` | `ProcessNotificationRecord` | Configuration du traitement (canaux, limite) |
| `$destinationFilters` | `array|null` | Filtres de destination par canal |

**Retourne :** `SendResultCollection` - Collection des résultats d'envoi

**Exceptions :** 
- `RuntimeException` si aucun canal disponible
- `RuntimeException` si aucun filtre ne correspond
- `RuntimeException` si la limite par canal ne laisse aucune route

**Exemple :**
```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Processors\NotificationSenderProcessor;
use AndyDefer\LaravelNotification\Records\ProcessNotificationRecord;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\LaravelNotification\ValueObjects\FqcnChannelVO;

$processor = app(NotificationSenderProcessor::class);

$channels = new FqcnChannelCollection;
$channels->add(new FqcnChannelVO(MailChannel::class));

$processRecord = new ProcessNotificationRecord(
    channels: $channels,
    limit_per_channel: 1
);

$destinationFilters = [
    MailChannel::class => ['user@example.com'],
];

$results = $processor->send($user, $message, $processRecord, $destinationFilters);

foreach ($results as $result) {
    if ($result->success) {
        echo "✅ Envoyé à {$result->destination}\n";
    } else {
        echo "❌ Échec: {$result->error_message->getValue()}\n";
    }
}
```

## Cas d'utilisation

### Cas 1 : Envoi sur tous les canaux disponibles

```php
<?php

use AndyDefer\LaravelNotification\Processors\NotificationSenderProcessor;
use AndyDefer\LaravelNotification\Records\ProcessNotificationRecord;

$processor = app(NotificationSenderProcessor::class);

// ✅ Aucun canal spécifié → tous les canaux disponibles sont utilisés
$processRecord = new ProcessNotificationRecord;

$results = $processor->send($user, $message, $processRecord);

// Résultats pour chaque destination (Mail, SMS, Database, etc.)
foreach ($results as $result) {
    echo "{$result->channel->getValue()}: {$result->destination}\n";
}
```

### Cas 2 : Envoi avec filtres de destination

```php
<?php

use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\Processors\NotificationSenderProcessor;
use AndyDefer\LaravelNotification\Records\ProcessNotificationRecord;

$processor = app(NotificationSenderProcessor::class);

$processRecord = new ProcessNotificationRecord;

// ✅ Filtrer : email uniquement vers l'email principal, SMS uniquement vers le numéro pro
$destinationFilters = [
    MailChannel::class => ['john@example.com'],
    SmsChannel::class => ['+33123456789'],
];

$results = $processor->send($user, $message, $processRecord, $destinationFilters);

// Seules les destinations correspondantes sont utilisées
```

### Cas 3 : Envoi avec limitation par canal

```php
<?php

use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Processors\NotificationSenderProcessor;
use AndyDefer\LaravelNotification\Records\ProcessNotificationRecord;

$processor = app(NotificationSenderProcessor::class);

$processRecord = new ProcessNotificationRecord(
    limit_per_channel: 1  // ✅ Une seule destination par canal
);

$results = $processor->send($user, $message, $processRecord);

// L'utilisateur a plusieurs emails, un seul sera utilisé
// L'utilisateur a plusieurs SMS, un seul sera utilisé
```

### Cas 4 : Envoi avec filtres et limitation combinés

```php
<?php

use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Processors\NotificationSenderProcessor;
use AndyDefer\LaravelNotification\Records\ProcessNotificationRecord;

$processor = app(NotificationSenderProcessor::class);

$processRecord = new ProcessNotificationRecord(
    limit_per_channel: 1
);

$destinationFilters = [
    MailChannel::class => ['john@example.com', 'admin@example.com'],
];

$results = $processor->send($user, $message, $processRecord, $destinationFilters);

// ✅ Filtre garde 2 destinations, limite réduit à 1
// Résultat : un seul email envoyé
```

## Flux d'exécution

```
send()
    │
    ▼
┌─────────────────────────────────────────────────────────────────┐
│ 1. Récupération des routes                                     │
│    $availableRoutes = $notifiable->getNotificationChannels()   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 2. Résolution des canaux                                       │
│    resolveRoutes($channels, $availableRoutes)                  │
│    ├── channels vide → toutes les routes                       │
│    └── channels spécifiés → filtre par canal                   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 3. Application des filtres de destination                      │
│    applyDestinationFilters($routes, $destinationFilters)       │
│    ├── pas de filtre → toutes les routes                       │
│    └── filtre présent → garde uniquement les destinations     │
│        correspondantes                                          │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 4. Application de la limite par canal                          │
│    applyLimitPerChannel($routes, $limit_per_channel)           │
│    ├── null ou ≤ 0 → toutes les routes                         │
│    └── > 0 → garde N routes par canal                          │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 5. Génération de l'ID de session                               │
│    $sessionId = UuidVO::generate()                             │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 6. Boucle sur chaque route                                     │
│    foreach ($routes as $route) {                               │
│      ├── createNotification() → notification en PENDING       │
│      ├── sendViaDriver() → envoi via le driver                │
│      │   ├── succès → status SENT, sent_at = now              │
│      │   └── échec → status FAILED, error = message           │
│      └── add result to collection                             │
│    }                                                           │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│ 7. Retourne SendResultCollection                               │
│    - Résultats par destination                                 │
│    - Statut SENT ou FAILED                                     │
│    - Messages d'erreur (le cas échéant)                       │
└─────────────────────────────────────────────────────────────────┘
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucun canal disponible | `RuntimeException` | `No available channels for notifiable {type}#{id}` |
| Aucune route après filtres | `RuntimeException` | `No routes after applying destination filters for notifiable {type}#{id}` |
| Aucune route après limitation | `RuntimeException` | `No routes after applying limit for notifiable {type}#{id}` |
| Échec du driver | `Exception` (capturée) | Message de l'exception originale |
| Driver non configuré | `RuntimeException` | `Driver {class} configuration is invalid.` |

## Intégration

### Dépendances injectées

```
NotificationSenderProcessor
    ├── NotificationRepositoryInterface    → Persistance des notifications
    └── LoggerInterface                     → Journalisation des échecs
```

### Relations avec les autres composants

```
┌─────────────────────────────────────────────────────────────────┐
│                    NotificationService                         │
│              (point d'entrée utilisateur)                       │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│              NotificationSenderProcessor                       │
│              (orchestrateur d'envoi)                           │
└────────────────────────┬────────────────────────────────────────┘
                         │
         ┌───────────────┼───────────────┐
         │               │               │
         ▼               ▼               ▼
┌─────────────────┐┌─────────────────┐┌─────────────────┐
│ Notification    ││    Driver       ││    Logger       │
│ Repository      ││    (Mail, SMS)  ││    (Journal)    │
└─────────────────┘└─────────────────┘└─────────────────┘
```

### NotificationRecord

Le processeur crée des `NotificationRecord` avec les champs suivants :

```php
NotificationRecord::from([
    'id' => UuidVO::generate(),
    'session_id' => $sessionId,
    'channel' => new FqcnChannelVO($route->getChannelClass()),
    'destination' => $route->getDestination(),
    'notifiable_type' => $notifiable->getMorphClass(),
    'notifiable_id' => $notifiable->getKey(),
    'message' => $message,
    'metadata' => $route->getMetadata(),
    'status' => NotificationStatus::PENDING,
]);
```

## Performance

| Opération | Complexité | Description |
|-----------|-----------|-------------|
| `resolveRoutes()` | O(n) | n = nombre de routes disponibles |
| `applyDestinationFilters()` | O(n*m) | n = routes, m = filtres |
| `applyLimitPerChannel()` | O(n) | n = routes |
| `sendViaDriver()` | O(1) par route | Dépend du driver |
| `createNotification()` | O(1) | Insertion en base de données |

**Recommandations :**
- Utiliser `limit_per_channel` pour éviter les spams sur les canaux avec plusieurs destinations
- Les filtres de destination sont appliqués **avant** la limite par canal
- Les notifications sont persistées avant l'envoi pour garantir la traçabilité

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.0 | ✅ Complet |
| Laravel 12.x | ✅ Complet |
| Laravel 13.x | ✅ Complet |
| Laravel 14.x | ✅ Complet |
| Laravel 15.x | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\Channels\SlackChannel;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\LaravelNotification\Processors\NotificationSenderProcessor;
use AndyDefer\LaravelNotification\Records\ProcessNotificationRecord;
use AndyDefer\LaravelNotification\ValueObjects\FqcnChannelVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;

// 1. Configuration du processeur
$processor = app(NotificationSenderProcessor::class);

// 2. Création du message
$message = new NotificationMessageVO(
    body: new MessageBodyVO('Votre commande #12345 a été expédiée.'),
    subject: new MessageSubjectVO('Commande expédiée'),
    type: 'order_shipped',
);

// 3. Configuration des canaux
$channels = new FqcnChannelCollection;
$channels->add(new FqcnChannelVO(MailChannel::class));
$channels->add(new FqcnChannelVO(SmsChannel::class));
$channels->add(new FqcnChannelVO(SlackChannel::class));

// 4. Configuration du traitement
$processRecord = new ProcessNotificationRecord(
    channels: $channels,
    limit_per_channel: 1,
);

// 5. Filtres de destination
$destinationFilters = [
    MailChannel::class => ['client@example.com'],
    SmsChannel::class => ['+33123456789'],
    SlackChannel::class => ['#commandes'],
];

// 6. Envoi
try {
    $results = $processor->send($user, $message, $processRecord, $destinationFilters);

    echo "📊 Résultats d'envoi :\n";
    echo "   ✅ Succès : " . $results->getSuccessCount() . "\n";
    echo "   ❌ Échecs : " . $results->getFailureCount() . "\n";

    foreach ($results as $result) {
        $status = $result->success ? '✅' : '❌';
        echo sprintf(
            "   %s %s → %s\n",
            $status,
            $result->channel->getValue(),
            $result->destination
        );
        if (!$result->success && $result->error_message) {
            echo "      Erreur : " . $result->error_message->getValue() . "\n";
        }
    }
} catch (RuntimeException $e) {
    echo "❌ Erreur : " . $e->getMessage() . "\n";
}
```

## Voir aussi
- `NotificationService` - Service principal utilisant ce processeur
- `ProcessNotificationRecord` - Record de configuration
- `SendResultCollection` - Collection des résultats
- `NotificationRouteVO` - Value Object des routes
- `AbstractDriver` - Classe de base des drivers
- `NotificationRepository` - Repository de persistance
- `Notification` - Modèle Eloquent