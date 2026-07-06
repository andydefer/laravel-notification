
<!-- ==== ./docs/api-reference/channels.md ==== -->

# Channels - Système de canaux de notification

## Description

Le système de canaux (Channels) est le cœur du package `laravel-notification`. Il définit chaque type de notification (Email, SMS, WhatsApp, etc.) comme une classe configurable, extensible et responsable de la création de son driver d'envoi et de la validation des destinations.

## Hiérarchie / Implémentations

```
ChannelInterface
    └── AbstractChannel (classe abstraite)
            ├── MailChannel
            ├── DatabaseChannel
            ├── SmsChannel
            ├── WhatsAppChannel
            ├── SlackChannel
            ├── TelegramChannel
            └── PushChannel
```

## Rôle principal

Chaque canal de notification est une **définition** qui :

1. **Identifie** le canal (nom, libellé, icône)
2. **Configure** les paramètres via un `AbstractRecord`
3. **Valide** les destinations (email, téléphone, token, etc.)
4. **Instancie** le driver responsable de l'envoi
5. **Vérifie** si le canal est activé dans la configuration

---

## Interface ChannelInterface

### Méthodes

#### `getName(): string`

Retourne l'identifiant technique du canal (ex: `'mail'`, `'sms'`).

#### `getLabel(): string`

Retourne le libellé lisible par un humain (ex: `'Email'`, `'SMS'`).

#### `getIcon(): string`

Retourne un emoji représentant le canal (ex: `'📧'`).

#### `getConfigKey(): string`

Retourne la clé utilisée dans le fichier de configuration (ex: `'mail'`).

#### `requiresConfiguration(): bool`

Indique si le canal nécessite une configuration spécifique (clés API, tokens, etc.). Les canaux comme `Database` retournent `false`.

#### `isEnabled(): bool`

Vérifie si le canal est activé dans le fichier de configuration.

#### `getConfig(): AbstractRecord`

Retourne l'objet de configuration du canal sous forme de `AbstractRecord`.

#### `createDriver(): AbstractDriver`

Instancie et retourne le driver responsable de l'envoi de la notification.

#### `validateDestination(string $destination): bool`

Valide que la destination est conforme pour ce canal. Chaque canal implémente sa propre validation.

---

## AbstractChannel

### Description

Classe abstraite fournissant l'implémentation de base pour tous les canaux. Gère la configuration, le chargement des paramètres et la vérification d'activation.

### Méthodes protégées

#### `createConfigRecord(array $data): AbstractRecord` (abstraite)

Crée l'objet de configuration spécifique au canal.

#### `getDefaultEnabled(): bool`

Retourne l'état d'activation par défaut. Surcharger pour activer un canal par défaut.

```php
protected function getDefaultEnabled(): bool
{
    return true; // Activé par défaut
}
```

#### `getDefaultConfig(): array`

Retourne la configuration par défaut du canal (valeurs `env()`).

```php
protected function getDefaultConfig(): array
{
    return [
        'enabled' => false,
        'api_key' => env('API_KEY'),
    ];
}
```

#### `loadConfig(): array`

Charge la configuration depuis le fichier `config/notification.php`.

### Méthodes publiques

#### `getConfig(): AbstractRecord`

Retourne l'objet de configuration du canal.

#### `isEnabled(): bool`

Vérifie si le canal est activé. Priorité à la configuration, sinon valeur par défaut.

#### `requiresConfiguration(): bool`

Indique si le canal nécessite une configuration. Peut être surchargé.

---

## Canaux disponibles

### MailChannel

**Canal email** utilisant le système de mail de Laravel.

| Propriété | Valeur |
|-----------|--------|
| Nom | `mail` |
| Libellé | `Email` |
| Icône | `📧` |
| Activé par défaut | `true` |

**Configuration :**
```php
'channels' => [
    'mail' => [
        'enabled' => true,
        'driver' => 'mail',
        'default_to' => env('MAIL_DEFAULT_TO'),
        'default_from' => env('MAIL_FROM_ADDRESS'),
        'default_from_name' => env('MAIL_FROM_NAME'),
    ],
]
```

**Validation :** Email valide via `filter_var($destination, FILTER_VALIDATE_EMAIL)`

**Driver associé :** `MailDriver`

---

### DatabaseChannel

**Canal base de données** qui stocke les notifications dans la table `notifications`.

| Propriété | Valeur |
|-----------|--------|
| Nom | `database` |
| Libellé | `Base de données` |
| Icône | `💾` |
| Activé par défaut | `true` |

**Configuration :**
```php
'channels' => [
    'database' => [
        'driver' => 'database',
        'table' => 'notifications',
    ],
]
```

**Validation :** La destination doit être exactement `'database'`

**Driver associé :** `DatabaseDriver`

---

### SmsChannel

**Canal SMS** (Twilio, Vonage, etc.).

| Propriété | Valeur |
|-----------|--------|
| Nom | `sms` |
| Libellé | `SMS` |
| Icône | `📱` |
| Activé par défaut | `false` |

**Configuration :**
```php
'channels' => [
    'sms' => [
        'enabled' => env('SMS_ENABLED', false),
        'driver' => 'twilio',
        'sid' => env('TWILIO_SID'),
        'token' => env('TWILIO_TOKEN'),
        'from' => env('TWILIO_FROM'),
    ],
]
```

**Validation :** Numéro de téléphone au format international `^\+[0-9]{10,15}$`

**Driver associé :** `SmsDriver`

---

### WhatsAppChannel

**Canal WhatsApp** (Meta API, Twilio, etc.).

| Propriété | Valeur |
|-----------|--------|
| Nom | `whatsapp` |
| Libellé | `WhatsApp` |
| Icône | `💬` |
| Activé par défaut | `false` |

**Configuration :**
```php
'channels' => [
    'whatsapp' => [
        'enabled' => env('WHATSAPP_ENABLED', false),
        'driver' => 'meta',
        'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
        'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
    ],
]
```

**Validation :** Numéro de téléphone au format international `^\+[0-9]{10,15}$`

**Driver associé :** `WhatsAppDriver`

---

### SlackChannel

**Canal Slack** via webhook.

| Propriété | Valeur |
|-----------|--------|
| Nom | `slack` |
| Libellé | `Slack` |
| Icône | `💼` |
| Activé par défaut | `false` |

**Configuration :**
```php
'channels' => [
    'slack' => [
        'enabled' => env('SLACK_ENABLED', false),
        'webhook_url' => env('SLACK_WEBHOOK_URL'),
    ],
]
```

**Validation :** URL webhook Slack valide contenant `hooks.slack.com`

**Driver associé :** `SlackDriver`

---

### TelegramChannel

**Canal Telegram** via Bot API.

| Propriété | Valeur |
|-----------|--------|
| Nom | `telegram` |
| Libellé | `Telegram` |
| Icône | `✈️` |
| Activé par défaut | `false` |

**Configuration :**
```php
'channels' => [
    'telegram' => [
        'enabled' => env('TELEGRAM_ENABLED', false),
        'bot_token' => env('TELEGRAM_BOT_TOKEN'),
        'chat_id' => env('TELEGRAM_CHAT_ID'),
    ],
]
```

**Validation :** Chat ID numérique (positif ou négatif pour les groupes)

**Driver associé :** `TelegramDriver`

---

### PushChannel

**Canal Push Notification** (FCM, APNS, etc.).

| Propriété | Valeur |
|-----------|--------|
| Nom | `push` |
| Libellé | `Push Notification` |
| Icône | `🔔` |
| Activé par défaut | `false` |

**Configuration :**
```php
'channels' => [
    'push' => [
        'enabled' => env('PUSH_ENABLED', false),
        'platform' => 'fcm',
        'fcm_api_key' => env('FCM_API_KEY'),
        'fcm_project_id' => env('FCM_PROJECT_ID'),
        'apns_key_path' => env('APNS_KEY_PATH'),
        'apns_key_id' => env('APNS_KEY_ID'),
        'apns_team_id' => env('APNS_TEAM_ID'),
        'apns_bundle_id' => env('APNS_BUNDLE_ID'),
        'default_sound' => 'default',
        'default_tokens' => [],
    ],
]
```

**Validation :** Token non vide d'au moins 10 caractères

**Driver associé :** `PushDriver`

---

## Validation des destinations

Chaque canal implémente la méthode `validateDestination()` pour garantir que les destinations sont valides avant utilisation.

| Canal | Validation | Exemple valide | Exemple invalide |
|-------|------------|----------------|------------------|
| **MailChannel** | Email valide | `john@example.com` | `john@` |
| **SmsChannel** | `^\+[0-9]{10,15}$` | `+33123456789` | `123456` |
| **WhatsAppChannel** | `^\+[0-9]{10,15}$` | `+33123456789` | `123456` |
| **DatabaseChannel** | `=== 'database'` | `database` | `anything_else` |
| **SlackChannel** | URL avec `hooks.slack.com` | `https://hooks.slack.com/...` | `http://example.com` |
| **TelegramChannel** | Chat ID numérique | `-123456789` | `abc123` |
| **PushChannel** | Token > 10 caractères | `device_token_1234567890` | `short` |

---

## Étendre le système

### Créer un canal personnalisé

1. **Créer la classe du canal** :

```php
<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Channels\AbstractChannel;
use AndyDefer\LaravelNotification\Drivers\AbstractDriver;
use AndyDefer\LaravelNotification\Records\DiscordConfigRecord;
use App\Notifications\Drivers\DiscordDriver;

final class DiscordChannel extends AbstractChannel
{
    public function getName(): string
    {
        return 'discord';
    }

    public function getLabel(): string
    {
        return 'Discord';
    }

    public function getIcon(): string
    {
        return '🎮';
    }

    public function getConfigKey(): string
    {
        return 'discord';
    }

    protected function getDefaultConfig(): array
    {
        return [
            'enabled' => false,
            'webhook_url' => env('DISCORD_WEBHOOK_URL'),
        ];
    }

    protected function createConfigRecord(array $data): AbstractRecord
    {
        return DiscordConfigRecord::from($data);
    }

    /**
     * Valider que la destination est une URL de webhook Discord valide.
     */
    public function validateDestination(string $destination): bool
    {
        return filter_var($destination, FILTER_VALIDATE_URL) !== false
            && str_contains($destination, 'discord.com/api/webhooks');
    }

    public function createDriver(): AbstractDriver
    {
        /** @var DiscordConfigRecord $config */
        $config = $this->config;

        return new DiscordDriver(
            $config,
            app(\AndyDefer\LaravelNotification\Repositories\NotificationRepository::class),
            $this->logger
        );
    }
}
```

2. **Créer le Record de configuration** :

```php
<?php

declare(strict_types=1);

namespace AndyDefer\LaravelNotification\Records;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;

final class DiscordConfigRecord extends AbstractRecord
{
    public function __construct(
        public readonly bool $enabled = false,
        public readonly ?string $webhook_url = null,
    ) {}
}
```

3. **Créer le Driver** :

```php
<?php

declare(strict_types=1);

namespace App\Notifications\Drivers;

use AndyDefer\LaravelNotification\Drivers\AbstractDriver;
use AndyDefer\LaravelNotification\Records\NotificationRecord;
use AndyDefer\LaravelNotification\Repositories\NotificationRepository;
use AndyDefer\Logger\Contracts\LoggerInterface;
use App\Notifications\Records\DiscordConfigRecord;

final class DiscordDriver extends AbstractDriver
{
    public function __construct(
        private readonly DiscordConfigRecord $config,
        private readonly NotificationRepository $repository,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    protected function execute(NotificationRecord $record): bool
    {
        // Logique d'envoi Discord...
        return true;
    }

    public function getChannel(): string
    {
        return 'discord';
    }
}
```

---

## Flux d'exécution

```
Service Provider
    → enregistre les canaux
    → NotificationService
        → récupère les canaux du notifiable
        → valide les destinations via validateDestination()
        → résout les canaux disponibles
        → pour chaque canal : createDriver()
            → Driver instancié
            → Driver->send()
```

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Canal non trouvé | `RuntimeException` | `No available channels for notifiable X#Y` |
| Configuration invalide | `RuntimeException` | `Channel X requires configuration` |
| Driver manquant | `RuntimeException` | `No driver configured for channel X` |
| Destination invalide | `InvalidArgumentException` | `Invalid destination "X" for channel Y` |

## Performance

- Les canaux sont instanciés **une seule fois** via le conteneur Laravel
- La configuration est chargée **au moment de l'instanciation**
- Les drivers sont créés **à chaque envoi** (pour chaque notification)
- La validation des destinations est effectuée **à la création de la VO**
- Aucun cache de configuration n'est utilisé (lecture directe)

## Exemple complet

```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;

// Récupérer le service
$service = app(NotificationService::class);

// Envoyer via Mail et SMS
$results = $service->send(
    $user,
    'Bienvenue sur notre plateforme !',
    [
        MailChannel::class,
        SmsChannel::class,
    ]
);

// Résultats
foreach ($results as $channel => $success) {
    echo $channel . ': ' . ($success ? '✅' : '❌');
}
```

## Intégration

Les canaux s'intègrent avec :

- **AbstractChannel** : classe parente pour tous les canaux
- **ChannelInterface** : interface contractuelle
- **Drivers** : chaque canal crée son propre driver
- **NotificationService** : orchestre l'envoi via les canaux
- **Config** : configuration centralisée dans `config/notification.php`
- **NotificationChannelVO** : encapsulation du canal avec sa destination
- **NotifiableInterface** : les entités notifiables fournissent leurs canaux
---
<!-- ==== ./docs/api-reference/repositories/notification-repository.md ==== -->

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
<!-- ==== ./docs/api-reference/services/notification-service.md ==== -->

# NotificationService - Référence Technique

## Description

Service central de gestion des notifications. Orchestre l'envoi immédiat, différé, planifié ou récurrent des notifications en s'appuyant sur le système de tâches (UniqueTask et RecurringTask).

## Hiérarchie / Implémentations

```
NotificationServiceInterface
    └── NotificationService (final)
```

## Rôle principal

**Orchestrateur des envois de notifications :**

1. **Envoi immédiat** (`sendNow`) - Envoi synchrone
2. **Envoi différé** (`sendLater`) - Planification dans X secondes
3. **Envoi planifié** (`sendAt`) - Planification à une date/heure spécifique
4. **Envoi récurrent** (`sendRecurring`) - Envoi périodique
5. **Gestion des tâches** - Pause, reprise, annulation, modification d'intervalle
6. **Statistiques** - Consultation des statistiques par notifiable ou session

---

## API / Méthodes publiques

### `sendNow(NotifiableInterface&Model $notifiable, NotificationMessageVO $message, SendNowRecord $record): SendResultCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface&Model` | L'entité à notifier |
| `$message` | `NotificationMessageVO` | Le message à envoyer |
| `$record` | `SendNowRecord` | Configuration d'envoi immédiat |

**Retourne :** `SendResultCollection` - Résultats de l'envoi

**Exceptions :** Aucune (déléguée au processor)

**Exemple :**
```php
$record = SendNowRecord::from([
    'channels' => [EmailChannel::class],
    'limit_per_channel' => 1,
]);

$results = $service->sendNow($user, $message, $record);
```

---

### `sendLater(NotifiableInterface&Model $notifiable, NotificationMessageVO $message, SendLaterRecord $record): TaskAliasVO`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface&Model` | L'entité à notifier |
| `$message` | `NotificationMessageVO` | Le message à envoyer |
| `$record` | `SendLaterRecord` | Configuration d'envoi différé |

**Retourne :** `TaskAliasVO` - Alias de la tâche créée

**Exceptions :** 
- `InvalidArgumentException` si `delay_seconds <= 0`

**Exemple :**
```php
$record = SendLaterRecord::from([
    'delay_seconds' => 300, // 5 minutes
    'channels' => [EmailChannel::class],
    'limit_per_channel' => 1,
]);

$alias = $service->sendLater($user, $message, $record);
```

---

### `sendAt(NotifiableInterface&Model $notifiable, NotificationMessageVO $message, SendAtRecord $record): TaskAliasVO`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface&Model` | L'entité à notifier |
| `$message` | `NotificationMessageVO` | Le message à envoyer |
| `$record` | `SendAtRecord` | Configuration d'envoi planifié |

**Retourne :** `TaskAliasVO` - Alias de la tâche créée

**Exceptions :** 
- `InvalidArgumentException` si la date est dans le passé

**Exemple :**
```php
$record = SendAtRecord::from([
    'scheduled_at' => new NotificationDateTimeVO('2026-07-07 10:00:00'),
    'channels' => [EmailChannel::class],
    'limit_per_channel' => 1,
]);

$alias = $service->sendAt($user, $message, $record);
```

---

### `sendRecurring(NotifiableInterface&Model $notifiable, NotificationMessageVO $message, SendRecurringRecord $record): TaskAliasVO`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface&Model` | L'entité à notifier |
| `$message` | `NotificationMessageVO` | Le message à envoyer |
| `$record` | `SendRecurringRecord` | Configuration d'envoi récurrent |

**Retourne :** `TaskAliasVO` - Alias de la tâche créée

**Exceptions :** 
- `InvalidArgumentException` si `interval_seconds < 1`

**Exemple :**
```php
$record = SendRecurringRecord::from([
    'interval_seconds' => 86400, // Tous les jours
    'start_at' => new NotificationDateTimeVO('2026-07-06 08:00:00'),
    'end_at' => new NotificationDateTimeVO('2026-07-31 23:59:59'),
    'channels' => [EmailChannel::class],
    'limit_per_channel' => 1,
]);

$alias = $service->sendRecurring($user, $message, $record);
```

---

### `cancel(string $signature): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la tâche à annuler |

**Retourne :** `bool` - `true` si annulée, `false` si non trouvée

**Exceptions :** Aucune (les exceptions sont capturées et loggées)

**Exemple :**
```php
$service->cancel('unique@abc-123');
```

---

### `pause(string $signature): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la tâche récurrente à mettre en pause |

**Retourne :** `bool` - `true` si mise en pause, `false` si non trouvée

**Exceptions :** Aucune

**Exemple :**
```php
$service->pause('recurring@def-456');
```

---

### `resume(string $signature): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la tâche récurrente à reprendre |

**Retourne :** `bool` - `true` si reprise, `false` si non trouvée

**Exceptions :** Aucune

**Exemple :**
```php
$service->resume('recurring@def-456');
```

---

### `changeInterval(string $signature, int $newIntervalSeconds): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$signature` | `string` | Signature de la tâche récurrente |
| `$newIntervalSeconds` | `int` | Nouvel intervalle en secondes |

**Retourne :** `bool` - `true` si modifié, `false` si non trouvée

**Exceptions :** 
- `InvalidArgumentException` si `newIntervalSeconds < 1`

**Exemple :**
```php
$service->changeInterval('recurring@def-456', 3600); // Toutes les heures
```

---

### `getStats(NotifiableInterface&Model $notifiable): NotificationStatsVO`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface&Model` | L'entité à notifier |

**Retourne :** `NotificationStatsVO` - Statistiques des notifications

**Exceptions :** Aucune

**Exemple :**
```php
$stats = $service->getStats($user);
echo "Taux de succès : " . $stats->success_rate . "%";
```

---

### `getSessionStats(string $sessionId): SessionStatsRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$sessionId` | `string` | ID de la session |

**Retourne :** `SessionStatsRecord` - Statistiques de la session

**Exceptions :** Aucune

**Exemple :**
```php
$sessionStats = $service->getSessionStats('session-123');
echo "En attente : " . $sessionStats->pending;
```

---

## Cas d'utilisation

### Cas 1 : Envoi immédiat après action utilisateur

```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;

// Inscription d'un nouvel utilisateur
public function register(Request $request)
{
    $user = User::create($request->validated());
    
    // Envoi immédiat d'un email de bienvenue
    $message = new NotificationMessageVO(
        subject: 'Bienvenue !',
        content: '<h1>Bonjour ' . $user->name . ' !</h1>...'
    );
    
    $record = SendNowRecord::from([
        'channels' => [EmailChannel::class],
        'limit_per_channel' => 1,
    ]);
    
    $service = app(NotificationService::class);
    $results = $service->sendNow($user, $message, $record);
    
    return response()->json(['user' => $user]);
}
```

---

### Cas 2 : Email de relance 30 minutes après abandon de panier

```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendLaterRecord;

public function abandonCart(Cart $cart)
{
    $user = $cart->user;
    
    $message = new NotificationMessageVO(
        subject: 'Votre panier vous attend !',
        content: '<p>Vous avez des articles dans votre panier...</p>'
    );
    
    $record = SendLaterRecord::from([
        'delay_seconds' => 1800, // 30 minutes
        'channels' => [EmailChannel::class],
        'limit_per_channel' => 1,
    ]);
    
    $service = app(NotificationService::class);
    $alias = $service->sendLater($user, $message, $record);
    
    // Stocker l'alias pour annulation si le panier est validé
    $cart->notification_task = $alias->getValue();
    $cart->save();
}
```

---

### Cas 3 : Newsletter hebdomadaire sur 4 semaines

```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendRecurringRecord;

public function scheduleNewsletter(User $user)
{
    $message = new NotificationMessageVO(
        subject: 'Votre newsletter hebdomadaire',
        content: '<p>Voici les dernières actualités...</p>'
    );
    
    $record = SendRecurringRecord::from([
        'interval_seconds' => 604800, // 7 jours
        'start_at' => new NotificationDateTimeVO('2026-07-08 09:00:00'),
        'end_at' => new NotificationDateTimeVO('2026-07-29 09:00:00'), // 4 semaines
        'channels' => [EmailChannel::class],
        'limit_per_channel' => 1,
    ]);
    
    $service = app(NotificationService::class);
    $alias = $service->sendRecurring($user, $message, $record);
    
    return $alias;
}
```

---

### Cas 4 : Gestion des notifications récurrentes

```php
<?php

// Admin : Pause des notifications
public function pauseNewsletter(string $alias)
{
    $service = app(NotificationService::class);
    
    if ($service->pause($alias)) {
        return response()->json(['message' => 'Newsletter mise en pause']);
    }
    
    return response()->json(['error' => 'Tâche non trouvée'], 404);
}

// Admin : Reprise
public function resumeNewsletter(string $alias)
{
    $service = app(NotificationService::class);
    
    if ($service->resume($alias)) {
        return response()->json(['message' => 'Newsletter reprise']);
    }
    
    return response()->json(['error' => 'Tâche non trouvée'], 404);
}

// Admin : Modification de la fréquence
public function changeNewsletterFrequency(string $alias, int $days)
{
    $service = app(NotificationService::class);
    $intervalSeconds = $days * 86400;
    
    if ($service->changeInterval($alias, $intervalSeconds)) {
        return response()->json(['message' => "Fréquence modifiée à {$days} jours"]);
    }
    
    return response()->json(['error' => 'Tâche non trouvée'], 404);
}
```

---

### Cas 5 : Statistiques des notifications

```php
<?php

public function showStats(User $user)
{
    $service = app(NotificationService::class);
    $stats = $service->getStats($user);
    
    return response()->json([
        'total' => $stats->total,
        'sent' => $stats->sent,
        'failed' => $stats->failed,
        'delivered' => $stats->delivered,
        'pending' => $stats->pending,
        'success_rate' => $stats->success_rate . '%',
    ]);
}
```

---

## Flux d'exécution

### Envoi immédiat

```
sendNow(Notifiable, Message, Record)
    ↓
Création de ProcessNotificationRecord
    ↓
Log 'Sending notification immediately'
    ↓
senderProcessor->send()
    ↓
SendResultCollection
```

### Envoi différé / planifié

```
sendLater/sendAt(Notifiable, Message, Record)
    ↓
Validation (delay > 0 ou date future)
    ↓
Création du payload
    ↓
Création de la configuration UniqueTask
    ↓
uniqueTaskService->register()
    ↓
TaskAliasVO
```

### Envoi récurrent

```
sendRecurring(Notifiable, Message, Record)
    ↓
Validation (interval >= 1)
    ↓
Création du payload
    ↓
Création de la configuration RecurringTask
    ↓
recurringTaskService->register()
    ↓
TaskAliasVO
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Delay <= 0 | `InvalidArgumentException` | `Delay seconds must be greater than 0.` |
| Date dans le passé | `InvalidArgumentException` | `Scheduled date must be in the future.` |
| Interval < 1 | `InvalidArgumentException` | `Interval seconds must be at least 1 second.` |
| Tâche non trouvée | `false` (retourné, non exception) | - |

---

## Intégration

### Avec le service provider

```php
<?php

namespace App\Providers;

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Contracts\Services\NotificationServiceInterface;
use Illuminate\Support\ServiceProvider;

final class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            NotificationServiceInterface::class,
            function ($app) {
                return new NotificationService(
                    $app->make(NotificationRepositoryInterface::class),
                    $app->make(NotificationSenderProcessorInterface::class),
                    $app->make(UniqueTaskServiceInterface::class),
                    $app->make(RecurringTaskServiceInterface::class),
                    $app->make(LoggerInterface::class),
                    $app->make(HydrationService::class),
                );
            }
        );
    }
}
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `sendNow()` | O(n) | n = nombre de routes |
| `sendLater()` | O(1) | + insertion base de données |
| `sendAt()` | O(1) | + insertion base de données |
| `sendRecurring()` | O(1) | + insertion base de données |
| `cancel()` | O(1) | Recherche + mise à jour |
| `pause()` | O(1) | Mise à jour statut |
| `resume()` | O(1) | Mise à jour statut |
| `changeInterval()` | O(1) | Mise à jour intervalle |
| `getStats()` | O(1) | 4 requêtes count |
| `getSessionStats()` | O(1) | 3 requêtes count |

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

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\Records\SendLaterRecord;
use AndyDefer\LaravelNotification\Records\SendRecurringRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationDateTimeVO;
use App\Models\User;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\SmsChannel;

$service = app(NotificationService::class);
$user = User::find(1);

// 1. Envoi immédiat
$message = new NotificationMessageVO(
    subject: 'Bienvenue !',
    content: '<h1>Bonjour !</h1><p>Bienvenue sur notre plateforme.</p>'
);

$nowRecord = SendNowRecord::from([
    'channels' => [EmailChannel::class, SmsChannel::class],
    'limit_per_channel' => 1,
]);

$results = $service->sendNow($user, $message, $nowRecord);

// 2. Envoi différé (15 minutes)
$laterRecord = SendLaterRecord::from([
    'delay_seconds' => 900,
    'channels' => [EmailChannel::class],
    'limit_per_channel' => 1,
]);

$alias = $service->sendLater($user, $message, $laterRecord);

// 3. Envoi récurrent (hebdomadaire)
$recurringRecord = SendRecurringRecord::from([
    'interval_seconds' => 604800,
    'start_at' => new NotificationDateTimeVO('2026-07-08 09:00:00'),
    'end_at' => new NotificationDateTimeVO('2026-07-29 09:00:00'),
    'channels' => [EmailChannel::class],
    'limit_per_channel' => 1,
]);

$recurringAlias = $service->sendRecurring($user, $message, $recurringRecord);

// 4. Gestion
$service->pause($recurringAlias->getValue());
$service->resume($recurringAlias->getValue());
$service->changeInterval($recurringAlias->getValue(), 86400); // Tous les jours
$service->cancel($alias->getValue());

// 5. Statistiques
$stats = $service->getStats($user);

echo "📊 Statistiques des notifications\n";
echo "Total : " . $stats->total . "\n";
echo "✅ Envoyées : " . $stats->sent . "\n";
echo "❌ Échouées : " . $stats->failed . "\n";
echo "📬 Délivrées : " . $stats->delivered . "\n";
echo "⏳ En attente : " . $stats->pending . "\n";
echo "📈 Taux de succès : " . $stats->success_rate . "%\n";
```
<!-- ==== ./docs/api-reference/WHY_LARAVEL_NOTIFICATION.md ==== -->

## ✅ CORRECTION - WHY LARAVEL NOTIFICATION

Tu as raison, `setNotificationChannels()` n'existe pas ! Les canaux sont définis via la méthode `getNotificationChannels()` de l'interface `NotifiableInterface`, qui retourne une `NotificationRouteCollection`.

Correction du document :

---

# WHY LARAVEL NOTIFICATION

## Le système de notifications multi-canaux qui s'adapte à vos besoins

---

## L'histoire qui a donné naissance à Laravel Notification

Imaginez la situation suivante :

Un développeur freelance est missionné pour créer une application de gestion médicale pour un réseau de cliniques. L'application doit notifier les médecins pour :
- Les rendez-vous confirmés
- Les résultats d'analyses disponibles
- Les alertes d'urgence
- Les rappels de consultation

Chaque médecin a :
- Une adresse email professionnelle
- Une adresse email personnelle (parfois)
- Un numéro de téléphone portable
- Un numéro de téléphone professionnel (parfois)
- Des préférences de notification (par SMS le jour, par email la nuit)

Le développeur commence par implémenter un système d'email avec Laravel Mail. Ça fonctionne bien. Puis le client demande les SMS. Le développeur ajoute Twilio. Puis WhatsApp Business API arrive. Le client veut aussi une trace de toutes les notifications en base de données pour l'audit.

**Rapidement, le code devient un cauchemar :**

```php
// ❌ Code spaghetti qui grandit avec chaque nouveau canal
class NotificationService
{
    public function send($user, $message, $channel)
    {
        if ($channel === 'email') {
            if ($user->email_primary) {
                Mail::to($user->email_primary)->send($message);
            }
            if ($user->email_secondary) {
                Mail::to($user->email_secondary)->send($message);
            }
        } elseif ($channel === 'sms') {
            if ($user->phone_primary) {
                Twilio::message($user->phone_primary)->send($message);
            }
            if ($user->phone_secondary) {
                Twilio::message($user->phone_secondary)->send($message);
            }
        } elseif ($channel === 'whatsapp') {
            WhatsApp::message($user->phone_primary)->send($message);
        } elseif ($channel === 'database') {
            NotificationLog::create([
                'user_id' => $user->id,
                'message' => $message,
                'channel' => $channel,
            ]);
        }
        // ... et ça continue à chaque nouveau canal
    }
}
```

Les problèmes s'accumulent au fil des sprints :

**Duplication de code** : Chaque canal a sa propre logique, mais la structure est toujours la même → validation, envoi, logging, gestion d'erreur. À chaque nouveau canal, c'est 50 lignes de code qui se ressemblent.

**Difficulté d'ajout** : Un nouveau canal (Slack, Telegram, Push Notification) signifie modifier 5 à 10 fichiers différents. Le développeur hésite à ajouter des fonctionnalités par peur de casser l'existant.

**Pas d'historique fiable** : La table `notification_logs` est remplie manuellement, avec des champs différents selon le canal. Impossible de faire un rapport fiable sur les notifications envoyées.

**Pas de gestion d'erreur unifiée** : Si l'email échoue, on loggue une erreur. Si le SMS échoue, on loggue une autre erreur. Si WhatsApp échoue, on ne loggue rien parce que le développeur a oublié.

**Pas de testabilité** : Pour tester l'envoi, il faut appeler les vraies APIs. Les tests sont lents, fragiles, et coûtent de l'argent (chaque SMS envoyé en test est facturé).

**Pas de traçabilité** : Quand un médecin dit "Je n'ai pas reçu la notification", impossible de savoir si elle a été envoyée, sur quel canal, et si elle a échoué. Le développeur passe des heures à chercher dans les logs.

Le client est mécontent. Le développeur est frustré. L'application devient difficile à maintenir.

**Ce développeur a besoin d'un système qui :**

1. **Standardise** l'envoi de notifications (même structure pour tous les canaux)
2. **Supporte** plusieurs canaux (Email, SMS, WhatsApp, Database, Slack, Telegram, Push...)
3. **Persiste** automatiquement toutes les notifications pour l'audit
4. **Unifie** la gestion des erreurs (même format, même logique)
5. **Facilite** l'ajout de nouveaux canaux (une classe, pas 10 fichiers)
6. **Est testable** sans appeler les vraies APIs

C'est précisément ce problème que **Laravel Notification** résout.

---

## Mais d'abord, qu'est-ce que Laravel offre nativement ?

### Laravel Notifications (le système natif)

Laravel propose un système de notifications intégré, simple et efficace pour les cas basiques.

```php
// ✅ Envoi simple d'une notification
$user->notify(new InvoicePaid($invoice));

// ✅ Définition de la notification
class InvoicePaid extends Notification
{
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }
    
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->line('Votre facture a été payée.')
            ->action('Voir la facture', url('/invoices/1'));
    }
}
```

**Ce qu'il fait bien :**
- ✅ Interface simple et intuitive
- ✅ Supporte plusieurs canaux (mail, database, broadcast, slack, nexmo)
- ✅ Intégré à Laravel, pas de dépendance externe
- ✅ Documentation officielle complète

**Ses limites :**

- ❌ **Une seule destination par canal** : Un utilisateur ne peut avoir qu'un seul email et qu'un seul numéro de téléphone. Impossible d'avoir une adresse email professionnelle et une personnelle.

- ❌ **Pas de persistance automatique** : Seul le canal `database` persiste les notifications. Les emails et SMS ne sont pas tracés automatiquement.

- ❌ **Pas de rapport d'envoi** : On ne sait pas si la notification a réellement été envoyée ou si elle a échoué. Le système natif ne retourne pas de résultat.

- ❌ **Pas de distinction entre "envoyée" et "reçue"** : Une notification marquée comme "envoyée" peut avoir atterri dans les spams.

- ❌ **Ajout d'un canal complexe** : Pour ajouter un canal personnalisé, il faut créer un service provider, enregistrer le canal, et modifier la configuration.

- ❌ **Pas de logique métier** : Impossible de définir des règles comme "envoyer par SMS le jour, par email la nuit".

---

## Et Laravel Notification dans tout ça ?

**Laravel Notification n'est pas un remplacement du système natif de Laravel.** C'est une extension conçue pour répondre aux besoins que le système natif ne couvre pas.

**Laravel Notification se concentre sur trois aspects que le système natif ignore :**

### 1. La persistance

> "Chaque notification envoyée doit pouvoir être retrouvée"

Le système natif persiste uniquement les notifications du canal `database`. Avec Laravel Notification, **toutes** les notifications sont persistées, quel que soit le canal.

```php
// Le système natif : seule la base de données est persistée
$user->notify(new InvoicePaid($invoice));
// → La notification est en base de données (si via() contient 'database')
// → L'email est envoyé mais pas tracé

// Laravel Notification : tout est persisté
$processor->send($user, $message, $processRecord);
// → La notification est en base de données (TOUJOURS)
// → L'email est envoyé ET tracé avec son statut
// → Le SMS est envoyé ET tracé avec son statut
// → WhatsApp est envoyé ET tracé avec son statut
```

### 2. Les routes multiples

> "Un utilisateur peut avoir plusieurs adresses email et plusieurs numéros de téléphone"

Le système natif suppose qu'un utilisateur a une seule destination par canal. Laravel Notification permet d'en avoir plusieurs via l'interface `NotifiableInterface`.

```php
// Système natif : une seule destination
$user->email = 'john@example.com';
$user->phone = '+33612345678';

// Laravel Notification : multiples destinations via getNotificationChannels()
class User extends Model implements NotifiableInterface
{
    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;
        
        // ✅ Email principal
        $collection->add(new NotificationRouteVO(
            channelClass: MailChannel::class,
            destination: $this->email_primary,
            metadata: new StrictDataObject(['type' => 'primary'])
        ));
        
        // ✅ Email secondaire
        $collection->add(new NotificationRouteVO(
            channelClass: MailChannel::class,
            destination: $this->email_secondary,
            metadata: new StrictDataObject(['type' => 'secondary'])
        ));
        
        // ✅ SMS principal
        $collection->add(new NotificationRouteVO(
            channelClass: SmsChannel::class,
            destination: $this->phone_primary
        ));
        
        // ✅ SMS secondaire
        $collection->add(new NotificationRouteVO(
            channelClass: SmsChannel::class,
            destination: $this->phone_secondary
        ));
        
        return $collection;
    }
}
```

### 3. Le contrôle

> "Je veux décider comment, quand et combien de notifications sont envoyées"

Le système natif envoie sur tous les canaux déclarés, sans contrôle. Laravel Notification offre un contrôle fin.

```php
// Système natif : tout ou rien
$user->notify($notification); // Envoie sur tous les canaux déclarés

// Laravel Notification : contrôle granulaire
$processRecord = ProcessNotificationRecord::from([
    'channels' => [EmailChannel::class],    // Uniquement les emails
    'limit_per_channel' => 1,               // Un seul email par canal
]);

$processor->send($user, $message, $processRecord);
// → Seulement le premier email (le principal) est envoyé
```

---

## La valeur ajoutée de Laravel Notification

### 1. Une architecture en couches claire

Le système est divisé en trois couches qui communiquent entre elles :

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                        NOTIFICATION PROCESSOR                              │
│                 L'orchestrateur : il coordonne tout                        │
│                                                                             │
│  Rôle : Résoudre les routes, créer les notifications, gérer les erreurs    │
│                                                                             │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │                         CHANNELS                                      │ │
│  │              La couche "QUOI" : le type de notification              │ │
│  │                                                                       │ │
│  │  Rôle : Définir le type (Email, SMS, WhatsApp) et valider la config  │ │
│  │                                                                       │ │
│  │  ┌─────────────────────────────────────────────────────────────────┐ │ │
│  │  │                         DRIVERS                                 │ │ │
│  │  │              La couche "COMMENT" : le mode d'envoi             │ │ │
│  │  │                                                                 │ │ │
│  │  │  Rôle : Exécuter l'envoi et retourner un résultat structuré    │ │ │
│  │  │                                                                 │ │ │
│  │  │  ┌───────────────────────────────────────────────────────────┐ │ │ │
│  │  │  │                      EXECUTION                            │ │ │
│  │  │  │              L'envoi réel de la notification              │ │ │
│  │  │  │                                                           │ │ │
│  │  │  │  Rôle : Appeler l'API externe et retourner true/false    │ │ │
│  │  │  └───────────────────────────────────────────────────────────┘ │ │ │
│  │  └─────────────────────────────────────────────────────────────────┘ │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Pourquoi cette séparation ?**

- **Le Processor** : Il ne sait pas comment envoyer une notification. Il sait seulement qui notifier et avec quels paramètres.
- **Le Channel** : Il sait quelle configuration est nécessaire. Il valide que tout est présent.
- **Le Driver** : Il sait comment envoyer réellement. Il appelle l'API externe.

Cette séparation permet de :

- **Tester chaque couche indépendamment** : On peut tester le Processor sans appeler les vraies APIs.
- **Remplacer un Driver sans toucher au Channel** : Passer de SMTP à SendGrid ne change pas le Channel Email.
- **Ajouter un Channel sans toucher aux Drivers** : Ajouter Slack ne modifie pas les Drivers Email ou SMS.

### 2. Des routes multiples et flexibles

Un utilisateur peut avoir plusieurs destinations par canal, et chaque destination peut avoir des métadonnées via `NotificationRouteVO`.

```php
public function getNotificationChannels(): NotificationRouteCollection
{
    return NotificationRouteCollection::from([
        new NotificationRouteVO(
            channelClass: EmailChannel::class,
            destination: 'john.doe@hospital.com',
            metadata: new StrictDataObject(['priority' => 'high', 'type' => 'professional'])
        ),
        new NotificationRouteVO(
            channelClass: EmailChannel::class,
            destination: 'john.doe@gmail.com',
            metadata: new StrictDataObject(['priority' => 'low', 'type' => 'personal'])
        ),
        new NotificationRouteVO(
            channelClass: SmsChannel::class,
            destination: '+33612345678'
        ),
        new NotificationRouteVO(
            channelClass: WhatsAppChannel::class,
            destination: '+33612345678'
        ),
    ]);
}
```

### 3. Une persistance complète

Toutes les notifications sont stockées en base de données avec :

- **Le statut** : PENDING, SENT, FAILED
- **Le canal utilisé** : email, sms, whatsapp, database...
- **La destination** : john@example.com, +33612345678...
- **Le message** : Le contenu exact qui a été envoyé
- **L'horodatage** : Quand la notification a été créée et envoyée
- **L'erreur éventuelle** : Si l'envoi a échoué, le message d'erreur

```sql
-- Exemple d'enregistrement en base de données
SELECT * FROM notifications WHERE notifiable_id = 42;

-- Résultat :
-- id | session_id | channel  | destination        | status  | error
-- 1  | abc-123    | email    | john@hospital.com  | SENT    | NULL
-- 2  | abc-123    | email    | john@gmail.com     | FAILED  | "Connection timeout"
-- 3  | abc-123    | sms      | +33612345678       | SENT    | NULL
```

### 4. Un contrôle granulaire

Vous pouvez décider :

- **Quels canaux utiliser** : email uniquement, SMS uniquement, ou tous
- **Combien de notifications par canal** : 1, 2, 3, ou toutes
- **Quelles destinations prioriser** : les destinations avec métadonnées

```php
// Envoyer à tous les emails mais un seul SMS
$processRecord = ProcessNotificationRecord::from([
    'channels' => [], // Tous les canaux disponibles
    'limit_per_channel' => [
        EmailChannel::class => null, // Tous les emails
        SmsChannel::class => 1,      // Un seul SMS
    ],
]);
```

### 5. Un rapport détaillé

Chaque envoi retourne un résultat structuré via `SendResultRecord` :

```php
$results = $processor->send($user, $message, $processRecord);

foreach ($results as $result) {
    if ($result->success) {
        echo "✅ Notification envoyée\n";
        echo "   Canal : " . $result->channel->getValue() . "\n";
        echo "   Destinataire : " . $result->destination . "\n";
    } else {
        echo "❌ Échec de l'envoi\n";
        echo "   Canal : " . $result->channel->getValue() . "\n";
        echo "   Destinataire : " . $result->destination . "\n";
        echo "   Erreur : " . $result->error_message->getValue() . "\n";
    }
}
```

---

## En une phrase

> **Laravel Notifications envoie un message sur un canal défini. Laravel Notification orchestre l'envoi sur tous les canaux d'une entité et trace chaque tentative.**

---

## Comparaison détaillée

| Fonctionnalité | Laravel Notifications | Laravel Notification |
|----------------|-----------------------|----------------------|
| **Une seule destination par canal** | ✅ | ❌ (supporte plusieurs) |
| **Plusieurs destinations par canal** | ❌ | ✅ |
| **Persistance automatique** | ❌ (sauf database) | ✅ (tous les canaux) |
| **Statut de l'envoi (SENT/FAILED)** | ❌ | ✅ |
| **Rapport détaillé** | ❌ | ✅ |
| **Limitation par canal** | ❌ | ✅ |
| **Métadonnées par destination** | ❌ | ✅ |
| **ID de session pour regroupement** | ❌ | ✅ |
| **Architecture extensible** | ⚠️ (complexe) | ✅ (simple) |
| **Testabilité** | ⚠️ (facades) | ✅ (injection) |
| **Gestion d'erreur unifiée** | ❌ | ✅ |
| **Logging intégré** | ❌ | ✅ |

---

## Cas d'usage concrets

### 1. Application médicale

```php
class Doctor extends Model implements NotifiableInterface
{
    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;
        
        // ✅ Email professionnel
        if ($this->email_professional) {
            $collection->add(new NotificationRouteVO(
                MailChannel::class,
                $this->email_professional,
                new StrictDataObject(['type' => 'professional'])
            ));
        }
        
        // ✅ Email personnel
        if ($this->email_personal) {
            $collection->add(new NotificationRouteVO(
                MailChannel::class,
                $this->email_personal,
                new StrictDataObject(['type' => 'personal'])
            ));
        }
        
        // ✅ SMS
        if ($this->phone) {
            $collection->add(new NotificationRouteVO(
                SmsChannel::class,
                $this->phone
            ));
        }
        
        return $collection;
    }
}

$doctor = Doctor::find(42);
$processor->send($doctor, $message, $processRecord);
// → 2 emails + 1 SMS → tracés en base de données
```

### 2. E-commerce - Confirmation de commande

```php
class Order extends Model implements NotifiableInterface
{
    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;
        
        // ✅ Client
        $collection->add(new NotificationRouteVO(
            MailChannel::class,
            $this->customer_email
        ));
        $collection->add(new NotificationRouteVO(
            SmsChannel::class,
            $this->customer_phone
        ));
        
        // ✅ Admin
        $collection->add(new NotificationRouteVO(
            MailChannel::class,
            'admin@shop.com'
        ));
        
        // ✅ Base de données
        $collection->add(new NotificationRouteVO(
            DatabaseChannel::class,
            'database'
        ));
        
        return $collection;
    }
}
```

### 3. Système de notification d'urgence

```php
// ⚠️ URGENCE : on envoie sur TOUS les canaux sans limite
$processRecord = ProcessNotificationRecord::from([
    'channels' => [], // Tous disponibles
    'limit_per_channel' => null, // Pas de limite
]);

// 🔔 Notification normale : uniquement les emails
$processRecord = ProcessNotificationRecord::from([
    'channels' => [EmailChannel::class],
    'limit_per_channel' => 1,
]);
```

---

## Ce que le développeur gagne en confort

### 1. Une API claire et intuitive

```php
// 1. Déclarer les canaux (dans le modèle)
public function getNotificationChannels(): NotificationRouteCollection
{
    return NotificationRouteCollection::from([
        new NotificationRouteVO(MailChannel::class, $this->email),
        new NotificationRouteVO(SmsChannel::class, $this->phone),
    ]);
}

// 2. Créer le message
$message = new NotificationMessageVO(
    subject: 'Bienvenue !',
    content: 'Contenu de la notification...'
);

// 3. Configurer l'envoi
$processRecord = ProcessNotificationRecord::from([
    'channels' => [MailChannel::class],
    'limit_per_channel' => 1,
]);

// 4. Envoyer
$results = $processor->send($user, $message, $processRecord);
```

### 2. Une traçabilité complète

```bash
# Toutes les notifications d'un utilisateur
SELECT * FROM notifications WHERE notifiable_type = 'User' AND notifiable_id = 1;

# Détail par session
SELECT channel, destination, status, error, created_at
FROM notifications
WHERE session_id = 'abc-123'
ORDER BY created_at;
```

### 3. Une gestion d'erreur transparente

```php
$results = $processor->send($user, $message, $processRecord);

if ($results->hasFailures()) {
    foreach ($results->getFailures() as $failure) {
        Log::error('Échec de notification', [
            'channel' => $failure->channel->getValue(),
            'destination' => $failure->destination,
            'error' => $failure->error_message->getValue(),
        ]);
    }
}
```

### 4. Une extensibilité sans limite

```php
// 1. Créer un nouveau Driver (une seule classe)
class TelegramDriver extends AbstractDriver { ... }

// 2. Créer un nouveau Channel (une seule classe)
class TelegramChannel extends AbstractChannel { ... }

// 3. Utiliser immédiatement
public function getNotificationChannels(): NotificationRouteCollection
{
    return NotificationRouteCollection::from([
        new NotificationRouteVO(TelegramChannel::class, '@john_doe'),
    ]);
}
```

---

## Architecture technique

### Les composants clés

| Composant | Fichier | Rôle |
|-----------|---------|------|
| **NotifiableInterface** | `Contracts/NotifiableInterface.php` | Interface pour les entités notifiables |
| **NotificationRouteVO** | `ValueObjects/NotificationRouteVO.php` | Route de notification (canal + destination) |
| **AbstractChannel** | `Abstracts/AbstractChannel.php` | Base pour tous les canaux |
| **AbstractDriver** | `Abstracts/AbstractDriver.php` | Base pour tous les drivers |
| **NotificationSenderProcessor** | `Processors/NotificationSenderProcessor.php` | Orchestrateur |
| **SendResultRecord** | `Records/SendResultRecord.php` | Résultat structuré |

---

## Installation et mise en route

```bash
# 1. Installation
composer require andydefer/laravel-notification

# 2. Migrations
php artisan vendor:publish --tag=notification-migrations
php artisan migrate

# 3. Configuration
php artisan vendor:publish --tag=notification-config

# 4. Implémenter NotifiableInterface sur vos modèles

# 5. Utiliser dans votre code
$processor = app(NotificationSenderProcessor::class);
```

---

## Conclusion

Laravel Notification n'est pas un remplacement du système de notification de Laravel.

**C'est un complément pour les applications qui ont besoin de :**

- ✅ Plusieurs destinations par canal (un utilisateur = plusieurs emails)
- ✅ Persistance et traçabilité complètes de toutes les notifications
- ✅ Contrôle fin sur l'envoi (limites par canal, filtrage)
- ✅ Architecture extensible (ajout facile de nouveaux canaux)
- ✅ Gestion d'erreur unifiée (même format pour tous les canaux)
- ✅ Rapports détaillés (succès/échec par canal et par destination)

---

**Le système de notifications multi-canaux pour Laravel.** 🚀
<!-- ==== ./docs/api-reference/notification-service.md ==== -->

# NotificationService - Service de notification

## Description

`NotificationService` est le service central du package `laravel-notification`. Il orchestre l'envoi des notifications en coordonnant les canaux, les drivers et la persistance des notifications.

## Rôle principal

Le service assure :

1. **Résolution des canaux** : Détermine quels canaux sont disponibles pour un destinataire
2. **Création du record** : Construit l'enregistrement de notification avec un `session_id` unique
3. **Orchestration des drivers** : Appelle le driver approprié pour chaque canal
4. **Statistiques** : Fournit des métriques sur les notifications envoyées
5. **Traçabilité** : Permet de suivre une session d'envoi via `session_id`

---

## Méthodes publiques

### `send(NotifiableInterface $notifiable, NotificationMessageVO $message, ?array $channels = null): Collection`

Envoie une notification à un destinataire via les canaux spécifiés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface` | Le destinataire de la notification |
| `$message` | `NotificationMessageVO` | Le message à envoyer |
| `$channels` | `?array` | Liste des canaux à utiliser (FQCN ou instances) |

**Retourne :** `Collection` - Résultats par canal (clé = FQCN, valeur = `bool`)

**Exceptions :** `RuntimeException` - Si aucun canal disponible

**Exemple :**
```php
$service = app(NotificationService::class);

// Envoyer via tous les canaux disponibles
$results = $service->send($user, new NotificationMessageVO('Bonjour !'));

// Envoyer via des canaux spécifiques
$results = $service->send(
    $user,
    new NotificationMessageVO('Bienvenue !', 'Welcome', 'welcome'),
    [MailChannel::class, SmsChannel::class]
);

// Avec des données structurées
$message = new NotificationMessageVO(
    body: 'Contenu du message',
    subject: 'Sujet',
    type: 'welcome',
    data: new StrictDataObject(['user_id' => $user->id])
);
$results = $service->send($user, $message);
```

---

### `getStats(NotifiableInterface&Model $notifiable): NotificationStatsVO`

Récupère les statistiques de notification pour un destinataire.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface&Model` | Le destinataire |

**Retourne :** `NotificationStatsVO` - Objet contenant les statistiques

**Exemple :**
```php
$stats = $service->getStats($user);
echo "Total: {$stats->total}";
echo "Envoyés: {$stats->sent}";
echo "Échecs: {$stats->failed}";
echo "Taux de succès: {$stats->getSuccessRate()}%";
```

---

### `getSessionStats(string $sessionId): array`

Récupère les statistiques pour une session d'envoi spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$sessionId` | `string` | L'identifiant unique de la session |

**Retourne :** `array` - Statistiques de la session

```php
$stats = $service->getSessionStats($sessionId);
// [
//     'session_id' => 'uuid',
//     'total' => 4,
//     'sent' => 3,
//     'failed' => 1,
//     'pending' => 0,
// ]
```

---

## Flux d'exécution

```
send()
    ↓
    → Résolution des canaux
        ├── Si $channels = null → tous les canaux disponibles
        └── Sinon → canaux spécifiés
    ↓
    → Filtrage des canaux disponibles
        ├── Vérification que le canal existe chez le notifiable
        └── Si aucun → RuntimeException
    ↓
    → Génération d'un session_id (UUID)
    ↓
    → Pour chaque canal disponible
        ├── buildRecord() → création du NotificationRecord
        ├── createDriver() → instanciation du driver
        ├── driver->send() → envoi
        └── Résultat stocké dans la collection
    ↓
    → Retourne la collection des résultats
```

## Détail des méthodes privées

### `sendViaChannel()`

Envoie la notification via un canal spécifique.

```php
private function sendViaChannel(
    NotifiableInterface $notifiable,
    NotificationMessageVO $message,
    ChannelInterface $definition,
    string $sessionId
): bool
```

**Étapes :**
1. `buildRecord()` : Crée le `NotificationRecord`
2. `$definition->createDriver()` : Instancie le driver
3. `$driver->send($record)` : Exécute l'envoi

---

### `buildRecord()`

Construit l'enregistrement de notification.

```php
private function buildRecord(
    NotifiableInterface $notifiable,
    NotificationMessageVO $message,
    ChannelInterface $definition,
    string $sessionId
): NotificationRecord
```

**Étapes :**
1. Récupère toutes les destinations pour le canal
2. Vérifie qu'au moins une destination existe
3. Construit le tableau `to` avec toutes les destinations
4. Utilise la première destination pour le `NotificationChannelVO`
5. Crée le `NotificationRecord` avec `status = PENDING`

**Points clés :**
- `to` est **toujours un tableau** (même pour une seule destination)
- Le `session_id` est commun à tous les canaux d'un même envoi
- La destination est validée par le canal lors de la création de la VO

---

## Cas d'utilisation

### Cas 1 : Envoi sur tous les canaux disponibles

```php
public function notifyUser(User $user)
{
    $service = app(NotificationService::class);
    
    $message = new NotificationMessageVO(
        body: 'Vous avez reçu un message important',
        subject: 'Nouveau message',
        type: 'new_message'
    );
    
    $results = $service->send($user, $message);
    
    foreach ($results as $channel => $success) {
        Log::info("Channel {$channel}: " . ($success ? '✅' : '❌'));
    }
}
```

### Cas 2 : Envoi sur canaux spécifiques

```php
public function sendWelcome(User $user)
{
    $service = app(NotificationService::class);
    
    $message = new NotificationMessageVO(
        body: 'Bienvenue sur notre plateforme !',
        subject: 'Bienvenue',
        type: 'welcome'
    );
    
    // Envoi uniquement par email et SMS
    $results = $service->send($user, $message, [
        MailChannel::class,
        SmsChannel::class,
    ]);
    
    if ($results->get(MailChannel::class) === false) {
        Log::warning('L\'email de bienvenue a échoué');
    }
}
```

### Cas 3 : Suivi d'une session d'envoi

```php
public function sendAndTrack(User $user, Order $order)
{
    $service = app(NotificationService::class);
    
    $message = new NotificationMessageVO(
        body: 'Votre commande a été expédiée',
        subject: 'Commande #' . $order->id,
        type: 'order_shipped',
        data: new StrictDataObject([
            'order_id' => $order->id,
            'tracking_number' => $order->tracking_number,
        ])
    );
    
    $results = $service->send($user, $message);
    
    $notification = Notification::where('notifiable_id', $user->id)
        ->latest()
        ->first();
    
    if ($notification) {
        $sessionStats = $service->getSessionStats($notification->session_id);
        Log::info('Session stats:', $sessionStats);
    }
}
```

### Cas 4 : Statistiques d'un utilisateur

```php
public function showStats(User $user)
{
    $service = app(NotificationService::class);
    $stats = $service->getStats($user);
    
    return view('user.stats', [
        'total' => $stats->total,
        'sent' => $stats->sent,
        'failed' => $stats->failed,
        'pending' => $stats->pending,
        'success_rate' => $stats->getSuccessRate(),
        'is_success' => $stats->isSuccess(),
        'has_failures' => $stats->hasFailures(),
    ]);
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucun canal disponible | `RuntimeException` | `No available channels for notifiable {type}#{id}` |
| Aucune destination pour un canal | `RuntimeException` | `No destination found for channel {class}` |
| Driver en échec | `NotificationSendException` | `Driver {class} failed: {message}` |

## Performance

| Aspect | Impact |
|--------|--------|
| Résolution des canaux | O(n) sur le nombre de canaux |
| Création des drivers | À chaque appel, pas de cache |
| Persistance | 1 insertion par canal |

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.2+ | ✅ Complet |

## Exemple complet

```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

class NotificationController extends Controller
{
    private NotificationService $service;

    public function __construct(NotificationService $service)
    {
        $this->service = $service;
    }

    public function send(User $user)
    {
        $message = new NotificationMessageVO(
            body: 'Bonjour !',
            subject: 'Test de notification',
            type: 'test',
            data: new StrictDataObject([
                'user_id' => $user->id,
                'timestamp' => now(),
            ])
        );

        $results = $this->service->send(
            $user,
            $message,
            [MailChannel::class, SmsChannel::class]
        );

        $success = $results->filter()->count();
        $total = $results->count();

        return response()->json([
            'message' => "{$success}/{$total} notifications envoyées",
            'details' => $results,
        ]);
    }

    public function stats(User $user)
    {
        $stats = $this->service->getStats($user);
        
        return response()->json([
            'total' => $stats->total,
            'sent' => $stats->sent,
            'failed' => $stats->failed,
            'pending' => $stats->pending,
            'success_rate' => $stats->getSuccessRate(),
        ]);
    }
}
```

## Intégration

`NotificationService` s'intègre avec :

- **ChannelInterface** : les canaux de notification
- **NotifiableInterface** : les destinataires
- **NotificationRepository** : la persistance
- **NotificationRecord** : les données de notification
- **NotificationChannelVO** : l'encapsulation des canaux
- **AbstractDriver** : les drivers d'envoi
- **NotificationMessageVO** : le message à envoyer
---
<!-- ==== ./docs/api-reference/abstracts/abstract-driver.md ==== -->

# AbstractDriver - Référence Technique

## Description

Classe abstraite de base pour tous les drivers de notification. Implémente le pattern **Template Method** pour standardiser le cycle de vie de l'envoi : `before()` → `execute()` → `after()`. Fournit une gestion unifiée des succès et des erreurs.

## Hiérarchie / Implémentations

```
DriverInterface
    └── AbstractDriver (abstract)
            ├── EmailDriver
            ├── SmsDriver
            ├── SlackDriver
            └── [Vos drivers personnalisés]
```

## Rôle principal

**Orchestrateur du cycle de vie d'une notification :**

1. **Préparation** (`before()`) : Valide la configuration
2. **Exécution** (`execute()`) : Envoie la notification
3. **Finalisation** (`after()`) : Logique post-envoi

Retourne toujours un `SendResultRecord` unifié, que l'envoi réussisse ou échoue.

---

## API / Méthodes publiques

### `send(NotificationMessageVO $message, NotificationRouteVO $route): SendResultRecord`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `NotificationMessageVO` | Le message à envoyer |
| `$route` | `NotificationRouteVO` | La destination (canal + adresse) |

**Retourne :** `SendResultRecord` - Résultat structuré de l'envoi

**Exceptions :** Aucune - toutes les exceptions sont capturées et transformées en `SendResultRecord` avec `success: false`

**Exemple :**
```php
$driver = new EmailDriver($config);
$result = $driver->send(
    new NotificationMessageVO('Welcome!', 'Welcome to our platform...'),
    new NotificationRouteVO('email', 'user@example.com')
);

if ($result->success) {
    echo "Email sent successfully!";
} else {
    echo "Error: " . $result->error_message->getValue();
}
```

---

### `getChannel(): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `string` - Nom du canal (ex: 'email', 'sms', 'slack')

**Exceptions :** Aucune

**Exemple :**
```php
$channel = $driver->getChannel(); // 'email'
```

---

### `validateConfiguration(): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `bool` - `true` si la configuration est valide

**Exceptions :** Aucune (peut être surchargée pour lancer des exceptions)

**Exemple :**
```php
if (!$driver->validateConfiguration()) {
    throw new \RuntimeException('Driver is not properly configured');
}
```

---

## Méthodes protégées (Template Method)

### `before(NotificationMessageVO $message, NotificationRouteVO $route): void`

**Objectif :** Préparation avant l'envoi

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `NotificationMessageVO` | Le message à envoyer |
| `$route` | `NotificationRouteVO` | La destination |

**Comportement par défaut :** Valide la configuration via `validateConfiguration()`. Lance une exception si invalide.

**Exemple de surcharge :**
```php
protected function before(NotificationMessageVO $message, NotificationRouteVO $route): void
{
    parent::before($message, $route);
    
    // Logique personnalisée
    if (strlen($message->getContent()) > 1000) {
        throw new \InvalidArgumentException('Message content exceeds maximum length');
    }
}
```

---

### `after(NotificationMessageVO $message, NotificationRouteVO $route, bool $success, ?\Exception $error = null): void`

**Objectif :** Finalisation après l'envoi

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `NotificationMessageVO` | Le message envoyé |
| `$route` | `NotificationRouteVO` | La destination |
| `$success` | `bool` | Succès ou échec de l'envoi |
| `$error` | `?\Exception` | Exception capturée (si échec) |

**Comportement par défaut :** Vide - à surcharger selon les besoins

**Exemple de surcharge :**
```php
protected function after(
    NotificationMessageVO $message,
    NotificationRouteVO $route,
    bool $success,
    ?\Exception $error = null
): void {
    if ($success) {
        Log::info("Notification sent to {$route->getDestination()}");
    } else {
        Log::error("Notification failed: " . $error->getMessage());
    }
}
```

---

### `execute(NotificationMessageVO $message, NotificationRouteVO $route): bool`

**Objectif :** Logique d'envoi (à implémenter par les drivers concrets)

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `NotificationMessageVO` | Le message à envoyer |
| `$route` | `NotificationRouteVO` | La destination |

**Retourne :** `bool` - `true` si l'envoi a réussi

**Exceptions :** Peut lancer des exceptions (capturées par `send()`)

**Exemple (EmailDriver) :**
```php
protected function execute(
    NotificationMessageVO $message,
    NotificationRouteVO $route
): bool {
    $this->mailer->send(
        $route->getDestination(),
        $message->getSubject(),
        $message->getContent()
    );
    
    return true;
}
```

---

## Cas d'utilisation

### Cas 1 : Driver Email

```php
<?php

declare(strict_types=1);

namespace App\Notifications\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

final class EmailDriver extends AbstractDriver
{
    public function __construct(
        private readonly array $config
    ) {}

    public function getChannel(): string
    {
        return 'email';
    }

    public function validateConfiguration(): bool
    {
        return !empty($this->config['host']) 
            && !empty($this->config['port'])
            && !empty($this->config['username'])
            && !empty($this->config['password']);
    }

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        // Simuler l'envoi d'email
        $sent = mail(
            $route->getDestination(),
            $message->getSubject(),
            $message->getContent(),
            "From: {$this->config['from']}"
        );
        
        return $sent;
    }
}

// Utilisation
$driver = new EmailDriver([
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'username' => 'user@gmail.com',
    'password' => '****',
    'from' => 'noreply@example.com'
]);

$result = $driver->send(
    new NotificationMessageVO(
        'Bienvenue !',
        'Bienvenue sur notre plateforme...'
    ),
    new NotificationRouteVO('email', 'john@example.com')
);
```

---

### Cas 2 : Driver SMS avec Twilio

```php
<?php

declare(strict_types=1);

namespace App\Notifications\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Twilio\Rest\Client;

final class SmsDriver extends AbstractDriver
{
    private Client $client;

    public function __construct(
        private readonly array $config
    ) {
        $this->client = new Client(
            $config['account_sid'],
            $config['auth_token']
        );
    }

    public function getChannel(): string
    {
        return 'sms';
    }

    public function validateConfiguration(): bool
    {
        return !empty($this->config['account_sid']) 
            && !empty($this->config['auth_token'])
            && !empty($this->config['from']);
    }

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        $this->client->messages->create(
            $route->getDestination(),
            [
                'from' => $this->config['from'],
                'body' => $message->getContent()
            ]
        );
        
        return true;
    }

    protected function after(
        NotificationMessageVO $message,
        NotificationRouteVO $route,
        bool $success,
        ?\Exception $error = null
    ): void {
        if ($success) {
            // Logguer l'envoi réussi
        } else {
            // Alerter l'équipe
        }
    }
}
```

---

### Cas 3 : Driver Slack

```php
<?php

declare(strict_types=1);

namespace App\Notifications\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use GuzzleHttp\Client;

final class SlackDriver extends AbstractDriver
{
    private Client $http;

    public function __construct(
        private readonly array $config
    ) {
        $this->http = new Client();
    }

    public function getChannel(): string
    {
        return 'slack';
    }

    public function validateConfiguration(): bool
    {
        return !empty($this->config['webhook_url']);
    }

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        $payload = [
            'text' => $message->getContent(),
            'channel' => $route->getDestination(),
        ];

        $response = $this->http->post(
            $this->config['webhook_url'],
            ['json' => $payload]
        );

        return $response->getStatusCode() === 200;
    }
}
```

---

## Flux d'exécution

```
send(Message, Route)
    ↓
before()
    ↓
validateConfiguration() → false → RuntimeException
    ↓ (true)
execute()
    ↓
    ├── true → after(success) → SendResultRecord(success: true)
    └── false → after(success, error) → SendResultRecord(success: false, error)
```

---

## Gestion des erreurs

| Situation | Exception capturée | Message résultant |
|-----------|-------------------|-------------------|
| Configuration invalide | `RuntimeException` | `Driver X configuration is invalid.` |
| Exception dans `execute()` | `Exception` (quelconque) | `[ExceptionClass] - Message` |
| Exception dans `before()` | `Exception` (quelconque) | `[ExceptionClass] - Message` |
| Exception dans `after()` | **Non capturée** (remontée) | - |

---

## Intégration

### Avec un service de notification

```php
<?php

namespace App\Services;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\Records\SendResultRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

final class NotificationService
{
    private array $drivers = [];

    public function register(string $name, AbstractDriver $driver): void
    {
        $this->drivers[$name] = $driver;
    }

    public function send(
        string $channel,
        string $to,
        string $subject,
        string $content
    ): SendResultRecord {
        if (!isset($this->drivers[$channel])) {
            throw new \InvalidArgumentException("Channel '{$channel}' not registered");
        }

        $message = new NotificationMessageVO($subject, $content);
        $route = new NotificationRouteVO($channel, $to);

        return $this->drivers[$channel]->send($message, $route);
    }
}

// Utilisation
$service = new NotificationService();
$service->register('email', new EmailDriver($emailConfig));
$service->register('sms', new SmsDriver($smsConfig));

$result = $service->send('email', 'user@example.com', 'Welcome', '...');
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `send()` | O(1) + réseau | Temps dépend du driver externe |
| `before()` | O(1) | Validation légère |
| `execute()` | Variable | Dépend du service externe |
| `after()` | O(1) | Logging léger |

**Optimisations :**
- Les connexions aux services externes sont maintenues en mémoire
- La validation de configuration est faite à chaque envoi (peut être mise en cache)

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.2+ | ✅ Complet |
| PHP 8.3+ | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

namespace App\Notifications\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Illuminate\Support\Facades\Log;

final class LogDriver extends AbstractDriver
{
    public function __construct(
        private readonly array $config = []
    ) {}

    public function getChannel(): string
    {
        return 'log';
    }

    protected function before(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): void {
        parent::before($message, $route);
        
        Log::debug('Sending notification via log driver', [
            'to' => $route->getDestination(),
            'subject' => $message->getSubject(),
        ]);
    }

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        Log::info('Notification logged', [
            'to' => $route->getDestination(),
            'subject' => $message->getSubject(),
            'content' => $message->getContent(),
        ]);
        
        return true;
    }

    protected function after(
        NotificationMessageVO $message,
        NotificationRouteVO $route,
        bool $success,
        ?\Exception $error = null
    ): void {
        Log::debug('Notification sent', [
            'success' => $success,
            'error' => $error?->getMessage(),
        ]);
    }
}

// Utilisation
$driver = new LogDriver();
$result = $driver->send(
    new NotificationMessageVO('Test', 'This is a test message'),
    new NotificationRouteVO('log', 'test@example.com')
);

// $result->success === true
// $result->channel->getValue() === 'log'
// $result->destination === 'test@example.com'
```
<!-- ==== ./docs/api-reference/abstracts/abstract-channel.md ==== -->

# AbstractChannel - Référence Technique

## Description

Classe abstraite de base pour tous les canaux de notification. Fournit l'infrastructure commune (configuration) et définit le contrat pour la création du driver associé.

## Hiérarchie / Implémentations

```
ChannelInterface
    └── AbstractChannel (abstract)
            ├── EmailChannel
            ├── SmsChannel
            ├── SlackChannel
            └── [Vos canaux personnalisés]
```

## Rôle principal

Agit comme une **fabrique abstraite** (Abstract Factory Pattern) qui :
- Injecte la configuration dans tous les canaux
- Définit le contrat de création du driver (`createDriver()`)
- Centralise les dépendances communes

## Installation

```bash
composer require andydefer/laravel-notification
```

### Configuration

```php
// config/notification.php
return [
    'channels' => [
        'email' => [
            'driver' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port' => env('MAIL_PORT', 587),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
        ],
        'sms' => [
            'driver' => 'twilio',
            'account_sid' => env('TWILIO_ACCOUNT_SID'),
            'auth_token' => env('TWILIO_AUTH_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],
    ],
];
```

## API / Méthodes publiques

### `__construct(ConfigRepository $configRepository)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$configRepository` | `ConfigRepository` | Instance de la configuration Laravel |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
use Illuminate\Contracts\Config\Repository as ConfigRepository;

$channel = new EmailChannel(
    app(ConfigRepository::class)
);
```

---

### `createDriver(): AbstractDriver`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `AbstractDriver` - Instance du driver associé au canal

**Exceptions :** 
- `InvalidArgumentException` si la configuration du driver est invalide
- `RuntimeException` si le driver ne peut pas être instancié

**Exemple :**
```php
$driver = $channel->createDriver();
// Retourne une instance de EmailDriver, SmsDriver, etc.
```

**Note :** Cette méthode est **abstraite** et doit être implémentée par chaque canal concret.

---

## Cas d'utilisation

### Cas 1 : Créer un canal email

```php
<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use App\Notifications\Drivers\EmailDriver;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class EmailChannel extends AbstractChannel
{
    public function createDriver(): AbstractDriver
    {
        $config = $this->configRepository->get('notification.channels.email', []);
        
        return new EmailDriver($config);
    }
}
```

### Cas 2 : Créer un canal SMS avec Twilio

```php
<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use App\Notifications\Drivers\TwilioSmsDriver;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class SmsChannel extends AbstractChannel
{
    public function createDriver(): AbstractDriver
    {
        $config = $this->configRepository->get('notification.channels.sms', []);
        
        if (empty($config['account_sid']) || empty($config['auth_token'])) {
            throw new \InvalidArgumentException('SMS configuration is incomplete');
        }
        
        return new TwilioSmsDriver($config);
    }
}
```

### Cas 3 : Canal avec validation

```php
<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use App\Notifications\Drivers\SlackDriver;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class SlackChannel extends AbstractChannel
{
    public function createDriver(): AbstractDriver
    {
        $config = $this->configRepository->get('notification.channels.slack', []);
        
        if (empty($config['webhook_url'])) {
            throw new \InvalidArgumentException('Slack webhook URL is required');
        }
        
        return new SlackDriver($config);
    }
}
```

---

## Flux d'exécution

```
1. Notification envoyée
   ↓
2. Système sélectionne le canal
   ↓
3. Canal est instancié
   ↓
4. Canal appelle createDriver()
   ↓
5. Driver est créé avec la configuration
   ↓
6. Driver envoie la notification
   ↓
7. Résultat retourné
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Configuration manquante | `InvalidArgumentException` | `Notification channel configuration is missing` |
| Driver non implémenté | `RuntimeException` | `Driver class X does not exist` |
| Driver invalide | `RuntimeException` | `Driver must be an instance of AbstractDriver` |
| Configuration incomplète | `InvalidArgumentException` | `SMS configuration is incomplete` |

---

## Intégration

### Avec le système de notification Laravel

```php
<?php

namespace App\Providers;

use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\SmsChannel;
use App\Notifications\Channels\SlackChannel;
use Illuminate\Support\ServiceProvider;

final class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EmailChannel::class, function ($app) {
            return new EmailChannel(
                $app->make(ConfigRepository::class)
            );
        });

        $this->app->singleton(SmsChannel::class, function ($app) {
            return new SmsChannel(
                $app->make(ConfigRepository::class)
            );
        });

        $this->app->singleton(SlackChannel::class, function ($app) {
            return new SlackChannel(
                $app->make(ConfigRepository::class)
            );
        });
    }
}
```

### Avec un service de notification

```php
<?php

namespace App\Services;

use AndyDefer\LaravelNotification\Contracts\ChannelInterface;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\SmsChannel;

final class NotificationService
{
    public function send(string $channel, Notification $notification): void
    {
        $channelInstance = match($channel) {
            'email' => app(EmailChannel::class),
            'sms' => app(SmsChannel::class),
            default => throw new \InvalidArgumentException("Unknown channel: {$channel}")
        };
        
        $driver = $channelInstance->createDriver();
        $driver->send($notification);
    }
}
```

---

## Performance

- **Instanciation** : `O(1)` - création légère
- **Configuration** : Chargée une fois au démarrage (cache de config)
- **Driver création** : `O(1)` - nouvelle instance à chaque appel
- **Mémoire** : Minimale - seulement la configuration injectée

**Optimisations :**
- Les drivers peuvent être mis en cache si lourds
- La configuration est partagée entre tous les canaux

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

namespace App\Notifications\Channels;

use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use App\Notifications\Drivers\EmailDriver;
use App\Notifications\Drivers\SmsDriver;
use App\Notifications\Drivers\SlackDriver;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class MultiChannel extends AbstractChannel
{
    private string $type;

    public function __construct(
        ConfigRepository $configRepository,
        string $type
    ) {
        parent::__construct($configRepository);
        $this->type = $type;
    }

    public function createDriver(): AbstractDriver
    {
        return match($this->type) {
            'email' => new EmailDriver(
                $this->configRepository->get('notification.channels.email')
            ),
            'sms' => new SmsDriver(
                $this->configRepository->get('notification.channels.sms')
            ),
            'slack' => new SlackDriver(
                $this->configRepository->get('notification.channels.slack')
            ),
            default => throw new \InvalidArgumentException(
                "Unknown channel type: {$this->type}"
            )
        };
    }
}

// Utilisation
$emailChannel = new MultiChannel(
    app(ConfigRepository::class),
    'email'
);
$driver = $emailChannel->createDriver();
$driver->send($notification);
```
<!-- ==== ./docs/api-reference/notifiable-interface.md ==== -->

# NotifiableInterface - Interface de notification

## Description

`NotifiableInterface` est le contrat que doivent implémenter les modèles ou entités qui souhaitent recevoir des notifications. Elle définit comment le système de notification peut découvrir **quels canaux** sont disponibles pour un destinataire, avec leurs destinations respectives, et comment l'identifier de manière polymorphique.

## Rôle principal

L'interface permet au système de notification de :

1. **Récupérer les canaux** de notification disponibles avec leurs destinations
2. **Identifier** le destinataire de manière polymorphique (type + ID)
3. **Découpler** le système de notification du modèle spécifique (User, Admin, etc.)
4. **Valider** les destinations avant l'envoi (via les canaux)

---

## API

### `getNotificationChannels(): NotificationChannelCollection`

Retourne la collection des canaux de notification disponibles pour le destinataire, chacun avec sa destination et ses métadonnées.

| Détail | Description |
|--------|-------------|
| **Retourne** | `NotificationChannelCollection` - Collection de `NotificationChannelVO` |
| **Usage** | Le service utilise cette méthode pour déterminer quels canaux sont disponibles et leurs destinations |

**Exemple :**
```php
public function getNotificationChannels(): NotificationChannelCollection
{
    $collection = new NotificationChannelCollection();

    if ($this->email) {
        $collection->add(
            new NotificationChannelVO(
                channelClass: MailChannel::class,
                destination: $this->email,
                metadata: new StrictDataObject(['name' => $this->name])
            )
        );
    }

    if ($this->phone) {
        $collection->add(
            new NotificationChannelVO(
                channelClass: SmsChannel::class,
                destination: $this->phone
            )
        );
    }

    return $collection;
}
```

---

### `getMorphClass(): string`

Retourne le nom de la classe polymorphique du destinataire.

| Détail | Description |
|--------|-------------|
| **Retourne** | `string` - Le nom de la classe (ex: `'user'`, `'admin'`) |
| **Usage** | Utilisé pour le stockage polymorphique dans la table `notifications` |

**Exemple :**
```php
public function getMorphClass(): string
{
    return 'user';
}
```

---

### `getKey(): int`

Retourne l'identifiant unique du destinataire.

| Détail | Description |
|--------|-------------|
| **Retourne** | `int` - L'ID du destinataire |
| **Usage** | Utilisé pour le stockage polymorphique dans la table `notifications` |

**Exemple :**
```php
public function getKey(): int
{
    return $this->id;
}
```

---

## Utilisation dans le service

### 1. Récupération des canaux

Le `NotificationService` utilise `getNotificationChannels()` pour savoir quels canaux sont disponibles :

```php
// Dans NotificationService::send()
$availableChannels = $notifiable->getNotificationChannels();

// Pour chaque canal, on vérifie s'il est demandé
foreach ($availableChannels as $item) {
    $definitionClass = $item->getDefinitionClass();
    // Vérification si le canal est disponible...
}
```

### 2. Récupération des destinations

Le service récupère les destinations pour un canal spécifique :

```php
// Dans NotificationService::buildRecord()
$destinations = [];
foreach ($notifiable->getNotificationChannels() as $channel) {
    if ($channel->getDefinitionClass() === $definitionClass) {
        $destinations[] = $channel->getDestination();
    }
}
```

### 3. Identification polymorphique

Le service utilise `getMorphClass()` et `getKey()` pour stocker la référence au destinataire :

```php
// Dans NotificationService::buildRecord()
return new NotificationRecord(
    // ...
    notifiable_type: $notifiable->getMorphClass(),
    notifiable_id: $notifiable->getKey(),
    // ...
);
```

### 4. Filtrage des canaux disponibles

Le service filtre les canaux demandés en fonction des canaux disponibles :

```php
// Vérification qu'un canal est disponible
foreach ($definitions as $definition) {
    $definitionClass = $definition::class;
    $hasChannel = false;
    foreach ($availableChannels as $item) {
        if ($item->getDefinitionClass() === $definitionClass) {
            $hasChannel = true;
            break;
        }
    }
    if ($hasChannel) {
        $available[] = $definition;
    }
}
```

---

## Cas d'utilisation

### Cas 1 : Utilisateur standard avec email et téléphone

```php
<?php

use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\Collections\NotificationChannelCollection;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\ValueObjects\NotificationChannelVO;
use Illuminate\Database\Eloquent\Model;

class User extends Model implements NotifiableInterface
{
    public function getNotificationChannels(): NotificationChannelCollection
    {
        $collection = new NotificationChannelCollection();

        if ($this->email) {
            $collection->add(
                new NotificationChannelVO(
                    channelClass: MailChannel::class,
                    destination: $this->email,
                    metadata: new StrictDataObject(['name' => $this->name])
                )
            );
        }

        if ($this->phone) {
            $collection->add(
                new NotificationChannelVO(
                    channelClass: SmsChannel::class,
                    destination: $this->phone
                )
            );
        }

        return $collection;
    }

    public function getMorphClass(): string
    {
        return 'user';
    }

    public function getKey(): int
    {
        return $this->id;
    }
}
```

### Cas 2 : Utilisateur avec multiples emails

```php
public function getNotificationChannels(): NotificationChannelCollection
{
    $collection = new NotificationChannelCollection();

    if ($this->primary_email) {
        $collection->add(
            new NotificationChannelVO(
                channelClass: MailChannel::class,
                destination: $this->primary_email,
                metadata: new StrictDataObject(['priority' => 'primary'])
            )
        );
    }

    if ($this->secondary_email) {
        $collection->add(
            new NotificationChannelVO(
                channelClass: MailChannel::class,
                destination: $this->secondary_email,
                metadata: new StrictDataObject(['priority' => 'secondary'])
            )
        );
    }

    return $collection;
}
```

### Cas 3 : Entité non persistante (service)

```php
class OrderNotification implements NotifiableInterface
{
    public function __construct(
        private string $email,
        private int $orderId,
    ) {}

    public function getNotificationChannels(): NotificationChannelCollection
    {
        $collection = new NotificationChannelCollection();

        $collection->add(
            new NotificationChannelVO(
                channelClass: MailChannel::class,
                destination: $this->email,
                metadata: new StrictDataObject([
                    'order_id' => $this->orderId,
                ])
            )
        );

        $collection->add(
            new NotificationChannelVO(
                channelClass: DatabaseChannel::class,
                destination: 'database'
            )
        );

        return $collection;
    }

    public function getMorphClass(): string
    {
        return 'order';
    }

    public function getKey(): int
    {
        return $this->orderId;
    }
}
```

---

## Validation des destinations

Les destinations sont automatiquement validées par le canal lors de la création du `NotificationChannelVO` :

```php
// La validation est effectuée dans le constructeur
new NotificationChannelVO(
    channelClass: MailChannel::class,
    destination: $this->email, // ✅ Validé par MailChannel::validateDestination()
);

// Si la destination est invalide, une InvalidArgumentException est levée
```

Chaque canal définit sa propre validation :

| Canal | Validation |
|-------|------------|
| `MailChannel` | Email valide via `filter_var()` |
| `SmsChannel` | Numéro de téléphone au format international |
| `WhatsAppChannel` | Numéro de téléphone au format international |
| `DatabaseChannel` | Doit être exactement `'database'` |
| `SlackChannel` | URL webhook valide contenant `hooks.slack.com` |
| `TelegramChannel` | Chat ID numérique |
| `PushChannel` | Token non vide d'au moins 10 caractères |

---

## Bonnes pratiques

### 1. Utiliser `add()` plutôt que `from()` pour la collection

```php
// ✅ Bon - évite les problèmes d'hydratation
$collection = new NotificationChannelCollection();
$collection->add($channel);
return $collection;

// ❌ Mauvais - peut causer des problèmes d'hydratation
return NotificationChannelCollection::from($channels);
```

### 2. Toujours vérifier la présence de la destination

```php
// ✅ Bon
if ($this->email) {
    $collection->add(
        new NotificationChannelVO(MailChannel::class, $this->email)
    );
}

// ❌ Mauvais - peut causer une exception
$collection->add(
    new NotificationChannelVO(MailChannel::class, $this->email) // peut être null
);
```

### 3. Ajouter le canal Database pour la traçabilité

```php
// ✅ Bon - toujours disponible pour la traçabilité
$collection->add(
    new NotificationChannelVO(
        channelClass: DatabaseChannel::class,
        destination: 'database',
        metadata: new StrictDataObject(['type' => 'audit'])
    )
);
```

### 4. Utiliser des métadonnées pour le contexte

```php
// ✅ Bon - contexte enrichi
$collection->add(
    new NotificationChannelVO(
        channelClass: MailChannel::class,
        destination: $this->email,
        metadata: new StrictDataObject([
            'name' => $this->name,
            'locale' => $this->locale,
            'timezone' => $this->timezone,
        ])
    )
);
```

---

## Performance

| Aspect | Impact |
|--------|--------|
| Appel de `getNotificationChannels()` | À chaque envoi de notification |
| Collection de canaux | Généralement petite (< 5 éléments) |
| Validation des destinations | Effectuée à la création de la VO |
| Métadonnées | Stockées en JSON (SQLite/MySQL) |

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.2+ | ✅ Complet |

## Exemple complet

```php
<?php

declare(strict_types=1);

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\Channels\WhatsAppChannel;
use AndyDefer\LaravelNotification\Collections\NotificationChannelCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\ValueObjects\NotificationChannelVO;
use Illuminate\Database\Eloquent\Model;

final class User extends Model implements NotifiableInterface
{
    protected $fillable = [
        'name',
        'email',
        'phone',
        'locale',
        'timezone',
    ];

    public function getNotificationChannels(): NotificationChannelCollection
    {
        $collection = new NotificationChannelCollection();

        if ($this->email) {
            $collection->add(
                new NotificationChannelVO(
                    channelClass: MailChannel::class,
                    destination: $this->email,
                    metadata: new StrictDataObject([
                        'name' => $this->name,
                        'locale' => $this->locale,
                    ])
                )
            );
        }

        if ($this->phone) {
            $collection->add(
                new NotificationChannelVO(
                    channelClass: SmsChannel::class,
                    destination: $this->phone
                )
            );
            $collection->add(
                new NotificationChannelVO(
                    channelClass: WhatsAppChannel::class,
                    destination: $this->phone
                )
            );
        }

        // Canal base de données toujours disponible pour la traçabilité
        $collection->add(
            new NotificationChannelVO(
                channelClass: DatabaseChannel::class,
                destination: 'database',
                metadata: new StrictDataObject([
                    'type' => 'user_notification',
                ])
            )
        );

        return $collection;
    }

    public function getMorphClass(): string
    {
        return 'user';
    }

    public function getKey(): int
    {
        return $this->id;
    }
}
```

## Intégration

`NotifiableInterface` s'intègre avec :

- **NotificationService** : orchestre l'envoi
- **NotificationChannelCollection** : collection des canaux
- **NotificationChannelVO** : encapsule canal + destination + métadonnées
- **ChannelInterface** : les canaux avec leur validation
- **NotificationRecord** : stocke la référence polymorphique
- **NotificationRepository** : persiste les notifications
---
<!-- ==== ./docs/api-reference/processors/notification-sender-processor.md ==== -->

# NotificationSenderProcessor - Référence Technique

## Description

Orchestrateur central du système de notification. Coordonne l'envoi de notifications en résolvant les routes disponibles, créant les enregistrements en base de données et dispatchant via les drivers appropriés.

## Hiérarchie / Implémentations

```
NotificationSenderProcessorInterface
    └── NotificationSenderProcessor (final)
```

## Rôle principal

**Orchestrateur du processus d'envoi :**

1. **Résolution des routes** : Filtre les canaux disponibles selon la demande
2. **Application des limites** : Applique `limit_per_channel` par canal
3. **Création des notifications** : Persiste chaque notification
4. **Envoi via drivers** : Dispatch via le driver approprié
5. **Gestion des erreurs** : Loggue les échecs et met à jour les statuts
6. **Collecte des résultats** : Retourne une collection structurée

---

## API / Méthodes publiques

### `send(NotifiableInterface&Model $notifiable, NotificationMessageVO $message, ProcessNotificationRecord $processRecord): SendResultCollection`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface&Model` | L'entité à notifier (User, Order, etc.) |
| `$message` | `NotificationMessageVO` | Le message à envoyer |
| `$processRecord` | `ProcessNotificationRecord` | Configuration du processus (canaux, limite) |

**Retourne :** `SendResultCollection` - Collection des résultats d'envoi

**Exceptions :** 
- `RuntimeException` si aucun canal disponible
- `RuntimeException` si aucun canal après application de la limite

**Exemple :**
```php
$processor = new NotificationSenderProcessor($repository, $logger);

$notifiable = User::find(1);
$message = new NotificationMessageVO(
    'Bienvenue !',
    'Bienvenue sur notre plateforme...'
);

$processRecord = ProcessNotificationRecord::from([
    'channels' => ['email', 'sms'],
    'limit_per_channel' => 1,
]);

$results = $processor->send($notifiable, $message, $processRecord);

foreach ($results as $result) {
    if ($result->success) {
        echo "Notification envoyée via " . $result->channel->getValue();
    }
}
```

---

## Cas d'utilisation

### Cas 1 : Envoi d'une notification à un utilisateur

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Processors\NotificationSenderProcessor;
use AndyDefer\LaravelNotification\Records\ProcessNotificationRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;

// 1. Configuration
$processor = app(NotificationSenderProcessor::class);

// 2. Entité notifiable
$user = User::find(123);
$user->setNotificationChannels([
    EmailChannel::class => 'john@example.com',
    SmsChannel::class => '+33612345678',
]);

// 3. Message
$message = new NotificationMessageVO(
    subject: 'Nouvelle commande',
    content: 'Votre commande #1234 a été validée.'
);

// 4. Processus
$processRecord = ProcessNotificationRecord::from([
    'channels' => [
        EmailChannel::class,
        SmsChannel::class,
    ],
    'limit_per_channel' => 1,
]);

// 5. Envoi
$results = $processor->send($user, $message, $processRecord);
```

---

### Cas 2 : Envoi avec filtrage des canaux

```php
<?php

// L'utilisateur a 3 adresses email et 2 numéros de téléphone
$user = User::find(456);
$user->setNotificationChannels([
    EmailChannel::class => 'john@example.com',
    EmailChannel::class => 'john-work@example.com',
    EmailChannel::class => 'john-backup@example.com',
    SmsChannel::class => '+33612345678',
    SmsChannel::class => '+33798765432',
]);

// Configuration : envoyer sur tous les canaux disponibles
$processRecord = ProcessNotificationRecord::from([
    'channels' => [], // Vide = tous les canaux disponibles
    'limit_per_channel' => null, // Pas de limite
]);

$results = $processor->send($user, $message, $processRecord);

// Résultat : 5 notifications envoyées
// (3 emails + 2 SMS)
```

---

### Cas 3 : Envoi avec limite par canal

```php
<?php

// Configuration : un seul email par canal
$processRecord = ProcessNotificationRecord::from([
    'channels' => [], // Tous les canaux disponibles
    'limit_per_channel' => 1, // Maximum 1 par canal
]);

$results = $processor->send($user, $message, $processRecord);

// Résultat : 2 notifications envoyées
// (1 email + 1 SMS)
```

---

### Cas 4 : Envoi à un modèle personnalisé

```php
<?php

namespace App\Models;

use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
use AndyDefer\LaravelNotification\Collections\NotificationRouteCollection;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Illuminate\Database\Eloquent\Model;

final class Order extends Model implements NotifiableInterface
{
    public function getNotificationChannels(): NotificationRouteCollection
    {
        $routes = new NotificationRouteCollection;
        
        // Notification au client
        $routes->add(new NotificationRouteVO(
            channelClass: EmailChannel::class,
            destination: $this->customer_email,
            metadata: ['order_id' => $this->id]
        ));
        
        // Notification à l'admin
        $routes->add(new NotificationRouteVO(
            channelClass: EmailChannel::class,
            destination: 'admin@example.com',
            metadata: ['role' => 'admin']
        ));
        
        return $routes;
    }

    public function getMorphClass(): string
    {
        return self::class;
    }
}

// Envoi
$order = Order::find(789);
$results = $processor->send($order, $message, $processRecord);
// Résultat : 2 emails (client + admin)
```

---

## Flux d'exécution

```
send(Notifiable, Message, ProcessRecord)
    ↓
Récupération des canaux disponibles
    ↓
Résolution des routes (filtrage)
    ↓
Application de la limite par canal
    ↓
Création d'une session UUID
    ↓
Pour chaque route :
    ↓
    Création de la notification (PENDING)
    ↓
    Création du driver
    ↓
    Envoi via driver
    ↓
    Mise à jour du statut (SENT/FAILED)
    ↓
    Collecte du résultat
    ↓
Retour de SendResultCollection
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucun canal disponible | `RuntimeException` | `No available channels for notifiable X#Y` |
| Aucun canal après limite | `RuntimeException` | `No routes after applying limit for notifiable X#Y` |
| Échec d'un driver | `Exception` (loggée, non bloquante) | `[ExceptionClass] - Message` |

---

## Intégration

### Avec le service provider

```php
<?php

namespace App\Providers;

use AndyDefer\LaravelNotification\Processors\NotificationSenderProcessor;
use AndyDefer\LaravelNotification\Contracts\Processors\NotificationSenderProcessorInterface;
use Illuminate\Support\ServiceProvider;

final class NotificationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(
            NotificationSenderProcessorInterface::class,
            function ($app) {
                return new NotificationSenderProcessor(
                    $app->make(NotificationRepositoryInterface::class),
                    $app->make(LoggerInterface::class)
                );
            }
        );
    }
}
```

### Avec un service de notification

```php
<?php

namespace App\Services;

use AndyDefer\LaravelNotification\Processors\NotificationSenderProcessor;
use AndyDefer\LaravelNotification\Records\ProcessNotificationRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;

final class NotificationService
{
    public function __construct(
        private NotificationSenderProcessor $processor
    ) {}

    public function notifyUser(User $user, string $subject, string $content): void
    {
        $message = new NotificationMessageVO($subject, $content);
        
        $processRecord = ProcessNotificationRecord::from([
            'channels' => [], // Tous les canaux
            'limit_per_channel' => 1,
        ]);
        
        $results = $this->processor->send($user, $message, $processRecord);
        
        if ($results->hasFailures()) {
            // Log ou alerte
        }
    }
}
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `send()` | O(n) | n = nombre de routes |
| Résolution | O(n × m) | n = canaux demandés, m = routes disponibles |
| Limite | O(n) | n = routes |
| Création | O(1) | Insertion base de données |
| Envoi | O(n × d) | n = routes, d = temps d'exécution driver |

**Optimisations :**
- Les résultats sont collectés en mémoire
- Les notifications sont persistées avant l'envoi
- Les échecs ne bloquent pas les autres canaux

---

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.1+ | ✅ Complet |
| PHP 8.2+ | ✅ Complet |
| PHP 8.3+ | ✅ Complet |

---

## Exemple complet

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Processors\NotificationSenderProcessor;
use AndyDefer\LaravelNotification\Records\ProcessNotificationRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use App\Models\User;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\SmsChannel;

// 1. Configuration du notifiable
$user = User::find(42);
$user->setNotificationChannels([
    EmailChannel::class => 'john.doe@example.com',
    EmailChannel::class => 'john@work.example.com',
    SmsChannel::class => '+33612345678',
]);

// 2. Création du message
$message = new NotificationMessageVO(
    subject: '🚀 Nouvelle fonctionnalité disponible',
    content: '<h1>Nouvelle version !</h1><p>Découvrez notre nouvelle interface...</p>'
);

// 3. Configuration du processus
$processRecord = ProcessNotificationRecord::from([
    'channels' => [
        EmailChannel::class,  // Seulement les emails
    ],
    'limit_per_channel' => 1, // Un seul email
]);

// 4. Exécution
$processor = app(NotificationSenderProcessor::class);
$results = $processor->send($user, $message, $processRecord);

// 5. Traitement des résultats
foreach ($results as $result) {
    if ($result->success) {
        echo "✅ Notification envoyée\n";
        echo "   Canal: " . $result->channel->getValue() . "\n";
        echo "   Destination: " . $result->destination . "\n";
    } else {
        echo "❌ Échec de l'envoi\n";
        echo "   Canal: " . $result->channel->getValue() . "\n";
        echo "   Destination: " . $result->destination . "\n";
        echo "   Erreur: " . $result->error_message->getValue() . "\n";
    }
}

// Résultat attendu :
// ✅ Notification envoyée
//    Canal: email
//    Destination: john.doe@example.com
```
<!-- ==== ./docs/api-reference/drivers.md ==== -->

# Drivers - Système de drivers de notification

## Description

Les drivers sont les composants chargés d'**exécuter l'envoi** de la notification vers un canal spécifique. Ils sont instanciés par les canaux et reçoivent la configuration et le record de notification à traiter. Chaque driver est responsable de :

1. **Valider** sa configuration
2. **Exécuter** l'envoi vers le service externe (API, mailer, base de données, etc.)
3. **Persister** le résultat de l'envoi (succès ou échec)
4. **Logger** l'événement via le système de logs structurés

---

## Hiérarchie / Implémentations

```
DriverInterface
    └── AbstractDriver (classe abstraite)
            ├── MailDriver
            ├── DatabaseDriver
            ├── SmsDriver
            ├── WhatsAppDriver
            ├── SlackDriver
            ├── TelegramDriver
            └── PushDriver
```

---

## Interface DriverInterface

### Méthodes

#### `send(NotificationRecord $record): bool`

Déclenche l'envoi de la notification. Cette méthode est le point d'entrée principal du driver.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$record` | `NotificationRecord` | Le record contenant toutes les informations de la notification |

**Retourne :** `bool` - `true` si l'envoi a réussi

**Exceptions :** `NotificationSendException` - Si l'envoi échoue

#### `getChannel(): string`

Retourne l'identifiant technique du canal associé (ex: `'mail'`, `'sms'`).

#### `validateConfiguration(): bool`

Vérifie que la configuration du driver est valide et complète.

---

## AbstractDriver

### Description

Classe abstraite fournissant l'implémentation de base pour tous les drivers. Elle gère le cycle de vie de l'envoi via trois méthodes :

1. `before()` - Log avant l'envoi (niveau DEBUG)
2. `execute()` - Logique d'envoi (à implémenter)
3. `after()` - Log après l'envoi (INFO ou ERROR)

### Méthodes publiques

#### `send(NotificationRecord $record): bool`

Orchestre le cycle complet d'envoi :

```
before() → execute() → after()
```

Si `execute()` lève une exception, `after()` est appelée avec `$success = false` et l'exception est transformée en `NotificationSendException`.

**Exemple :**
```php
$driver = new MailDriver($config, $repository, $logger);
$success = $driver->send($record);
```

### Méthodes protégées

#### `before(NotificationRecord $record): void`

Logue l'événement `notification_sending` au niveau DEBUG.

#### `after(NotificationRecord $record, bool $success, ?\Exception $error = null): void`

Logue l'événement :
- `notification_sent` au niveau INFO si succès
- `notification_failed` au niveau ERROR si échec

#### `execute(NotificationRecord $record): bool` (abstraite)

Logique d'envoi à implémenter par chaque driver. Doit retourner `true` en cas de succès ou lever une exception.

### Méthodes publiques

#### `getChannel(): string` (abstraite)

Retourne l'identifiant du canal.

#### `validateConfiguration(): bool`

Vérifie la configuration. Par défaut retourne `true`. À surcharger pour des validations spécifiques.

---

## Drivers disponibles

### MailDriver

**Driver email** utilisant le système de mail de Laravel.

| Propriété | Valeur |
|-----------|--------|
| Canal | `mail` |
| Dépendances | `MailConfigRecord`, `NotificationRepository`, `LoggerInterface` |

**Responsabilités :**
1. Envoyer l'email via `Mail::send()`
2. Gérer les destinataires uniques ou multiples
3. Persister le résultat en base

**Configuration requise :**
- `default_to` ou `default_from` (via `MailConfigRecord`)

**Exemple d'envoi :**
```php
// Dans le record, les données contiennent :
$data = [
    'to' => 'user@example.com', // ou ['user1@example.com', 'user2@example.com']
    'subject' => 'Bienvenue',
    'body' => 'Contenu de l\'email',
    'from' => 'noreply@example.com',
    'from_name' => 'Application',
];
```

---

### DatabaseDriver

**Driver base de données** qui persiste la notification dans la table `notifications`.

| Propriété | Valeur |
|-----------|--------|
| Canal | `database` |
| Dépendances | `DatabaseConfigRecord`, `NotificationRepository`, `LoggerInterface` |

**Responsabilités :**
1. Persister la notification avec status `SENT`
2. Une exception est levée si la persistance échoue

**Configuration requise :**
- `table` (via `DatabaseConfigRecord`)

---

### SmsDriver

**Driver SMS** (Twilio, Vonage, etc.).

| Propriété | Valeur |
|-----------|--------|
| Canal | `sms` |
| Dépendances | `SmsConfigRecord`, `NotificationRepository`, `LoggerInterface` |

**Responsabilités :**
1. Envoyer le SMS via l'API choisie
2. Persister le résultat (SENT ou FAILED)
3. Logger l'événement SMS

**Configuration requise :**
- `sid`, `token`, `from` (via `SmsConfigRecord`)

**Exemple d'envoi :**
```php
// Dans le record, les données contiennent :
$data = [
    'to' => '+33123456789',
    'body' => 'Votre code est 123456',
];
```

---

### WhatsAppDriver

**Driver WhatsApp** (Meta API, Twilio, etc.).

| Propriété | Valeur |
|-----------|--------|
| Canal | `whatsapp` |
| Dépendances | `WhatsAppConfigRecord`, `NotificationRepository`, `LoggerInterface` |

**Responsabilités :**
1. Envoyer le message WhatsApp via l'API
2. Persister le résultat (SENT ou FAILED)
3. Logger l'événement WhatsApp

**Configuration requise :**
- `access_token`, `phone_number_id` (via `WhatsAppConfigRecord`)

---

### SlackDriver

**Driver Slack** via webhook.

| Propriété | Valeur |
|-----------|--------|
| Canal | `slack` |
| Dépendances | `SlackConfigRecord`, `LoggerInterface` |

**Responsabilités :**
1. Envoyer le message Slack via webhook
2. Vérifier la réponse de l'API

**Configuration requise :**
- `webhook_url` (via `SlackConfigRecord`)

**Exemple d'envoi :**
```php
// Dans le record, les données contiennent :
$data = [
    'webhook_url' => 'https://hooks.slack.com/services/...',
    'text' => 'Message important',
    'attachments' => [
        ['color' => 'danger', 'text' => 'Détails']
    ],
];
```

---

### TelegramDriver

**Driver Telegram** via Bot API.

| Propriété | Valeur |
|-----------|--------|
| Canal | `telegram` |
| Dépendances | `TelegramConfigRecord`, `LoggerInterface` |

**Responsabilités :**
1. Envoyer le message Telegram via l'API
2. Vérifier la réponse de l'API

**Configuration requise :**
- `bot_token`, `chat_id` (via `TelegramConfigRecord`)

---

### PushDriver

**Driver Push Notification** (FCM, APNS, etc.).

| Propriété | Valeur |
|-----------|--------|
| Canal | `push` |
| Dépendances | `PushConfigRecord`, `LoggerInterface` |

**Responsabilités :**
1. Préparer le payload de notification
2. Envoyer vers les plateformes configurées
3. Logger l'événement

**Configuration requise :**
- `fcm_api_key` ou `apns_key_path` (via `PushConfigRecord`)

**Exemple d'envoi :**
```php
// Dans le record, les données contiennent :
$data = [
    'tokens' => ['device_token_1', 'device_token_2'],
    'platform' => 'fcm',
    'title' => 'Nouveau message',
    'body' => 'Contenu',
    'data' => ['key' => 'value'],
    'sound' => 'default',
    'badge' => 1,
];
```

---

## Cycle de vie d'un driver

```
1. Canal appelle createDriver()
   ↓
2. Driver instancié avec config + repository + logger
   ↓
3. Service appelle driver->send($record)
   ↓
4. AbstractDriver::send() → before() → execute() → after()
   ├── before() : log DEBUG 'notification_sending'
   ├── execute() : logique d'envoi
   │   ├── Succès → return true
   │   └── Échec → throw Exception
   └── after() :
       ├── Succès → log INFO 'notification_sent'
       └── Échec → log ERROR 'notification_failed' + throw NotificationSendException
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Envoi échoué (MailDriver) | `RuntimeException` | `Mail destination not specified.` |
| Configuration invalide (SmsDriver) | `RuntimeException` | `SMS destination not specified.` |
| Slack API error | `RuntimeException` | `Slack API error: {message}` |
| Telegram API error | `RuntimeException` | `Telegram API error: {message}` |
| Driver générique | `NotificationSendException` | `Driver {class} failed: {message}` |

---

## Étendre le système

### Créer un driver personnalisé

1. **Créer la classe du driver** :

```php
<?php

declare(strict_types=1);

namespace App\Notifications\Drivers;

use AndyDefer\LaravelNotification\Drivers\AbstractDriver;
use AndyDefer\LaravelNotification\Records\NotificationRecord;
use AndyDefer\LaravelNotification\Repositories\NotificationRepository;
use AndyDefer\Logger\Contracts\LoggerInterface;
use App\Notifications\Records\DiscordConfigRecord;

final class DiscordDriver extends AbstractDriver
{
    public function __construct(
        private readonly DiscordConfigRecord $config,
        private readonly NotificationRepository $repository,
        LoggerInterface $logger,
    ) {
        parent::__construct($logger);
    }

    protected function execute(NotificationRecord $record): bool
    {
        $data = $record->data->toArray();
        $webhookUrl = $data['webhook_url'] ?? $this->config->webhook_url;

        if (! $webhookUrl) {
            throw new \RuntimeException('Discord webhook URL not specified.');
        }

        try {
            // Logique d'envoi Discord
            Http::post($webhookUrl, [
                'content' => $data['body'] ?? 'Notification',
            ]);

            $this->repository->create($record, [
                'status' => NotificationStatus::SENT->value,
                'sent_at' => now(),
            ]);

            return true;

        } catch (\Exception $e) {
            $this->repository->create($record, [
                'status' => NotificationStatus::FAILED->value,
                'error' => $e->getMessage(),
            ]);

            throw $e;
        }
    }

    public function getChannel(): string
    {
        return 'discord';
    }

    public function validateConfiguration(): bool
    {
        return $this->config->webhook_url !== null;
    }
}
```

2. **Utiliser dans le canal associé** :

```php
// Dans DiscordChannel::createDriver()
return new DiscordDriver(
    $this->config,
    app(NotificationRepository::class),
    $this->logger
);
```

---

## Performance

| Aspect | Impact |
|--------|--------|
| Instanciation | Les drivers sont créés à chaque envoi (aucun cache) |
| Logging | 2 appels par envoi (before + after) |
| Persistance | 1 appel à `create()` par driver (sauf erreur) |
| API externes | Temps d'attente variable (réseau) |

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.2+ | ✅ Complet |

## Exemple complet

```php
<?php

use AndyDefer\LaravelNotification\Drivers\MailDriver;
use AndyDefer\LaravelNotification\Records\MailConfigRecord;
use AndyDefer\LaravelNotification\Records\NotificationRecord;
use AndyDefer\LaravelNotification\Repositories\NotificationRepository;
use AndyDefer\Logger\Services\LoggerService;

// Configuration
$config = new MailConfigRecord(
    enabled: true,
    default_to: 'admin@example.com',
    default_from: 'noreply@example.com',
    default_from_name: 'My App'
);

// Dépendances
$repository = app(NotificationRepository::class);
$logger = app(LoggerService::class);

// Instanciation du driver
$driver = new MailDriver($config, $repository, $logger);

// Création du record
$record = new NotificationRecord(
    session_id: 'abc-123',
    type: 'welcome',
    channel: new NotificationChannelVO(MailChannel::class),
    notifiable_type: 'user',
    notifiable_id: 42,
    data: new StrictDataObject([
        'to' => 'user@example.com',
        'subject' => 'Bienvenue',
        'body' => 'Merci de vous être inscrit !',
    ]),
    status: NotificationStatus::PENDING,
);

// Envoi
$success = $driver->send($record);
echo $success ? '✅' : '❌';
```

## Intégration

Les drivers s'intègrent avec :

- **Channels** : chaque driver est créé par un canal
- **AbstractDriver** : classe parente pour tous les drivers
- **NotificationService** : orchestre l'appel aux drivers
- **Repository** : persistance des notifications
- **Logger** : logs structurés (package `andydefer/laravel-logger`)
---
<!-- ==== ./docs/api-reference/tasks/send-recurring-notification-task.md ==== -->

# SendRecurringNotificationTask - Référence Technique

## Description

Tâche récurrente (`AbstractRecurringTask`) pour l'envoi périodique de notifications. S'exécute à intervalles réguliers pour envoyer des notifications à une entité notifiable.

## Hiérarchie / Implémentations

```
AbstractRecurringTask
    └── SendRecurringNotificationTask (final)
```

## Rôle principal

**Point d'entrée pour les notifications périodiques :**

1. **Validation** (`before()`) : Vérifie l'intégrité du payload et l'intervalle
2. **Exécution** (`process()`) : Récupère l'entité et envoie la notification
3. **Finalisation** (`after()`) : Loggue le résultat avec gestion d'erreur structurée

---

## API / Méthodes publiques

### `before(StrictDataObject $payload): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$payload` | `StrictDataObject` | Le payload de la tâche |

**Retourne :** `void`

**Exceptions :** 
- `InvalidArgumentException` si le payload est invalide
- `InvalidArgumentException` si l'intervalle est inférieur à 60 secondes

**Exemple :**
```php
protected function before(StrictDataObject $payload): void
{
    // Validation automatique via NotificationTaskPayloadRecord
    // Vérification supplémentaire : interval >= 60 secondes
}
```

---

### `process(): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `void`

**Exceptions :** `RuntimeException` si l'entité notifiable n'est pas trouvée

**Exemple :**
```php
protected function process(): void
{
    $payload = NotificationTaskPayloadRecord::from($this->context->getPayload());
    $notifiable = $this->findNotifiable($payload);
    // ... envoi de la notification
}
```

---

### `after(bool $success, ?DescriptionVO $error = null): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$success` | `bool` | Indique si la tâche a réussi |
| `$error` | `?DescriptionVO` | Description de l'erreur (si échec) |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
protected function after(bool $success, ?DescriptionVO $error = null): void
{
    // Log du succès ou de l'échec
    // En cas d'échec, log structuré avec LoggerInterface
}
```

---

## Cas d'utilisation

### Cas 1 : Newsletter hebdomadaire

```php
<?php

use AndyDefer\LaravelNotification\Tasks\SendRecurringNotificationTask;
use AndyDefer\LaravelNotification\Records\NotificationTaskPayloadRecord;
use AndyDefer\Task\ValueObjects\RecurringTaskFqcnVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\DurationVO;
use App\Models\User;
use App\Notifications\Channels\EmailChannel;

$payload = NotificationTaskPayloadRecord::from([
    'notifiable_type' => User::class,
    'notifiable_id' => 123,
    'body' => '<h1>Votre newsletter</h1><p>Les dernières actualités...</p>',
    'subject' => 'Newsletter hebdomadaire',
    'type' => 'newsletter',
    'data' => ['user_id' => 123, 'edition' => '2026-W27'],
    'channels' => [EmailChannel::class],
    'limit_per_channel' => 1,
]);

$config = RecurringTaskConfigRecord::from([
    'description' => 'Newsletter hebdomadaire pour user #123',
    'interval_seconds' => 604800, // 7 jours
    'start_at' => new Iso8601DateTimeVO('2026-07-08 09:00:00'),
    'end_at' => new Iso8601DateTimeVO('2026-07-29 09:00:00'),
    'max_attempts' => 3,
]);

$taskService = app(RecurringTaskServiceInterface::class);
$alias = $taskService->register(
    new RecurringTaskFqcnVO(SendRecurringNotificationTask::class),
    StrictDataObject::from($payload->toArray()),
    $config
);
```

---

### Cas 2 : Utilisation via NotificationService

```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendRecurringRecord;

$service = app(NotificationService::class);

$record = SendRecurringRecord::from([
    'interval_seconds' => 86400, // Tous les jours
    'start_at' => new NotificationDateTimeVO('2026-07-06 08:00:00'),
    'end_at' => new NotificationDateTimeVO('2026-07-31 23:59:59'),
    'channels' => [EmailChannel::class, SmsChannel::class],
    'limit_per_channel' => 1,
    'max_attempts' => 2,
]);

$message = new NotificationMessageVO(
    subject: 'Rappel quotidien',
    body: 'N\'oubliez pas de consulter vos notifications.'
);

$alias = $service->sendRecurring($user, $message, $record);
// La tâche SendRecurringNotificationTask est automatiquement planifiée
```

---

### Cas 3 : Gestion des tâches récurrentes

```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;

$service = app(NotificationService::class);
$alias = 'recurring@abc-123';

// Pause de la tâche
$service->pause($alias);

// Reprise
$service->resume($alias);

// Modification de l'intervalle (toutes les 12 heures)
$service->changeInterval($alias, 43200);

// Annulation définitive
$service->cancel($alias);
```

---

## Flux d'exécution

```
1. Tâche déclenchée par cron (intervalle défini)
    ↓
2. before() → Validation du payload + intervalle >= 60s
    ↓
3. process() → Récupération du payload
    ↓
4. findNotifiable() → Recherche de l'entité
    ↓
5. createNotificationMessage() → Construction du message
    ↓
6. createProcessRecord() → Construction du record
    ↓
7. sendNotification() → Envoi via NotificationSenderProcessor
    ↓
8. after() → Logging du résultat
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Notifiable type manquant | `InvalidArgumentException` | `Notifiable type is required` |
| Notifiable ID manquant | `InvalidArgumentException` | `Notifiable ID is required` |
| Body manquant | `InvalidArgumentException` | `Notification body is required` |
| Subject manquant | `InvalidArgumentException` | `Notification subject is required` |
| Aucun canal | `InvalidArgumentException` | `At least one notification channel is required` |
| Interval < 60s | `InvalidArgumentException` | `Interval must be at least 60 seconds for recurring notifications` |
| Entité non trouvée | `RuntimeException` | `Notifiable not found: {type} #{id}` |

---

## Intégration

### Dans le service provider

```php
<?php

namespace App\Providers;

use AndyDefer\LaravelNotification\Tasks\SendRecurringNotificationTask;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
use Illuminate\Support\ServiceProvider;

final class NotificationTaskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SendRecurringNotificationTask::class);
    }
}
```

### Dans une commande

```bash
# Exécuter toutes les tâches récurrentes
./vendor/bin/directive process-tasks --recurring-only

# Surveillance continue
./vendor/bin/directive tasks-watch --recurring-only --interval=60
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `before()` | O(1) | Validation simple |
| `process()` | O(n) | n = nombre de routes |
| `findNotifiable()` | O(1) | Requête Eloquent par ID |
| `sendNotification()` | O(n) | n = nombre de routes |
| `after()` | O(1) | Logging |

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

use AndyDefer\LaravelNotification\Tasks\SendRecurringNotificationTask;
use AndyDefer\LaravelNotification\Records\NotificationTaskPayloadRecord;
use AndyDefer\Task\ValueObjects\RecurringTaskFqcnVO;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\DurationVO;
use AndyDefer\Task\Contracts\Services\RecurringTaskServiceInterface;
use App\Models\User;
use App\Notifications\Channels\EmailChannel;
use App\Notifications\Channels\SmsChannel;

// 1. Création du payload
$payload = NotificationTaskPayloadRecord::from([
    'notifiable_type' => User::class,
    'notifiable_id' => 456,
    'body' => '<h1>Bonjour !</h1><p>Ceci est votre notification quotidienne.</p>',
    'subject' => 'Votre notification quotidienne',
    'type' => 'daily_reminder',
    'data' => [
        'user_id' => 456,
        'day' => '2026-07-06',
        'reminder_type' => 'daily',
    ],
    'channels' => [EmailChannel::class, SmsChannel::class],
    'limit_per_channel' => 1,
]);

// 2. Création de la configuration
$config = RecurringTaskConfigRecord::from([
    'description' => 'Notification quotidienne pour user #456',
    'interval_seconds' => 86400, // Tous les jours
    'start_at' => new Iso8601DateTimeVO('2026-07-06 08:00:00'),
    'end_at' => new Iso8601DateTimeVO('2026-07-31 08:00:00'),
    'max_attempts' => 3,
]);

// 3. Enregistrement de la tâche
$taskService = app(RecurringTaskServiceInterface::class);
$alias = $taskService->register(
    new RecurringTaskFqcnVO(SendRecurringNotificationTask::class),
    StrictDataObject::from($payload->toArray()),
    $config
);

echo "Tâche récurrente planifiée : " . $alias->getValue() . "\n";

// 4. Gestion de la tâche
$taskService->pause($alias);
$taskService->changeInterval($alias, new DurationVO(43200)); // 12 heures
$taskService->resume($alias);

// 5. Vérification de l'état
$debug = $taskService->getDebug($alias);
echo "Statut : " . $debug->status . "\n";
echo "Dernière exécution : " . $debug->last_run_at . "\n";
echo "Prochaine exécution : " . $debug->next_run_at . "\n";
```
<!-- ==== ./docs/api-reference/tasks/send-delayed-notification-task.md ==== -->

# SendDelayedNotificationTask - Référence Technique

## Description

Tâche unique (`AbstractUniqueTask`) pour l'envoi de notifications différées. Récupère l'entité notifiable, construit le message et exécute l'envoi via `NotificationSenderProcessor`.

## Hiérarchie / Implémentations

```
AbstractUniqueTask
    └── SendDelayedNotificationTask (final)
```

## Rôle principal

**Point d'entrée pour l'envoi de notifications planifiées :**

1. **Validation** (`before()`) : Vérifie l'intégrité du payload
2. **Exécution** (`process()`) : Récupère l'entité et envoie la notification
3. **Finalisation** (`after()`) : Loggue le résultat

---

## API / Méthodes publiques

### `before(StrictDataObject $payload): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$payload` | `StrictDataObject` | Le payload de la tâche |

**Retourne :** `void`

**Exceptions :** `InvalidArgumentException` si le payload est invalide

**Exemple :**
```php
protected function before(StrictDataObject $payload): void
{
    // Validation automatique via NotificationTaskPayloadRecord
    // Lance une exception si un champ requis est manquant
}
```

---

### `process(): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `void`

**Exceptions :** `RuntimeException` si l'entité notifiable n'est pas trouvée

**Exemple :**
```php
protected function process(): void
{
    $payload = NotificationTaskPayloadRecord::from($this->context->getPayload());
    $notifiable = $this->findNotifiable($payload);
    // ... envoi de la notification
}
```

---

### `after(bool $success, ?DescriptionVO $error = null): void`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$success` | `bool` | Indique si la tâche a réussi |
| `$error` | `?DescriptionVO` | Description de l'erreur (si échec) |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
protected function after(bool $success, ?DescriptionVO $error = null): void
{
    if ($success) {
        $this->info(new DescriptionVO('Notification envoyée avec succès'));
    } else {
        $this->error(new DescriptionVO('Échec de l\'envoi: ' . $error?->getValue()));
    }
}
```

---

## Cas d'utilisation

### Cas 1 : Envoi d'un email 30 minutes après l'inscription

```php
<?php

use AndyDefer\LaravelNotification\Tasks\SendDelayedNotificationTask;
use AndyDefer\LaravelNotification\Records\NotificationTaskPayloadRecord;
use AndyDefer\LaravelNotification\Records\SendLaterRecord;
use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;

$payload = NotificationTaskPayloadRecord::from([
    'notifiable_type' => App\Models\User::class,
    'notifiable_id' => $user->id,
    'body' => 'Bienvenue sur notre plateforme !',
    'subject' => 'Bienvenue',
    'type' => 'email',
    'data' => ['user_id' => $user->id],
    'channels' => [App\Notifications\Channels\EmailChannel::class],
    'limit_per_channel' => 1,
]);

$config = UniqueTaskConfigRecord::from([
    'description' => 'Email de bienvenue - ' . $user->email,
    'scheduled_at' => new Iso8601DateTimeVO(now()->addMinutes(30)->toIso8601String()),
    'max_attempts' => 3,
    'grace_period' => 86400,
]);

$task = new SendDelayedNotificationTask();
$alias = $task->register(
    new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
    StrictDataObject::from($payload->toArray()),
    $config
);
```

---

### Cas 2 : Utilisation via NotificationService

```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendLaterRecord;

$service = app(NotificationService::class);

$record = SendLaterRecord::from([
    'delay_seconds' => 900, // 15 minutes
    'channels' => [EmailChannel::class, SmsChannel::class],
    'limit_per_channel' => 1,
]);

$message = new NotificationMessageVO(
    subject: 'Rappel de rendez-vous',
    body: 'Votre rendez-vous est dans 15 minutes.'
);

$alias = $service->sendLater($user, $message, $record);
// La tâche SendDelayedNotificationTask est automatiquement planifiée
```

---

## Flux d'exécution

```
1. Tâche planifiée (par cron)
    ↓
2. before() → Validation du payload
    ↓
3. process() → Récupération du payload
    ↓
4. findNotifiable() → Recherche de l'entité
    ↓
5. createNotificationMessage() → Construction du message
    ↓
6. createProcessRecord() → Construction du record
    ↓
7. sendNotification() → Envoi via NotificationSenderProcessor
    ↓
8. after() → Logging du résultat
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Notifiable type manquant | `InvalidArgumentException` | `Notifiable type is required` |
| Notifiable ID manquant | `InvalidArgumentException` | `Notifiable ID is required` |
| Body manquant | `InvalidArgumentException` | `Notification body is required` |
| Subject manquant | `InvalidArgumentException` | `Notification subject is required` |
| Aucun canal | `InvalidArgumentException` | `At least one notification channel is required` |
| Entité non trouvée | `RuntimeException` | `Notifiable not found: {type} #{id}` |

---

## Intégration

### Dans le service provider

```php
<?php

namespace App\Providers;

use AndyDefer\LaravelNotification\Tasks\SendDelayedNotificationTask;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use Illuminate\Support\ServiceProvider;

final class NotificationTaskServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // La tâche est automatiquement disponible via le conteneur
        $this->app->bind(SendDelayedNotificationTask::class);
    }
}
```

### Dans une commande

```bash
# Exécuter manuellement une tâche planifiée
./vendor/bin/directive process-tasks --unique-only
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `before()` | O(1) | Validation simple |
| `process()` | O(n) | n = nombre de routes |
| `findNotifiable()` | O(1) | Requête Eloquent par ID |
| `sendNotification()` | O(n) | n = nombre de routes |

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

use AndyDefer\LaravelNotification\Tasks\SendDelayedNotificationTask;
use AndyDefer\LaravelNotification\Records\NotificationTaskPayloadRecord;
use AndyDefer\Task\ValueObjects\Iso8601DateTimeVO;
use AndyDefer\Task\ValueObjects\UniqueTaskFqcnVO;
use AndyDefer\Task\ValueObjects\UniqueTaskConfigRecord;
use AndyDefer\Task\Contracts\Services\UniqueTaskServiceInterface;
use App\Models\User;
use App\Notifications\Channels\EmailChannel;

// 1. Création du payload
$payload = NotificationTaskPayloadRecord::from([
    'notifiable_type' => User::class,
    'notifiable_id' => 123,
    'body' => '<h1>Bonjour !</h1><p>Votre compte a été créé avec succès.</p>',
    'subject' => 'Bienvenue sur notre plateforme',
    'type' => 'welcome',
    'data' => ['user_id' => 123, 'welcome_code' => 'ABC-123'],
    'channels' => [EmailChannel::class],
    'limit_per_channel' => 1,
]);

// 2. Création de la configuration
$config = UniqueTaskConfigRecord::from([
    'description' => 'Email de bienvenue pour user #123',
    'scheduled_at' => new Iso8601DateTimeVO('2026-07-07 10:00:00'),
    'max_attempts' => 3,
    'grace_period' => 86400,
]);

// 3. Enregistrement de la tâche
$taskService = app(UniqueTaskServiceInterface::class);
$alias = $taskService->register(
    new UniqueTaskFqcnVO(SendDelayedNotificationTask::class),
    StrictDataObject::from($payload->toArray()),
    $config
);

echo "Tâche planifiée avec l'alias : " . $alias->getValue() . "\n";

// 4. Exécution de la tâche (lorsque la date est atteinte)
// La tâche sera exécutée automatiquement par process-tasks

// 5. Vérification du résultat
$debug = $taskService->getDebug($alias);
echo "Statut : " . $debug->status . "\n";
```
<!-- ==== ./docs/api-reference/channels-and-drivers-architecture.md ==== -->

# Channels et Drivers - Comprendre l'architecture

---

## Introduction

Dans Laravel Notification, **Channel** et **Driver** sont deux concepts complémentaires qui forment ensemble le système d'envoi de notifications.

### En une phrase

> **Un Channel définit le "quoi" (le type de notification) et un Driver définit le "comment" (la manière de l'envoyer).**

```
Channel = Type de notification (Email, SMS, Slack, etc.)
Driver  = Mode d'envoi (SMTP, SendGrid, Twilio, etc.)
```

---

## Le Channel

### Qu'est-ce qu'un Channel ?

Un **Channel** représente un **type de notification**. C'est une abstraction qui définit :

- Le type de canal (email, sms, slack, push, etc.)
- La configuration nécessaire (paramètres, credentials)
- La logique de validation des données

### Le rôle du Channel

Le Channel est une **fabrique de Driver**. Il :

1. Reçoit la configuration via le constructeur
2. Valide que la configuration est complète
3. Crée et retourne un Driver configuré

### Structure d'un Channel

```php
<?php

namespace AndyDefer\LaravelNotification\Abstracts;

use AndyDefer\LaravelNotification\Contracts\ChannelInterface;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

abstract class AbstractChannel implements ChannelInterface
{
    public function __construct(
        protected readonly ConfigRepository $configRepository,
    ) {}

    abstract public function createDriver(): AbstractDriver;
}
```

### Pourquoi un Channel plutôt qu'un Driver direct ?

**Séparation des responsabilités :**
- Le Channel gère la **configuration** et la **validation**
- Le Driver gère **l'exécution** de l'envoi

**Flexibilité :**
- Un même Channel peut créer différents Drivers (SMTP, SendGrid, Mailgun) selon la configuration
- Un même Driver peut être utilisé par plusieurs Channels

---

## Le Driver

### Qu'est-ce qu'un Driver ?

Un **Driver** est une **implémentation concrète d'un mode d'envoi**. C'est lui qui :

- Exécute réellement l'envoi de la notification
- Gère les erreurs et les exceptions
- Retourne un résultat structuré

### Le rôle du Driver

Le Driver est le **cœur exécutant** du système. Il :

1. Reçoit le message et la route
2. Valide sa configuration (`validateConfiguration()`)
3. Exécute l'envoi (`execute()`)
4. Retourne un résultat structuré (`SendResultRecord`)

### Cycle de vie d'un Driver

```
send(Message, Route)
    ↓
before() → valide la configuration
    ↓
execute() → envoie réellement
    ↓
after() → logique post-envoi
    ↓
SendResultRecord → résultat structuré
```

### Structure d'un Driver

```php
<?php

namespace AndyDefer\LaravelNotification\Abstracts;

abstract class AbstractDriver implements DriverInterface
{
    final public function send(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): SendResultRecord {
        $this->before($message, $route);

        try {
            $result = $this->execute($message, $route);
            $this->after($message, $route, $result, null);

            return new SendResultRecord(...);
        } catch (\Exception $e) {
            return new SendResultRecord(success: false, ...);
        }
    }

    abstract protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool;

    abstract public function getChannel(): string;
}
```

### Pourquoi un Driver plutôt qu'un Channel direct ?

**Séparation des responsabilités :**
- Le Driver se concentre sur l'exécution
- Le Channel se concentre sur la configuration

**Réutilisabilité :**
- Un Driver peut être réutilisé par plusieurs Channels
- Un Channel peut utiliser différents Drivers

---

## La relation Channel ↔ Driver

### Schéma conceptuel

```
┌─────────────────────────────────────────────────────────────────────┐
│                        CHANNEL                                     │
│  "Je représente le type de notification"                          │
│                                                                     │
│  ┌───────────────────────────────────────────────────────────────┐ │
│  │                       DRIVER                                   │ │
│  │  "Je sais envoyer une notification de ce type"               │ │
│  │                                                               │ │
│  │  ┌─────────────────────────────────────────────────────────┐ │ │
│  │  │                    EXECUTION                            │ │ │
│  │  │  "J'envoie réellement la notification"                  │ │ │
│  │  └─────────────────────────────────────────────────────────┘ │ │
│  └───────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────┘
```

### Exemple concret

```
Channel: Email
    ↓
    Configuration: SMTP (host, port, username, password)
    ↓
Driver: SMTPDriver
    ↓
    Exécution: envoi via PHPMailer / Symfony Mailer
    ↓
Résultat: SendResultRecord (success: true/false, erreur éventuelle)
```

---

## Créer son propre Channel

### Étape 1 : Créer la classe du Channel

```php
<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use App\Notifications\Drivers\TelegramDriver;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class TelegramChannel extends AbstractChannel
{
    public function createDriver(): AbstractDriver
    {
        $config = $this->configRepository->get('notification.channels.telegram', []);
        
        if (empty($config['bot_token'])) {
            throw new \InvalidArgumentException('Telegram bot token is required');
        }
        
        return new TelegramDriver($config);
    }
}
```

### Étape 2 : Enregistrer la configuration

```php
// config/notification.php
return [
    'channels' => [
        'telegram' => [
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_CHAT_ID'),
        ],
    ],
];
```

### Étape 3 : Utiliser le Channel

```php
$channel = new TelegramChannel(app(ConfigRepository::class));
$driver = $channel->createDriver();

$result = $driver->send(
    new NotificationMessageVO('Alert!', 'System down'),
    new NotificationRouteVO('telegram', $config['chat_id'])
);
```

---

## Créer son propre Driver

### Étape 1 : Créer la classe du Driver

```php
<?php

declare(strict_types=1);

namespace App\Notifications\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

final class TelegramDriver extends AbstractDriver
{
    public function __construct(
        private readonly array $config
    ) {}

    public function getChannel(): string
    {
        return 'telegram';
    }

    public function validateConfiguration(): bool
    {
        return !empty($this->config['bot_token']);
    }

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        $url = "https://api.telegram.org/bot{$this->config['bot_token']}/sendMessage";
        
        $payload = [
            'chat_id' => $route->getDestination(),
            'text' => $message->getContent(),
            'parse_mode' => 'HTML',
        ];
        
        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($payload));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        return $httpCode === 200;
    }

    protected function after(
        NotificationMessageVO $message,
        NotificationRouteVO $route,
        bool $success,
        ?\Exception $error = null
    ): void {
        // Logique de logging personnalisée
        if ($success) {
            \Log::info("Telegram notification sent to {$route->getDestination()}");
        } else {
            \Log::error("Telegram notification failed: " . ($error?->getMessage() ?? 'Unknown error'));
        }
    }
}
```

### Étape 2 : Utiliser le Driver

```php
$driver = new TelegramDriver([
    'bot_token' => env('TELEGRAM_BOT_TOKEN'),
]);

$result = $driver->send(
    new NotificationMessageVO('Alert!', 'System down'),
    new NotificationRouteVO('telegram', env('TELEGRAM_CHAT_ID'))
);

if ($result->success) {
    echo "Message sent!";
} else {
    echo "Error: " . $result->error_message->getValue();
}
```

---

## Créer un Driver avec une bibliothèque externe

```php
<?php

declare(strict_types=1);

namespace App\Notifications\Drivers;

use AndyDefer\LaravelNotification\Abstracts\AbstractDriver;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Twilio\Rest\Client;

final class TwilioSmsDriver extends AbstractDriver
{
    private Client $client;

    public function __construct(
        private readonly array $config
    ) {
        $this->client = new Client(
            $config['account_sid'],
            $config['auth_token']
        );
    }

    public function getChannel(): string
    {
        return 'sms';
    }

    public function validateConfiguration(): bool
    {
        return !empty($this->config['account_sid']) 
            && !empty($this->config['auth_token'])
            && !empty($this->config['from']);
    }

    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        $this->client->messages->create(
            $route->getDestination(),
            [
                'from' => $this->config['from'],
                'body' => $message->getContent()
            ]
        );
        
        return true;
    }
}
```

---

## Bonnes pratiques

### 1. Valider la configuration

```php
public function validateConfiguration(): bool
{
    return !empty($this->config['api_key']) 
        && !empty($this->config['base_url']);
}
```

### 2. Gérer les erreurs proprement

```php
protected function execute(
    NotificationMessageVO $message,
    NotificationRouteVO $route
): bool {
    try {
        $response = $this->http->post($this->config['url'], [
            'json' => $message->toArray(),
        ]);
        
        return $response->getStatusCode() === 200;
    } catch (\Exception $e) {
        throw new \RuntimeException(
            "API call failed: " . $e->getMessage(),
            previous: $e
        );
    }
}
```

### 3. Ajouter des logs (méthode after)

```php
protected function after(
    NotificationMessageVO $message,
    NotificationRouteVO $route,
    bool $success,
    ?\Exception $error = null
): void {
    $context = [
        'channel' => $this->getChannel(),
        'destination' => $route->getDestination(),
        'success' => $success,
    ];
    
    if ($success) {
        Log::info('Notification sent', $context);
    } else {
        Log::error('Notification failed', $context + [
            'error' => $error?->getMessage(),
        ]);
    }
}
```

### 4. Rendre le Driver testable

```php
class MyDriver extends AbstractDriver
{
    private ClientInterface $http;

    public function __construct(
        array $config,
        ?ClientInterface $http = null
    ) {
        $this->config = $config;
        $this->http = $http ?? new Client();
    }
}

// Dans les tests
$mockHttp = $this->createMock(ClientInterface::class);
$driver = new MyDriver($config, $mockHttp);
```

---

## Résumé

| Concept | Rôle | Exemple |
|---------|------|---------|
| **Channel** | "Quoi" envoyer | Email, SMS, Slack, Push |
| **Driver** | "Comment" envoyer | SMTP, Twilio, SendGrid, FCM |
| **AbstractChannel** | Fabrique de Driver | Configuration + Validation |
| **AbstractDriver** | Exécution de l'envoi | Cycle de vie + Résultat |

---

## Conclusion

**Channel et Driver travaillent ensemble** pour offrir un système flexible :

- **Le Channel** est le "quoi" : il définit le type de notification et sa configuration.
- **Le Driver** est le "comment" : il exécute l'envoi et gère les erreurs.

Cette séparation permet :

- ✅ De réutiliser un Driver pour plusieurs Channels
- ✅ De changer facilement de mode d'envoi
- ✅ De tester indépendamment la configuration et l'exécution
- ✅ D'ajouter de nouveaux Channels sans toucher aux Drivers existants

---

## Liens utiles

- [📦 Laravel Notification](https://github.com/andydefer/laravel-notification)
<!-- ==== ./docs/api-reference/drivers/database-driver.md ==== -->

# DatabaseDriver - Référence Technique

## Description

Driver de notification qui stocke les notifications dans la base de données pour une consultation ultérieure. Idéal pour l'audit, l'historique des notifications, ou les systèmes nécessitant une traçabilité complète.

## Hiérarchie / Implémentations

```
DriverInterface
    └── AbstractDriver
            └── DatabaseDriver (final)
```

## Rôle principal

- Stocke les notifications en base de données
- Permet la consultation ultérieure des notifications envoyées
- Utile pour l'audit et la traçabilité
- Fonctionne avec n'importe quel système de base de données Laravel

---

## API / Méthodes publiques

### `__construct(DatabaseConfigRecord $config)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$config` | `DatabaseConfigRecord` | Configuration de la base de données (table, etc.) |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
$config = DatabaseConfigRecord::from([
    'table' => 'notifications',
]);

$driver = new DatabaseDriver($config);
```

---

### `send(NotificationMessageVO $message, NotificationRouteVO $route): SendResultRecord`

*Héritée de `AbstractDriver`*

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `NotificationMessageVO` | Le message à stocker |
| `$route` | `NotificationRouteVO` | La route de la notification |

**Retourne :** `SendResultRecord` - Résultat du stockage

**Exceptions :** Aucune (capturées par `AbstractDriver`)

**Exemple :**
```php
$message = new NotificationMessageVO(
    subject: 'Bienvenue !',
    content: 'Contenu de la notification...'
);

$route = new NotificationRouteVO(
    channelClass: DatabaseDriver::class,
    destination: 'user@example.com'
);

$result = $driver->send($message, $route);
```

---

### `getChannel(): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `string` - `'database'`

**Exceptions :** Aucune

**Exemple :**
```php
$channel = $driver->getChannel(); // 'database'
```

---

### `validateConfiguration(): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `bool` - `true` si le nom de la table est défini

**Exceptions :** Aucune

**Exemple :**
```php
if (!$driver->validateConfiguration()) {
    throw new \RuntimeException('Database driver is not properly configured');
}
```

---

## Cas d'utilisation

### Cas 1 : Stockage simple d'une notification

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Drivers\DatabaseDriver;
use AndyDefer\LaravelNotification\Records\DatabaseConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

$config = DatabaseConfigRecord::from([
    'table' => 'notifications',
]);

$driver = new DatabaseDriver($config);

$message = new NotificationMessageVO(
    subject: 'Nouvelle commande',
    content: 'La commande #1234 a été créée.'
);

$route = new NotificationRouteVO(
    channelClass: DatabaseDriver::class,
    destination: 'admin@example.com'
);

$result = $driver->send($message, $route);

if ($result->success) {
    echo "Notification stockée en base de données !";
}
```

---

### Cas 2 : Avec le système de canaux

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Records\DatabaseConfigRecord;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

$configRecord = DatabaseConfigRecord::from([
    'table' => config('notification.database.table', 'notifications'),
]);

$channel = new DatabaseChannel(
    configRepository: app(ConfigRepository::class),
    config: $configRecord
);

$driver = $channel->createDriver();

// La notification sera stockée dans la table 'notifications'
$result = $driver->send($message, $route);
```

---

### Cas 3 : Avec un modèle Eloquent pour la consultation

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

final class Notification extends Model
{
    protected $table = 'notifications';
    
    protected $fillable = [
        'channel',
        'destination',
        'subject',
        'content',
        'sent_at',
        'status',
    ];
}

// Consultation des notifications
$notifications = Notification::where('destination', 'admin@example.com')
    ->orderBy('created_at', 'desc')
    ->limit(10)
    ->get();

foreach ($notifications as $notification) {
    echo $notification->subject . "\n";
    echo $notification->content . "\n";
    echo "---\n";
}
```

---

### Cas 4 : Stockage avec métadonnées (extension possible)

```php
<?php

// Extension du driver pour ajouter des métadonnées
class EnhancedDatabaseDriver extends DatabaseDriver
{
    protected function execute(
        NotificationMessageVO $message,
        NotificationRouteVO $route
    ): bool {
        $data = [
            'channel' => $this->getChannel(),
            'destination' => $route->getDestination(),
            'subject' => $message->getSubjectValue(),
            'content' => $message->getContentValue(),
            'metadata' => json_encode($message->getPayload()),
            'sent_at' => now(),
            'status' => 'sent',
        ];
        
        return DB::table($this->config->table)->insert($data);
    }
}
```

---

## Flux d'exécution

```
DatabaseDriver::send(Message, Route)
    ↓
AbstractDriver::send()
    ↓
DatabaseDriver::before() (hérité)
    ↓
DatabaseDriver::execute()
    ↓
Insertion en base de données
    ↓
DatabaseDriver::after() (hérité)
    ↓
SendResultRecord (success: true/false)
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Table non définie | `RuntimeException` (dans `before()`) | `Driver DatabaseDriver configuration is invalid.` |
| Erreur de base de données | `Exception` (capturée) | `[ExceptionClass] - Message` |

---

## Intégration

### Avec le système de canaux

```php
<?php

namespace App\Notifications\Channels;

use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Drivers\DatabaseDriver;
use AndyDefer\LaravelNotification\Records\DatabaseConfigRecord;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class DatabaseChannel extends AbstractChannel
{
    private DatabaseConfigRecord $config;

    public function __construct(
        ConfigRepository $configRepository
    ) {
        parent::__construct($configRepository);
        
        $this->config = DatabaseConfigRecord::from([
            'table' => $this->configRepository->get('notification.database.table', 'notifications'),
        ]);
    }

    public function createDriver(): AbstractDriver
    {
        return new DatabaseDriver($this->config);
    }
}
```

### Avec un service de notification

```php
<?php

namespace App\Services;

use AndyDefer\LaravelNotification\Drivers\DatabaseDriver;
use AndyDefer\LaravelNotification\Records\DatabaseConfigRecord;

final class NotificationService
{
    private DatabaseDriver $databaseDriver;

    public function __construct()
    {
        $config = DatabaseConfigRecord::from([
            'table' => 'notifications',
        ]);
        
        $this->databaseDriver = new DatabaseDriver($config);
    }

    public function storeNotification(string $channel, string $to, string $subject, string $content): void
    {
        $message = new NotificationMessageVO($subject, $content);
        $route = new NotificationRouteVO($channel, $to);
        
        $this->databaseDriver->send($message, $route);
    }
}
```

---

## Migration de la table

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notifications', function (Blueprint $table) {
            $table->id();
            $table->string('channel');
            $table->string('destination');
            $table->string('subject');
            $table->text('content');
            $table->json('metadata')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
            
            $table->index('destination');
            $table->index('status');
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notifications');
    }
};
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `send()` | O(1) + base de données | Dépend du temps d'insertion |
| `validateConfiguration()` | O(1) | Vérification simple |
| `execute()` | O(1) | Insertion en base de données |

**Optimisations :**
- Les insertions peuvent être bufferisées
- Les index sur `destination` et `created_at` pour les consultations rapides
- Utilisation des bulk inserts pour les volumes importants

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

use AndyDefer\LaravelNotification\Drivers\DatabaseDriver;
use AndyDefer\LaravelNotification\Records\DatabaseConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use App\Models\Notification;

// 1. Configuration
$config = DatabaseConfigRecord::from([
    'table' => 'notifications',
]);

// 2. Création du driver
$driver = new DatabaseDriver($config);

// 3. Validation de la configuration
if (!$driver->validateConfiguration()) {
    throw new \RuntimeException('Database driver is not properly configured');
}

// 4. Création du message
$message = new NotificationMessageVO(
    subject: 'Commande confirmée',
    content: 'Votre commande #12345 a été confirmée et sera expédiée sous 48h.'
);

// 5. Création de la route
$route = new NotificationRouteVO(
    channelClass: DatabaseDriver::class,
    destination: 'client@example.com'
);

// 6. Stockage
$result = $driver->send($message, $route);

// 7. Consultation des notifications
if ($result->success) {
    $notifications = Notification::where('destination', 'client@example.com')
        ->orderBy('created_at', 'desc')
        ->get();
        
    foreach ($notifications as $notification) {
        echo "📧 {$notification->subject}\n";
        echo "📝 {$notification->content}\n";
        echo "📅 {$notification->created_at}\n";
        echo "---\n";
    }
}

// Résultat attendu :
// 📧 Commande confirmée
// 📝 Votre commande #12345 a été confirmée et sera expédiée sous 48h.
// 📅 2026-07-06 14:30:00
// ---
```
<!-- ==== ./docs/api-reference/drivers/mail-driver.md ==== -->

# MailDriver - Référence Technique

## Description

Driver d'envoi d'emails utilisant le système de mail de Laravel (`Illuminate\Support\Facades\Mail`). Il envoie des emails via le driver de mail configuré dans Laravel (SMTP, Sendmail, Mailgun, etc.).

## Hiérarchie / Implémentations

```
DriverInterface
    └── AbstractDriver
            └── MailDriver (final)
```

## Rôle principal

- Envoie des emails via le système de mail de Laravel
- Utilise la configuration définie dans `MailConfigRecord`
- Supporte l'expéditeur par défaut (`default_from`)
- Gère les sujets et les corps HTML

---

## API / Méthodes publiques

### `__construct(MailConfigRecord $config)`

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$config` | `MailConfigRecord` | Configuration du mail (expéditeur, activation, etc.) |

**Retourne :** `void`

**Exceptions :** Aucune

**Exemple :**
```php
$config = MailConfigRecord::from([
    'enabled' => true,
    'default_from' => 'noreply@example.com',
    'default_from_name' => 'My App',
]);

$driver = new MailDriver($config);
```

---

### `send(NotificationMessageVO $message, NotificationRouteVO $route): SendResultRecord`

*Héritée de `AbstractDriver`*

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$message` | `NotificationMessageVO` | Le message à envoyer |
| `$route` | `NotificationRouteVO` | La route (doit contenir l'email du destinataire) |

**Retourne :** `SendResultRecord` - Résultat de l'envoi

**Exceptions :** `RuntimeException` si la destination est vide

**Exemple :**
```php
$message = new NotificationMessageVO(
    subject: 'Bienvenue !',
    content: 'Contenu HTML de l\'email...'
);

$route = new NotificationRouteVO(
    channelClass: MailDriver::class,
    destination: 'user@example.com'
);

$result = $driver->send($message, $route);
```

---

### `getChannel(): string`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `string` - `'mail'`

**Exceptions :** Aucune

**Exemple :**
```php
$channel = $driver->getChannel(); // 'mail'
```

---

### `validateConfiguration(): bool`

| Paramètre | Type | Description |
|-----------|------|-------------|
| Aucun | - | - |

**Retourne :** `bool` - `true` si le mail est activé et qu'un expéditeur par défaut est configuré

**Exceptions :** Aucune

**Exemple :**
```php
if (!$driver->validateConfiguration()) {
    throw new \RuntimeException('Mail driver is not properly configured');
}
```

---

## Cas d'utilisation

### Cas 1 : Envoi d'un email de bienvenue

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Drivers\MailDriver;
use AndyDefer\LaravelNotification\Records\MailConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

$config = MailConfigRecord::from([
    'enabled' => true,
    'default_from' => 'welcome@example.com',
    'default_from_name' => 'My App Team',
]);

$driver = new MailDriver($config);

$message = new NotificationMessageVO(
    subject: 'Bienvenue sur notre plateforme !',
    content: '<h1>Bonjour !</h1><p>Bienvenue sur notre application...</p>'
);

$route = new NotificationRouteVO(
    channelClass: MailDriver::class,
    destination: 'newuser@example.com'
);

$result = $driver->send($message, $route);

if ($result->success) {
    echo "Email de bienvenue envoyé !";
} else {
    echo "Erreur : " . $result->error_message->getValue();
}
```

---

### Cas 2 : Envoi d'une notification avec le système de canaux

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Records\MailConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

$configRecord = MailConfigRecord::from([
    'enabled' => true,
    'default_from' => config('mail.from.address'),
    'default_from_name' => config('mail.from.name'),
]);

$channel = new MailChannel(
    configRepository: app(ConfigRepository::class),
    config: $configRecord
);

$driver = $channel->createDriver();

$result = $driver->send(
    new NotificationMessageVO(
        'Nouveau message',
        '<p>Vous avez reçu un nouveau message...</p>'
    ),
    new NotificationRouteVO(MailDriver::class, 'user@example.com')
);
```

---

### Cas 3 : Avec pièce jointe (extension possible)

```php
<?php

// Le driver peut être étendu pour supporter les pièces jointes
// En surchargeant la méthode execute()

protected function execute(
    NotificationMessageVO $message,
    NotificationRouteVO $route
): bool {
    // ... code existant ...
    
    // Ajout de pièces jointes
    if ($message->hasAttachments()) {
        foreach ($message->getAttachments() as $attachment) {
            $mailMessage->attach(
                $attachment['path'],
                ['as' => $attachment['name']]
            );
        }
    }
    
    // ... suite ...
}
```

---

### Cas 4 : Email avec vue Blade

```php
<?php

// Utilisation avec une vue Blade personnalisée
protected function execute(
    NotificationMessageVO $message,
    NotificationRouteVO $route
): bool {
    $to = $route->getDestination();
    $data = $message->getPayload();

    Mail::send('emails.welcome', $data, function ($mailMessage) use ($to) {
        $mailMessage->to($to)
            ->subject('Bienvenue !')
            ->from('noreply@example.com');
    });

    return true;
}
```

---

## Flux d'exécution

```
MailDriver::send(Message, Route)
    ↓
AbstractDriver::send()
    ↓
MailDriver::before() (hérité)
    ↓
MailDriver::execute()
    ↓
Mail::send() via Laravel Mail facade
    ↓
Envoi de l'email
    ↓
MailDriver::after() (hérité)
    ↓
SendResultRecord (success: true/false)
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Destination vide | `RuntimeException` | `Mail destination not specified.` |
| Configuration invalide | `RuntimeException` (dans `before()`) | `Driver MailDriver configuration is invalid.` |
| Échec de l'envoi | `Exception` (capturée par `AbstractDriver`) | `[ExceptionClass] - Message` |

---

## Intégration

### Avec le système de canaux

```php
<?php

namespace App\Notifications\Channels;

use AndyDefer\LaravelNotification\Abstracts\AbstractChannel;
use AndyDefer\LaravelNotification\Drivers\MailDriver;
use AndyDefer\LaravelNotification\Records\MailConfigRecord;
use Illuminate\Contracts\Config\Repository as ConfigRepository;

final class MailChannel extends AbstractChannel
{
    private MailConfigRecord $config;

    public function __construct(
        ConfigRepository $configRepository
    ) {
        parent::__construct($configRepository);
        
        $this->config = MailConfigRecord::from([
            'enabled' => $this->configRepository->get('mail.enabled', true),
            'default_from' => $this->configRepository->get('mail.from.address'),
            'default_from_name' => $this->configRepository->get('mail.from.name'),
        ]);
    }

    public function createDriver(): AbstractDriver
    {
        if (!$this->config->enabled) {
            throw new \RuntimeException('Mail is disabled');
        }
        
        return new MailDriver($this->config);
    }
}
```

### Avec un service de notification

```php
<?php

namespace App\Services;

use AndyDefer\LaravelNotification\Drivers\MailDriver;
use AndyDefer\LaravelNotification\Records\MailConfigRecord;

final class NotificationService
{
    private MailDriver $mailDriver;

    public function __construct()
    {
        $config = MailConfigRecord::from([
            'enabled' => true,
            'default_from' => config('mail.from.address'),
            'default_from_name' => config('mail.from.name'),
        ]);
        
        $this->mailDriver = new MailDriver($config);
    }

    public function sendWelcomeEmail(string $email, string $name): void
    {
        $message = new NotificationMessageVO(
            subject: 'Bienvenue !',
            content: "<h1>Bonjour {$name} !</h1><p>Bienvenue sur notre plateforme...</p>"
        );
        
        $route = new NotificationRouteVO(MailDriver::class, $email);
        
        $this->mailDriver->send($message, $route);
    }
}
```

---

## Performance

| Opération | Complexité | Notes |
|-----------|------------|-------|
| `send()` | O(1) + réseau | Dépend du temps d'envoi du serveur SMTP |
| `validateConfiguration()` | O(1) | Vérification simple |
| `execute()` | Variable | Dépend du driver de mail configuré |

**Optimisations :**
- Les emails sont envoyés de manière synchrone (par défaut)
- Peut être rendu asynchrone en utilisant les queues Laravel
- La configuration est chargée depuis le `MailConfigRecord`

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

use AndyDefer\LaravelNotification\Drivers\MailDriver;
use AndyDefer\LaravelNotification\Records\MailConfigRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationRouteVO;

// 1. Configuration
$config = MailConfigRecord::from([
    'enabled' => true,
    'default_from' => 'noreply@example.com',
    'default_from_name' => 'Example App',
]);

// 2. Création du driver
$driver = new MailDriver($config);

// 3. Validation de la configuration
if (!$driver->validateConfiguration()) {
    throw new \RuntimeException('Mail driver is not properly configured');
}

// 4. Création du message
$message = new NotificationMessageVO(
    subject: 'Vérification de votre compte',
    content: '<h1>Bonjour !</h1><p>Cliquez sur le lien pour vérifier votre compte :</p><a href="https://example.com/verify">Vérifier</a>'
);

// 5. Création de la route
$route = new NotificationRouteVO(
    channelClass: MailDriver::class,
    destination: 'john.doe@example.com'
);

// 6. Envoi
$result = $driver->send($message, $route);

// 7. Traitement du résultat
if ($result->success) {
    echo "Email envoyé avec succès à {$result->destination}\n";
    echo "Canal : " . $result->channel->getValue() . "\n";
} else {
    echo "Échec de l'envoi : " . $result->error_message->getValue() . "\n";
}

// Résultat attendu :
// Email envoyé avec succès à john.doe@example.com
// Canal : mail
```