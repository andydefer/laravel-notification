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