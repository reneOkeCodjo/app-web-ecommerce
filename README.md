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
- `browser-sync` ^3.0 — Live reload local (proxifie Apache sur le port 3000)

**Fichiers de configuration :**

- `composer.json` — Définition des dépendances et autoloading PSR-4
- `composer.lock` — Verrouillage des versions des dépendances
- `package.json` / `package-lock.json` — Outils front locaux (BrowserSync)
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

- `findByUsername(string $username): ?User` — Recherche un utilisateur par login
- `findByEmail(string $email): ?User` — Recherche un utilisateur par email
- `create(string $username, string $email, string $passwordHash, int $compteId): User` — Crée un utilisateur
- `findCompteIdByType(string $type): ?int` — Résout l’id de compte (ex. `client`)

**Implémentation :** `UserRepository`

- Connexion via `mysqli`
- Colonnes SQL réelles (`user_id`, `user_login`, `user_mail`, `user_password`, `user_compte_id`)
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

**Fichier :** `src/Application/Services/Auth/Auth.php`

**Interface :** `AuthInterface`

- `login(string $username, string $password): bool` — Authentifie et enregistre `user_id` + `compte_type` en session
- `register(string $username, string $email, string $password): bool` — Crée un user `client` et ouvre la session
- `logout(): void` — Supprime l’auth et détruit la session
- `isAuthenticated(): bool` — Indique si un `user_id` valide est présent en session
- `isAdmin(): bool` — `true` si authentifié avec `compte_type === 'admin'`
- `getAuthenticatedUserId(): ?int` — Retourne l'id utilisateur en session, ou `null`
- `getCompteType(): ?string` — Type de compte en session (`client`, `admin`, …)

**Implémentation :** `Auth`

- Constructeur injecte `UserRepositoryInterface` et `SessionStoreInterface`
- Méthodes d’instance (plus de `static`)
- Login : `findByUsername()` + `password_verify()`
- Register : unicité login/email, `findCompteIdByType('client')`, `password_hash()`, `create()`
- En cas de succès : `session_regenerate_id(true)` puis `user_id` + `compte_type` en session
- En cas d'échec : ne touche pas à la session

**DTO :**

- `src/Application/DTO/LoginRequestDTO.php` — username, password
- `src/Application/DTO/RegisterRequestDTO.php` — username, email, password

**Utilisation :**

```php
use App\Application\Services\Auth\Auth;
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
- `testRegisterCreatesUserAndStoresSession` — Inscription OK + session
- `testRegisterRejectsExistingUsername` — Rejet login déjà pris
- `testLogoutClearsSession` — Logout vide et détruit la session
- Utilise un store de session en mémoire (mock) pour isoler le service

**Exécution :**

```bash
./vendor/bin/phpunit tests/unit/service/AuthServiceTest.php
```

---

### Handlers login / register

La validation se fait dans les pages (`index.php`, `register.php`). Les handlers ne reçoivent que des DTO déjà validés.

**Fichiers :**

- `src/Application/Services/Auth/LoginHandler.php`
- `src/Application/Services/Auth/RegisterHandler.php`

**Contrat :**

- `handle('GET')` → `''` (anonyme) ou `null` (déjà connecté → redirection produits)
- `handle('POST', $dto)` → message d’erreur HTML, ou `null` (succès → redirection produits)

**Flux page :**

1. `Validation::validateLogin()` / `validateRegister()`
2. Si erreurs de champs → affichage inline sous chaque input
3. Sinon → handler + DB ; succès → `header('Location: products.php')`

**Tests :** `LoginHandlerTest.php`, `RegisterHandlerTest.php`

---

### Interface utilisateur

Pages d’authentification alignées sur la maquette Figma, plus une landing produits après succès.

**Maquette Figma (lecture seule) :**

- [Login — Figma](https://www.figma.com/design/IUNoBVcRSL0D5sNNFnaWtr/Login?node-id=0-1&m=dev)

**Fichiers :**

- `public/index.php` — Login (validation champs + feedback serveur)
- `public/register.php` — Inscription (username, email, password, confirmation)
- `public/products.php` — Landing post-auth (placeholder catalogue + logout)
- `public/styles.css` — Point d’entrée CSS (`@import`)
- `public/styles/base.css` — Tokens + layout
- `public/styles/header.css` — Titre auth
- `public/styles/form.css` — Champs / labels flottants
- `public/styles/button.css` — Bouton submit + toggle œil
- `public/styles/tooltip.css` — Tooltip règles password (register)
- `public/styles/feedback.css` — Alertes succès / erreur globales
- `public/styles/validation.css` — Erreurs par champ (`field--error`, `field__error`)
- `public/scripts/password-toggle.js` — Afficher / masquer le mot de passe

**Fonctionnement :**

- Formulaires HTML avec `novalidate` (validation serveur prioritaire)
- Erreurs de champ sous chaque input (ARIA `aria-invalid` / `aria-describedby`)
- Session via `NativeSessionStore`
- Succès login/register → redirection `products.php`
- Utilisateur déjà connecté sur login/register → redirection `products.php`
- Logout depuis `products.php` → `index.php`

**Accès :**
```
http://localhost:8080/index.php
http://localhost:8080/register.php
http://localhost:8080/products.php
```

**Live reload (optionnel) :**

```bash
npx browser-sync start --proxy "localhost:8080" --files "**/*.php, **/*.css"
# → http://localhost:3000
```

---

### Validation des entrées

Validation serveur dans les pages, avant l’appel aux handlers / `Auth`.

**Principe (SRP) :**

- Une règle atomique = une classe : `RequiredValidator`, `MinLengthValidator`, `MaxLengthValidator`, `RegexValidator`
- Un champ = un validateur composé : `UsernameValidator`, `PasswordValidator`, `LoginPasswordValidator`, `EmailValidator`, `ConfirmPasswordValidator`
- `Validation` orchestre et renvoie un `ValidationResult` (+ DTO normalisé)

**Fichiers :**

- `src/Application/Validation/{Shared,Auth,Product}/`
- `src/Application/Services/Shared/Validation.php`
- `src/Application/Services/Shared/ValidationResult.php`

**Login (`validateLogin`) :** username requis + password requis / max 72.  
Pas de règles de complexité au login (un mot de passe faible doit plutôt produire « Invalid username or password » via `Auth`).

**Register (`validateRegister`) :** email + confirmation ; password 8–72 caractères avec majuscule, minuscule, chiffre et caractère spécial.

**Produit (`validateProduct`) :** catégorie (catalogue JSON), désignation, prix HT, stock, date d’entrée ; promo et image optionnels.

**Migration requise :** `database/migrations/002_fix_users_unique_constraints.sql` (login/email uniques, plusieurs users par type de compte).

**Test unitaire :** `ValidationServiceTest.php`, `ProductValidationTest.php`, `RegisterHandlerTest.php`, `AuthServiceTest` (`register`)

```bash
./vendor/bin/phpunit tests/unit/service/ValidationServiceTest.php
./vendor/bin/phpunit tests/unit/service/ProductValidationTest.php
./vendor/bin/phpunit tests/unit/service/LoginHandlerTest.php
./vendor/bin/phpunit tests/unit/service/RegisterHandlerTest.php
```

---

### Back-office admin

Zone d’administration **séparée** du front client (pas le même login que `index.php`).

**Décisions :**

| Sujet | Choix |
|-------|--------|
| Accès | Compte `admin` uniquement |
| Périmètre métier | Gestion des **produits** (CRUD MySQL + promo / prix TTC) |
| Entrée | `public/admin/login.php` → `public/admin/index.php` |

**Types de compte déjà en base (`compte`) :** `client`, `vendeur`, `admin`.

**Fait (étape auth admin) :**

- `User` expose `compte_type` ; le DAO joint `compte` au login
- Session : `user_id` + `compte_type`
- `Auth::isAdmin()` / `getCompteType()`
- `AdminLoginHandler` : refuse les non-admin (logout + message)
- Pages : `public/admin/login.php`, `public/admin/index.php` (garde admin + logout)

**Admin seed (migration MySQL) :**

- Fichier : `database/migrations/003_seed_admin_user.sql`
- Exécuté au premier init Docker (`./database/migrations` → `/docker-entrypoint-initdb.d`)
- Identifiants de dev : login `admin` / mot de passe `Admin123!`
- Ne recrée pas l’utilisateur s’il existe déjà (`NOT EXISTS`)

> Si le volume MySQL existait déjà, recréer la base ou appliquer la migration à la main.

**Accès :**

```
http://localhost:8080/admin/login.php
http://localhost:8080/admin/index.php
http://localhost:8080/admin/products.php
```

**Produits — état actuel :**

| Couche | Statut |
|--------|--------|
| Entité `Product` | `getPriceHt()`, `getDateNew()`, `getPromotion()`, `getImage()` |
| DAO SQL | `ProductRepository` (+ interface) dans `ProductDAO.php` — CRUD + promo/image |
| Service métier | `src/Application/Services/Product/ProductService.php` (+ validation) |
| Validation | `Validation/{Shared,Auth,Product}/` + `Services/Shared/Validation` |
| UI admin | CRUD MySQL via `ProductService` (+ promo % et aperçu prix) |

- Page : `public/admin/products.php` — liste / create / update / delete MySQL
- Catégories : `public/data/product-categories.json` (filtre + select + validation)
- Scripts : `public/scripts/admin-products.js` + `public/scripts/admin/*`
- Styles : `public/styles/admin.css`
- Promo : champ `ppromo` ; **TVA 20 %** (`ProductPricing::TVA_RATE`) ; TTC barré + **prix disponible** si promo &gt; 0
- Image : upload admin → `public/uploads/products/` ; chemin relatif en `product_image` (`ProductImageUploader`)
- Helpers de test uniquement : `InMemoryProductRepository` + `SessionProductRepository` (plus utilisés par l’UI)

> **Temporaire (dev / fil rouge) :** les fichiers sont sous `public/`, donc **visibles via l’URL** (le développeur et quiconque connaît le chemin peut les ouvrir). Ce n’est **pas** un stockage privé. Avant la prod : stockage hors web root, CDN, ou URLs signées.

**Feuille de route image produit :**

| Étape | Contenu | Statut |
|-------|---------|--------|
| 1 | Upload fichier + chemin en base | Fait |
| 2 | Affichage `<img>` liste / détail / aperçu | Fait |
| 3 | Robustesse (suppression ancienne image à l’update/delete) | Fait |

**Feuille de route robustesse admin produits :**

| Étape | Contenu | Statut |
|-------|---------|--------|
| 1 | Valider avant upload + rollback fichier si persist échoue | Fait |
| 2 | Erreurs par champ + repopulate formulaire | Fait |
| 3 | Connexion DB try/catch + action POST invalide | Fait |

**Feuille de route admin produits :**

| Étape | Contenu | Statut |
|-------|---------|--------|
| 0 | README aligné (état courant) | Fait |
| 1 | Liste + delete via `ProductService` / SQL | Fait |
| 2 | Create + update via `ProductService` | Fait |
| 3 | Promo dans les formulaires (TTC barré + prix disponible) | Fait |
| 4 | Mocks réservés aux tests + polish libellés UI | Fait |

**Migrations produits (ordre Docker init) :**

- `004_create_products_table.sql` — table `product`
- `005_add_product_promotion.sql` — colonne `ppromo`
- `006_add_product_image.sql` — colonne `product_image`

```bash
./vendor/bin/phpunit tests/unit/service/ProductServiceTest.php
./vendor/bin/phpunit tests/unit/dao/InMemoryProductRepositoryTest.php
./vendor/bin/phpunit tests/unit/dao/SessionProductRepositoryTest.php
./vendor/bin/phpunit tests/integration/dao/ProductDaoTest.php
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
- `compteType` (string) — Type de compte (`client`, `admin`, `vendeur`, …)

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

# Auth (sessions + user_id edge cases)
./vendor/bin/phpunit tests/unit/service/AuthServiceTest.php

# Session store natif
./vendor/bin/phpunit tests/unit/http/NativeSessionStoreTest.php

# Validation
./vendor/bin/phpunit tests/unit/service/ValidationServiceTest.php

# Flux login / register
./vendor/bin/phpunit tests/unit/service/LoginHandlerTest.php
./vendor/bin/phpunit tests/unit/service/RegisterHandlerTest.php

# DAO utilisateurs
./vendor/bin/phpunit tests/integration/dao/UserDaoTest.php

# DAO produits
./vendor/bin/phpunit tests/integration/dao/ProductDaoTest.php

# Connexion DB
./vendor/bin/phpunit tests/integration/db/DatabaseConnectionTest.php
```

---

## Architecture

```
src/
├── Domain/
│   └── Model/
│       ├── User.php                         → Entité User (+ compte_type)
│       └── Product.php                      → Entité Product (DAO SQL)
│   ├── Pricing/
│   │   └── ProductPricing.php               → HT → TTC (TVA 20 %) + prix promo
├── Application/
│   ├── DTO/
│   │   ├── LoginRequestDTO.php              → DTO de login
│   │   ├── RegisterRequestDTO.php           → DTO d’inscription
│   │   └── ProductRequestDTO.php            → DTO produit
│   ├── Services/
│   │   ├── Shared/                          → Validation + ValidationResult
│   │   ├── Auth/                            → Auth, Login/Admin/Register handlers
│   │   └── Product/                         → ProductService (CRUD métier)
│   └── Validation/
│       ├── Shared/                          → Règles atomiques + FieldResult
│       ├── Auth/                            → Username, password, email, confirm
│       └── Product/                         → Catégorie, prix, stock, promo, image
└── Infrastructure/
    ├── Http/
    │   ├── SessionStoreInterface.php        → Contrat session
    │   └── NativeSessionStore.php           → session_start() sécurisé
    ├── Storage/
    │   └── ProductImageUploader.php         → Upload image → /uploads/products
    └── Persistence/
        └── DAO/
            ├── UserDAO.php                  → UserRepository + Interface
            ├── NullUserRepository.php       → Repo no-op (GET anonyme)
            ├── ProductDAO.php               → ProductRepository + Interface (SQL)
            ├── InMemoryProductRepository.php → Helper tests (mémoire)
            └── SessionProductRepository.php → Helper tests (session)

config/
└── database.php                             → Connexion à la base de données

database/
└── migrations/
    ├── 001_create_users_table.sql           → Tables compte + user
    ├── 002_fix_users_unique_constraints.sql → Login/email uniques
    ├── 003_seed_admin_user.sql              → Seed admin (admin / Admin123!)
    ├── 004_create_products_table.sql        → Table product
    ├── 005_add_product_promotion.sql        → Colonne ppromo
    └── 006_add_product_image.sql            → Colonne product_image

public/
├── index.php                                → Page de login client
├── register.php                             → Page d’inscription
├── products.php                             → Landing post-auth + logout
├── admin/
│   ├── login.php                            → Login admin (séparé)
│   ├── index.php                            → Dashboard admin (garde isAdmin)
│   └── products.php                         → CRUD produits (ProductService / MySQL)
├── styles.css                               → Imports CSS
├── styles/                                  → base, header, form, button, tooltip, feedback, validation, admin
└── scripts/
    └── password-toggle.js                   → Toggle visibilité password

php/
└── Dockerfile                               → Image PHP 8.3 + Apache

tests/
├── integration/
│   ├── dao/
│   │   ├── UserDaoTest.php                  → Test intégration User DAO
│   │   └── ProductDaoTest.php               → Test intégration Product DAO
│   └── db/
│       └── DatabaseConnectionTest.php       → Test connexion DB
└── unit/
    ├── dao/
    │   ├── InMemoryProductRepositoryTest.php → Mock produits
    │   └── SessionProductRepositoryTest.php → Mock session
    ├── http/
    │   └── NativeSessionStoreTest.php       → Session PHP sécurisée
    └── service/
        ├── AuthServiceTest.php              → Auth login/register/session/rôle
        ├── LoginHandlerTest.php             → Flux login + redirect
        ├── AdminLoginHandlerTest.php        → Flux login admin + garde
        ├── RegisterHandlerTest.php          → Flux register + redirect
        ├── ProductServiceTest.php           → Service produits + fake repo
        ├── ProductValidationTest.php        → validateProduct + règles Product/
        └── ValidationServiceTest.php        → Validateurs auth + Validation

docs/
└── bugs.md                                  → Journal des bugs connus / corrigés
```

---

## Branches

Remote : `origin` → https://github.com/reneOkeCodjo/app-web-ecommerce.git

### Branches distantes (`origin`)

| Branche | Rôle |
|---------|------|
| `origin/main` | Branche principale publiée |
| `origin/develop` | Branche d’intégration publiée |

### Branches locales

| Branche | Suivi distant |
|---------|---------------|
| `main` | `origin/main` |
| `develop` | `origin/develop` (merge local + distant effectué) |
| `chore/resolve-merge` | aucune — clarification post-merge (migrations + README) |
| `feature/admin` | aucune (locale uniquement) — back-office admin (fusionnée dans `develop`) |
| `feature/admin-products-crud` | aucune (locale uniquement) — UI CRUD produits (fusionnée) |
| `feature/admin-product-image` | aucune — upload / affichage images admin (fusionnée dans `develop`) |
| `feature/register` | aucune (locale uniquement) |
| `feature/login` | aucune (locale uniquement) |
| `feature/session` | aucune (locale uniquement) |
| `feature/ui` | aucune (locale uniquement) |
| `feature/validator` | aucune (locale uniquement) |
| `feature/test` | aucune (locale uniquement) |

> Les branches `feature/*` n’ont pas de correspondance distante sur `origin`.

---

## Auteur

- **Nom** : reneokecodjo
- **Email** : hokecodjo@icloud.com
