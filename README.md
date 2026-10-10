# Site de Vente

## Description

Application e-commerce en PHP 8.3 avec architecture DDD (Domain-Driven Design).
Le projet utilise MySQL comme base de données et Docker pour l'environnement de développement.

## État actuel du projet

### Initialisation du projet

Le projet a été initialisé avec Composer pour gérer les dépendances PHP.

**Dépendances installées :**

- `vlucas/phpdotenv` ^5.7 — Gestion des variables d'environnement via fichier `.env`
- `phpunit/phpunit` ^11.5 — Framework de tests (unitaires et intégration)

**Fichiers de configuration :**

- `composer.json` — Définition des dépendances et autoloading PSR-4
- `composer.lock` — Verrouillage des versions des dépendances
- `.gitignore` — Exclusion des secrets, dépendances et fichiers d’éditeur (`.env`, `vendor/`, `node_modules/`, `.cursor/`, `.vscode/`, caches)
- `.env.example` — Template des variables d'environnement requises

**Structure de l'autoloading :**

- `App\` → `src/`
- `Db_config\` → `config/`

**Variables d'environnement requises :**

- `DB_HOST` — Hôte de la base de données
- `DB_PORT` — Port de la base de données (défaut: 3306)
- `DB_NAME` — Nom de la base de données
- `DB_USER` — Utilisateur de la base de données
- `DB_PASSWORD` — Mot de passe de la base de données

---

### Connexion à la base de données

La connexion à la base de données est gérée par la classe `Database` dans `config/database.php`.

**Fonctionnement :**

- Espace de noms `Db_config`
- Connexion via `mysqli` avec gestion d'erreurs
- Configuration chargée depuis le fichier `.env` via `vlucas/phpdotenv`
- Valeurs par défaut appliquées si les variables sont absentes

**Variables utilisées :**

- `DB_HOST` — Hôte de la base de données (défaut: `localhost`)
- `DB_PORT` — Port de la base de données (défaut: `3306`)
- `DB_NAME` — Nom de la base de données (défaut: `my_database`)
- `DB_USER` — Utilisateur de la base de données (défaut: `root`)
- `DB_PASSWORD` — Mot de passe de la base de données (défaut: ``)

**Utilisation :**

```php
use Db_config\Database;

$connection = Database::connect();
// $connection est une instance de \mysqli
```

---

### Test de connexion à la base de données

Le projet utilise PHPUnit pour tester la connexion à la base de données.

**Configuration (`phpunit.xml`) :**

- Suite "Integration tests" → `tests/integration`

**Test d'intégration :**

- `tests/integration/db/DatabaseConnectionTest.php` — Test de connexion DB
  - Vérifie que la connexion MySQL fonctionne en exécutant `SELECT 1`

**Exécution :**

```bash
# Test de connexion DB
./vendor/bin/phpunit tests/integration/db/DatabaseConnectionTest.php
```

---

### Environnement Docker

Deux services conteneurisés ont été initialisés pour créer un environnement de test et de déploiement de l'application PHP et de la base de données MySQL.

**Service PHP (`php`) :**

- Image basée sur `php:8.3-apache`
- Extensions installées : `mysqli`, `pdo_mysql`, `zip`, `mbstring`
- Composer installé globalement
- Apache configuré pour servir le dossier `/public`
- Port exposé : `8080:80`
- Volume monté : `./` → `/var/www/html` (code source en temps réel)

**Service MySQL (`mysql`) :**

- Image officielle `mysql:8.0`
- Port exposé : `3306:3306`
- Volume persistant : `mysql_data` → `/var/lib/mysql`
- Healthcheck : `mysqladmin ping` (intervalle 5s, timeout 5s, 20 retries)
- Démarrage conditionnel : PHP attend que MySQL soit healthy

**Réseau interne :**

- Réseau bridge `site_de_vente` permettant la communication PHP ↔ MySQL via le nom de service `mysql`

**Fichiers de configuration :**

- `php/Dockerfile` — Définition de l'image PHP personnalisée
- `docker-compose.yml` — Orchestration des services PHP et MySQL

---

### Accès aux données (DAO)

Le projet utilise le pattern Repository pour accéder aux données.

**Fichier :** `src/Infrastructure/Persistence/DAO/UserDAO.php`

**Interface :** `UserRepositoryInterface`

- `findByUsername(string $username): ?User` — Recherche un utilisateur par son nom d'utilisateur

**Implémentation :** `UserRepository`

- Connexion via `mysqli`
- Requêtes préparées pour éviter les injections SQL
- Retourne `null` si l'utilisateur n'est pas trouvé

**Test d'intégration :** `tests/integration/dao/UserDaoTest.php`

- `testFindByUsernameReturnsSeededUser` — Vérifie le retour d'un utilisateur existant
- `testFindByUsernameReturnsNullForUnknownUser` — Vérifie le retour null pour un utilisateur inconnu
- Utilise une table temporaire MySQL pour isoler les tests

**Exécution :**

```bash
./vendor/bin/phpunit tests/integration/dao/UserDaoTest.php
```

---

### Gestion des sessions

L'état d'authentification est stocké côté serveur via les sessions PHP (`session_start()` / `$_SESSION`).
Le navigateur ne conserve que le cookie de session `PHPSESSID` géré par PHP.

**Fichiers :**

- `src/Infrastructure/Http/SessionStoreInterface.php` — Contrat d'accès à la session
- `src/Infrastructure/Http/NativeSessionStore.php` — Implémentation native PHP

**Interface :** `SessionStoreInterface`

- `set(string $key, mixed $value): void` — Écrit une valeur en session
- `get(string $key): mixed` — Lit une valeur (ou `null`)
- `remove(string $key): void` — Supprime une clé
- `regenerate(): void` — Régénère l'id de session (`session_regenerate_id(true)`)
- `destroy(): void` — Vide et détruit la session

**Sécurité appliquée avant `session_start()` :**

| Paramètre | Valeur | Rôle |
|-----------|--------|------|
| `use_only_cookies` | `1` | Pas d'id de session dans l'URL |
| `use_strict_mode` | `1` | Rejette les ids de session inconnus |
| `use_trans_sid` | `0` | Pas de réécriture d'URL avec SID |
| `cookie_httponly` | `true` | Cookie inaccessible en JavaScript |
| `cookie_samesite` | `Lax` | Limite les envois cross-site (CSRF) |
| `cookie_secure` | `true` si HTTPS | Cookie uniquement en HTTPS |
| `cookie_path` | `/` | Valable pour toute l'application |
| `cookie_lifetime` | `0` | Expire à la fermeture du navigateur |

**Utilisation :**

```php
use App\Infrastructure\Http\NativeSessionStore;

$session = new NativeSessionStore(); // démarre la session de façon sécurisée
$session->set('user_id', 1);
$userId = $session->get('user_id');
```

**Redirection vers une autre page :**

1. Au login : écrire `user_id` en session, puis `header('Location: /page.php'); exit;`
2. Sur la page cible : instancier `NativeSessionStore` (→ `session_start()`), puis vérifier `Auth::isAuthenticated()`
3. Si non authentifié : rediriger vers `index.php`

Aucun paramètre d'auth n'est passé dans l'URL : le cookie `PHPSESSID` suffit pour retrouver la session serveur.

---

### Service d'authentification

Le service `Auth` gère l'authentification des utilisateurs et persiste l'état en session.

**Fichier :** `src/Application/Services/Auth.php`

**Interface :** `AuthInterface`

- `login(string $username, string $password): bool` — Authentifie et enregistre `user_id` en session
- `logout(): void` — Supprime `user_id` et détruit la session
- `isAuthenticated(): bool` — Indique si un `user_id` valide est présent en session
- `getAuthenticatedUserId(): ?int` — Retourne l'id utilisateur en session, ou `null`

**Implémentation :** `Auth`

- Constructeur injecte `UserRepositoryInterface` et `SessionStoreInterface`
- Vérifie l'utilisateur via `findByUsername()`
- Vérifie le mot de passe avec `password_verify()`
- En cas de succès : `session_regenerate_id(true)` puis `$_SESSION['user_id'] = …`
- En cas d'échec : ne touche pas à la session

**DTO :** `src/Application/DTO/LoginRequestDTO.php`

- Encapsule les données de login (username, password)

**Utilisation :**

```php
use App\Application\Services\Auth;
use App\Infrastructure\Http\NativeSessionStore;
use App\Infrastructure\Persistence\DAO\UserRepository;

$session = new NativeSessionStore();
$auth = new Auth(new UserRepository($connection), $session);

if ($auth->login('username', 'password')) {
    header('Location: /products.php');
    exit;
}

if ($auth->isAuthenticated()) {
    $userId = $auth->getAuthenticatedUserId();
}
```

**Test unitaire :** `tests/unit/service/AuthServiceTest.php`

- `testLoginSucceedsWithValidCredentialsAndStoresSession` — Login OK + écriture session + regenerate
- `testLoginRejectsInvalidPasswordAndDoesNotStoreSession` — Rejet mauvais mot de passe
- `testLoginRejectsUnknownUser` — Rejet utilisateur inconnu
- `testLogoutClearsSession` — Logout vide et détruit la session
- Utilise un store de session en mémoire (mock) pour isoler le service

**Exécution :**

```bash
./vendor/bin/phpunit tests/unit/service/AuthServiceTest.php
```

---

### Interface utilisateur

La page de login permet aux utilisateurs de s'authentifier (maquette Figma).

**Maquette Figma (lecture seule) :**

- [Login — Figma](https://www.figma.com/design/IUNoBVcRSL0D5sNNFnaWtr/Login?node-id=0-1&m=dev)

**Fichiers (état `develop` / session) :**

- `public/index.php` — Page de login + démarrage de session
- `public/styles.css` — Styles de la page de login

Les styles modulaires (`public/styles/*`), tooltip, feedback et toggle mot de passe sont livrés sur la branche `feature/ui`.

**Fonctionnement :**

- Formulaire HTML avec champs username et password
- Démarrage de session via `NativeSessionStore`
- Connexion à la base de données via `Database::connect()`
- Authentification via `Auth::login()` (persistance en session)
- Message « already signed in » si `user_id` est déjà en session (GET)
- Affichage des messages de feedback (succès/erreur)
- Gestion des exceptions avec try/catch

**Accès :**

```
http://localhost:8080/index.php
```

---

### Validation des entrées

La validation du formulaire de login est livrée sur la branche `feature/validator`.

**Principe :**

- Une règle atomique = une classe (`RequiredValidator`, `MinLengthValidator`, `MaxLengthValidator`, `RegexValidator`)
- Un champ = un validateur composé (`UsernameValidator`, `PasswordValidator`)
- `Validation` orchestre les validateurs et renvoie un `ValidationResult` (+ `LoginRequestDTO` normalisé)

**Règles password :** 8–72 caractères, majuscule, minuscule, chiffre, caractère spécial.

**Tests :** `tests/unit/service/ValidationServiceTest.php`

```bash
./vendor/bin/phpunit tests/unit/service/ValidationServiceTest.php
```

---

### Modèle de données

L'entité `User` représente un utilisateur dans le système.

**Fichier :** `src/Domain/Model/User.php`

**Propriétés :**

- `id` (int) — Identifiant unique de l'utilisateur
- `username` (string) — Nom d'utilisateur
- `email` (string) — Adresse email
- `password` (string) — Mot de passe (hashé)

**Utilisation :**

```php
use App\Domain\Model\User;

$user = new User(1, 'john_doe', 'john@example.com', 'hashed_password');
echo $user->getUsername(); // john_doe
```

---

## Environnement

### Prérequis

- Docker
- Docker Compose
- PHP 8.3+
- MySQL 8.0+

### Démarrage

```bash
docker-compose up -d
```

### Accès

- Application : http://localhost:8080
- MySQL : localhost:3306

### Tests

```bash
# Tous les tests
./vendor/bin/phpunit

# Auth (sessions)
./vendor/bin/phpunit tests/unit/service/AuthServiceTest.php

# DAO utilisateurs
./vendor/bin/phpunit tests/integration/dao/UserDaoTest.php

# Connexion DB
./vendor/bin/phpunit tests/integration/db/DatabaseConnectionTest.php
```

---

## Architecture

```
src/
├── Domain/
│   └── Model/
│       └── User.php                         → Entité User
├── Application/
│   ├── DTO/
│   │   └── LoginRequestDTO.php              → DTO de login
│   └── Services/
│       └── Auth.php                         → Auth + AuthInterface (session)
└── Infrastructure/
    ├── Http/
    │   ├── SessionStoreInterface.php        → Contrat session
    │   └── NativeSessionStore.php           → session_start() sécurisé
    └── Persistence/
        └── DAO/
            └── UserDAO.php                  → UserRepository + Interface

config/
└── database.php                             → Connexion à la base de données

database/
└── migrations/
    └── 001_create_users_table.sql           → Tables compte + user

public/
├── index.php                                → Page de login (+ session)
└── styles.css                               → Styles UI

php/
└── Dockerfile                               → Image PHP 8.3 + Apache

tests/
├── integration/
│   ├── dao/
│   │   └── UserDaoTest.php                  → Test intégration DAO
│   └── db/
│       └── DatabaseConnectionTest.php       → Test connexion DB
└── unit/
    └── service/
        └── AuthServiceTest.php              → Test unitaire Auth / session

docs/
└── bugs.md                                  → Journal des bugs connus / corrigés
```

---

## Branches

- `develop` — Intégration (sessions + auth)
- `feature/session` — Sessions PHP sécurisées + auth persistée en session (fusionnée dans `develop`)
- `feature/ui` — UI login Figma (styles modulaires, tooltip, feedback, toggle password)
- `feature/validator` — Validateurs par règle / par champ + tests

---

## Auteur

- **Nom** : reneokecodjo
- **Email** : hokecodjo@icloud.com
