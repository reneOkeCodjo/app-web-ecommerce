<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Application\Services\Cart\CartService;
use App\Application\Services\Auth\Auth;
use App\Infrastructure\Http\NativeSessionStore;
use App\Infrastructure\Persistence\DAO\CartRepository;
use App\Infrastructure\Persistence\DAO\NullUserRepository;
use App\Infrastructure\Persistence\DAO\ProductRepository;
use App\Infrastructure\Persistence\DAO\UserRepository;
use Db_config\Database;

$session = new NativeSessionStore();
$auth = new Auth(new NullUserRepository(), $session);
$username = 'Utilisateur';
$currentUser = null;
$connection = null;

try {
    // Le service utilise MySQL pour les produits et la session pour les quantités.
    $connection = Database::connect();
    $userRepository = new UserRepository($connection);
    $userId = $auth->getAuthenticatedUserId();
    $currentUser = $userId !== null ? $userRepository->findById($userId) : null;
    if ($currentUser !== null) {
        $username = $currentUser->getUsername();
    }

    $cart = new CartService(
        new ProductRepository($connection),
        $session,
        new CartRepository($connection)
    );

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Les requêtes POST constituent l'API utilisée par JavaScript.
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
        $productId = (int) ($_POST['product_id'] ?? 0);

        if ($action === 'logout') {
            $auth->logout();
            header('Location: index.php');
            exit;
        }

        // Chaque action délègue ses règles métier au CartService.
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

        // On renvoie le panier recalculé pour actualiser immédiatement l'interface.
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => true, 'cart' => $cart->snapshot()], JSON_THROW_ON_ERROR);
        exit;
    }

    $snapshot = $cart->snapshot();
} catch (Throwable $e) {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Les erreurs de l'API sont renvoyées au navigateur au format JSON.
        http_response_code(400);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['ok' => false, 'error' => $e->getMessage()], JSON_THROW_ON_ERROR);
        exit;
    }

    // La page peut afficher un panier vide si la connexion à la base échoue.
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
    $error = 'Impossible de charger le panier.';
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
    <title>Mon panier</title>
    <?php $cartStylesVersion = is_file(__DIR__ . '/styles/catalog.css') ? filemtime(__DIR__ . '/styles/catalog.css') : false; ?>
    <link rel="stylesheet" href="styles.css<?= $cartStylesVersion !== false ? '?v=' . $cartStylesVersion : '' ?>">
</head>
<body>
    <main class="auth-page">
        <section class="catalog-page cart-page" aria-labelledby="cart-title">
            <div class="auth-header">
                <h1 id="cart-title" class="auth-title">
                    <span class="auth-title__text">Mon panier</span>
                </h1>
            </div>

            <div class="cart-page__toolbar">
                <div class="catalog-toolbar__actions">
                    <?php if ($currentUser !== null): ?>
                        <details class="catalog-profile-menu">
                            <summary aria-label="Ouvrir le menu utilisateur">
                                <svg viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                                    <circle cx="12" cy="8" r="3.5"></circle>
                                    <path d="M5 20c.7-3.5 3.2-5.5 7-5.5s6.3 2 7 5.5"></path>
                                </svg>
                                <span><?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?></span>
                            </summary>
                            <div class="catalog-profile-menu__content">
                                <a href="profile.php">Modifier le profil</a>
                                <form method="post">
                                    <input type="hidden" name="action" value="logout">
                                    <button type="submit">Se déconnecter</button>
                                </form>
                            </div>
                        </details>
                    <?php endif; ?>
                </div>
            </div>

            <?php if (isset($error)): ?>
                <p class="feedback feedback-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <!-- cart.js insère ici les lignes et les totaux du panier. -->
            <div id="cart-content"></div>
            <p class="auth-switch"><a href="products.php">Continuer mes achats</a></p>
        </section>
    </main>
    <!-- Transmission de l'état initial calculé par le serveur. -->
    <script>
        window.__CART__ = <?= json_encode($snapshot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <?php $cartScriptVersion = is_file(__DIR__ . '/scripts/cart.js') ? filemtime(__DIR__ . '/scripts/cart.js') : false; ?>
    <script src="scripts/cart.js<?= $cartScriptVersion !== false ? '?v=' . $cartScriptVersion : '' ?>"></script>
</body>
</html>
