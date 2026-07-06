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