# Site de Vente

Application e-commerce en PHP 8.3, sans framework, avec une architecture inspirée du DDD.
Le catalogue et le back-office utilisent un design « Noir & Champagne » (thème sombre par défaut,
thème clair « Ivoire » disponible).

## Fonctionnalités

- Connexion et inscription des clients (mots de passe hachés avec Argon2id).
- Catalogue avec recherche, filtre par catégorie, pagination et fiche produit rapide.
- Back-office administrateur : tableau de bord, création, modification et suppression de produits.
- Gestion des images produits, des promotions et calcul automatique des prix TTC.
- Protection CSRF sur tous les formulaires sensibles.
- Limitation des tentatives de connexion (anti-force brute).
- En-têtes de sécurité et CSP stricte avec nonce, sans script inline.
- Thème sombre ou clair mémorisé dans le navigateur.

## Prérequis

- PHP 8.2 ou supérieur (8.3 recommandé) avec les extensions `mysqli`, `mbstring` et `fileinfo`.
- MySQL 8.
- Composer 2.
- Node.js et npm uniquement pour BrowserSync (facultatif).

## Installation

```bash
composer install
cp .env.example .env
```

Sous Windows (PowerShell) :

```powershell
Copy-Item .env.example .env
```

Renseignez ensuite les informations de connexion MySQL dans `.env` :

```dotenv
APP_ENV=local
APP_TIMEZONE=Europe/Paris

DB_HOST=localhost
DB_PORT=3306
DB_NAME=site_de_vente
DB_USER=root
DB_PASSWORD=
```

## Base de données

Appliquer les migrations (scripts SQL classés par numéro) :

```bash
composer migrate
```

Options utiles :

```bash
php database/migrate.php --status
php database/migrate.php --dry-run
```

Créer des données de démonstration :

```bash
composer seed
```

Le script refuse de s'exécuter si des produits existent déjà. Pour repartir de zéro :

```bash
php database/seed.php --fresh
```

Les migrations sont suivies dans la table `schema_migrations`, ce qui rend les exécutions
idempotentes.

## Lancer le projet

Avec le serveur intégré de PHP :

```bash
php -S localhost:8000 -t public
```

Puis ouvrez `http://localhost:8000`.

Avec Docker :

```bash
docker compose up -d
```

L'application est alors servie sur `http://localhost:8080`.

## Tests

```bash
composer test
```

Ou uniquement les tests unitaires (aucune base de données nécessaire) :

```bash
vendor/bin/phpunit --testsuite "Unit tests"
```

Les tests d'intégration nécessitent une base MySQL accessible avec les identifiants du fichier `.env`.

## Vérifier la syntaxe PHP

```bash
find src config public tests database -name "*.php" -exec php -l {} \;
```

Sous PowerShell :

```powershell
Get-ChildItem -Path src,config,public,tests,database -Recurse -Include *.php |
  ForEach-Object { php -l $_.FullName }
```

## Structure du projet

```text
config/                 Bootstrap, sécurité, connexion et configuration MySQL
database/               Migrations SQL, runner et script de seed
public/                 Point d'entrée web, pages, styles, scripts et partiels
src/
  Application/          Cas d'usage, DTO, validateurs, présentateurs
  Domain/               Modèles métier et calcul des prix
  Infrastructure/       Accès MySQL, session, CSRF, stockage des images, HTTP
tests/
  unit/                 Tests rapides sans base de données
  integration/          Tests avec une vraie base MySQL
```

## Sécurité

- Tous les formulaires de modification utilisent un jeton CSRF (`csrf_field` / `csrf_verify`).
- Les cookies de session sont `HttpOnly`, `SameSite=Lax` et `Secure` en HTTPS.
- La CSP interdit les scripts inline : les données destinées au JavaScript passent par des
  balises `<script type="application/json" nonce="...">`.
- Les échecs de connexion répétés sont temporairement bloqués (`LoginThrottle`).

## Design

Les styles sont organisés par rôle :

- `styles/tokens.css` : couleurs, espacements, rayons et ombres.
- `styles/base.css` : éléments HTML de base.
- `styles/layout.css` : structure des pages.
- `styles/components.css`, `form.css`, `button.css`, `feedback.css`, `toast.css` : composants.
- `styles/catalog.css` et `styles/admin.css` : styles propres au catalogue et au back-office.
- `scripts/ui/` : scripts du thème sombre / clair et des messages temporaires.

## Licence

Projet privé. Tous droits réservés.
