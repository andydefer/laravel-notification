# SendOptions - Référence Technique

## Description

`SendOptions` est un conteneur de configuration pour l'envoi de notifications. Il permet de définir les canaux à utiliser, la limite par canal et les filtres de destination de manière fluide et immuable.

## Hiérarchie / Implémentations

```
SendOptions (final)
    └── Aucune interface implémentée
```

## Rôle principal

Cette classe agit comme un **builder d'options** pour le service de notification :

1. **Définition des canaux** - Sélection des canaux à utiliser (Mail, SMS, Slack, etc.)
2. **Limitation par canal** - Contrôle du nombre de destinations par canal
3. **Filtrage des destinations** - Restriction des destinations autorisées par canal
4. **Immutabilité** - Chaque modification retourne une nouvelle instance
5. **Fluidité** - Interface fluide pour le chaînage des méthodes

## API / Méthodes publiques

### `__construct(?FqcnChannelCollection $channels = null, ?int $limitPerChannel = null, ?StrictAssociative $destinationFilters = null)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$channels` | `FqcnChannelCollection|null` | Canaux à utiliser |
| `$limitPerChannel` | `int|null` | Nombre max de destinations par canal |
| `$destinationFilters` | `StrictAssociative|null` | Filtres de destination par canal |

**Exemple :**
```php
<?php

use AndyDefer\LaravelNotification\Options\SendOptions;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Collections\FqcnChannelCollection;
use AndyDefer\DomainStructures\Utils\StrictAssociative;

$channels = new FqcnChannelCollection;
$channels->add(new FqcnChannelVO(MailChannel::class));

$filters = new StrictAssociative([
    MailChannel::class => ['user@example.com'],
]);

$options = new SendOptions(
    channels: $channels,
    limitPerChannel: 1,
    destinationFilters: $filters,
);
```

---

### `static init(): self`

Crée une nouvelle instance de `SendOptions`.

**Retourne :** `self` - Nouvelle instance

**Exemple :**
```php
$options = SendOptions::init();
```

---

### `withChannel(string $channelClass): self`

Ajoute un canal à la configuration.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$channelClass` | `string` | FQCN du canal (ex: `MailChannel::class`) |

**Retourne :** `self` - Nouvelle instance avec le canal ajouté

**Exemple :**
```php
$options = SendOptions::init()
    ->withChannel(MailChannel::class)
    ->withChannel(SmsChannel::class);
```

---

### `withChannels(array $channelClasses): self`

Ajoute plusieurs canaux à la configuration.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$channelClasses` | `array<string>` | Tableau de FQCN de canaux |

**Retourne :** `self` - Nouvelle instance avec les canaux ajoutés

**Comportement :** Fusionne avec les canaux existants et évite les doublons

**Exemple :**
```php
$options = SendOptions::init()
    ->withChannels([MailChannel::class, SmsChannel::class, SlackChannel::class]);
```

---

### `withLimitPerChannel(int $limit): self`

Définit la limite de destinations par canal.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$limit` | `int` | Nombre maximum de destinations par canal |

**Retourne :** `self` - Nouvelle instance avec la limite définie

**Exemple :**
```php
$options = SendOptions::init()
    ->withChannel(MailChannel::class)
    ->withLimitPerChannel(1);  // Un seul email envoyé
```

---

### `withDestinationFilter(string $channelClass, string|array $destinations): self`

Ajoute un filtre de destination pour un canal spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$channelClass` | `string` | FQCN du canal |
| `$destinations` | `string|array<string>` | Destination(s) autorisée(s) |

**Retourne :** `self` - Nouvelle instance avec le filtre ajouté

**Comportement :** Les filtres sont cumulatifs (ajout aux filtres existants)

**Exemple :**
```php
$options = SendOptions::init()
    ->withChannel(MailChannel::class)
    ->withDestinationFilter(MailChannel::class, 'user@example.com')
    ->withDestinationFilter(MailChannel::class, ['admin@example.com', 'support@example.com']);
```

---

### `withDestinationFilters(array $filters): self`

Remplace tous les filtres de destination.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$filters` | `array<string, array<string>>` | Nouveaux filtres |

**Retourne :** `self` - Nouvelle instance avec les filtres remplacés

**Exemple :**
```php
$options = SendOptions::init()
    ->withDestinationFilters([
        MailChannel::class => ['user@example.com'],
        SmsChannel::class => ['+33123456789'],
    ]);
```

---

### `getDestinationFilters(): ?StrictAssociative`

Récupère les filtres de destination.

**Retourne :** `StrictAssociative|null` - Les filtres ou null si aucun

**Exemple :**
```php
$filters = $options->getDestinationFilters();
if ($filters) {
    $mailDestinations = $filters->get(MailChannel::class);
}
```

## Cas d'utilisation

### Cas 1 : Envoi simple avec un canal

```php
<?php

use AndyDefer\LaravelNotification\Options\SendOptions;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Services\NotificationService;

$options = SendOptions::init()
    ->withChannel(MailChannel::class)
    ->withDestinationFilter(MailChannel::class, 'user@example.com');

$results = $notificationService
    ->withOptions($options)
    ->sendNow($user, $message);
```

### Cas 2 : Envoi sur plusieurs canaux avec limites

```php
<?php

use AndyDefer\LaravelNotification\Options\SendOptions;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;

$options = SendOptions::init()
    ->withChannels([MailChannel::class, SmsChannel::class])
    ->withLimitPerChannel(1);  // Un email, un SMS

$results = $notificationService
    ->withOptions($options)
    ->sendNow($user, $message);
```

### Cas 3 : Filtres avancés par canal

```php
<?php

use AndyDefer\LaravelNotification\Options\SendOptions;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\Channels\SlackChannel;

$options = SendOptions::init()
    ->withChannels([MailChannel::class, SmsChannel::class, SlackChannel::class])
    ->withDestinationFilter(MailChannel::class, [
        'user@example.com',
        'admin@example.com',
    ])
    ->withDestinationFilter(SmsChannel::class, '+33123456789')
    ->withDestinationFilter(SlackChannel::class, '#notifications');

$results = $notificationService
    ->withOptions($options)
    ->sendNow($user, $message);
```

### Cas 4 : Envoi différé avec options

```php
<?php

use AndyDefer\LaravelNotification\Options\SendOptions;
use AndyDefer\LaravelNotification\Channels\MailChannel;

$options = SendOptions::init()
    ->withChannel(MailChannel::class)
    ->withDestinationFilter(MailChannel::class, 'user@example.com')
    ->withLimitPerChannel(1);

$alias = $notificationService
    ->withOptions($options)
    ->sendLater($user, $message, new SendLaterRecord(delay_seconds: 300));
```

### Cas 5 : Envoi récurrent avec filtres

```php
<?php

use AndyDefer\LaravelNotification\Options\SendOptions;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Records\SendRecurringRecord;

$options = SendOptions::init()
    ->withChannel(MailChannel::class)
    ->withDestinationFilter(MailChannel::class, ['user@example.com']);

$record = new SendRecurringRecord(
    interval_seconds: 86400,
    start_at: new NotificationDateTimeVO(now()->toIso8601String())
);

$alias = $notificationService
    ->withOptions($options)
    ->sendRecurring($user, $message, $record);
```

## Flux d'exécution

```
SendOptions::init()
    │
    ├── withChannel(MailChannel::class)
    │       │
    │       └── Nouvelle instance avec MailChannel
    │
    ├── withDestinationFilter(MailChannel::class, 'user@example.com')
    │       │
    │       └── Nouvelle instance avec filtre
    │
    └── withLimitPerChannel(1)
            │
            └── Nouvelle instance avec limite

Résultat final : instance immuable avec toutes les options
```

## Gestion des erreurs

| Situation | Comportement | Message |
|-----------|--------------|---------|
| Canal inexistant | Exception levée par `FqcnChannelVO` | `Channel class "X" does not exist.` |
| Canal abstrait | Exception levée par `FqcnChannelVO` | `Channel class "X" cannot be abstract.` |
| Canal non instantiable | Exception levée par `FqcnChannelVO` | `Channel class "X" is not instantiable.` |

## Intégration

### Avec NotificationService

```php
$options = SendOptions::init()
    ->withChannel(MailChannel::class)
    ->withDestinationFilter(MailChannel::class, 'user@example.com');

$results = $notificationService
    ->withOptions($options)      // ✅ Définition des options
    ->sendNow($user, $message);  // ✅ Envoi
```

### Avec les Records

Les options sont automatiquement fusionnées avec les records :

```php
$record = new SendNowRecord(
    channels: $channels,          // Valeurs par défaut
    limit_per_channel: 2
);

$options = SendOptions::init()
    ->withChannel(MailChannel::class)  // ✅ Écrase $record->channels
    ->withLimitPerChannel(1);          // ✅ Écrase $record->limit_per_channel

$results = $notificationService
    ->withOptions($options)
    ->sendNow($user, $message, $record);
```

## Performance

| Opération | Complexité | Description |
|-----------|-----------|-------------|
| `init()` | O(1) | Création d'une instance vide |
| `withChannel()` | O(1) | Ajout d'un canal |
| `withChannels()` | O(n) | n = nombre de canaux |
| `withLimitPerChannel()` | O(1) | Définition de la limite |
| `withDestinationFilter()` | O(1) | Ajout d'un filtre |
| `withDestinationFilters()` | O(1) | Remplacement des filtres |

**Immutabilité :** Chaque modification crée une nouvelle instance. Aucun effet de bord.

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

use AndyDefer\LaravelNotification\Options\SendOptions;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\ValueObjects\MessageBodyVO;
use AndyDefer\LaravelNotification\ValueObjects\MessageSubjectVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $service,
    ) {}

    public function sendNotification(User $user): JsonResponse
    {
        // 1. Création du message
        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Votre commande a été expédiée.'),
            subject: new MessageSubjectVO('Commande expédiée #' . $order->id),
            type: 'order_shipped',
        );

        // 2. Configuration des options
        $options = SendOptions::init()
            ->withChannels([MailChannel::class, SmsChannel::class])
            ->withLimitPerChannel(1)
            ->withDestinationFilter(MailChannel::class, $user->email)
            ->withDestinationFilter(SmsChannel::class, $user->phone);

        // 3. Envoi de la notification
        $results = $this->service
            ->withOptions($options)
            ->sendNow($user, $message);

        // 4. Analyse des résultats
        if ($results->allSuccess()) {
            return response()->json([
                'status' => 'success',
                'message' => 'Notification envoyée',
                'sent_to' => $results->map(fn($r) => $r->destination)->toArray(),
            ]);
        }

        return response()->json([
            'status' => 'partial',
            'success_count' => $results->getSuccessCount(),
            'failure_count' => $results->getFailureCount(),
            'errors' => $results->filterByFailure()
                ->map(fn($r) => $r->error_message->getValue())
                ->toArray(),
        ], 207);
    }

    public function scheduleRecurringNotification(User $user): JsonResponse
    {
        $message = new NotificationMessageVO(
            body: new MessageBodyVO('Votre newsletter hebdomadaire.'),
            subject: new MessageSubjectVO('Newsletter #' . now()->weekOfYear),
        );

        $options = SendOptions::init()
            ->withChannel(MailChannel::class)
            ->withDestinationFilter(MailChannel::class, $user->email)
            ->withLimitPerChannel(1);

        $record = new SendRecurringRecord(
            interval_seconds: 604800, // 7 jours
            start_at: new NotificationDateTimeVO(now()->startOfWeek()->toIso8601String()),
        );

        $alias = $this->service
            ->withOptions($options)
            ->sendRecurring($user, $message, $record);

        return response()->json([
            'status' => 'scheduled',
            'task_alias' => $alias->getValue(),
            'next_run' => now()->startOfWeek()->toIso8601String(),
        ]);
    }
}
```

## Voir aussi
- `SendOptions` - Cette classe
- `FqcnChannelCollection` - Collection de canaux
- `StrictAssociative` - Conteneur de données immuable
- `NotificationService` - Service utilisant ces options
- `SendNowRecord` - Record d'envoi immédiat
- `SendLaterRecord` - Record d'envoi différé
- `SendAtRecord` - Record d'envoi planifié
- `SendRecurringRecord` - Record d'envoi récurrent