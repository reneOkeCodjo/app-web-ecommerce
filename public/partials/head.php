<?php

// On refuse un accès direct à ce fichier partiel.
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(403);
    exit;
}

// Le titre peut être fourni par la page ; sinon on met un titre par défaut.
$pageTitle = $pageTitle ?? 'Site de Vente';

// Le préfixe permet d'adapter les liens depuis /admin/ (ex. « ../ »).
$assetPrefix = $assetPrefix ?? '';
?>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= e($pageTitle) ?></title>

<!-- Le thème est appliqué avant le CSS pour éviter un flash de couleur. -->
<script src="<?= e(asset_url('scripts/ui/theme-init.js', $assetPrefix)) ?>"></script>

<!-- Feuille de styles principale. -->
<link rel="stylesheet" href="<?= e(asset_url('styles.css', $assetPrefix)) ?>">
