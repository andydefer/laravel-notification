Vous avez parfaitement raison ! C'est une omission importante. Le `NotificationService` est le point d'entrée principal pour l'utilisateur, l'API publique qui orchestre tout. Je vais immédiatement corriger le document en ajoutant une section dédiée à ce service central.

---

# WHY LARAVEL NOTIFICATION

## Le système de notifications multi-canaux qui s'adapte à vos besoins

---

## L'histoire qui a donné naissance à Laravel Notification

Imaginez la situation suivante :

Un développeur freelance est missionné pour créer une application de gestion médicale pour un réseau de cliniques. L'application doit notifier les médecins pour :
- Les rendez-vous confirmés
- Les résultats d'analyses disponibles
- Les alertes d'urgence
- Les rappels de consultation

Chaque médecin a :
- Une adresse email professionnelle
- Une adresse email personnelle (parfois)
- Un numéro de téléphone portable
- Un numéro de téléphone professionnel (parfois)
- Des préférences de notification (par SMS le jour, par email la nuit)

Le développeur commence par implémenter un système d'email avec Laravel Mail. Ça fonctionne bien. Puis le client demande les SMS. Le développeur ajoute Twilio. Puis WhatsApp Business API arrive. Le client veut aussi une trace de toutes les notifications en base de données pour l'audit.

**Rapidement, le code devient un cauchemar :**

```php
// ❌ Code spaghetti qui grandit avec chaque nouveau canal
class NotificationService
{
    public function send($user, $message, $channel)
    {
        if ($channel === 'email') {
            if ($user->email_primary) {
                Mail::to($user->email_primary)->send($message);
            }
            if ($user->email_secondary) {
                Mail::to($user->email_secondary)->send($message);
            }
        } elseif ($channel === 'sms') {
            if ($user->phone_primary) {
                Twilio::message($user->phone_primary)->send($message);
            }
            if ($user->phone_secondary) {
                Twilio::message($user->phone_secondary)->send($message);
            }
        } elseif ($channel === 'whatsapp') {
            WhatsApp::message($user->phone_primary)->send($message);
        } elseif ($channel === 'database') {
            NotificationLog::create([
                'user_id' => $user->id,
                'message' => $message,
                'channel' => $channel,
            ]);
        }
        // ... et ça continue à chaque nouveau canal
    }
}
```

Les problèmes s'accumulent au fil des sprints :

**Duplication de code** : Chaque canal a sa propre logique, mais la structure est toujours la même → validation, envoi, logging, gestion d'erreur. À chaque nouveau canal, c'est 50 lignes de code qui se ressemblent.

**Difficulté d'ajout** : Un nouveau canal (Slack, Telegram, Push Notification) signifie modifier 5 à 10 fichiers différents. Le développeur hésite à ajouter des fonctionnalités par peur de casser l'existant.

**Pas d'historique fiable** : La table `notification_logs` est remplie manuellement, avec des champs différents selon le canal. Impossible de faire un rapport fiable sur les notifications envoyées.

**Pas de gestion d'erreur unifiée** : Si l'email échoue, on loggue une erreur. Si le SMS échoue, on loggue une autre erreur. Si WhatsApp échoue, on ne loggue rien parce que le développeur a oublié.

**Pas de testabilité** : Pour tester l'envoi, il faut appeler les vraies APIs. Les tests sont lents, fragiles, et coûtent de l'argent (chaque SMS envoyé en test est facturé).

**Pas de traçabilité** : Quand un médecin dit "Je n'ai pas reçu la notification", impossible de savoir si elle a été envoyée, sur quel canal, et si elle a échoué. Le développeur passe des heures à chercher dans les logs.

Le client est mécontent. Le développeur est frustré. L'application devient difficile à maintenir.

**Ce développeur a besoin d'un système qui :**

1. **Standardise** l'envoi de notifications (même structure pour tous les canaux)
2. **Supporte** plusieurs canaux (Email, SMS, WhatsApp, Database, Slack, Telegram, Push...)
3. **Persiste** automatiquement toutes les notifications pour l'audit
4. **Unifie** la gestion des erreurs (même format, même logique)
5. **Facilite** l'ajout de nouveaux canaux (une classe, pas 10 fichiers)
6. **Est testable** sans appeler les vraies APIs

C'est précisément ce problème que **Laravel Notification** résout.

---

## Le système de notification natif de Laravel

Laravel propose un système de notifications intégré, simple et efficace pour les cas basiques.

```php
// ✅ Envoi simple d'une notification
$user->notify(new InvoicePaid($invoice));

// ✅ Définition de la notification
class InvoicePaid extends Notification
{
    public function via($notifiable)
    {
        return ['mail', 'database'];
    }
    
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->line('Votre facture a été payée.')
            ->action('Voir la facture', url('/invoices/1'));
    }
}
```

**Ce qu'il fait bien :**
- ✅ Interface simple et intuitive
- ✅ Supporte plusieurs canaux (mail, database, broadcast, slack, nexmo)
- ✅ Intégré à Laravel, pas de dépendance externe
- ✅ Documentation officielle complète

**Ce qu'il ne couvre pas (et que Laravel Notification complète) :**

- 📌 **Une seule destination par canal** : Par défaut, un utilisateur ne peut avoir qu'un seul email et qu'un seul numéro de téléphone. Pour gérer plusieurs adresses (professionnelle, personnelle), il faut des solutions alternatives.

- 📌 **Persistance sélective** : Seul le canal `database` persiste les notifications. Les emails et SMS ne sont pas tracés automatiquement, ce qui rend l'audit complexe.

- 📌 **Absence de retour d'envoi** : Le système natif ne retourne pas de résultat. On ne sait pas si la notification a réellement été envoyée ou si elle a échoué.

- 📌 **Ajout de canaux personnalisés** : Pour ajouter un canal personnalisé, il faut créer un service provider, enregistrer le canal, et modifier la configuration. C'est faisable mais nécessite une bonne connaissance de l'architecture interne.

- 📌 **Logique métier limitée** : Il n'existe pas de mécanisme natif pour définir des règles métier complexes comme "envoyer par SMS le jour, par email la nuit" ou "limiter le nombre de notifications par jour".

Ces limitations ne sont pas des défauts du système natif, mais des choix de conception pour rester simple et adapté à 80% des cas d'usage. **Laravel Notification** est conçu pour couvrir les 20% restants, où la complexité métier l'emporte sur la simplicité d'implémentation.

---

## Laravel Notification : Une extension complémentaire

**Laravel Notification n'est pas un remplacement du système natif de Laravel.** C'est une extension conçue pour répondre aux besoins que le système natif ne couvre pas.

**Laravel Notification se concentre sur trois aspects que le système natif ne gère pas :**

### 1. La persistance systématique

> "Chaque notification envoyée doit pouvoir être retrouvée"

Le système natif persiste uniquement les notifications du canal `database`. Avec Laravel Notification, **toutes** les notifications sont persistées, quel que soit le canal.

```php
// Laravel Notification : tout est persisté
$processor->send($user, $message, $processRecord);
// → La notification est en base de données (TOUJOURS)
// → L'email est envoyé ET tracé avec son statut
// → Le SMS est envoyé ET tracé avec son statut
// → WhatsApp est envoyé ET tracé avec son statut
```

### 2. Les routes multiples

> "Un utilisateur peut avoir plusieurs adresses email et plusieurs numéros de téléphone"

Le système natif suppose qu'un utilisateur a une seule destination par canal. Laravel Notification permet d'en avoir plusieurs via l'interface `NotifiableInterface`.

```php
// Laravel Notification : multiples destinations via getNotificationChannels()
class User extends Model implements NotifiableInterface
{
    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;
        
        // ✅ Email principal
        $collection->add(new NotificationRouteVO(
            channelClass: MailChannel::class,
            destination: $this->email_primary,
            metadata: new StrictDataObject(['type' => 'primary'])
        ));
        
        // ✅ Email secondaire
        $collection->add(new NotificationRouteVO(
            channelClass: MailChannel::class,
            destination: $this->email_secondary,
            metadata: new StrictDataObject(['type' => 'secondary'])
        ));
        
        // ✅ SMS principal
        $collection->add(new NotificationRouteVO(
            channelClass: SmsChannel::class,
            destination: $this->phone_primary
        ));
        
        // ✅ SMS secondaire
        $collection->add(new NotificationRouteVO(
            channelClass: SmsChannel::class,
            destination: $this->phone_secondary
        ));
        
        return $collection;
    }
}
```

### 3. Le contrôle granulaire

> "Je veux décider comment, quand et combien de notifications sont envoyées"

Le système natif envoie sur tous les canaux déclarés, sans contrôle. Laravel Notification offre un contrôle fin.

```php
// Laravel Notification : contrôle granulaire
$processRecord = ProcessNotificationRecord::from([
    'channels' => [EmailChannel::class],    // Uniquement les emails
    'limit_per_channel' => 1,               // Un seul email par canal
]);

$processor->send($user, $message, $processRecord);
// → Seulement le premier email (le principal) est envoyé
```

---

## Le cœur du système : NotificationService

**Le `NotificationService` est le point d'entrée unique et l'API publique de Laravel Notification.** C'est la classe que les développeurs utilisent directement dans leur code applicatif.

### Son rôle central

Le `NotificationService` est l'orchestrateur principal qui :

1. **Expose une API simple et unifiée** : `sendNow()`, `sendLater()`, `sendAt()`, `sendRecurring()`
2. **Gère la planification** : Il utilise les services `UniqueTaskService` et `RecurringTaskService` pour planifier les notifications différées et récurrentes
3. **Délègue l'exécution** : Il passe le relais au `NotificationSenderProcessor` pour l'envoi immédiat
4. **Assure la traçabilité** : Il logge chaque action et utilise le `NotificationRepository` pour les statistiques
5. **Gère le cycle de vie des tâches** : Pause, reprise, annulation, modification d'intervalle

```php
// L'API publique du service - simple et intuitive
$service = app(NotificationService::class);

// ✅ Envoi immédiat
$results = $service->sendNow($user, $message, $sendNowRecord);

// ⏱️ Envoi différé (dans 30 minutes)
$alias = $service->sendLater($user, $message, $sendLaterRecord);

// 📅 Envoi planifié (à une date précise)
$alias = $service->sendAt($user, $message, $sendAtRecord);

// 🔄 Envoi récurrent (tous les jours)
$alias = $service->sendRecurring($user, $message, $sendRecurringRecord);

// 🎮 Gestion des tâches
$service->pause($alias);
$service->resume($alias);
$service->cancel($alias);
$service->changeInterval($alias, 7200); // Toutes les 2 heures

// 📊 Statistiques
$stats = $service->getStats($user);
$sessionStats = $service->getSessionStats($sessionId);
```

### Pourquoi ce service est-il central ?

**1. Il masque la complexité sous-jacente**

L'utilisateur n'a pas besoin de connaître :
- Le `NotificationSenderProcessor`
- Les services de tâches (`UniqueTaskService`, `RecurringTaskService`)
- Les repositories
- Les détails d'implémentation des canaux et drivers

**2. Il offre une interface cohérente**

Que vous envoyiez une notification immédiatement ou dans 3 mois, l'API reste la même. Le `NotificationService` s'occupe de la logique de planification.

**3. Il intègre la logique métier**

- Validation des délais (`delay_seconds > 0`)
- Validation des dates (`scheduled_at` doit être dans le futur)
- Validation des intervalles (`interval_seconds >= 1`)
- Gestion des erreurs et logging

**4. Il est entièrement testable**

Grâce à l'injection de dépendances, chaque composant peut être mocké :
```php
$mockProcessor = $this->createMock(NotificationSenderProcessor::class);
$mockUniqueTask = $this->createMock(UniqueTaskServiceInterface::class);
$service = new NotificationService(
    $mockRepository,
    $mockProcessor,
    $mockUniqueTask,
    $mockRecurringTask,
    $mockLogger,
    $mockHydration
);
```

### Exemple d'utilisation complète

```php
<?php

declare(strict_types=1);

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\Records\SendLaterRecord;
use AndyDefer\LaravelNotification\Records\SendRecurringRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;
use AndyDefer\LaravelNotification\ValueObjects\NotificationDateTimeVO;
use AndyDefer\LaravelNotification\Channels\MailChannel;
use AndyDefer\LaravelNotification\Channels\SmsChannel;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $service
    ) {}

    public function sendWelcome(User $user): JsonResponse
    {
        $message = new NotificationMessageVO(
            subject: 'Bienvenue !',
            content: '<h1>Bonjour !</h1><p>Bienvenue sur notre plateforme.</p>'
        );

        $record = SendNowRecord::from([
            'channels' => [MailChannel::class, SmsChannel::class],
            'limit_per_channel' => 1,
        ]);

        $results = $this->service->sendNow($user, $message, $record);

        return response()->json([
            'success' => $results->allSuccess(),
            'sent' => $results->getSuccessCount(),
            'failed' => $results->getFailureCount(),
        ]);
    }

    public function scheduleReminder(User $user): JsonResponse
    {
        $message = new NotificationMessageVO(
            subject: 'Rappel de rendez-vous',
            content: 'Votre rendez-vous est dans 30 minutes.'
        );

        $record = SendLaterRecord::from([
            'delay_seconds' => 1800, // 30 minutes
            'channels' => [MailChannel::class, SmsChannel::class],
            'limit_per_channel' => 1,
        ]);

        $alias = $this->service->sendLater($user, $message, $record);

        return response()->json([
            'alias' => $alias->getValue(),
            'scheduled_at' => now()->addSeconds(1800),
        ]);
    }

    public function scheduleNewsletter(User $user): JsonResponse
    {
        $message = new NotificationMessageVO(
            subject: 'Votre newsletter hebdomadaire',
            content: '<p>Voici les dernières actualités...</p>'
        );

        $record = SendRecurringRecord::from([
            'interval_seconds' => 604800, // 7 jours
            'start_at' => new NotificationDateTimeVO('2026-07-08 09:00:00'),
            'end_at' => new NotificationDateTimeVO('2026-12-31 09:00:00'),
            'channels' => [MailChannel::class],
            'limit_per_channel' => 1,
        ]);

        $alias = $this->service->sendRecurring($user, $message, $record);

        return response()->json([
            'alias' => $alias->getValue(),
            'next_run' => '2026-07-08 09:00:00',
        ]);
    }

    public function stats(User $user): JsonResponse
    {
        $stats = $this->service->getStats($user);

        return response()->json([
            'total' => $stats->total,
            'sent' => $stats->sent,
            'failed' => $stats->failed,
            'delivered' => $stats->delivered,
            'pending' => $stats->pending,
            'success_rate' => $stats->success_rate . '%',
        ]);
    }
}
```

---

## La valeur ajoutée de Laravel Notification

### 1. Une architecture en couches claire

Le système est divisé en couches qui communiquent entre elles :

```
┌─────────────────────────────────────────────────────────────────────────────┐
│                     NOTIFICATION SERVICE (API Publique)                    │
│                 Le point d'entrée unique pour l'utilisateur                │
│                                                                             │
│  Rôle : Exposer une API simple, gérer la planification, orchestrer l'envoi │
│                                                                             │
│  ┌───────────────────────────────────────────────────────────────────────┐ │
│  │                   NOTIFICATION SENDER PROCESSOR                       │ │
│  │                 L'orchestrateur d'envoi immédiat                     │ │
│  │                                                                       │ │
│  │  Rôle : Résoudre les routes, créer les notifications, gérer les logs │ │
│  │                                                                       │ │
│  │  ┌─────────────────────────────────────────────────────────────────┐ │ │
│  │  │                         CHANNELS                                │ │ │
│  │  │              La couche "QUOI" : le type de notification        │ │ │
│  │  │                                                                 │ │ │
│  │  │  Rôle : Définir le type (Email, SMS, WhatsApp) et la config    │ │ │
│  │  │                                                                 │ │ │
│  │  │  ┌───────────────────────────────────────────────────────────┐ │ │ │
│  │  │  │                         DRIVERS                           │ │ │
│  │  │  │              La couche "COMMENT" : le mode d'envoi       │ │ │
│  │  │  │                                                           │ │ │
│  │  │  │  Rôle : Exécuter l'envoi et retourner un résultat        │ │ │
│  │  │  │                                                           │ │ │
│  │  │  │  ┌─────────────────────────────────────────────────────┐ │ │ │
│  │  │  │  │                   EXECUTION                        │ │ │
│  │  │  │  │            L'envoi réel de la notification         │ │ │
│  │  │  │  │                                                     │ │ │
│  │  │  │  │  Rôle : Appeler l'API externe retourner true/false│ │ │
│  │  │  │  └─────────────────────────────────────────────────────┘ │ │ │
│  │  │  └───────────────────────────────────────────────────────────┘ │ │ │
│  │  └─────────────────────────────────────────────────────────────────┘ │ │
│  └───────────────────────────────────────────────────────────────────────┘ │
└─────────────────────────────────────────────────────────────────────────────┘
```

**Pourquoi cette séparation ?**

- **Le Service** : API publique. Ne sait pas comment envoyer. Gère la planification et délègue.
- **Le Processor** : Orchestrateur d'envoi. Ne sait pas comment envoyer. Résout les routes et crée les drivers.
- **Le Channel** : Définit le type. Valide la configuration et crée le driver.
- **Le Driver** : Exécute l'envoi. Appelle l'API externe.

Cette séparation permet de :

- **Tester chaque couche indépendamment** : On peut tester le Service sans appeler les vraies APIs.
- **Remplacer un Driver sans toucher au Channel** : Passer de SMTP à SendGrid ne change pas le Channel Email.
- **Ajouter un Channel sans toucher aux Drivers** : Ajouter Slack ne modifie pas les Drivers Email ou SMS.
- **Modifier la logique de planification** sans toucher à l'envoi.

### 2. Des routes multiples et flexibles

Un utilisateur peut avoir plusieurs destinations par canal, et chaque destination peut avoir des métadonnées via `NotificationRouteVO`.

```php
public function getNotificationChannels(): NotificationRouteCollection
{
    return NotificationRouteCollection::from([
        new NotificationRouteVO(
            channelClass: EmailChannel::class,
            destination: 'john.doe@hospital.com',
            metadata: new StrictDataObject(['priority' => 'high', 'type' => 'professional'])
        ),
        new NotificationRouteVO(
            channelClass: EmailChannel::class,
            destination: 'john.doe@gmail.com',
            metadata: new StrictDataObject(['priority' => 'low', 'type' => 'personal'])
        ),
        new NotificationRouteVO(
            channelClass: SmsChannel::class,
            destination: '+33612345678'
        ),
        new NotificationRouteVO(
            channelClass: WhatsAppChannel::class,
            destination: '+33612345678'
        ),
    ]);
}
```

### 3. Une persistance complète

Toutes les notifications sont stockées en base de données avec :

- **Le statut** : PENDING, SENT, FAILED
- **Le canal utilisé** : email, sms, whatsapp, database...
- **La destination** : john@example.com, +33612345678...
- **Le message** : Le contenu exact qui a été envoyé
- **L'horodatage** : Quand la notification a été créée et envoyée
- **L'erreur éventuelle** : Si l'envoi a échoué, le message d'erreur

```sql
-- Exemple d'enregistrement en base de données
SELECT * FROM notifications WHERE notifiable_id = 42;

-- Résultat :
-- id | session_id | channel  | destination        | status  | error
-- 1  | abc-123    | email    | john@hospital.com  | SENT    | NULL
-- 2  | abc-123    | email    | john@gmail.com     | FAILED  | "Connection timeout"
-- 3  | abc-123    | sms      | +33612345678       | SENT    | NULL
```

### 4. Un contrôle granulaire

Vous pouvez décider :

- **Quels canaux utiliser** : email uniquement, SMS uniquement, ou tous
- **Combien de notifications par canal** : 1, 2, 3, ou toutes
- **Quelles destinations prioriser** : les destinations avec métadonnées

```php
// Envoyer à tous les emails mais un seul SMS
$processRecord = ProcessNotificationRecord::from([
    'channels' => [], // Tous les canaux disponibles
    'limit_per_channel' => [
        EmailChannel::class => null, // Tous les emails
        SmsChannel::class => 1,      // Un seul SMS
    ],
]);
```

### 5. Un rapport détaillé

Chaque envoi retourne un résultat structuré via `SendResultRecord` :

```php
$results = $processor->send($user, $message, $processRecord);

foreach ($results as $result) {
    if ($result->success) {
        echo "✅ Notification envoyée\n";
        echo "   Canal : " . $result->channel->getValue() . "\n";
        echo "   Destinataire : " . $result->destination . "\n";
    } else {
        echo "❌ Échec de l'envoi\n";
        echo "   Canal : " . $result->channel->getValue() . "\n";
        echo "   Destinataire : " . $result->destination . "\n";
        echo "   Erreur : " . $result->error_message->getValue() . "\n";
    }
}
```

### 6. La planification avancée

Laravel Notification intègre un système de tâches qui permet :

- **L'envoi différé** : Planifier une notification dans X secondes
- **L'envoi planifié** : Planifier une notification à une date précise
- **L'envoi récurrent** : Planifier des notifications à intervalles réguliers (journalier, hebdomadaire, etc.)
- **La gestion des tâches** : Pause, reprise, modification de l'intervalle, annulation

```php
// Envoi différé de 30 minutes
$service->sendLater($user, $message, SendLaterRecord::from([
    'delay_seconds' => 1800
]));

// Envoi récurrent tous les jours à 8h
$service->sendRecurring($user, $message, SendRecurringRecord::from([
    'interval_seconds' => 86400,
    'start_at' => new NotificationDateTimeVO('2026-07-07 08:00:00')
]));
```

---

## En une phrase

> **Laravel Notifications envoie un message sur un canal défini. Laravel Notification orchestre l'envoi sur tous les canaux d'une entité, trace chaque tentative et permet la planification avancée.**

---

## Comparaison des approches

| Fonctionnalité | Laravel Notifications (natif) | Laravel Notification |
|----------------|-------------------------------|----------------------|
| **Configuration initiale** | Simple, prête à l'emploi | Nécessite une configuration des canaux |
| **API publique unifiée** | ✅ (`notify()`) | ✅ (`NotificationService`) |
| **Une seule destination par canal** | ✅ (par défaut) | ❌ (supporte plusieurs) |
| **Plusieurs destinations par canal** | ❌ | ✅ |
| **Persistance automatique** | ❌ (sauf database) | ✅ (tous les canaux) |
| **Statut de l'envoi (SENT/FAILED)** | ❌ | ✅ |
| **Rapport détaillé par envoi** | ❌ | ✅ |
| **Limitation par canal** | ❌ | ✅ |
| **Métadonnées par destination** | ❌ | ✅ |
| **ID de session pour regroupement** | ❌ | ✅ |
| **Architecture extensible** | ⚠️ (nécessite plusieurs classes) | ✅ (une classe par canal/driver) |
| **Testabilité** | ⚠️ (dépend des facades) | ✅ (injection de dépendances) |
| **Gestion d'erreur unifiée** | ❌ | ✅ |
| **Logging intégré** | ❌ | ✅ |
| **Envoi différé** | ⚠️ (via queues manuelles) | ✅ (intégré via `sendLater()`) |
| **Envoi planifié** | ⚠️ (via tâches planifiées) | ✅ (intégré via `sendAt()`) |
| **Envoi récurrent** | ⚠️ (via tâches planifiées) | ✅ (intégré via `sendRecurring()`) |
| **Gestion des tâches** | ❌ | ✅ (pause, reprise, annulation) |

---

## Cas d'usage concrets

### 1. Application médicale

```php
class Doctor extends Model implements NotifiableInterface
{
    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;
        
        // ✅ Email professionnel
        if ($this->email_professional) {
            $collection->add(new NotificationRouteVO(
                MailChannel::class,
                $this->email_professional,
                new StrictDataObject(['type' => 'professional'])
            ));
        }
        
        // ✅ Email personnel
        if ($this->email_personal) {
            $collection->add(new NotificationRouteVO(
                MailChannel::class,
                $this->email_personal,
                new StrictDataObject(['type' => 'personal'])
            ));
        }
        
        // ✅ SMS
        if ($this->phone) {
            $collection->add(new NotificationRouteVO(
                SmsChannel::class,
                $this->phone
            ));
        }
        
        return $collection;
    }
}

$doctor = Doctor::find(42);
$processor->send($doctor, $message, $processRecord);
// → 2 emails + 1 SMS → tracés en base de données
```

### 2. E-commerce - Confirmation de commande

```php
class Order extends Model implements NotifiableInterface
{
    public function getNotificationChannels(): NotificationRouteCollection
    {
        $collection = new NotificationRouteCollection;
        
        // ✅ Client
        $collection->add(new NotificationRouteVO(
            MailChannel::class,
            $this->customer_email
        ));
        $collection->add(new NotificationRouteVO(
            SmsChannel::class,
            $this->customer_phone
        ));
        
        // ✅ Admin
        $collection->add(new NotificationRouteVO(
            MailChannel::class,
            'admin@shop.com'
        ));
        
        // ✅ Base de données
        $collection->add(new NotificationRouteVO(
            DatabaseChannel::class,
            'database'
        ));
        
        return $collection;
    }
}
```

### 3. Système de notification d'urgence

```php
// ⚠️ URGENCE : on envoie sur TOUS les canaux sans limite
$processRecord = ProcessNotificationRecord::from([
    'channels' => [], // Tous disponibles
    'limit_per_channel' => null, // Pas de limite
]);

// 🔔 Notification normale : uniquement les emails
$processRecord = ProcessNotificationRecord::from([
    'channels' => [EmailChannel::class],
    'limit_per_channel' => 1,
]);
```

### 4. Newsletter hebdomadaire

```php
// 📰 Envoi récurrent tous les lundis à 9h
$service->sendRecurring($user, $message, SendRecurringRecord::from([
    'interval_seconds' => 604800, // 7 jours
    'start_at' => new NotificationDateTimeVO('2026-07-07 09:00:00'),
    'end_at' => new NotificationDateTimeVO('2026-12-31 09:00:00'),
]));

// La tâche peut être mise en pause, reprise ou annulée
$service->pause($alias);
$service->resume($alias);
$service->cancel($alias);
```

### 5. Relance après abandon de panier

```php
// 🛒 Envoi différé de 30 minutes après abandon de panier
$service->sendLater($user, $message, SendLaterRecord::from([
    'delay_seconds' => 1800,
    'channels' => [MailChannel::class, SmsChannel::class],
    'limit_per_channel' => 1,
]));

// Si le panier est validé, on annule la tâche
$service->cancel($alias);
```

---

## Ce que le développeur gagne en confort

### 1. Une API claire et intuitive

```php
// 1. Déclarer les canaux (dans le modèle)
public function getNotificationChannels(): NotificationRouteCollection
{
    return NotificationRouteCollection::from([
        new NotificationRouteVO(MailChannel::class, $this->email),
        new NotificationRouteVO(SmsChannel::class, $this->phone),
    ]);
}

// 2. Créer le message
$message = new NotificationMessageVO(
    subject: 'Bienvenue !',
    content: 'Contenu de la notification...'
);

// 3. Configurer l'envoi
$record = SendNowRecord::from([
    'channels' => [MailChannel::class],
    'limit_per_channel' => 1,
]);

// 4. Envoyer (via le service)
$results = $service->sendNow($user, $message, $record);
```

### 2. Une traçabilité complète

```bash
# Toutes les notifications d'un utilisateur
SELECT * FROM notifications WHERE notifiable_type = 'User' AND notifiable_id = 1;

# Détail par session
SELECT channel, destination, status, error, created_at
FROM notifications
WHERE session_id = 'abc-123'
ORDER BY created_at;

# Statistiques de succès
SELECT 
    status,
    COUNT(*) as count,
    ROUND(COUNT(*) * 100.0 / SUM(COUNT(*)) OVER(), 2) as percentage
FROM notifications
WHERE notifiable_id = 1
GROUP BY status;
```

### 3. Une gestion d'erreur transparente

```php
$results = $service->sendNow($user, $message, $record);

if (!$results->allSuccess()) {
    foreach ($results->getFailures() as $failure) {
        Log::error('Échec de notification', [
            'channel' => $failure->channel->getValue(),
            'destination' => $failure->destination,
            'error' => $failure->error_message->getValue(),
        ]);
    }
}
```

### 4. Une extensibilité sans limite

```php
// 1. Créer un nouveau Driver (une seule classe)
class TelegramDriver extends AbstractDriver { ... }

// 2. Créer un nouveau Channel (une seule classe)
class TelegramChannel extends AbstractChannel { ... }

// 3. Utiliser immédiatement
public function getNotificationChannels(): NotificationRouteCollection
{
    return NotificationRouteCollection::from([
        new NotificationRouteVO(TelegramChannel::class, '@john_doe'),
    ]);
}
```

---

## Architecture technique

### Les composants clés

| Composant | Fichier | Rôle |
|-----------|---------|------|
| **NotificationService** | `Services/NotificationService.php` | **API publique** - Point d'entrée unique |
| **NotifiableInterface** | `Contracts/NotifiableInterface.php` | Interface pour les entités notifiables |
| **NotificationRouteVO** | `ValueObjects/NotificationRouteVO.php` | Route de notification (canal + destination) |
| **AbstractChannel** | `Abstracts/AbstractChannel.php` | Base pour tous les canaux |
| **AbstractDriver** | `Abstracts/AbstractDriver.php` | Base pour tous les drivers |
| **NotificationSenderProcessor** | `Processors/NotificationSenderProcessor.php` | Orchestrateur d'envoi immédiat |
| **SendResultRecord** | `Records/SendResultRecord.php` | Résultat structuré |
| **SendDelayedNotificationTask** | `Tasks/SendDelayedNotificationTask.php` | Tâche pour les envois différés |
| **SendRecurringNotificationTask** | `Tasks/SendRecurringNotificationTask.php` | Tâche pour les envois récurrents |
| **NotificationRepository** | `Repositories/NotificationRepository.php` | Persistance des notifications |

### Flux d'exécution complet

```
1. Appel de $service->sendNow($user, $message, $record)
   ↓
2. NotificationService crée un ProcessNotificationRecord
   ↓
3. NotificationService appelle $senderProcessor->send()
   ↓
4. NotificationSenderProcessor récupère les routes via $user->getNotificationChannels()
   ↓
5. Filtrage des routes selon les canaux demandés
   ↓
6. Application de limit_per_channel
   ↓
7. Pour chaque route :
   a. Création d'un enregistrement Notification (status: PENDING)
   b. $channel->createDriver() → récupération du driver
   c. $driver->send($message, $route)
   d. Mise à jour du statut (SENT ou FAILED)
   ↓
8. Retour de SendResultCollection

--- Pour les envois différés/planifiés ---

1. Appel de $service->sendLater($user, $message, $record)
   ↓
2. NotificationService valide le délai
   ↓
3. NotificationService crée un NotificationTaskPayloadRecord
   ↓
4. NotificationService appelle $uniqueTaskService->register()
   ↓
5. UniqueTaskService enregistre la tâche SendDelayedNotificationTask
   ↓
6. La tâche s'exécute à la date prévue
   ↓
7. SendDelayedNotificationTask exécute la logique d'envoi via le Processor
```

---

## Installation et mise en route

```bash
# 1. Installation
composer require andydefer/laravel-notification

# 2. Publier et exécuter les migrations
php artisan vendor:publish --tag=notification-migrations
php artisan migrate

# 3. Publier la configuration
php artisan vendor:publish --tag=notification-config

# 4. Configurer les canaux dans config/notification.php
# 5. Implémenter NotifiableInterface sur vos modèles
# 6. Utiliser le service dans votre code
```

**Configuration minimale :**
```php
// config/notification.php
return [
    'channels' => [
        'mail' => [
            'enabled' => true,
            'default_from' => env('MAIL_FROM_ADDRESS'),
        ],
        'database' => [
            'driver' => 'database',
            'table' => 'notifications',
        ],
    ],
];
```

**Utilisation basique :**
```php
<?php

use AndyDefer\LaravelNotification\Services\NotificationService;
use AndyDefer\LaravelNotification\Records\SendNowRecord;
use AndyDefer\LaravelNotification\ValueObjects\NotificationMessageVO;

class UserController extends Controller
{
    public function __construct(
        private readonly NotificationService $notificationService
    ) {}

    public function welcome(User $user)
    {
        $message = new NotificationMessageVO(
            subject: 'Bienvenue !',
            content: '<h1>Bienvenue sur notre plateforme !</h1>'
        );

        $record = SendNowRecord::from([
            'channels' => [MailChannel::class, SmsChannel::class],
            'limit_per_channel' => 1,
        ]);

        $results = $this->notificationService->sendNow($user, $message, $record);

        return response()->json([
            'success' => $results->allSuccess(),
        ]);
    }
}
```

---

## Conclusion

**Laravel Notification** n'est pas un remplacement du système de notification de Laravel, mais un complément puissant pour les applications qui ont des besoins avancés en matière de notifications.

**Le système natif reste la solution idéale pour :**
- Les applications simples avec une seule destination par canal
- Les projets où la traçabilité n'est pas une exigence critique
- Les cas d'usage standards (un email, un SMS, une notification en base de données)

**Laravel Notification apporte une valeur ajoutée pour les applications qui ont besoin de :**

- ✅ Plusieurs destinations par canal (un utilisateur = plusieurs emails)
- ✅ Persistance et traçabilité complètes de toutes les notifications
- ✅ Contrôle fin sur l'envoi (limites par canal, filtrage)
- ✅ Architecture extensible (ajout facile de nouveaux canaux)
- ✅ Gestion d'erreur unifiée (même format pour tous les canaux)
- ✅ Rapports détaillés (succès/échec par canal et par destination)
- ✅ Planification avancée (différé, planifié, récurrent)
- ✅ Testabilité accrue grâce à l'injection de dépendances
- ✅ API publique unifiée via `NotificationService`

---

**Le système de notifications multi-canaux pour Laravel.** 🚀