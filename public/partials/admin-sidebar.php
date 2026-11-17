<?php

// On refuse un accès direct à ce fichier partiel.
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(403);
    exit;
}

// La page indique quel lien doit être marqué comme actif.
$activeAdmin = $activeAdmin ?? '';
?>
<aside class="admin-sidebar">
    <!-- Marque de l'administration. -->
    <a class="brand" href="index.php" aria-label="Site de Vente, administration">
        <span class="brand__mark" aria-hidden="true">SV</span>
        <span class="brand__name">Site de Vente</span>
    </a>

    <!-- Navigation de l'administration. -->
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

        <a class="admin-nav__link" href="../products.php">
            <span class="admin-nav__icon" aria-hidden="true">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9h18M9 21V9M4 4h16a1 1 0 0 1 1 1v14a1 1 0 0 1-1 1H4a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1Z"/></svg>
            </span>
            Voir le catalogue
        </a>
    </nav>

    <!-- Déconnexion en bas du menu, protégée par CSRF. -->
    <form method="post" class="admin-logout">
        <?= csrf_field($session) ?>
        <input type="hidden" name="action" value="logout">
        <button type="submit" class="btn btn--ghost btn--block">Déconnexion</button>
    </form>
</aside>
