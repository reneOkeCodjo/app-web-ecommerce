<?php

// On refuse un accès direct à ce fichier partiel.
if (!defined('APP_BOOTSTRAPPED')) {
    http_response_code(403);
    exit;
}

// La page fournit la liste des messages à afficher.
$flashMessages = $flashMessages ?? [];

// S'il n'y a rien à afficher, on n'ajoute rien au HTML.
if ($flashMessages === []) {
    return;
}
?>
<!-- Zone des messages temporaires (succès, erreur, information). -->
<div class="toast-stack" role="status" aria-live="polite">
    <?php foreach ($flashMessages as $flash): ?>
        <?php
        $type = is_string($flash['type'] ?? null) ? $flash['type'] : 'info';
        $message = is_string($flash['message'] ?? null) ? $flash['message'] : '';
        if ($message === '') {
            continue;
        }
        ?>
        <div class="toast toast--<?= e($type) ?>" data-toast>
            <span class="toast__message"><?= e($message) ?></span>
            <button type="button" class="toast__close" data-toast-close aria-label="Fermer le message">&times;</button>
        </div>
    <?php endforeach; ?>
</div>
