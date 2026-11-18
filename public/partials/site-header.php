<?php

// On refuse un accès direct à ce fichier partiel.
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(403);
    exit;
}

// Valeurs fournies par la page.
$session = $session ?? null;
$isAdmin = $isAdmin ?? false;

// Le formulaire de déconnexion exige un jeton CSRF et une session valide.
$canLogout = $session instanceof \App\Infrastructure\Http\SessionStoreInterface;

// La marque renvoie vers le catalogue.
$brandHref = 'products.php';
$brandLabel = 'ATLAS, catalogue';
$brandTagline = 'Student living';
?>
<header class="site-header">
    <div class="container site-header__inner">
        <?php require __DIR__ . '/brand.php'; ?>

        <!-- Navigation principale. -->
        <nav class="site-nav" aria-label="Navigation principale">
            <a class="site-nav__link site-nav__link--active" href="products.php">Catalogue</a>
            <a class="site-nav__link" href="#arrival">Arrivée</a>
            <a class="site-nav__link" href="#guide">Guide</a>

            <?php if ($canLogout): ?>
                <a class="site-nav__link" href="cart.php">Panier</a>
                <a class="site-nav__link" href="profile.php">Profil</a>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
                <a class="site-nav__link site-nav__link--admin" href="admin/index.php">Administration</a>
            <?php endif; ?>

            <?php require __DIR__ . '/theme-toggle.php'; ?>

            <?php if ($canLogout): ?>
                <!-- Déconnexion en POST, protégée par CSRF. -->
                <form method="post" class="site-nav__form">
                    <?= csrf_field($session) ?>
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="site-nav__link site-nav__link--logout">Déconnexion</button>
                </form>
            <?php endif; ?>
        </nav>
    </div>
</header>
