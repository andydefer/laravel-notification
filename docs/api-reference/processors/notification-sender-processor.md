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