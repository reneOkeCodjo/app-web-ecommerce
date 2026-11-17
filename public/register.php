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
$pageTitle = 'Inscription — Site de Vente';
$assetPrefix = '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body>
    <!-- Lien d'évitement clavier. -->
    <a class="skip-link" href="#main">Aller au contenu principal</a>

    <main id="main" class="auth-page">
        <!-- Carte en deux colonnes. -->
        <section class="auth-split" aria-labelledby="register-title">
            <!-- Panneau de marque. -->
            <aside class="auth-brand">
                <a class="brand" href="index.php" aria-label="Site de Vente, accueil">
                    <span class="brand__mark" aria-hidden="true">SV</span>
                    <span class="brand__name">Site de Vente</span>
                </a>

                <div class="auth-brand__content">
                    <p class="eyebrow">Nouvelle maison</p>
                    <h2 class="auth-brand__title">Entrez dans<br>la sélection.</h2>
                    <p class="auth-brand__lead">
                        Créez votre compte en quelques secondes et retrouvez
                        une sélection de produits choisis avec exigence.
                    </p>
                    <ul class="auth-brand__proof">
                        <li>Inscription gratuite et immédiate</li>
                        <li>Vos données restent confidentielles</li>
                        <li>Accès à toutes les nouveautés</li>
                    </ul>
                </div>

                <p class="auth-brand__foot">© 2026 Site de Vente</p>
            </aside>

            <!-- Panneau du formulaire. -->
            <div class="auth-panel">
                <div class="auth-panel__top">
                    <?php require __DIR__ . '/partials/theme-toggle.php'; ?>
                </div>

                <div class="auth-card auth-card--register">
                    <header class="auth-header">
                        <p class="eyebrow">Créer un compte</p>
                        <h1 id="register-title" class="auth-title">Bienvenue.</h1>
                        <p class="auth-subtitle">Quelques informations et c'est parti.</p>
                    </header>

                    <form method="post" class="login-form">
                        <!-- Jeton de sécurité. -->
                        <?= csrf_field($session) ?>

                        <!-- Message global. -->
                        <?= $feedbackHtml ?>

                        <!-- Nom d'utilisateur. -->
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
                            <label for="username">Nom d'utilisateur</label>
                            <?php if ($userLoginError): ?>
                                <p class="field__error" id="user_login-error" role="alert">
                                    <?= htmlspecialchars($userLoginError, ENT_QUOTES, 'UTF-8') ?>
                                </p>
                            <?php endif; ?>
                        </div>

                        <!-- Adresse email. -->
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

                        <!-- Mot de passe avec info-bulle. -->
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

                            <!-- Bouton d'aide sur les règles du mot de passe. -->
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

                            <!-- Bouton afficher / masquer. -->
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

                        <!-- Confirmation du mot de passe. -->
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

                            <!-- Bouton afficher / masquer la confirmation. -->
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

                        <!-- Bouton d'inscription. -->
                        <div class="login-form__actions">
                            <button type="submit">Créer mon compte</button>
                        </div>

                        <p class="auth-switch">
                            Déjà un compte ?
                            <a href="index.php">Se connecter</a>
                        </p>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <!-- Scripts de la page. -->
    <script src="<?= e(asset_url('scripts/ui/theme.js')) ?>" defer></script>
    <script src="<?= e(asset_url('scripts/password-toggle.js')) ?>" defer></script>
    <script src="<?= e(asset_url('scripts/form-validation.js')) ?>" defer></script>
</body>
</html>
