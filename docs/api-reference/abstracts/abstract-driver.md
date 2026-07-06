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