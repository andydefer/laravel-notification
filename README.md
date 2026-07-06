# Laravel Notification

> Système de notification polymorphique extensible pour applications Laravel avec support multi-canaux

Un package Laravel complet pour gérer des notifications via plusieurs canaux (Email, Base de données, SMS, WhatsApp, etc.) avec le pattern Repository, des DTOs, des Value Objects et une architecture extensible.

---

## 📋 Table des matières

- [Fonctionnalités](#fonctionnalités)
- [Prérequis](#prérequis)
- [Installation](#installation)
- [Configuration](#configuration)
- [Architecture](#architecture)
- [Utilisation](#utilisation)
  - [Implémenter NotifiableInterface](#implémenter-notifiableinterface)
  - [Envoyer une notification](#envoyer-une-notification)
  - [Canaux disponibles](#canaux-disponibles)
  - [Gérer les statistiques](#gérer-les-statistiques)
- [Étendre le système](#étendre-le-système)
  - [Créer un canal personnalisé](#créer-un-canal-personnalisé)
  - [Créer un driver personnalisé](#créer-un-driver-personnalisé)
- [API de référence](#api-de-référence)
- [Tests](#tests)
- [Contribuer](#contribuer)
- [Licence](#licence)

---

## ✨ Fonctionnalités

- ✅ **Double polymorphisme** - Notifiez n'importe quel modèle avec n'importe quel utilisateur
- ✅ **Multi-canaux** - Mail, Database (SMS, WhatsApp, Slack, Telegram, Push à venir)
- ✅ **Validation des destinations** - Chaque canal valide ses destinations (email, téléphone, etc.)
- ✅ **Découverte automatique** - Les canaux sont découverts dynamiquement via `NotifiableInterface`
- ✅ **Session de notification** - Suivez un envoi avec un `session_id` unique
- ✅ **Pattern Repository** - Séparation propre de la logique d'accès aux données
- ✅ **Support des DTOs** - Objets de transfert de données typés
- ✅ **Value Objects** - DateTime, Métadonnées
- ✅ **Logs structurés** - Intégration avec `andydefer/laravel-logger`
- ✅ **Statistiques** - Compteurs par statut, taux de succès, statistiques par session
- ✅ **Extensible** - Ajoutez facilement vos propres canaux et drivers

---

## 🚀 Prérequis

- PHP 8.2 ou supérieur
- Laravel 12.0, 13.0, 14.0 ou 15.0

---

## 📦 Installation

Installez le package via Composer :

```bash
composer require andydefer/laravel-notification
```

### Publier les migrations

```bash
php artisan vendor:publish --tag=notification-migrations
```

### Exécuter les migrations

```bash
php artisan migrate
```

### Publier la configuration (optionnel)

```bash
php artisan vendor:publish --tag=notification-config
```

---

## ⚙️ Configuration

Le fichier de configuration `config/notification.php` :

```php
<?php

return [
    'default_channels' => ['mail', 'database'],

    'channels' => [
        'mail' => [
            'enabled' => env('MAIL_ENABLED', true),
            'driver' => 'mail',
            'default_to' => env('MAIL_DEFAULT_TO'),
            'default_from' => env('MAIL_FROM_ADDRESS'),
            'default_from_name' => env('MAIL_FROM_NAME'),
        ],

        'database' => [
            'driver' => 'database',
            'table' => 'notifications',
        ],

        'sms' => [
            'enabled' => env('SMS_ENABLED', false),
            'driver' => 'twilio',
            'sid' => env('TWILIO_SID'),
            'token' => env('TWILIO_TOKEN'),
            'from' => env('TWILIO_FROM'),
        ],

        'whatsapp' => [
            'enabled' => env('WHATSAPP_ENABLED', false),
            'driver' => 'meta',
            'access_token' => env('WHATSAPP_ACCESS_TOKEN'),
            'phone_number_id' => env('WHATSAPP_PHONE_NUMBER_ID'),
        ],

        'slack' => [
            'enabled' => env('SLACK_ENABLED', false),
            'webhook_url' => env('SLACK_WEBHOOK_URL'),
        ],

        'telegram' => [
            'enabled' => env('TELEGRAM_ENABLED', false),
            'bot_token' => env('TELEGRAM_BOT_TOKEN'),
            'chat_id' => env('TELEGRAM_CHAT_ID'),
        ],

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
    ],

    'logging' => [
        'enabled' => env('NOTIFICATION_LOGGING_ENABLED', true),
        'channel' => env('NOTIFICATION_LOG_CHANNEL', 'daily'),
        'level' => env('NOTIFICATION_LOG_LEVEL', 'info'),
    ],
];
```

### Variables d'environnement

```env
# Mail
MAIL_ENABLED=true
MAIL_DEFAULT_TO=admin@example.com
MAIL_FROM_ADDRESS=noreply@example.com
MAIL_FROM_NAME="My Application"

# SMS (Twilio)
SMS_ENABLED=false
TWILIO_SID=your_twilio_sid
TWILIO_TOKEN=your_twilio_token
TWILIO_FROM=+1234567890

# WhatsApp (Meta)
WHATSAPP_ENABLED=false
WHATSAPP_ACCESS_TOKEN=your_whatsapp_access_token
WHATSAPP_PHONE_NUMBER_ID=your_phone_number_id

# Slack
SLACK_ENABLED=false
SLACK_WEBHOOK_URL=https://hooks.slack.com/services/xxx/yyy/zzz

# Telegram
TELEGRAM_ENABLED=false
TELEGRAM_BOT_TOKEN=your_bot_token
TELEGRAM_CHAT_ID=your_chat_id

# Push Notifications (FCM/APNS)
PUSH_ENABLED=false
FCM_API_KEY=your_fcm_api_key
FCM_PROJECT_ID=your_fcm_project_id
APNS_KEY_PATH=/path/to/apns_key.p8
APNS_KEY_ID=your_key_id
APNS_TEAM_ID=your_team_id
APNS_BUNDLE_ID=com.your.app
```

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────────────────┐
│                      NotificationService                       │
│                     (Orchestrateur principal)                   │
├─────────────────────────────────────────────────────────────────┤
│  send() → Résout les canaux → Crée session_id → Appelle drivers│
│  getStats() → Statistiques par notifiable                      │
│  getSessionStats() → Statistiques par session                  │
└─────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────┐
│                   NotifiableInterface                          │
│                  (Contrat pour les entités)                    │
├─────────────────────────────────────────────────────────────────┤
│  getNotificationChannels() → Canaux disponibles                │
│  getMorphClass() → Type polymorphique                          │
│  getKey() → ID unique                                          │
└─────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────┐
│                       ChannelInterface                         │
│                  (Définition d'un canal)                       │
├─────────────────────────────────────────────────────────────────┤
│  MailChannel │ DatabaseChannel │ SmsChannel │ WhatsAppChannel  │
│  SlackChannel │ TelegramChannel │ PushChannel                  │
└─────────────────────────────────────────────────────────────────┘
                                    │
                                    ▼
┌─────────────────────────────────────────────────────────────────┐
│                       AbstractDriver                           │
│                    (Base des drivers)                          │
├─────────────────────────────────────────────────────────────────┤
│  MailDriver │ DatabaseDriver │ SmsDriver │ WhatsAppDriver      │
│  SlackDriver │ TelegramDriver │ PushDriver                     │
└─────────────────────────────────────────────────────────────────┘
```

---

## 📖 Utilisation

### Implémenter NotifiableInterface

Pour qu'un modèle puisse recevoir des notifications, il doit implémenter `NotifiableInterface` :

```php
<?php

namespace App\Models;

use AndyDefer\DomainStructures\Utils\StrictDataObject;
use AndyDefer\LaravelNotification\Channels\DatabaseChannel;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\Collections\NotificationChannelCollection;
use AndyDefer\LaravelNotification\Contracts\NotifiableInterface;
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

        // Canal base de données toujours disponible pour la traçabilité
        $collection->add(
            new NotificationChannelVO(
                channelClass: DatabaseChannel::class,
                destination: 'database',
                metadata: new StrictDataObject(['type' => 'user_notification'])
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

### Envoyer une notification

```php
<?php

namespace App\Http\Controllers;

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function sendWelcome(User $user)
    {
        // Message simple
        $message = new NotificationMessageVO(
            body: 'Bienvenue sur notre plateforme !',
            subject: 'Bienvenue',
            type: 'welcome'
        );

        // Envoyer via tous les canaux disponibles
        $results = $this->notificationService->send($user, $message);

        return response()->json([
            'message' => 'Notification envoyée',
            'results' => $results,
        ]);
    }

    public function sendOrderShipped(User $user, Order $order)
    {
        // Message avec données structurées
        $message = new NotificationMessageVO(
            body: 'Votre commande a été expédiée',
            subject: 'Commande #' . $order->id,
            type: 'order_shipped',
            data: new StrictDataObject([
                'order_id' => $order->id,
                'tracking_number' => $order->tracking_number,
                'shipping_date' => $order->shipped_at->toIso8601String(),
            ])
        );

        // Envoyer uniquement par email et SMS
        $results = $this->notificationService->send(
            $user,
            $message,
            [
                MailChannel::class,
                SmsChannel::class,
            ]
        );

        return response()->json($results);
    }
}
```

---

## 📧 Canaux disponibles

### MailChannel

Le canal email utilise le système de mail de Laravel.

**Validation :** Email valide via `filter_var($destination, FILTER_VALIDATE_EMAIL)`

**Configuration :**
```php
'mail' => [
    'enabled' => true,
    'driver' => 'mail',
    'default_to' => env('MAIL_DEFAULT_TO'),
    'default_from' => env('MAIL_FROM_ADDRESS'),
    'default_from_name' => env('MAIL_FROM_NAME'),
]
```

**Exemple d'utilisation :**
```php
$message = new NotificationMessageVO(
    body: 'Contenu de l\'email',
    subject: 'Sujet de l\'email',
    type: 'email_notification'
);

$results = $service->send($user, $message, [MailChannel::class]);
```

### DatabaseChannel

Le canal base de données stocke les notifications dans la table `notifications`.

**Validation :** La destination doit être exactement `'database'`

**Configuration :**
```php
'database' => [
    'driver' => 'database',
    'table' => 'notifications',
]
```

**Exemple d'utilisation :**
```php
$message = new NotificationMessageVO(
    body: 'Notification stockée en base',
    type: 'database_notification'
);

$results = $service->send($user, $message, [DatabaseChannel::class]);
```

### SMS, WhatsApp, Slack, Telegram, Push (à venir)

Les canaux suivants sont disponibles dans le package et prêts à être configurés :

| Canal | Statut | Configuration requise |
|-------|--------|----------------------|
| **SmsChannel** | ✅ Disponible | `sid`, `token`, `from` |
| **WhatsAppChannel** | ✅ Disponible | `access_token`, `phone_number_id` |
| **SlackChannel** | ✅ Disponible | `webhook_url` |
| **TelegramChannel** | ✅ Disponible | `bot_token`, `chat_id` |
| **PushChannel** | ✅ Disponible | `fcm_api_key` ou `apns_key_path` |

---

## 📊 Gérer les statistiques

### Statistiques d'un utilisateur

```php
$stats = $service->getStats($user);

echo "Total: {$stats->total}\n";
echo "Envoyés: {$stats->sent}\n";
echo "Échecs: {$stats->failed}\n";
echo "En attente: {$stats->pending}\n";
echo "Taux de succès: {$stats->getSuccessRate()}%\n";
echo "Succès global: " . ($stats->isSuccess() ? '✅' : '❌') . "\n";
```

### Statistiques d'une session

```php
// Après un envoi, récupérez le session_id
$notification = Notification::where('notifiable_id', $user->id)
    ->latest()
    ->first();

if ($notification) {
    $sessionStats = $service->getSessionStats($notification->session_id);
    
    echo "Session: {$sessionStats['session_id']}\n";
    echo "Total: {$sessionStats['total']}\n";
    echo "Envoyés: {$sessionStats['sent']}\n";
    echo "Échecs: {$sessionStats['failed']}\n";
    echo "En attente: {$sessionStats['pending']}\n";
}
```

---

## 🔧 Étendre le système

### Créer un canal personnalisé

Pour ajouter un nouveau canal (ex: Discord), créez une classe qui étend `AbstractChannel` :

```php
<?php

namespace App\Notifications\Channels;

use AndyDefer\DomainStructures\Abstracts\AbstractRecord;
use AndyDefer\LaravelNotification\Channels\AbstractChannel;
use AndyDefer\LaravelNotification\Drivers\AbstractDriver;
use AndyDefer\LaravelNotification\Records\DiscordConfigRecord;
use AndyDefer\LaravelNotification\Repositories\NotificationRepository;
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
            app(NotificationRepository::class),
            $this->logger
        );
    }
}
```

### Créer un driver personnalisé

```php
<?php

namespace App\Notifications\Drivers;

use AndyDefer\LaravelNotification\Drivers\AbstractDriver;
use AndyDefer\LaravelNotification\Enums\NotificationStatus;
use AndyDefer\LaravelNotification\Records\NotificationRecord;
use AndyDefer\LaravelNotification\Repositories\NotificationRepository;
use AndyDefer\Logger\Contracts\LoggerInterface;
use App\Notifications\Records\DiscordConfigRecord;
use Illuminate\Support\Facades\Http;

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
            $response = Http::post($webhookUrl, [
                'content' => $data['body'] ?? 'Notification',
            ]);

            if (! $response->successful()) {
                throw new \RuntimeException('Discord API error: ' . $response->body());
            }

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
}
```

### Créer le Record de configuration

```php
<?php

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

### Ajouter la configuration

```php
// config/notification.php
'discord' => [
    'enabled' => env('DISCORD_ENABLED', false),
    'webhook_url' => env('DISCORD_WEBHOOK_URL'),
]
```

### Utiliser le canal personnalisé

```php
use App\Notifications\Channels\DiscordChannel;

// Ajouter le canal au notifiable
public function getNotificationChannels(): NotificationChannelCollection
{
    $collection = parent::getNotificationChannels();
    
    $collection->add(
        new NotificationChannelVO(
            channelClass: DiscordChannel::class,
            destination: config('notification.channels.discord.webhook_url')
        )
    );
    
    return $collection;
}

// Envoyer via Discord
$results = $service->send($user, $message, [DiscordChannel::class]);
```

**Important :** Les canaux sont découverts automatiquement via `NotifiableInterface::getNotificationChannels()`. Il n'y a pas de méthode `registerChannel()` à appeler. Le service utilise dynamiquement les canaux fournis par le notifiable.

---

## 📚 API de référence

### NotificationService

| Méthode | Description | Retourne |
|---------|-------------|----------|
| `send(NotifiableInterface $notifiable, NotificationMessageVO $message, ?array $channels)` | Envoie une notification | `Collection` |
| `getStats(NotifiableInterface&Model $notifiable)` | Statistiques d'un notifiable | `NotificationStatsVO` |
| `getSessionStats(string $sessionId)` | Statistiques d'une session | `array` |

### NotificationChannelVO

| Méthode | Description | Retourne |
|---------|-------------|----------|
| `getDefinition()` | Récupère la définition du canal | `ChannelInterface` |
| `getName()` | Nom du canal | `string` |
| `getLabel()` | Libellé du canal | `string` |
| `getIcon()` | Icône du canal | `string` |
| `getDestination()` | Destination du canal | `string` |
| `getMetadata()` | Métadonnées | `?StrictDataObject` |
| `getValue()` | Clé unique (FQCN) | `string` |
| `getData()` | Données complètes | `StrictDataObject` |

### NotificationMessageVO

| Méthode | Description | Retourne |
|---------|-------------|----------|
| `__construct(string $body, ?string $subject, ?string $type, ?StrictDataObject $data)` | Crée un message | - |
| `getBody()` | Corps du message | `string` |
| `getSubject()` | Sujet | `?string` |
| `getType()` | Type de notification | `string` |
| `getData()` | Données structurées | `StrictDataObject` |
| `with(string $key, mixed $value)` | Ajoute une donnée | `self` |
| `has(string $key)` | Vérifie l'existence | `bool` |
| `get(string $key, mixed $default)` | Récupère une donnée | `mixed` |

---

## 🧪 Tests

### Exécuter les tests

```bash
composer test
```

### Exécuter les tests unitaires

```bash
composer test-unit
```

### Exécuter les tests d'intégration

```bash
composer test-integration
```

---

## 🤝 Contribuer

Veuillez consulter [CONTRIBUTING](CONTRIBUTING.md) pour plus de détails.

### Flux de développement

1. Forkez le dépôt
2. Créez une branche de fonctionnalité (`git checkout -b feature/amazing-feature`)
3. Apportez vos modifications
4. Exécutez les tests (`composer test`)
5. Committez vos modifications (`git commit -m 'Ajouter une fonctionnalité géniale'`)
6. Poussez vers la branche (`git push origin feature/amazing-feature`)
7. Ouvrez une Pull Request

---

## 📦 Dépendances

- [`andydefer/laravel-repository`](https://github.com/andydefer/laravel-repository) - Pattern Repository
- [`andydefer/laravel-logger`](https://github.com/andydefer/laravel-logger) - Logs structurés
- [`andydefer/php-vo`](https://github.com/andydefer/php-vo) - Value Objects
- [`andydefer/domain-structures`](https://github.com/andydefer/domain-structures) - Structures de domaine

---

## 👨‍💻 Auteur

**Andy Kani**
- GitHub: [@andydefer](https://github.com/andydefer)
- Email: andykanidimbu@gmail.com

---

## 📄 Licence

Ce package est sous licence MIT. Voir le fichier [LICENSE](LICENSE) pour plus d'informations.

---

## ⭐ Support

Si vous trouvez ce package utile, n'hésitez pas à lui donner une ⭐ sur GitHub !

---

## 🚀 Roadmap

Les canaux suivants sont déjà implémentés dans le package :

- [x] MailChannel
- [x] DatabaseChannel
- [x] SmsChannel
- [x] WhatsAppChannel
- [x] SlackChannel
- [x] TelegramChannel
- [x] PushChannel

Améliorations futures :

- [ ] File d'attente pour les envois asynchrones
- [ ] Templates de notifications
- [ ] Notifications groupées
- [ ] Webhooks de statut
- [ ] Support des pièces jointes

---

**Construit avec ❤️ pour la communauté Laravel**