<?php

// On refuse un accès direct à ce fichier partiel.
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(403);
    exit;
}
?>
<!-- Fenêtre de détail rapide d'un produit. -->
<dialog class="product-dialog" id="quick-view-dialog" aria-labelledby="quick-view-title">
    <div class="product-dialog__panel">
        <!-- Image du produit. -->
        <div class="product-dialog__media">
            <img data-quick-image alt="">
        </div>

        <!-- Informations du produit. -->
        <div class="product-dialog__body">
            <div class="dialog-header">
                <p class="eyebrow" data-quick-category></p>
                <button type="button" class="dialog-close" data-close-dialog aria-label="Fermer">&times;</button>
            </div>

            <h2 class="product-dialog__title" id="quick-view-title" data-quick-name></h2>
            <p class="product-dialog__price" data-quick-price></p>

            <div class="product-dialog__meta">
                <div><span>Stock</span><span data-quick-stock></span></div>
                <div><span>Catégorie</span><span data-quick-category-text></span></div>
                <div><span>Référence</span><span data-quick-id></span></div>
                <div><span>Date d'entrée</span><span data-quick-date></span></div>
            </div>

            <button type="button" class="btn btn--ghost" data-close-dialog>Fermer</button>
        </div>
    </div>
</dialog>
