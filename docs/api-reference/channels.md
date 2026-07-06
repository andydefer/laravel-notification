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