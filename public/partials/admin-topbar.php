<?php

// On refuse un accès direct à ce fichier partiel.
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(403);
    exit;
}

// Libellé affiché dans le fil d'Ariane.
$breadcrumbLabel = $breadcrumbLabel ?? 'Administration';

// Identifiant de l'administrateur connecté, s'il existe.
$adminUserId = $adminUserId ?? null;
?>
<header class="admin-topbar">
    <!-- Fil d'Ariane. -->
    <nav class="breadcrumb" aria-label="Fil d'Ariane">
        <a href="index.php">Administration</a>
        <span class="breadcrumb__sep">/</span>
        <span><?= e($breadcrumbLabel) ?></span>
    </nav>

    <div class="actions">
        <?php require __DIR__ . '/theme-toggle.php'; ?>

        <!-- Petite pastille qui rappelle l'utilisateur connecté. -->
        <span class="admin-user">
            <span class="admin-user__avatar" aria-hidden="true">A</span>
            <?= $adminUserId !== null ? 'Admin #' . e((string) $adminUserId) : 'Admin' ?>
        </span>
    </div>
</header>
