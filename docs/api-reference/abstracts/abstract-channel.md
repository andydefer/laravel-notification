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