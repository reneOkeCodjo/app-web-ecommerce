<?php

// On refuse un accès direct à ce fichier partiel.
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(403);
    exit;
}

// Libellé affiché dans le fil d'Ariane.
$breadcrumbLabel = $breadcrumbLabel ?? 'Administration';

// Identifiant et nom de l'administrateur connecté.
$adminUserId = $adminUserId ?? null;
$adminUsername = $adminUsername ?? 'Admin';

// Initiale affichée dans la pastille utilisateur.
$adminInitial = mb_strtoupper(mb_substr($adminUsername, 0, 1));
?>
<header class="admin-topbar">
    <!-- Fil d'Ariane. -->
    <nav class="breadcrumb" aria-label="Fil d'Ariane">
        <a href="index.php">Administration</a>
        <span class="breadcrumb__sep">/</span>
        <span><?= e($breadcrumbLabel) ?></span>
    </nav>

    <div class="actions">
        <a class="btn btn--soft btn--sm admin-topbar__site" href="../products.php">
            Retour au site
        </a>

        <?php require __DIR__ . '/theme-toggle.php'; ?>

        <!-- Petite pastille qui rappelle l'utilisateur connecté. -->
        <span class="admin-user" title="Administrateur connecté">
            <span class="admin-user__avatar" aria-hidden="true"><?= e($adminInitial) ?></span>
            <span class="admin-user__copy">
                <strong><?= e($adminUsername) ?></strong>
                <span>Administrateur</span>
            </span>
        </span>
    </div>
</header>
