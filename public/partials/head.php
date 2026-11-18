<?php

// On refuse un accès direct à ce fichier partiel.
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(403);
    exit;
}

// Le titre peut être fourni par la page ; sinon on met un titre par défaut.
$pageTitle = $pageTitle ?? 'ATLAS — La vie étudiante sans frontières';

// La description aide les moteurs de recherche et les partages.
$pageDescription = $pageDescription ?? 'ATLAS accompagne les étudiants internationaux en France : repères concrets, essentiels tech et accès aux démarches officielles.';

// Le préfixe permet d'adapter les liens depuis /admin/ (ex. « ../ »).
$assetPrefix = $assetPrefix ?? '';

// On précharge les deux polices principales pour éviter un flash de texte.
$fontPrefix = $assetPrefix . 'fonts/';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="description" content="<?= e($pageDescription) ?>">
<meta name="theme-color" content="#07070b">
<title><?= e($pageTitle) ?></title>

<!-- Les polices locales arrivent avant le premier rendu. -->
<link rel="preload" href="<?= e($fontPrefix) ?>fraunces-latin.woff2" as="font" type="font/woff2" crossorigin>
<link rel="preload" href="<?= e($fontPrefix) ?>manrope-latin.woff2" as="font" type="font/woff2" crossorigin>

<!-- Le thème est appliqué avant le CSS pour éviter un flash de couleur. -->
<script src="<?= e(asset_url('scripts/ui/theme-init.js', $assetPrefix)) ?>"></script>

<!-- Feuille de styles principale. -->
<link rel="stylesheet" href="<?= e(asset_url('styles.css', $assetPrefix)) ?>">

<!-- Le préloader et les révélations au scroll sont chargés sans bloquer le rendu. -->
<script type="module" src="<?= e(asset_url('scripts/preloader.js', $assetPrefix)) ?>"></script>
<script type="module" src="<?= e(asset_url('scripts/atlas-motion.js', $assetPrefix)) ?>"></script>
