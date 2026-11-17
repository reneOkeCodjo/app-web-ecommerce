<?php

// On active les types stricts de PHP.
declare(strict_types=1);

// On charge le démarrage commun.
require_once __DIR__ . '/../config/bootstrap.php';

// On importe les classes nécessaires.
use App\Application\Presenter\ProductPresenter;           // Transforme les produits pour l'affichage.
use App\Application\Services\Auth\Auth;                   // Service d'authentification.
use App\Application\Services\Product\ProductService;      // Service des produits.
use App\Infrastructure\Http\NativeSessionStore;           // Session sécurisée.
use App\Infrastructure\Persistence\DAO\NullUserRepository; // Dépôt vide pour lire la session.
use App\Infrastructure\Persistence\DAO\ProductRepository;  // Dépôt produits MySQL.
use Db_config\Database;                                    // Connexion à la base.

// On ouvre la session.
$session = new NativeSessionStore();

// On crée le service d'authentification (dépôt vide : on lit seulement la session).
$auth = new Auth(new NullUserRepository(), $session);

// Si l'utilisateur n'est pas connecté, on le renvoie vers la page de connexion.
if (!$auth->isAuthenticated()) {
    header('Location: index.php');
    exit;
}

// Si l'utilisateur demande à se déconnecter (formulaire POST).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    // On vérifie d'abord le jeton CSRF.
    if (!csrf_verify($session)) {
        http_response_code(419);
        exit('Session expirée. Rechargez la page.');
    }

    // On détruit la session et on redirige vers la connexion.
    $auth->logout();
    header('Location: index.php');
    exit;
}

// On récupère l'identifiant de l'utilisateur connecté.
$userId = $auth->getAuthenticatedUserId();

// On récupère aussi son rôle pour afficher éventuellement le lien admin.
$isAdmin = $auth->isAdmin();

// On charge la liste des catégories depuis le fichier JSON.
$categoriesPath = __DIR__ . '/data/product-categories.json';
$categoriesRaw = is_file($categoriesPath) ? file_get_contents($categoriesPath) : false;
$categoriesDecoded = is_string($categoriesRaw) ? json_decode($categoriesRaw, true) : null;
$categories = is_array($categoriesDecoded) ? $categoriesDecoded : [];

// On prépare la liste des produits et leur version JSON.
$products = [];
$productsJson = [];
$connection = null;
$error = '';

// On protège le chargement des produits.
try {
    // On ouvre la connexion à la base.
    $connection = Database::connect();

    // On crée le service produits.
    $productService = new ProductService(new ProductRepository($connection));

    // On récupère tous les produits.
    $products = $productService->findAll();

    // On transforme chaque produit en tableau simple pour le JavaScript.
    $productsJson = ProductPresenter::collection($products);
} catch (\Throwable $e) {
    // En cas d'erreur de base, on affiche un message propre.
    $error = 'Connexion à la base impossible. Réessayez plus tard.';
} finally {
    // On ferme la connexion si elle existe.
    if ($connection !== null) {
        $connection->close();
    }
}

// On prépare la page et ses fichiers communs.
$pageTitle = 'Catalogue — Site de Vente';
$assetPrefix = '';
$activeNav = 'catalog';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body>
    <a class="skip-link" href="#main">Aller au contenu principal</a>

    <!-- En-tête commun du site. -->
    <?php require __DIR__ . '/partials/site-header.php'; ?>

    <main id="main" class="catalog">
        <!-- Hero éditorial. -->
        <section class="container catalog-hero">
            <div>
                <p class="eyebrow">Collection 2026</p>
                <h1 class="catalog-hero__title">La sélection<br><em>essentielle.</em></h1>
            </div>
            <div class="catalog-hero__aside">
                <p>
                    Une sélection resserrée de produits vérifiés, avec des prix
                    nets et des promotions claires.
                </p>
                <p class="catalog-hero__stat">
                    <strong><?= count($products) ?></strong>
                    <span class="muted">produits disponibles</span>
                </p>
                <p class="muted">Connecté en tant qu'utilisateur #<?= htmlspecialchars((string) $userId, ENT_QUOTES, 'UTF-8') ?>.</p>
            </div>
        </section>

        <section class="container">
            <!-- Message d'erreur de base de données. -->
            <?php if ($error !== ''): ?>
                <p class="feedback feedback-error" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <!-- Barre de filtres. -->
            <div class="catalog-toolbar" role="search">
                <label>
                    <span>Catégorie</span>
                    <select id="catalog-category-filter" aria-label="Filtrer par catégorie">
                        <option value="">Toutes</option>
                        <?php foreach ($categories as $category): ?>
                            <?php
                            $label = is_array($category) ? (string) ($category['categorie'] ?? '') : '';
                            if ($label === '') {
                                continue;
                            }
                            ?>
                            <option value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label>
                    <span>Trier par</span>
                    <select id="catalog-sort" aria-label="Trier les produits">
                        <option value="recent">Plus récent</option>
                        <option value="price-asc">Prix croissant</option>
                        <option value="price-desc">Prix décroissant</option>
                        <option value="name-asc">Nom A-Z</option>
                        <option value="name-desc">Nom Z-A</option>
                        <option value="stock">Stock disponible</option>
                    </select>
                </label>

                <label>
                    <span>Rechercher</span>
                    <input type="text" id="catalog-search" placeholder="Nom du produit…" aria-label="Rechercher un produit">
                </label>

                <label>
                    <span>Par page</span>
                    <select id="catalog-page-size" aria-label="Produits par page">
                        <option value="6">6</option>
                        <option value="12" selected>12</option>
                        <option value="24">24</option>
                    </select>
                </label>
            </div>

            <!-- Grille des produits, remplie par JavaScript. -->
            <div class="catalog-grid" id="catalog-grid" aria-live="polite"></div>

            <!-- Pagination, remplie par JavaScript. -->
            <nav id="catalog-pagination" class="catalog-pagination" aria-label="Pagination du catalogue"></nav>
        </section>
    </main>

    <!-- Fenêtre de détail rapide commune. -->
    <?php require __DIR__ . '/partials/quick-view-dialog.php'; ?>

    <!-- Données produits en JSON, lues par JavaScript sans script inline. -->
    <script type="application/json" id="catalog-products-data" nonce="<?= csp_nonce() ?>"><?= ProductPresenter::json($productsJson) ?></script>

    <!-- Scripts de la page. -->
    <script src="<?= e(asset_url('scripts/ui/theme.js')) ?>" defer></script>
    <script src="<?= e(asset_url('scripts/product-catalog.js')) ?>" defer></script>
</body>
</html>
