# GenerateVapidKeysDirective - Référence Technique

## Description

Directive CLI générant une paire de clés VAPID (publique + privée) pour Web Push et l'écrivant dans un fichier `.env`.

## Hiérarchie

```
AbstractDirective
    └── GenerateVapidKeysDirective
```

## Rôle principal

Fournir une commande d'installation pour le canal Web Push. La directive génère les clés cryptographiques nécessaires au protocole VAPID, les persiste dans un fichier d'environnement, et ajoute les variables complémentaires (`WEBPUSH_NOTIFICATION_ENABLED`, `WEBPUSH_SUBJECT`) si elles sont absentes.

## Prérequis

- Package `minishlink/web-push` installé (fournit la classe `VAPID`).
- Accès en écriture au fichier `.env` ciblé.
- `andydefer/laravel-directive` installé et le kernel configuré.

## Signature

```
notification:generate-vapid {env=.env}#"Path to the env file" {--force}#"Overwrite existing VAPID keys"
```

| Argument / Option | Type | Défaut | Description |
|-------------------|------|--------|-------------|
| `env` | `string` | `.env` | Chemin du fichier env (relatif ou absolu) |
| `--force` | `bool` | `false` | Écrase les clés existantes |

## Alias

- `notification:gvk`
- `n:gvk`

## API / Méthodes publiques

### `getSignature(): string`

Retourne la signature CLI complète de la directive.

**Retourne :** `string` - La signature au format Laravel Directive.

### `getDescription(): string`

Retourne la description affichée dans l'aide CLI.

**Retourne :** `string` - `"Generate a VAPID key pair for Web Push"`

### `getAliases(): StringTypedCollection`

Retourne les alias acceptés par la directive.

**Retourne :** `StringTypedCollection` - Collection contenant `notification:gvk` et `n:gvk`.

### `execute(): ExitCode` (protégée)

Génère et persiste la paire de clés VAPID.

**Retourne :**
- `ExitCode::SUCCESS` — clés générées, ou clés existantes sans `--force`.
- `ExitCode::FAILURE` — fichier introuvable, illisible, ou erreur de génération.

**Exceptions :** Aucune n'est propagée — toutes les erreurs sont converties en `ExitCode::FAILURE` avec affichage du message via `$this->error()`.

### `upsertEnv(string $env, string $key, string $value): string` (privée)

Met à jour ou ajoute une variable dans le contenu du fichier env.

| Paramètre | Type | Description |
|-----------|------|-------------|
| `$env` | `string` | Contenu actuel du fichier env |
| `$key` | `string` | Nom de la variable |
| `$value` | `string` | Nouvelle valeur |

**Retourne :** `string` - Le contenu mis à jour.

## Comportement

### Ordre d'exécution

```
1. Résolution du chemin env
   ├── Argument vide → ".env"
   ├── Chemin absolu → tel quel
   └── Chemin relatif → base_path()

2. Lecture du fichier
   ├── Fichier introuvable → ExitCode::FAILURE
   └── Lecture échouée → ExitCode::FAILURE

3. Détection des clés existantes
   ├── WEBPUSH_PUBLIC_KEY= présent
   └── WEBPUSH_PRIVATE_KEY= présent
       ├── --force absent → avertissement + ExitCode::SUCCESS
       └── --force présent → continue

4. Génération via VAPID::createVapidKeys()
   └── Échec → ExitCode::FAILURE

5. Écriture des variables
   ├── WEBPUSH_PUBLIC_KEY (upsert)
   ├── WEBPUSH_PRIVATE_KEY (upsert)
   ├── WEBPUSH_NOTIFICATION_ENABLED=true (si absent)
   └── WEBPUSH_SUBJECT="mailto:contact@afya-medical.com" (si absent)

6. Sortie console + avertissement de sécurité
```

## Cas d'utilisation

### Cas 1 : Installation initiale

```bash
./vendor/bin/directive notification:generate-vapid
```

Génère une nouvelle paire et l'écrit dans `.env`.

Sortie :
```
VAPID keys generated and written to /var/www/app/.env
WEBPUSH_PUBLIC_KEY  = BDw92Y6vnPXYNN90QwiqmLAnnEX9...
WEBPUSH_PRIVATE_KEY = m7lTHzndL5P7QL0be8a0l1whYsy8...

Do not commit the private key. Keep it secret.
```

### Cas 2 : Régénération forcée

```bash
./vendor/bin/directive notification:generate-vapid --force
```

Écrase les clés existantes.

### Cas 3 : Cible alternative

```bash
./vendor/bin/directive notification:generate-vapid .env.production
```

Écrit les clés dans `.env.production` à la racine du projet.

### Cas 4 : Chemin absolu

```bash
./vendor/bin/directive n:gvk /etc/my-app/.env
```

Écrit directement dans le chemin fourni.

## Gestion des erreurs

| Situation | ExitCode | Message |
|-----------|----------|---------|
| Fichier env introuvable | `FAILURE` | `Env file not found at "..."` |
| Fichier env illisible | `FAILURE` | `Unable to read env file at "..."` |
| Clés existantes sans `--force` | `SUCCESS` | `VAPID keys already exist in env file. Use --force to overwrite.` |
| Échec de génération VAPID | `FAILURE` | `VAPID generation failed: <reason>` |

## Variables d'environnement gérées

| Variable | Toujours écrite | Valeur par défaut |
|----------|-----------------|-------------------|
| `WEBPUSH_PUBLIC_KEY` | ✅ | Généré aléatoirement |
| `WEBPUSH_PRIVATE_KEY` | ✅ | Généré aléatoirement |
| `WEBPUSH_NOTIFICATION_ENABLED` | Si absent | `true` |
| `WEBPUSH_SUBJECT` | Si absent | `"mailto:contact@afya-medical.com"` |

## Intégration

| Composant | Rôle |
|-----------|------|
| `Minishlink\WebPush\VAPID` | Génération cryptographique |
| `NotificationConfig` | Consomme les variables générées |
| `WebPushDriver` | Utilise les clés à l'envoi |
| `AbstractDirective` | Contrat parent de la CLI |

## Performance

- Coût dominé par la génération cryptographique (`VAPID::createVapidKeys()`), quelques millisecondes.
- Lecture/écriture du fichier env : O(taille du fichier).
- Aucun appel réseau.

## Compatibilité

| Version | Support |
|---------|---------|
| PHP 8.2+ | ✅ |
| Laravel 12+ | ✅ |
| `minishlink/web-push` | ✅ Requis |
| `andydefer/laravel-directive` | ✅ Requis |

## Sécurité

⚠️ La clé privée ne doit **jamais** être commitée dans un dépôt Git. La directive affiche un avertissement explicite en fin d'exécution. Ajouter `.env` au `.gitignore` est obligatoire.

## Exemple complet

```bash
# 1. Générer les clés
./vendor/bin/directive notification:generate-vapid

# 2. Vérifier le résultat
grep WEBPUSH .env
# WEBPUSH_PUBLIC_KEY=BDw92Y6vnPXYNN90QwiqmLAnnEX9...
# WEBPUSH_PRIVATE_KEY=m7lTHzndL5P7QL0be8a0l1whYsy8...
# WEBPUSH_NOTIFICATION_ENABLED=true
# WEBPUSH_SUBJECT="mailto:contact@afya-medical.com"

# 3. Régénérer pour un autre environnement
./vendor/bin/directive n:gvk .env.staging --force
```