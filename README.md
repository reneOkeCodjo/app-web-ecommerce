# Site de Vente

## Description

Application e-commerce en PHP 8.3 avec architecture DDD (Domain-Driven Design).
Le projet utilise MySQL comme base de données et Docker pour l'environnement de développement.

## Design

**Maquette Figma (lecture seule) :**

- [Login — Figma](https://www.figma.com/design/IUNoBVcRSL0D5sNNFnaWtr/Login?node-id=0-1&m=dev)

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
```

---

## Auteur

- **Nom** : reneokecodjo
- **Email** : hokecodjo@icloud.com
