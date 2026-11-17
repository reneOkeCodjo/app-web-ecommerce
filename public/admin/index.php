<?php

// On active les types stricts de PHP.
declare(strict_types=1);

// On charge le démarrage commun.
require_once __DIR__ . '/../../config/bootstrap.php';

// On importe les classes nécessaires.
use App\Application\Services\Auth\Auth;              // Service d'authentification.
use App\Application\Services\Product\ProductService; // Service des produits.
use App\Infrastructure\Http\NativeSessionStore;      // Session sécurisée.
use App\Infrastructure\Persistence\DAO\NullUserRepository; // Dépôt vide pour lire la session.
use App\Infrastructure\Persistence\DAO\ProductRepository;  // Dépôt produits MySQL.
use Db_config\Database;                               // Connexion à la base.

// On ouvre la session.
$session = new NativeSessionStore();

// On crée le service d'authentification en lecture seule.
$auth = new Auth(new NullUserRepository(), $session);

// Si l'utilisateur n'est pas administrateur, on le renvoie à la connexion admin.
if (!$auth->isAdmin()) {
    header('Location: login.php');
    exit;
}

// Si l'administrateur demande à se déconnecter.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    // On vérifie le jeton CSRF.
    if (!csrf_verify($session)) {
        http_response_code(419);
        exit('Session expirée. Rechargez la page.');
    }

    // On détruit la session puis on redirige.
    $auth->logout();
    header('Location: login.php');
    exit;
}

// On récupère l'identifiant de l'administrateur.
$userId = $auth->getAuthenticatedUserId();

// On prépare les statistiques affichées dans les cartes.
$totalProducts = 0;
$totalStock = 0;
$stockValue = 0.0;
$promoCount = 0;
$outOfStock = 0;
$connection = null;
$error = '';

// On protège le calcul des statistiques.
try {
    // On ouvre la connexion à la base.
    $connection = Database::connect();

    // On crée le service produits.
    $productService = new ProductService(new ProductRepository($connection));

    // On récupère tous les produits.
    $products = $productService->findAll();

    // On compte les produits.
    $totalProducts = count($products);

    // On parcourt chaque produit pour additionner le stock et la valeur.
    foreach ($products as $product) {
        // On convertit les valeurs en nombres.
        $priceHt = (float) $product->getPriceHt();
        $stock = $product->getStock();
        $promotion = $product->getPromotion();

        // On additionne les unités en stock.
        $totalStock += $stock;

        // On ajoute la valeur du stock (prix HT × quantité).
        $stockValue += $priceHt * $stock;

        // On compte les produits en promotion.
        if ($promotion !== null && (float) $promotion > 0) {
            $promoCount++;
        }

        // On compte les produits en rupture.
        if ($stock <= 0) {
            $outOfStock++;
        }
    }
} catch (\Throwable $e) {
    // Message propre en cas d'erreur de base.
    $error = 'Impossible de charger les statistiques pour le moment.';
} finally {
    // On ferme la connexion si elle existe.
    if ($connection !== null) {
        $connection->close();
    }
}

// On prépare la page et ses fichiers communs.
$pageTitle = 'Tableau de bord — Administration';
$assetPrefix = '../';
$activeAdmin = 'dashboard';
$breadcrumbLabel = 'Tableau de bord';
$adminUserId = $userId;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require __DIR__ . '/../partials/head.php'; ?>
</head>
<body>
    <a class="skip-link" href="#main">Aller au contenu principal</a>

    <!-- Structure de l'administration : menu à gauche, contenu à droite. -->
    <div class="admin-shell">
        <!-- Menu latéral commun. -->
        <?php require __DIR__ . '/../partials/admin-sidebar.php'; ?>

        <!-- Zone principale. -->
        <div class="admin-main">
            <?php require __DIR__ . '/../partials/admin-topbar.php'; ?>

            <main id="main" class="admin-content">
                <!-- En-tête de page. -->
                <div class="page-head reveal">
                    <div>
                        <p class="eyebrow">Vue d'ensemble</p>
                        <h1 class="page-title">Tableau de bord</h1>
                        <p class="page-lead">
                            Suivez l'état du catalogue et accédez rapidement à la gestion des produits.
                        </p>
                    </div>
                    <a class="btn btn--primary" href="products.php">Gérer les produits</a>
                </div>

                <!-- Message d'erreur éventuel. -->
                <?php if ($error !== ''): ?>
                    <p class="feedback feedback-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <!-- Cartes de statistiques. -->
                <section class="kpi-grid reveal reveal--delay-1" aria-label="Statistiques du catalogue">
                    <article class="stat-card">
                        <p class="stat-card__label">Produits</p>
                        <p class="stat-card__value"><?= htmlspecialchars((string) $totalProducts, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="stat-card__hint">Références au catalogue</p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-card__label">Unités en stock</p>
                        <p class="stat-card__value"><?= htmlspecialchars((string) $totalStock, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="stat-card__hint">Toutes références confondues</p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-card__label">Valeur du stock</p>
                        <p class="stat-card__value"><?= htmlspecialchars(number_format($stockValue, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</p>
                        <p class="stat-card__hint">Prix HT cumulé</p>
                    </article>
                    <article class="stat-card">
                        <p class="stat-card__label">Promotions</p>
                        <p class="stat-card__value"><?= htmlspecialchars((string) $promoCount, ENT_QUOTES, 'UTF-8') ?></p>
                        <p class="stat-card__hint"><?= $outOfStock ?> rupture<?= $outOfStock > 1 ? 's' : '' ?></p>
                    </article>
                </section>

                <!-- Carte d'accès rapide. -->
                <section class="surface-card surface-card--pad reveal reveal--delay-2">
                    <div class="toolbar">
                        <div>
                            <p class="eyebrow">Actions rapides</p>
                            <h2 class="quick-card__title">Catalogue produits</h2>
                            <p class="muted quick-card__lead">
                                Créez, modifiez ou supprimez une référence, gérez les promotions et les images.
                            </p>
                        </div>
                        <div class="actions">
                            <a class="btn btn--ghost" href="../products.php">Voir la boutique</a>
                            <a class="btn btn--primary" href="products.php">Ouvrir la gestion</a>
                        </div>
                    </div>
                </section>
            </main>
        </div>
    </div>

    <!-- Script de thème. -->
    <script src="<?= e(asset_url('scripts/ui/theme.js', '../')) ?>" defer></script>
</body>
</html>
