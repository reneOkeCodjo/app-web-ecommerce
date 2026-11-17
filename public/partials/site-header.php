<?php

// On refuse un accès direct à ce fichier partiel.
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(403);
    exit;
}

// Valeurs fournies par la page.
$session = $session ?? null;
$isAdmin = $isAdmin ?? false;
$activeNav = $activeNav ?? '';

// Le formulaire de déconnexion exige un jeton CSRF et une session valide.
$canLogout = $session instanceof \App\Infrastructure\Http\SessionStoreInterface;
?>
<header class="site-header">
    <div class="container site-header__inner">
        <!-- Marque cliquable qui ramène au catalogue. -->
        <a class="brand" href="products.php" aria-label="Site de Vente, catalogue">
            <span class="brand__mark" aria-hidden="true">SV</span>
            <span class="brand__name">Site de Vente</span>
        </a>

        <!-- Navigation principale. -->
        <nav class="site-nav" aria-label="Navigation principale">
            <?php if ($isAdmin): ?>
                <a class="site-nav__link" href="admin/index.php">Administration</a>
            <?php endif; ?>

            <a class="site-nav__link" href="products.php"<?= $activeNav === 'catalog' ? ' aria-current="page"' : '' ?>>
                Catalogue
            </a>

            <?php require __DIR__ . '/theme-toggle.php'; ?>

            <?php if ($canLogout): ?>
                <!-- Déconnexion en POST, protégée par CSRF. -->
                <form method="post" class="site-nav__form">
                    <?= csrf_field($session) ?>
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="site-nav__link">Déconnexion</button>
                </form>
            <?php endif; ?>
        </nav>
    </div>
</header>
