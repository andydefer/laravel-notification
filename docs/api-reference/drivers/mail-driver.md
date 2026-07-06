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