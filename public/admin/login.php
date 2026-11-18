<?php

// On active les types stricts de PHP.
declare(strict_types=1);

// On charge le démarrage commun.
require_once __DIR__ . '/../../config/bootstrap.php';

// On importe les classes nécessaires.
use App\Application\Services\Auth\AdminLoginHandler;      // Gère la connexion admin.
use App\Application\Services\Shared\Validation;           // Valide les champs.
use App\Infrastructure\Http\NativeSessionStore;           // Session sécurisée.
use App\Infrastructure\Persistence\DAO\NullUserRepository; // Dépôt vide pour lire la session.
use App\Infrastructure\Persistence\DAO\UserRepository;     // Dépôt réel MySQL.
use Db_config\Database;                                    // Connexion à la base.

// On ouvre la session.
$session = new NativeSessionStore();

// On prépare le validateur.
$validation = new Validation();

// Message global à afficher.
$feedbackHtml = '';

// Erreurs par champ.
$fieldErrors = [];

// Valeur du login conservée.
$values = [
    'user_login' => '',
];

// Connexion à fermer à la fin.
$connection = null;

// On protège le traitement.
try {
    // Si le formulaire est envoyé.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // On mémorise le login saisi.
        $values['user_login'] = is_string($_POST['user_login'] ?? null)
            ? $_POST['user_login']
            : '';

        // On vérifie le jeton CSRF.
        if (!csrf_verify($session)) {
            // Code HTTP 419 : session expirée.
            http_response_code(419);

            // Message affiché.
            $feedbackHtml = '<p class="feedback feedback-error" role="alert">Votre session a expiré. Rechargez la page et réessayez.</p>';
        } else {
            // On valide le login et le mot de passe.
            $validationResult = $validation->validateLogin(
                $_POST['user_login'] ?? null,
                $_POST['user_password'] ?? null
            );

            // Si un champ est invalide, on garde les erreurs.
            if (!$validationResult->isValid()) {
                $fieldErrors = $validationResult->getErrors();
            } else {
                // Sinon, on ouvre la base.
                $connection = Database::connect();

                // On crée le gestionnaire de connexion admin.
                $handler = new AdminLoginHandler(
                    new UserRepository($connection),
                    $session
                );

                // On tente la connexion.
                $feedbackHtml = $handler->handle('POST', $validationResult->getLoginRequest());

                // Null signifie que la connexion admin a réussi.
                if ($feedbackHtml === null) {
                    // On redirige vers le tableau de bord admin.
                    header('Location: index.php');
                    exit;
                }
            }
        }
    } else {
        // Si on arrive sur la page sans envoyer de formulaire...
        $handler = new AdminLoginHandler(
            new NullUserRepository(),
            $session
        );

        // On demande si l'utilisateur est déjà admin.
        $feedbackHtml = $handler->handle('GET');

        // Si oui, on l'envoie au tableau de bord.
        if ($feedbackHtml === null) {
            header('Location: index.php');
            exit;
        }
    }
} catch (Exception $e) {
    // Message générique en cas de problème.
    $feedbackHtml = '<p class="feedback feedback-error" role="alert">Connexion à la base de données impossible. Réessayez plus tard.</p>';
} finally {
    // On ferme la connexion si elle existe.
    if (isset($connection) && $connection instanceof mysqli) {
        $connection->close();
    }
}

// On prépare les erreurs de champ.
$userLoginError = $fieldErrors['user_login'] ?? null;
$userPasswordError = $fieldErrors['user_password'] ?? null;

// On prépare la page et ses fichiers communs.
$pageTitle = 'ATLAS Studio — Connexion équipe';
$pageDescription = 'Accès réservé à l’équipe ATLAS : catalogue, utilisateurs et pilotage de la plateforme.';
$assetPrefix = '../';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require __DIR__ . '/../partials/head.php'; ?>
</head>
<body class="atlas-page atlas-admin-login">
    <a class="skip-link" href="#main">Aller au contenu principal</a>
    <?php require __DIR__ . '/../partials/preloader.php'; ?>

    <div class="experience admin-access">
        <header class="experience__top">
            <?php
            $brandHref = '../index.php';
            $brandLabel = 'ATLAS, accueil';
            $brandTagline = 'Studio admin';
            require __DIR__ . '/../partials/brand.php';
            ?>

            <nav class="experience__nav" aria-label="Navigation administration">
                <a href="../products.php">Retour au site</a>
                <?php require __DIR__ . '/../partials/theme-toggle.php'; ?>
            </nav>
        </header>

        <main id="main" class="admin-access__stage">
            <section class="admin-access__intro" data-reveal>
                <p class="eyebrow">ATLAS Studio · Accès réservé</p>
                <h1 class="atlas-title atlas-title--compact">
                    Le contrôle,
                    <em>en toute clarté.</em>
                </h1>
                <p class="atlas-lead">
                    Catalogue, rôles et accès : un espace de pilotage simple pour garder
                    la plateforme nette et rapide.
                </p>

                <div class="console-card">
                    <div class="console-card__head">
                        <span>SYSTEM / READY</span>
                        <span class="console-card__dot" aria-hidden="true"></span>
                    </div>
                    <ul>
                        <li><span>Catalogue</span><strong>Produits & stock</strong></li>
                        <li><span>Utilisateurs</span><strong>Rôles & accès</strong></li>
                        <li><span>Sécurité</span><strong>CSRF · Argon2id</strong></li>
                    </ul>
                </div>

                <p class="admin-access__note">
                    Toute session déjà ouverte est revalidée en base avant l’accès au tableau de bord.
                </p>
            </section>

            <aside class="access-panel" aria-label="Connexion administration">
                <div class="access-panel__top">
                    <span class="pill"><span class="pill__dot" aria-hidden="true"></span> Équipe interne</span>
                    <span class="access-panel__index">ADMIN</span>
                </div>

                <div class="access-card" data-reveal>
                    <header class="access-card__head">
                        <p class="eyebrow">Connexion admin</p>
                        <h2>Reprendre la main.</h2>
                        <p>Identifiants réservés à l’équipe ATLAS.</p>
                    </header>

                    <form method="post" class="login-form login-form--atlas">
                        <?= csrf_field($session) ?>
                        <?= $feedbackHtml ?>

                        <div class="field<?= $userLoginError ? ' field--error' : '' ?>">
                            <input
                                placeholder=" "
                                type="text"
                                id="username"
                                name="user_login"
                                autocomplete="username"
                                maxlength="255"
                                value="<?= htmlspecialchars($values['user_login'], ENT_QUOTES, 'UTF-8') ?>"
                                <?php if ($userLoginError): ?>
                                    aria-invalid="true"
                                    aria-describedby="user_login-error"
                                <?php endif; ?>
                                required
                            />
                            <label for="username">Nom d’utilisateur</label>
                            <?php if ($userLoginError): ?>
                                <p class="field__error" id="user_login-error" role="alert">
                                    <?= htmlspecialchars($userLoginError, ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="field field--password<?= $userPasswordError ? ' field--error' : '' ?>">
                            <input
                                placeholder=" "
                                type="password"
                                id="password"
                                name="user_password"
                                autocomplete="current-password"
                                maxlength="72"
                                <?php if ($userPasswordError): ?>
                                    aria-invalid="true"
                                    aria-describedby="user_password-error"
                                <?php endif; ?>
                                required
                            />
                            <label for="password">Mot de passe</label>

                            <button type="button" class="password-toggle" data-password-target="password" aria-label="Afficher le mot de passe" aria-controls="password" aria-pressed="false">
                                <svg class="password-toggle__icon password-toggle__icon--show" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" hidden>
                                    <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12Z"/>
                                    <circle cx="12" cy="12" r="3"/>
                                </svg>
                                <svg class="password-toggle__icon password-toggle__icon--hide" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                    <path d="M3 3l18 18"/>
                                    <path d="M10.6 10.6a3 3 0 0 0 4.2 4.2"/>
                                    <path d="M9.9 5.1A10.5 10.5 0 0 1 12 5c6.5 0 10 7 10 7a17.7 17.7 0 0 1-2.2 3.2"/>
                                    <path d="M6.1 6.1C3.8 7.8 2 12 2 12s3.5 7 10 7a10.4 10.4 0 0 0 4.4-1"/>
                                </svg>
                            </button>
                            <?php if ($userPasswordError): ?>
                                <p class="field__error" id="user_password-error" role="alert">
                                    <?= htmlspecialchars($userPasswordError, ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="login-form__actions">
                            <button type="submit" class="btn btn--primary btn--block">Ouvrir le studio</button>
                        </div>

                        <p class="auth-switch">
                            <a href="../index.php">Retour à l’espace étudiant</a>
                        </p>
                    </form>

                    <p class="access-card__note">
                        Sessions revalidées, rôle vérifié en base, déconnexion protégée par jeton.
                    </p>
                </div>

                <p class="access-panel__foot">ATLAS Studio · Accès équipe</p>
            </aside>
        </main>

        <footer class="atlas-footer">
            <p>ATLAS · Studio admin</p>
            <nav aria-label="Liens de pied de page">
                <a href="../index.php">Espace étudiant</a>
                <a href="../products.php">Catalogue</a>
            </nav>
        </footer>
    </div>

    <!-- Scripts. -->
    <script src="<?= e(asset_url('scripts/ui/theme.js', '../')) ?>" defer></script>
    <script src="<?= e(asset_url('scripts/password-toggle.js', '../')) ?>" defer></script>
    <script src="<?= e(asset_url('scripts/form-validation.js', '../')) ?>" defer></script>
</body>
</html>
