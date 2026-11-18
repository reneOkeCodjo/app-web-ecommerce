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
use App\Infrastructure\Persistence\DAO\UserRepository;      // Dépôt utilisateurs MySQL.
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
$totalUsers = 0;
$totalAdmins = 0;
$products = [];
$recentProducts = [];
$recentUsers = [];
$categoryTotals = [];
$adminUsername = 'Admin';
$connection = null;
$error = '';

// On protège le calcul des statistiques.
try {
    // On ouvre la connexion à la base.
    $connection = Database::connect();

    // On revalide le rôle admin directement en base.
    $userRepository = new UserRepository($connection);
    $auth = new Auth($userRepository, $session);

    if (!$auth->isAdminFresh()) {
        header('Location: login.php');
        exit;
    }

    // On prépare le nom affiché dans la barre du haut.
    $admin = $userRepository->findById((int) $auth->getAuthenticatedUserId());
    $adminUsername = $admin?->getUsername() ?? 'Admin';

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

        // On additionne les unités en stock.
        $totalStock += $stock;

        // On ajoute la valeur du stock (prix HT × quantité).
        $stockValue += $priceHt * $stock;

        // On prépare la répartition par catégorie pour le panneau latéral.
        $category = $product->getType();
        $categoryTotals[$category] = ($categoryTotals[$category] ?? 0) + 1;
    }

    // On garde les produits les plus récents pour le tableau de bord.
    $recentProducts = array_slice($products, 0, 4);
    arsort($categoryTotals);

    // On charge les utilisateurs pour les indicateurs et la liste récente.
    $users = $userRepository->findAll();
    $totalUsers = count($users);
    $totalAdmins = $userRepository->countByType('admin');
    $recentUsers = array_slice($users, 0, 3);
} catch (\Throwable $e) {
    // Message propre en cas d'erreur de base.
    $error = 'Impossible de charger les statistiques pour le moment.';
} finally {
    // On ferme la connexion si elle existe.
    if ($connection !== null) {
        $connection->close();
    }
}

// Libellés et petits utilitaires d'affichage.
$roleLabels = [
    'client' => 'Étudiant',
    'vendeur' => 'Partenaire',
    'admin' => 'Administrateur',
];

$formatDate = static function (?string $date): string {
    if ($date === null || $date === '') {
        return 'Jamais';
    }

    $timestamp = strtotime($date);

    return $timestamp !== false ? date('d/m/Y', $timestamp) : '—';
};

$today = date('d/m/Y');

// On prépare la page et ses fichiers communs.
$pageTitle = 'Tableau de bord — Administration ATLAS';
$pageDescription = 'Pilotage du catalogue, des utilisateurs et des accès ATLAS.';
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
<body class="atlas-admin">
    <a class="skip-link" href="#main">Aller au contenu principal</a>
    <?php require __DIR__ . '/../partials/preloader.php'; ?>

    <!-- Structure de l'administration : menu à gauche, contenu à droite. -->
    <div class="admin-shell">
        <!-- Menu latéral commun. -->
        <?php require __DIR__ . '/../partials/admin-sidebar.php'; ?>

        <!-- Zone principale. -->
        <div class="admin-main">
            <?php require __DIR__ . '/../partials/admin-topbar.php'; ?>

            <main id="main" class="admin-content admin-content--wide">
                <!-- En-tête de page. -->
                <div class="page-head" data-reveal>
                    <div>
                        <p class="eyebrow">Vue d’ensemble · <?= e($today) ?></p>
                        <h1 class="page-title">Bonjour, <?= e($adminUsername) ?>.</h1>
                        <p class="page-lead">
                            Le catalogue, l’équipe et les accès en un seul regard.
                        </p>
                    </div>
                    <div class="page-head__actions">
                        <a class="btn btn--soft" href="../products.php">Voir le site</a>
                        <a class="btn btn--primary" href="products.php">Gérer les produits</a>
                    </div>
                </div>

                <!-- Message d'erreur éventuel. -->
                <?php if ($error !== ''): ?>
                    <p class="feedback feedback-error" role="alert"><?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?></p>
                <?php endif; ?>

                <section class="admin-hero" data-reveal>
                    <div class="admin-hero__copy">
                        <p class="eyebrow">ATLAS Studio · Pilotage</p>
                        <h2>Un catalogue net.<br><em>Une équipe alignée.</em></h2>
                        <p>
                            Gardez les produits à jour, ouvrez les bons accès et laissez la plateforme
                            rester rapide pour les étudiants.
                        </p>
                        <div class="admin-hero__actions">
                            <a class="btn btn--primary btn--sm" href="products.php">Catalogue</a>
                            <a class="btn btn--ghost btn--sm" href="users.php">Utilisateurs</a>
                        </div>
                    </div>

                    <div class="admin-hero__orb" aria-label="<?= e((string) $totalProducts) ?> produits">
                        <span><?= e((string) $totalProducts) ?></span>
                        <small>références<br>au catalogue</small>
                        <i aria-hidden="true"></i>
                    </div>

                    <div class="admin-hero__note">
                        <span class="pill"><span class="pill__dot" aria-hidden="true"></span> Plateforme opérationnelle</span>
                        <p><?= e((string) $totalAdmins) ?> administrateur<?= $totalAdmins > 1 ? 's' : '' ?> · <?= e((string) $totalUsers) ?> compte<?= $totalUsers > 1 ? 's' : '' ?></p>
                    </div>
                </section>

                <!-- Cartes de statistiques. -->
                <section class="metric-grid" aria-label="Statistiques de la plateforme" data-reveal>
                    <article class="metric-card">
                        <span class="metric-card__index">01</span>
                        <p class="metric-card__label">Produits</p>
                        <p class="metric-card__value"><?= e((string) $totalProducts) ?></p>
                        <p class="metric-card__note">Références publiées</p>
                    </article>
                    <article class="metric-card">
                        <span class="metric-card__index">02</span>
                        <p class="metric-card__label">Utilisateurs</p>
                        <p class="metric-card__value"><?= e((string) $totalUsers) ?></p>
                        <p class="metric-card__note">Comptes ouverts</p>
                    </article>
                    <article class="metric-card">
                        <span class="metric-card__index">03</span>
                        <p class="metric-card__label">Unités en stock</p>
                        <p class="metric-card__value"><?= e((string) $totalStock) ?></p>
                        <p class="metric-card__note">Tous produits confondus</p>
                    </article>
                    <article class="metric-card metric-card--accent">
                        <span class="metric-card__index">04</span>
                        <p class="metric-card__label">Valeur du stock</p>
                        <p class="metric-card__value"><?= e(number_format($stockValue, 0, ',', ' ')) ?> €</p>
                        <p class="metric-card__note">Prix HT × quantités</p>
                    </article>
                </section>

                <div class="admin-dashboard-grid">
                    <section class="admin-panel" data-reveal>
                        <header class="admin-panel__head admin-panel__head--row">
                            <div>
                                <p class="eyebrow">Derniers ajouts</p>
                                <h2>Le catalogue bouge.</h2>
                            </div>
                            <a class="back-link" href="products.php">Tout voir →</a>
                        </header>

                        <?php if ($recentProducts === []): ?>
                            <p class="admin-empty">Aucun produit pour le moment.</p>
                        <?php else: ?>
                            <ul class="admin-list">
                                <?php foreach ($recentProducts as $product): ?>
                                    <li>
                                        <span class="admin-list__index">#<?= e((string) $product->getId()) ?></span>
                                        <span class="admin-list__main">
                                            <strong><?= e($product->getDesignation()) ?></strong>
                                            <small><?= e($product->getType()) ?></small>
                                        </span>
                                        <span class="admin-list__meta"><?= e((string) $product->getStock()) ?> en stock</span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>

                    <aside class="admin-panel" data-reveal>
                        <header class="admin-panel__head">
                            <p class="eyebrow">Répartition</p>
                            <h2>Par catégorie.</h2>
                        </header>

                        <ul class="category-list">
                            <?php foreach ($categoryTotals as $category => $total): ?>
                                <li>
                                    <span><?= e($category) ?></span>
                                    <strong><?= e((string) $total) ?></strong>
                                </li>
                            <?php endforeach; ?>
                        </ul>
                    </aside>
                </div>

                <div class="admin-dashboard-grid admin-dashboard-grid--lower">
                    <section class="admin-panel" data-reveal>
                        <header class="admin-panel__head admin-panel__head--row">
                            <div>
                                <p class="eyebrow">Équipe</p>
                                <h2>Les derniers comptes.</h2>
                            </div>
                            <a class="back-link" href="users.php">Gérer →</a>
                        </header>

                        <?php if ($recentUsers === []): ?>
                            <p class="admin-empty">Aucun utilisateur pour le moment.</p>
                        <?php else: ?>
                            <ul class="admin-user-list">
                                <?php foreach ($recentUsers as $user): ?>
                                    <?php $initial = mb_strtoupper(mb_substr($user->getUsername(), 0, 1)); ?>
                                    <li>
                                        <span class="table-user__avatar" aria-hidden="true"><?= e($initial) ?></span>
                                        <span class="admin-list__main">
                                            <strong><?= e($user->getUsername()) ?></strong>
                                            <small><?= e($user->getEmail()) ?></small>
                                        </span>
                                        <span class="role-badge role-badge--<?= e($user->getCompteType()) ?>">
                                            <?= e($roleLabels[$user->getCompteType()] ?? $user->getCompteType()) ?>
                                        </span>
                                    </li>
                                <?php endforeach; ?>
                            </ul>
                        <?php endif; ?>
                    </section>

                    <aside class="admin-panel admin-panel--accent" data-reveal>
                        <p class="eyebrow">Accès rapide</p>
                        <h2>Continuer le travail.</h2>
                        <p>Les actions essentielles, sans détour.</p>
                        <div class="admin-quick-links">
                            <a href="products.php">Gérer les produits <span>→</span></a>
                            <a href="users.php">Gérer les utilisateurs <span>→</span></a>
                            <a href="../products.php">Voir le catalogue public <span>↗</span></a>
                        </div>
                    </aside>
                </div>
            </main>
        </div>
    </div>

    <!-- Script de thème. -->
    <script src="<?= e(asset_url('scripts/ui/theme.js', '../')) ?>" defer></script>
</body>
</html>
