<?php

// On refuse un accès direct à ce fichier partiel.
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(403);
    exit;
}

// Chaque page peut choisir la destination et le libellé de la marque.
$brandHref = $brandHref ?? 'index.php';
$brandLabel = $brandLabel ?? 'ATLAS, accueil';
$brandTagline = $brandTagline ?? 'Student living';
?>
<a class="brand brand--atlas" href="<?= e($brandHref) ?>" aria-label="<?= e($brandLabel) ?>">
    <span class="brand__mark" aria-hidden="true">
        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round">
            <path d="M4 20 12 4l8 16"/>
            <path d="M7.2 14h9.6"/>
        </svg>
    </span>
    <span class="brand__word">
        <span class="brand__name">ATLAS</span>
        <span class="brand__tagline"><?= e($brandTagline) ?></span>
    </span>
</a>
