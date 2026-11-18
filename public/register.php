<?php

// On active les types stricts de PHP.
declare(strict_types=1);

// On charge le démarrage commun (autoload, .env, sécurité, erreurs).
require_once __DIR__ . '/../config/bootstrap.php';

// On importe les classes utilisées par la page.
use App\Application\Services\Auth\RegisterHandler;        // Gère l'inscription.
use App\Application\Services\Shared\Validation;           // Valide les champs.
use App\Infrastructure\Http\NativeSessionStore;           // Session sécurisée.
use App\Infrastructure\Persistence\DAO\NullUserRepository; // Dépôt vide pour la lecture de session.
use App\Infrastructure\Persistence\DAO\UserRepository;     // Dépôt réel MySQL.
use Db_config\Database;                                    // Connexion à la base.

// On ouvre la session.
$session = new NativeSessionStore();

// On prépare le validateur.
$validation = new Validation();

// Message global (erreur ou succès).
$feedbackHtml = '';

// Erreurs par champ.
$fieldErrors = [];

// Valeurs conservées pour ne pas faire retaper l'utilisateur.
$values = [
    'user_login' => '',
    'user_mail' => '',
];

// Connexion à la base, fermée à la fin.
$connection = null;

// On protège le traitement contre les erreurs.
try {
    // Si le formulaire a été envoyé...
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // On mémorise le nom d'utilisateur saisi.
        $values['user_login'] = is_string($_POST['user_login'] ?? null)
            ? $_POST['user_login']
            : '';

        // On mémorise l'email saisi.
        $values['user_mail'] = is_string($_POST['user_mail'] ?? null)
            ? $_POST['user_mail']
            : '';

        // On vérifie d'abord le jeton CSRF.
        if (!csrf_verify($session)) {
            // Code HTTP 419 : la session a expiré.
            http_response_code(419);

            // Message affiché à l'utilisateur.
            $feedbackHtml = '<p class="feedback feedback-error" role="alert">Votre session a expiré. Rechargez la page et réessayez.</p>';
        } else {
            // On valide le nom, l'email, le mot de passe et sa confirmation.
            $validationResult = $validation->validateRegister(
                $_POST['user_login'] ?? null,
                $_POST['user_mail'] ?? null,
                $_POST['user_password'] ?? null,
                $_POST['password_confirm'] ?? null
            );

            // Si un champ est invalide, on garde les erreurs.
            if (!$validationResult->isValid()) {
                $fieldErrors = $validationResult->getErrors();
            } else {
                // Sinon, on ouvre la base de données.
                $connection = Database::connect();

                // On crée le gestionnaire d'inscription.
                $handler = new RegisterHandler(
                    new UserRepository($connection),
                    $session
                );

                // On tente l'inscription.
                $feedbackHtml = $handler->handle('POST', $validationResult->getRegisterRequest());

                // Null signifie que l'inscription a réussi.
                if ($feedbackHtml === null) {
                    // On redirige vers le catalogue.
                    header('Location: products.php');
                    exit;
                }
            }
        }
    } else {
        // Si on arrive simplement sur la page...
        $handler = new RegisterHandler(
            new NullUserRepository(),
            $session
        );

        // On demande si l'utilisateur est déjà connecté.
        $feedbackHtml = $handler->handle('GET');

        // Si oui, on l'envoie au catalogue.
        if ($feedbackHtml === null) {
            header('Location: products.php');
            exit;
        }
    }
} catch (Exception $e) {
    // En cas de problème technique, message générique.
    $feedbackHtml = '<p class="feedback feedback-error" role="alert">Connexion à la base de données impossible. Réessayez plus tard.</p>';
} finally {
    // On ferme la connexion si elle existe.
    if (isset($connection) && $connection instanceof mysqli) {
        $connection->close();
    }
}

// On prépare les erreurs de chaque champ.
$userLoginError = $fieldErrors['user_login'] ?? null;
$userMailError = $fieldErrors['user_mail'] ?? null;
$userPasswordError = $fieldErrors['user_password'] ?? null;
$passwordConfirmError = $fieldErrors['password_confirm'] ?? null;

// On prépare la page et ses fichiers communs.
$pageTitle = 'Créer un compte — ATLAS';
$pageDescription = 'Créez votre compte ATLAS et retrouvez les essentiels tech et les repères concrets de la vie étudiante en France.';
$assetPrefix = '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body class="atlas-page atlas-auth atlas-register">
    <a class="skip-link" href="#main">Aller au contenu principal</a>
    <?php require __DIR__ . '/partials/preloader.php'; ?>

    <div class="experience">
        <header class="experience__top">
            <?php
            $brandHref = 'index.php';
            $brandLabel = 'ATLAS, accueil';
            $brandTagline = 'Student living';
            require __DIR__ . '/partials/brand.php';
            ?>

            <nav class="experience__nav" aria-label="Navigation d’inscription">
                <a href="index.php">Se connecter</a>
                <a href="index.php#arrivee">L’arrivée</a>
                <?php require __DIR__ . '/partials/theme-toggle.php'; ?>
            </nav>
        </header>

        <main id="main" class="auth-stage auth-stage--register">
            <section class="register-visual" aria-labelledby="register-title" data-reveal>
                <div class="register-visual__copy">
                    <p class="eyebrow">Première étape</p>
                    <h1 id="register-title" class="atlas-title atlas-title--compact">
                        Ouvrez votre
                        <em>atlas.</em>
                    </h1>
                    <p class="atlas-lead">
                        Un compte, un accès : les essentiels pour votre quotidien et les repères
                        pour comprendre l’arrivée en France.
                    </p>
                </div>

                <article class="checklist-card">
                    <div class="checklist-card__head">
                        <span>ARRIVAL CHECKLIST</span>
                        <span>01—04</span>
                    </div>
                    <ol>
                        <li class="is-done"><span>01</span><div><strong>Compte ATLAS</strong><small>Vous y êtes presque.</small></div></li>
                        <li><span>02</span><div><strong>Visa & séjour</strong><small>Les démarches officielles.</small></div></li>
                        <li><span>03</span><div><strong>Logement</strong><small>CROUS, Visale, caution.</small></div></li>
                        <li><span>04</span><div><strong>Santé & banque</strong><small>Ameli, CAF, compte français.</small></div></li>
                    </ol>
                    <div class="checklist-card__foot">
                        <span class="pass-card__stamp pass-card__stamp--small">READY</span>
                        <p>La suite se construit<br>étape par étape.</p>
                    </div>
                </article>

                <p class="register-visual__note">ATLAS · France · 2026</p>
            </section>

            <aside class="access-panel access-panel--register" aria-label="Création de compte">
                <div class="access-panel__top">
                    <span class="pill"><span class="pill__dot" aria-hidden="true"></span> Nouveau compte</span>
                    <span class="access-panel__index">01 / 04</span>
                </div>

                <div class="access-card" data-reveal>
                    <header class="access-card__head">
                        <p class="eyebrow">Bienvenue</p>
                        <h2>Créer votre accès.</h2>
                        <p>Quelques informations, puis votre espace est prêt.</p>
                    </header>

                    <form method="post" class="login-form login-form--atlas login-form--register">
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

                        <div class="field<?= $userMailError ? ' field--error' : '' ?>">
                            <input
                                placeholder=" "
                                type="email"
                                id="email"
                                name="user_mail"
                                autocomplete="email"
                                maxlength="255"
                                pattern="^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$"
                                data-error-message="Veuillez saisir une adresse email valide."
                                value="<?= htmlspecialchars($values['user_mail'], ENT_QUOTES, 'UTF-8') ?>"
                                <?php if ($userMailError): ?>
                                    aria-invalid="true"
                                    aria-describedby="user_mail-error"
                                <?php endif; ?>
                                required
                            />
                            <label for="email">Adresse email</label>
                            <?php if ($userMailError): ?>
                                <p class="field__error" id="user_mail-error" role="alert">
                                    <?= htmlspecialchars($userMailError, ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="field field--password<?= $userPasswordError ? ' field--error' : '' ?>">
                            <input
                                placeholder=" "
                                type="password"
                                id="password"
                                name="user_password"
                                autocomplete="new-password"
                                pattern="^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$"
                                data-error-message="8 caractères minimum, avec majuscule, minuscule, chiffre et caractère spécial."
                                aria-describedby="password-hint<?= $userPasswordError ? ' user_password-error' : '' ?>"
                                minlength="8"
                                maxlength="72"
                                <?php if ($userPasswordError): ?>
                                    aria-invalid="true"
                                <?php endif; ?>
                                required
                            />
                            <label for="password">Mot de passe</label>

                            <span class="tooltip">
                                <button type="button" class="tooltip__trigger" aria-label="Règles du mot de passe" aria-describedby="password-hint">
                                    <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false">
                                        <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/>
                                        <path d="M10 9v5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                        <circle cx="10" cy="6.25" r="0.9" fill="currentColor" aria-hidden="true"/>
                                    </svg>
                                </button>
                                <span class="tooltip__content" id="password-hint" role="tooltip">
                                    <ul>
                                        <li>Au moins 8 caractères</li>
                                        <li>Au plus 72 caractères</li>
                                        <li>Une majuscule</li>
                                        <li>Une minuscule</li>
                                        <li>Un chiffre</li>
                                        <li>Un caractère spécial</li>
                                    </ul>
                                </span>
                            </span>

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

                        <div class="field field--password<?= $passwordConfirmError ? ' field--error' : '' ?>">
                            <input
                                placeholder=" "
                                type="password"
                                id="password_confirm"
                                name="password_confirm"
                                autocomplete="new-password"
                                maxlength="72"
                                <?php if ($passwordConfirmError): ?>
                                    aria-invalid="true"
                                    aria-describedby="password_confirm-error"
                                <?php endif; ?>
                                required
                            />
                            <label for="password_confirm">Confirmer le mot de passe</label>

                            <button type="button" class="password-toggle" data-password-target="password_confirm" aria-label="Afficher le mot de passe" aria-controls="password_confirm" aria-pressed="false">
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
                            <?php if ($passwordConfirmError): ?>
                                <p class="field__error" id="password_confirm-error" role="alert">
                                    <?= htmlspecialchars($passwordConfirmError, ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <div class="login-form__actions">
                            <button type="submit" class="btn btn--primary btn--block">Créer mon compte</button>
                        </div>

                        <p class="auth-switch">
                            Déjà un compte ?
                            <a href="index.php">Se connecter</a>
                        </p>
                    </form>

                    <p class="access-card__note">
                        En créant un compte, vous acceptez de recevoir uniquement les informations utiles à votre arrivée.
                    </p>
                </div>

                <p class="access-panel__foot">ATLAS · La vie étudiante sans frontières</p>
            </aside>
        </main>

        <footer class="atlas-footer">
            <p>ATLAS · Student living</p>
            <nav aria-label="Liens de pied de page">
                <a href="index.php">Se connecter</a>
                <a href="index.php#arrivee">L’arrivée</a>
            </nav>
        </footer>
    </div>

    <!-- Scripts de la page. -->
    <script src="<?= e(asset_url('scripts/ui/theme.js')) ?>" defer></script>
    <script src="<?= e(asset_url('scripts/password-toggle.js')) ?>" defer></script>
    <script src="<?= e(asset_url('scripts/form-validation.js')) ?>" defer></script>

    <!-- Globe décoratif : chargé seulement si le thème est sombre. -->
    <script type="module" src="<?= e(asset_url('scripts/globe-background.js')) ?>"></script>
</body>
</html>
