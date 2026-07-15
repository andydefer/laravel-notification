# NotifiableBuilder - Référence Technique

## Description

`NotifiableBuilder` est un **builder fluide** qui permet de construire et d'envoyer des notifications de manière programmatique sans avoir à implémenter l'interface `NotifiableInterface`. Il offre une API générique pour définir des canaux, destinations et messages, puis les envoyer immédiatement ou les planifier.

## Hiérarchie / Implémentations

```
NotifiableBuilder (final)
    └── Aucune interface implémentée
```

## Rôle principal

Ce builder agit comme un **constructeur fluide** pour les notifications :

1. **Définition des canaux et destinations** - `to()` permet de spécifier un canal et sa ou ses destinations
2. **Construction du message** - `body()`, `subject()`, `type()`, `data()`
3. **Configuration avancée** - `limit()`, `filter()`, `filters()`, `options()`, `metadata()`
4. **Traçabilité** - `as()` pour définir la classe morph et la clé
5. **Envoi** - `sendNow()`, `sendLater()`, `sendAt()`, `sendRecurring()`

## API / Méthodes publiques

### `__construct(?NotificationService $service = null)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$service` | `NotificationService|null` | Instance du service de notification (résolu automatiquement si null) |

**Exemple :**
```php
$builder = new NotifiableBuilder($notificationService);
```

---

### `static create(?NotificationService $service = null): self`

Crée une nouvelle instance du builder.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$service` | `NotificationService|null` | Instance du service de notification |

**Retourne :** `self` - Nouvelle instance

**Exemple :**
```php
$builder = NotifiableBuilder::create();
```

---

### `to(string $channelClass, string|array $destination): self`

Définit la ou les destinations pour un canal spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$channelClass` | `string` | FQCN du canal (ex: `MailChannel::class`) |
| `$destination` | `string|array<string>` | Destination(s) pour ce canal |

**Retourne :** `self` - Instance du builder (fluent)

**Exceptions :** `InvalidArgumentException` si la destination est vide

**Exemple :**
```php
$builder->to(MailChannel::class, 'user@example.com');
$builder->to(SmsChannel::class, ['+33123456789', '+33987654321']);
```

---

### `body(string $body): self`

Définit le corps du message.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$body` | `string` | Corps du message (HTML ou texte) |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder->body('<h1>Bienvenue !</h1><p>Contenu du message.</p>');
```

---

### `subject(string $subject): self`

Définit le sujet du message.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$subject` | `string` | Sujet du message |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder->subject('Bienvenue sur notre plateforme');
```

---

### `type(string $type): self`

Définit le type du message (pour la catégorisation).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$type` | `string` | Type de notification |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder->type('welcome');
```

---

### `data(array $data): self`

Définit les données supplémentaires du message.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$data` | `array<string, mixed>` | Données supplémentaires |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder->data(['user_id' => 123, 'order_id' => 456]);
```

---

### `options(SendOptions $options): self`

Définit les options d'envoi.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$options` | `SendOptions` | Options d'envoi |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$options = SendOptions::init()->withLimitPerChannel(1);
$builder->options($options);
```

---

### `limit(int $limit): self`

Définit la limite de destinations par canal.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$limit` | `int` | Nombre maximum de destinations par canal |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder->limit(1);
```

---

### `filter(string $channelClass, string|array $destinations): self`

Ajoute un filtre de destination pour un canal spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$channelClass` | `string` | FQCN du canal |
| `$destinations` | `string|array<string>` | Destination(s) autorisée(s) |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder->filter(MailChannel::class, 'user@example.com');
```

---

### `filters(array $filters): self`

Remplace tous les filtres de destination.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$filters` | `array<string, array<string>>` | Filtres par canal |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder->filters([
    MailChannel::class => ['user@example.com'],
    SmsChannel::class => ['+33123456789'],
]);
```

---

### `metadata(string $channelClass, StrictDataObject $metadata): self`

Ajoute des métadonnées pour un canal spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$channelClass` | `string` | FQCN du canal |
| `$metadata` | `StrictDataObject` | Métadonnées |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder->metadata(MailChannel::class, new StrictDataObject([
    'priority' => 'high',
    'name' => 'John Doe',
]));
```

---

### `metadataAll(StrictDataObject $metadata): self`

Ajoute des métadonnées à tous les canaux.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$metadata` | `StrictDataObject` | Métadonnées |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder->metadataAll(new StrictDataObject([
    'source' => 'api',
    'version' => '1.0',
]));
```

---

### `as(string $morphClass, int|string $key = 0): self`

Définit la classe morph et la clé pour le notifiable (utile pour le traçage).

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$morphClass` | `string` | Classe morph (ex: 'direct', 'external_user') |
| `$key` | `int|string` | Clé identifiante (facultatif) |

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder->as('external_user', 12345);
```

---

### `sendNow(?SendNowRecord $record = null): SendResultCollection`

Envoie la notification immédiatement.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `SendNowRecord|null` | Configuration d'envoi (facultatif) |

**Retourne :** `SendResultCollection` - Collection des résultats

**Exceptions :** 
- `RuntimeException` si le corps ou le sujet sont manquants
- `RuntimeException` si aucun canal disponible

**Exemple :**
```php
$results = $builder->sendNow();
```

---

### `sendLater(int $delaySeconds = 60): TaskAliasVO`

Envoie la notification après un délai.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$delaySeconds` | `int` | Délai en secondes (défaut: 60) |

**Retourne :** `TaskAliasVO` - Alias de la tâche

**Exceptions :** 
- `RuntimeException` si le corps ou le sujet sont manquants

**Exemple :**
```php
$alias = $builder->sendLater(300); // Dans 5 minutes
```

---

### `sendAt(NotificationDateTimeVO $scheduledAt): TaskAliasVO`

Envoie la notification à une date/heure précise.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$scheduledAt` | `NotificationDateTimeVO` | Date/heure planifiée |

**Retourne :** `TaskAliasVO` - Alias de la tâche

**Exceptions :** 
- `RuntimeException` si le corps ou le sujet sont manquants

**Exemple :**
```php
$scheduledAt = new NotificationDateTimeVO('2026-12-25T09:00:00+00:00');
$alias = $builder->sendAt($scheduledAt);
```

---

### `sendRecurring(int $intervalSeconds, NotificationDateTimeVO $startAt, ?NotificationDateTimeVO $endAt = null): TaskAliasVO`

Crée une tâche récurrente pour la notification.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$intervalSeconds` | `int` | Intervalle en secondes |
| `$startAt` | `NotificationDateTimeVO` | Date de début |
| `$endAt` | `NotificationDateTimeVO|null` | Date de fin (facultatif) |

**Retourne :** `TaskAliasVO` - Alias de la tâche

**Exceptions :** 
- `RuntimeException` si le corps ou le sujet sont manquants

**Exemple :**
```php
$alias = $builder->sendRecurring(
    86400, // 1 jour
    new NotificationDateTimeVO(now()->toIso8601String()),
    new NotificationDateTimeVO(now()->addDays(30)->toIso8601String())
);
```

---

### `reset(): self`

Réinitialise le builder.

**Retourne :** `self` - Instance du builder (fluent)

**Exemple :**
```php
$builder->reset();
```

## Cas d'utilisation

### Cas 1 : Envoi d'email simple

```php
<?php

use AndyDefer\LaravelNotification\Builders\NotifiableBuilder;
use AndyDefer\LaravelNotification\Channels\MailChannel;

$results = NotifiableBuilder::create()
    ->to(MailChannel::class, 'user@example.com')
    ->subject('Bienvenue')
    ->body('<h1>Bienvenue sur notre plateforme</h1>')
    ->sendNow();

if ($results->allSuccess()) {
    echo "✅ Email envoyé avec succès";
}
```

### Cas 2 : Envoi multi-canaux

```php
<?php

use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;

$results = NotifiableBuilder::create()
    ->to(MailChannel::class, 'user@example.com')
    ->to(SmsChannel::class, '+33123456789')
    ->subject('Notification importante')
    ->body('Votre commande a été expédiée.')
    ->data(['order_id' => 12345])
    ->sendNow();
```

### Cas 3 : Envoi différé

```php
<?php

$alias = NotifiableBuilder::create()
    ->to(MailChannel::class, 'user@example.com')
    ->subject('Rappel')
    ->body('N\'oubliez pas votre rendez-vous demain.')
    ->sendLater(1800); // Dans 30 minutes
```

### Cas 4 : Envoi récurrent avec limite

```php
<?php

$alias = NotifiableBuilder::create()
    ->to(MailChannel::class, 'user@example.com')
    ->subject('Newsletter hebdomadaire')
    ->body('Voici les dernières actualités...')
    ->limit(1)
    ->sendRecurring(
        604800, // 7 jours
        new NotificationDateTimeVO(now()->startOfWeek()->toIso8601String()),
        new NotificationDateTimeVO(now()->addWeeks(4)->toIso8601String())
    );
```

### Cas 5 : Avec filtres et métadonnées

```php
<?php

$results = NotifiableBuilder::create()
    ->to(MailChannel::class, ['user@example.com', 'admin@example.com'])
    ->subject('Offre spéciale')
    ->body('Profitez de notre offre exclusive.')
    ->filter(MailChannel::class, 'user@example.com')
    ->metadata(MailChannel::class, new StrictDataObject([
        'priority' => 'high',
        'name' => 'John Doe',
    ]))
    ->limit(1)
    ->sendNow();
```

### Cas 6 : Avec traçage

```php
<?php

$results = NotifiableBuilder::create()
    ->to(MailChannel::class, 'user@example.com')
    ->subject('Notification tracée')
    ->body('Cette notification est tracée.')
    ->as('external_user', 12345)
    ->sendNow();
```

## Flux d'exécution

```
NotifiableBuilder::create()
    │
    ├── to(MailChannel::class, 'user@example.com')
    ├── subject('Bienvenue')
    ├── body('Contenu')
    ├── limit(1)
    │
    └── sendNow()
            │
            ├── buildNotifiable()
            │   └── DirectNotifiable avec les routes
            │
            ├── buildMessage()
            │   └── NotificationMessageVO
            │
            ├── Appliquer les options (si présentes)
            │
            └── $service->sendNow($notifiable, $message, $record)
                    │
                    └── SendResultCollection
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Corps manquant | `RuntimeException` | `Message body is required. Call body() first.` |
| Sujet manquant | `RuntimeException` | `Message subject is required. Call subject() first.` |
| Destination vide | `InvalidArgumentException` | `Destination cannot be empty.` |
| Aucun canal disponible | `RuntimeException` | `No available channels for notifiable {type}#{id}` |

## Intégration

### Dépendances injectées

```
NotifiableBuilder
    └── NotificationService
        └── NotificationSenderProcessor
            ├── NotificationRepository
            └── Logger
```

### Relations avec les autres composants

```
┌─────────────────────────────────────────────────────────────────┐
│                    NotifiableBuilder                            │
│              (API fluente pour l'utilisateur)                   │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│                    NotificationService                          │
│              (Service principal)                                │
└────────────────────────┬────────────────────────────────────────┘
                         │
                         ▼
┌─────────────────────────────────────────────────────────────────┐
│              NotificationSenderProcessor                        │
│              (Orchestrateur d'envoi)                            │
└─────────────────────────────────────────────────────────────────┘
```

## Performance

| Opération | Complexité | Description |
|-----------|-----------|-------------|
| `to()` | O(1) | Ajout d'une route |
| `body()` / `subject()` | O(1) | Définition de propriétés |
| `limit()` / `filter()` | O(1) | Configuration des options |
| `sendNow()` | O(n) | n = nombre de routes |
| `sendLater()` | O(1) | Création d'une tâche |
| `sendRecurring()` | O(1) | Création d'une tâche récurrente |

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

use AndyDefer\LaravelNotification\Builders\NotifiableBuilder;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\ValueObjects\NotificationDateTimeVO;

class NotificationController extends Controller
{
    public function sendWelcome(User $user): JsonResponse
    {
        $results = NotifiableBuilder::create()
            ->to(MailChannel::class, $user->email)
            ->to(SmsChannel::class, $user->phone)
            ->subject('Bienvenue !')
            ->body('<h1>Bonjour</h1><p>Bienvenue sur notre plateforme.</p>')
            ->data(['user_id' => $user->id])
            ->limit(1)
            ->sendNow();

        return response()->json([
            'success' => $results->allSuccess(),
            'sent' => $results->getSuccessCount(),
            'failed' => $results->getFailureCount(),
        ]);
    }

    public function scheduleReminder(User $user, Appointment $appointment): JsonResponse
    {
        $scheduledAt = $appointment->start_at->subDay();

        $alias = NotifiableBuilder::create()
            ->to(MailChannel::class, $user->email)
            ->to(SmsChannel::class, $user->phone)
            ->subject('Rappel de rendez-vous')
            ->body('Votre rendez-vous est dans 24h.')
            ->sendAt(new NotificationDateTimeVO($scheduledAt->toIso8601String()));

        return response()->json([
            'message' => 'Rappel planifié',
            'task_alias' => $alias->getValue(),
        ]);
    }

    public function sendNewsletter(User $user): JsonResponse
    {
        $alias = NotifiableBuilder::create()
            ->to(MailChannel::class, $user->email)
            ->subject('Newsletter hebdomadaire')
            ->body('Voici les dernières actualités...')
            ->limit(1)
            ->sendRecurring(
                604800,
                new NotificationDateTimeVO(now()->startOfWeek()->toIso8601String()),
                new NotificationDateTimeVO(now()->addWeeks(4)->toIso8601String())
            );

        return response()->json([
            'message' => 'Newsletter planifiée',
            'task_alias' => $alias->getValue(),
        ]);
    }

    public function sendDirectEmail(Request $request): JsonResponse
    {
        $results = NotifiableBuilder::create()
            ->to(MailChannel::class, $request->input('email'))
            ->subject($request->input('subject'))
            ->body($request->input('body'))
            ->type('direct')
            ->as('direct_send', uniqid())
            ->sendNow();

        return response()->json([
            'success' => $results->allSuccess(),
        ]);
    }

    public function sendBulkNewsletter(): JsonResponse
    {
        $results = NotifiableBuilder::create()
            ->to(MailChannel::class, [
                'user1@example.com',
                'user2@example.com',
                'user3@example.com',
            ])
            ->subject('Newsletter du mois')
            ->body('Contenu de la newsletter...')
            ->limit(3)
            ->sendNow();

        return response()->json([
            'total' => $results->count(),
            'success' => $results->getSuccessCount(),
            'failed' => $results->getFailureCount(),
        ]);
    }

    public function cancelNewsletter(string $alias): JsonResponse
    {
        if ($this->service->cancel($alias)) {
            return response()->json(['message' => 'Newsletter annulée']);
        }

        return response()->json(['error' => 'Tâche non trouvée'], 404);
    }
}
```

## Voir aussi
- `DirectNotifiable` - Notifiable dynamique utilisé en interne
- `SendOptions` - Options d'envoi
- `NotificationService` - Service principal
- `NotificationMessageVO` - Value Object du message
- `SendNowRecord` - Record d'envoi immédiat
- `SendLaterRecord` - Record d'envoi différé
- `SendAtRecord` - Record d'envoi planifié
- `SendRecurringRecord` - Record d'envoi récurrent