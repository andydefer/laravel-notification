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