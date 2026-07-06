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