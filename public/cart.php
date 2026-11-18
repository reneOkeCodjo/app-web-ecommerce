<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

use App\Application\Services\Auth\Auth;
use App\Application\Services\Cart\CartService;
use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Http\NativeSessionStore;
use App\Infrastructure\Persistence\DAO\CartRepository;
use App\Infrastructure\Persistence\DAO\NullUserRepository;
use App\Infrastructure\Persistence\DAO\ProductRepository;
use Db_config\Database;

// On démarre la session et on lit l'état de connexion.
$session = new NativeSessionStore();
$auth = new Auth(new NullUserRepository(), $session);
$isAdmin = $auth->isAdmin();

// Le jeton CSRF protège les actions envoyées en POST.
$csrfToken = Csrf::token($session);
$connection = null;
$error = '';

// État de secours affiché si le panier ne peut pas être chargé.
$snapshot = [
    'items' => [],
    'totals' => [
        'quantity' => 0,
        'subtotalHt' => 0,
        'vat' => 0,
        'subtotalTtc' => 0,
        'shipping' => 0,
        'total' => 0,
    ],
];

try {
    // On ouvre la connexion MySQL et on assemble le service panier.
    $connection = Database::connect();
    $cart = new CartService(
        new ProductRepository($connection),
        $session,
        new CartRepository($connection)
    );

    // Les actions du panier passent par le même endpoint JSON.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Sans jeton valide, aucune modification n'est autorisée.
        if (!csrf_verify($session)) {
            http_response_code(403);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode(['ok' => false, 'error' => 'Session expirée. Rechargez la page.'], JSON_THROW_ON_ERROR);
            exit;
        }

        // On lit l'action et le produit envoyés par le formulaire.
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        $productId = (int) ($_POST['product_id'] ?? 0);

        // La déconnexion reste gérée ici pour l'en-tête commun.
        if ($action === 'logout') {
            $auth->logout();
            header('Location: index.php');
            exit;
        }

        // Le service vérifie les quantités et le stock avant d'écrire.
        switch ($action) {
            case 'add':
                $cart->add($productId, max(1, (int) ($_POST['quantity'] ?? 1)));
                break;
            case 'set':
                $cart->setQuantity($productId, (int) ($_POST['quantity'] ?? 0));
                break;
            case 'remove':
                $cart->remove($productId);
                break;
            case 'clear':
                $cart->clear();
                break;
            default:
                throw new InvalidArgumentException('Action panier invalide.');
        }

        // On renvoie le panier complet pour actualiser l'interface.
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'cart' => $cart->snapshot()], JSON_THROW_ON_ERROR);
        exit;
    }

    // Premier chargement : on prépare l'état affiché par la page.
    $snapshot = $cart->snapshot();
} catch (Throwable $e) {
    // Une action en échec renvoie toujours une réponse JSON au JavaScript.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR);
        exit;
    }

    // La page reste consultable avec un message d'erreur lisible.
    $error = 'Impossible de charger le panier pour le moment.';
} finally {
    if ($connection !== null) {
        $connection->close();
    }
}

// Métadonnées communes lues par le partial d'en-tête.
$pageTitle = 'Mon panier — ATLAS';
$pageDescription = 'Votre panier ATLAS : quantités, promotions, TVA, livraison et total.';
$assetPrefix = '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body class="atlas-page atlas-cart">
    <a class="skip-link" href="#main">Aller au contenu principal</a>
    <?php require __DIR__ . '/partials/preloader.php'; ?>
    <?php require __DIR__ . '/partials/site-header.php'; ?>

    <!-- Contenu principal du panier. -->
    <main id="main" class="container cart-page">
        <header class="cart-page__head" data-reveal>
            <p class="eyebrow">Votre sélection</p>
            <h1 class="atlas-title atlas-title--compact">Le panier,<br><em>sans détour.</em></h1>
            <p class="atlas-lead">
                Vérifiez vos essentiels, ajustez les quantités et gardez un œil sur le total.
            </p>
        </header>

        <?php if ($error !== ''): ?>
            <p class="feedback feedback-error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>

        <!-- Le contenu est rendu par cart.js à partir des données serveur. -->
        <section class="cart-page__panel" aria-labelledby="cart-title">
            <div class="cart-page__panel-head">
                <h2 id="cart-title">Articles sélectionnés</h2>
                <a class="cart-page__continue" href="products.php">Continuer mes achats</a>
            </div>

            <p class="cart-feedback" id="cart-feedback" role="status"></p>

            <?php if ($auth->isAuthenticated()): ?>
                <div id="cart-content" data-csrf="<?= e($csrfToken) ?>"></div>
            <?php else: ?>
                <p class="catalog-empty">Connectez-vous pour ajouter des articles à votre panier.</p>
            <?php endif; ?>
        </section>
    </main>

    <!-- Pied de page commun. -->
    <footer class="atlas-footer atlas-footer--wide">
        <div>
            <?php
            $brandHref = 'products.php';
            $brandLabel = 'ATLAS, catalogue';
            $brandTagline = 'Student living';
            require __DIR__ . '/partials/brand.php';
            ?>
            <p>La vie étudiante sans frontières.</p>
        </div>
        <nav aria-label="Liens de pied de page">
            <a href="products.php">Catalogue</a>
            <a href="profile.php">Mon profil</a>
            <a href="admin/login.php">Accès équipe</a>
        </nav>
        <p class="atlas-footer__meta">© <?= date('Y') ?> ATLAS · France</p>
    </footer>

    <!-- État initial du panier, protégé par la CSP et le nonce. -->
    <script type="application/json" id="cart-data" nonce="<?= csp_nonce() ?>"><?= json_encode($snapshot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
    <script src="<?= e(asset_url('scripts/ui/theme.js')) ?>" defer></script>
    <?php if ($auth->isAuthenticated()): ?>
        <script src="<?= e(asset_url('scripts/cart.js')) ?>" defer></script>
    <?php endif; ?>
</body>
</html>
