# Site de Vente

## Description

Application e-commerce en PHP 8.3 avec architecture DDD (Domain-Driven Design).
Le projet utilise MySQL comme base de données et Docker pour l'environnement de développement.

## État actuel du projet

### ✅ Initialisation du projet

Le projet a été initialisé avec Composer pour gérer les dépendances PHP.

**Dépendances installées :**
- `vlucas/phpdotenv` ^5.7 — Gestion des variables d'environnement via fichier `.env`
- `phpunit/phpunit` ^11.5 — Framework de tests (unitaires et intégration)

**Fichiers de configuration :**
- `composer.json` — Définition des dépendances et autoloading PSR-4
- `composer.lock` — Verrouillage des versions des dépendances
- `.gitignore` — Exclusion des fichiers sensibles (`.env`, `vendor/`, cache)
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

### ✅ Connexion à la base de données

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

### ✅ Test de connexion à la base de données

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

### ✅ Environnement Docker

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

---

## Architecture

```
src/
├── Domain/Model/              → Entités métier
├── Application/
│   ├── DTO/                   → Objets de transfert
│   └── Services/              → Logique métier
└── Infrastructure/
    └── Persistence/DAO/       → Accès données

database/
└── migrations/               → Scripts SQL de migration
    └── 001_create_users_table.sql
```

---

## Auteur

- **Nom** : reneokecodjo
- **Email** : hokecodjo@icloud.com
