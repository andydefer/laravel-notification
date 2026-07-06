# NotificationService - Service de notification

## Description

`NotificationService` est le service central du package `laravel-notification`. Il orchestre l'envoi des notifications en coordonnant les canaux, les drivers et la persistance des notifications.

## Rôle principal

Le service assure :

1. **Résolution des canaux** : Détermine quels canaux sont disponibles pour un destinataire
2. **Création du record** : Construit l'enregistrement de notification avec un `session_id` unique
3. **Orchestration des drivers** : Appelle le driver approprié pour chaque canal
4. **Statistiques** : Fournit des métriques sur les notifications envoyées
5. **Traçabilité** : Permet de suivre une session d'envoi via `session_id`

---

## Méthodes publiques

### `send(NotifiableInterface $notifiable, NotificationMessageVO $message, ?array $channels = null): Collection`

Envoie une notification à un destinataire via les canaux spécifiés.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface` | Le destinataire de la notification |
| `$message` | `NotificationMessageVO` | Le message à envoyer |
| `$channels` | `?array` | Liste des canaux à utiliser (FQCN ou instances) |

**Retourne :** `Collection` - Résultats par canal (clé = FQCN, valeur = `bool`)

**Exceptions :** `RuntimeException` - Si aucun canal disponible

**Exemple :**
```php
$service = app(NotificationService::class);

// Envoyer via tous les canaux disponibles
$results = $service->send($user, new NotificationMessageVO('Bonjour !'));

// Envoyer via des canaux spécifiques
$results = $service->send(
    $user,
    new NotificationMessageVO('Bienvenue !', 'Welcome', 'welcome'),
    [MailChannel::class, SmsChannel::class]
);

// Avec des données structurées
$message = new NotificationMessageVO(
    body: 'Contenu du message',
    subject: 'Sujet',
    type: 'welcome',
    data: new StrictDataObject(['user_id' => $user->id])
);
$results = $service->send($user, $message);
```

---

### `getStats(NotifiableInterface&Model $notifiable): NotificationStatsVO`

Récupère les statistiques de notification pour un destinataire.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$notifiable` | `NotifiableInterface&Model` | Le destinataire |

**Retourne :** `NotificationStatsVO` - Objet contenant les statistiques

**Exemple :**
```php
$stats = $service->getStats($user);
echo "Total: {$stats->total}";
echo "Envoyés: {$stats->sent}";
echo "Échecs: {$stats->failed}";
echo "Taux de succès: {$stats->getSuccessRate()}%";
```

---

### `getSessionStats(string $sessionId): array`

Récupère les statistiques pour une session d'envoi spécifique.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$sessionId` | `string` | L'identifiant unique de la session |

**Retourne :** `array` - Statistiques de la session

```php
$stats = $service->getSessionStats($sessionId);
// [
//     'session_id' => 'uuid',
//     'total' => 4,
//     'sent' => 3,
//     'failed' => 1,
//     'pending' => 0,
// ]
```

---

## Flux d'exécution

```
send()
    ↓
    → Résolution des canaux
        ├── Si $channels = null → tous les canaux disponibles
        └── Sinon → canaux spécifiés
    ↓
    → Filtrage des canaux disponibles
        ├── Vérification que le canal existe chez le notifiable
        └── Si aucun → RuntimeException
    ↓
    → Génération d'un session_id (UUID)
    ↓
    → Pour chaque canal disponible
        ├── buildRecord() → création du NotificationRecord
        ├── createDriver() → instanciation du driver
        ├── driver->send() → envoi
        └── Résultat stocké dans la collection
    ↓
    → Retourne la collection des résultats
```

## Détail des méthodes privées

### `sendViaChannel()`

Envoie la notification via un canal spécifique.

```php
private function sendViaChannel(
    NotifiableInterface $notifiable,
    NotificationMessageVO $message,
    ChannelInterface $definition,
    string $sessionId
): bool
```

**Étapes :**
1. `buildRecord()` : Crée le `NotificationRecord`
2. `$definition->createDriver()` : Instancie le driver
3. `$driver->send($record)` : Exécute l'envoi

---

### `buildRecord()`

Construit l'enregistrement de notification.

```php
private function buildRecord(
    NotifiableInterface $notifiable,
    NotificationMessageVO $message,
    ChannelInterface $definition,
    string $sessionId
): NotificationRecord
```

**Étapes :**
1. Récupère toutes les destinations pour le canal
2. Vérifie qu'au moins une destination existe
3. Construit le tableau `to` avec toutes les destinations
4. Utilise la première destination pour le `NotificationChannelVO`
5. Crée le `NotificationRecord` avec `status = PENDING`

**Points clés :**
- `to` est **toujours un tableau** (même pour une seule destination)
- Le `session_id` est commun à tous les canaux d'un même envoi
- La destination est validée par le canal lors de la création de la VO

---

## Cas d'utilisation

### Cas 1 : Envoi sur tous les canaux disponibles

```php
public function notifyUser(User $user)
{
    $service = app(NotificationService::class);
    
    $message = new NotificationMessageVO(
        body: 'Vous avez reçu un message important',
        subject: 'Nouveau message',
        type: 'new_message'
    );
    
    $results = $service->send($user, $message);
    
    foreach ($results as $channel => $success) {
        Log::info("Channel {$channel}: " . ($success ? '✅' : '❌'));
    }
}
```

### Cas 2 : Envoi sur canaux spécifiques

```php
public function sendWelcome(User $user)
{
    $service = app(NotificationService::class);
    
    $message = new NotificationMessageVO(
        body: 'Bienvenue sur notre plateforme !',
        subject: 'Bienvenue',
        type: 'welcome'
    );
    
    // Envoi uniquement par email et SMS
    $results = $service->send($user, $message, [
        MailChannel::class,
        SmsChannel::class,
    ]);
    
    if ($results->get(MailChannel::class) === false) {
        Log::warning('L\'email de bienvenue a échoué');
    }
}
```

### Cas 3 : Suivi d'une session d'envoi

```php
public function sendAndTrack(User $user, Order $order)
{
    $service = app(NotificationService::class);
    
    $message = new NotificationMessageVO(
        body: 'Votre commande a été expédiée',
        subject: 'Commande #' . $order->id,
        type: 'order_shipped',
        data: new StrictDataObject([
            'order_id' => $order->id,
            'tracking_number' => $order->tracking_number,
        ])
    );
    
    $results = $service->send($user, $message);
    
    $notification = Notification::where('notifiable_id', $user->id)
        ->latest()
        ->first();
    
    if ($notification) {
        $sessionStats = $service->getSessionStats($notification->session_id);
        Log::info('Session stats:', $sessionStats);
    }
}
```

### Cas 4 : Statistiques d'un utilisateur

```php
public function showStats(User $user)
{
    $service = app(NotificationService::class);
    $stats = $service->getStats($user);
    
    return view('user.stats', [
        'total' => $stats->total,
        'sent' => $stats->sent,
        'failed' => $stats->failed,
        'pending' => $stats->pending,
        'success_rate' => $stats->getSuccessRate(),
        'is_success' => $stats->isSuccess(),
        'has_failures' => $stats->hasFailures(),
    ]);
}
```

---

## Gestion des erreurs

| Situation | Exception | Message |
|-----------|-----------|---------|
| Aucun canal disponible | `RuntimeException` | `No available channels for notifiable {type}#{id}` |
| Aucune destination pour un canal | `RuntimeException` | `No destination found for channel {class}` |
| Driver en échec | `NotificationSendException` | `Driver {class} failed: {message}` |

## Performance

| Aspect | Impact |
|--------|--------|
| Résolution des canaux | O(n) sur le nombre de canaux |
| Création des drivers | À chaque appel, pas de cache |
| Persistance | 1 insertion par canal |

## Compatibilité

| Version PHP | Support |
|-------------|---------|
| PHP 8.2+ | ✅ Complet |

## Exemple complet

```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\DomainStructures\Utils\StrictDataObject;

class NotificationController extends Controller
{
    private NotificationService $service;

    public function __construct(NotificationService $service)
    {
        $this->service = $service;
    }

    public function send(User $user)
    {
        $message = new NotificationMessageVO(
            body: 'Bonjour !',
            subject: 'Test de notification',
            type: 'test',
            data: new StrictDataObject([
                'user_id' => $user->id,
                'timestamp' => now(),
            ])
        );

        $results = $this->service->send(
            $user,
            $message,
            [MailChannel::class, SmsChannel::class]
        );

        $success = $results->filter()->count();
        $total = $results->count();

        return response()->json([
            'message' => "{$success}/{$total} notifications envoyées",
            'details' => $results,
        ]);
    }

    public function stats(User $user)
    {
        $stats = $this->service->getStats($user);
        
        return response()->json([
            'total' => $stats->total,
            'sent' => $stats->sent,
            'failed' => $stats->failed,
            'pending' => $stats->pending,
            'success_rate' => $stats->getSuccessRate(),
        ]);
    }
}
```

## Intégration

`NotificationService` s'intègre avec :

- **ChannelInterface** : les canaux de notification
- **NotifiableInterface** : les destinataires
- **NotificationRepository** : la persistance
- **NotificationRecord** : les données de notification
- **NotificationChannelVO** : l'encapsulation des canaux
- **AbstractDriver** : les drivers d'envoi
- **NotificationMessageVO** : le message à envoyer
---