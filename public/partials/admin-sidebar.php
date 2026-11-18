<?php

// On refuse un accès direct à ce fichier partiel.
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(403);
    exit;
}

// La page indique quel lien doit être marqué comme actif.
$activeAdmin = $activeAdmin ?? '';

// La marque renvoie vers le tableau de bord admin.
$brandHref = 'index.php';
$brandLabel = 'ATLAS, administration';
$brandTagline = 'Studio admin';
?>
<aside class="admin-sidebar">
    <div class="admin-sidebar__head">
        <?php require __DIR__ . '/brand.php'; ?>
    </div>

    <p class="admin-sidebar__label">Pilotage</p>

    <nav class="admin-nav" aria-label="Navigation administration">
        <a class="admin-nav__link" href="index.php"<?= $activeAdmin === 'dashboard' ? ' aria-current="page"' : '' ?>>
            <span class="admin-nav__icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12h7V3H3zM14 21h7v-9h-7zM14 3h7v6h-7zM3 21h7v-6H3z"/></svg>
            </span>
            Tableau de bord
        </a>

        <a class="admin-nav__link" href="products.php"<?= $activeAdmin === 'products' ? ' aria-current="page"' : '' ?>>
            <span class="admin-nav__icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5M12 22V12"/></svg>
            </span>
            Produits
        </a>

        <a class="admin-nav__link" href="users.php"<?= $activeAdmin === 'users' ? ' aria-current="page"' : '' ?>>
            <span class="admin-nav__icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/></svg>
            </span>
            Utilisateurs
        </a>
    </nav>

    <div class="admin-sidebar__foot">
        <a class="admin-nav__link admin-nav__link--site" href="../products.php">
            <span class="admin-nav__icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M15 3h6v6M10 14 21 3M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/></svg>
            </span>
            Voir le site
        </a>

        <!-- Déconnexion en bas du menu, protégée par CSRF. -->
        <form method="post" action="index.php" class="admin-logout">
            <?= csrf_field($session) ?>
            <input type="hidden" name="action" value="logout">
            <button type="submit" class="btn btn--ghost btn--block">Déconnexion</button>
        </form>
    </div>
</aside>
