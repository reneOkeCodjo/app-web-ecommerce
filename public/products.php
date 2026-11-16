<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Application\Services\Auth\Auth;
use App\Application\Services\Product\ProductService;
use App\Domain\Model\Product;
use App\Domain\Pricing\ProductPricing;
use App\Infrastructure\Http\NativeSessionStore;
use App\Infrastructure\Persistence\DAO\NullUserRepository;
use App\Infrastructure\Persistence\DAO\ProductRepository;
use Db_config\Database;

$session = new NativeSessionStore();
$auth = new Auth(new NullUserRepository(), $session);

if (!$auth->isAuthenticated()) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    $auth->logout();
    header('Location: index.php');
    exit;
}

$userId = $auth->getAuthenticatedUserId();

$categoriesPath = __DIR__ . '/data/product-categories.json';
$categoriesRaw = is_file($categoriesPath) ? file_get_contents($categoriesPath) : false;
$categoriesDecoded = is_string($categoriesRaw) ? json_decode($categoriesRaw, true) : null;
$categories = is_array($categoriesDecoded) ? $categoriesDecoded : [];

$products = [];
$productsJson = [];
$connection = null;
$error = '';

try {
    $connection = Database::connect();
    $productService = new ProductService(new ProductRepository($connection));

    $products = $productService->findAll();
    $productsJson = array_map(
        static function (Product $product): array {
            $priceHt = (float) $product->getPriceHt();
            $promotion = $product->getPromotion() !== null
                ? (float) $product->getPromotion()
                : null;

            return [
                'id' => $product->getId(),
                'type' => $product->getType(),
                'designation' => $product->getDesignation(),
                'prixHt' => $priceHt,
                'promotion' => $promotion,
                'prixTtc' => ProductPricing::priceTtc($priceHt),
                'prixDisponible' => ProductPricing::priceAvailable($priceHt, $promotion),
                'dateIn' => $product->getDateIn(),
                'timesIn' => $product->getDateNew() ?? '',
                'stock' => $product->getStock(),
                'image' => $product->getImage(),
            ];
        },
        $products
    );
} catch (\Throwable $e) {
    $error = 'Connexion à la base impossible. Réessayez plus tard.';
} finally {
    if ($connection !== null) {
        $connection->close();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Catalogue produits</title>
    <?php
    // Cache-busting: append each asset's mtime so browsers reload after changes.
    $assetVersion = static function (string $relativePath): string {
        $absolute = __DIR__ . '/' . $relativePath;
        $mtime = is_file($absolute) ? filemtime($absolute) : false;
        return $relativePath . ($mtime !== false ? '?v=' . $mtime : '');
    };
    ?>
    <link rel="stylesheet" href="<?= htmlspecialchars($assetVersion('styles.css'), ENT_QUOTES, 'UTF-8') ?>">
</head>

<body>
    <main class="auth-page">
        <section class="catalog-page" aria-labelledby="products-title">
            <div class="auth-header">
                <h1 id="products-title" class="auth-title">
                    <span class="auth-title__text">Catalogue</span>
                </h1>
            </div>

            <?php if ($error !== ''): ?>
                <p class="feedback feedback-error" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <div class="catalog-toolbar">
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

            <div class="catalog-grid" id="catalog-grid">
                <!-- Rendered by JavaScript -->
            </div>

            <nav id="catalog-pagination" class="catalog-pagination" aria-label="Pagination"></nav>

            <p class="auth-switch">
                <form method="post" class="login-form" style="display:inline">
                    <input type="hidden" name="action" value="logout">
                    <button type="submit">Se déconnecter</button>
                </form>
            </p>
        </section>
    </main>

    <script>
        window.__CATALOG_PRODUCTS__ = <?= json_encode($productsJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="<?= htmlspecialchars($assetVersion('scripts/product-catalog.js'), ENT_QUOTES, 'UTF-8') ?>"></script>
</body>

</html>
