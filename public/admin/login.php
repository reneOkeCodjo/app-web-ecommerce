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
$pageTitle = 'Administration — Site de Vente';
$assetPrefix = '../';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require __DIR__ . '/../partials/head.php'; ?>
</head>
<body>
    <a class="skip-link" href="#main">Aller au contenu principal</a>

    <main id="main" class="auth-page">
        <section class="auth-split" aria-labelledby="admin-login-title">
            <!-- Panneau de marque admin. -->
            <aside class="auth-brand">
                <a class="brand" href="../index.php" aria-label="Site de Vente, accueil">
                    <span class="brand__mark" aria-hidden="true">SV</span>
                    <span class="brand__name">Site de Vente</span>
                </a>

                <div class="auth-brand__content">
                    <p class="eyebrow">Back-office</p>
                    <h2 class="auth-brand__title">Le contrôle,<br>en toute clarté.</h2>
                    <p class="auth-brand__lead">
                        Gérez le catalogue, les prix, les promotions et les images
                        depuis un espace réservé aux administrateurs.
                    </p>
                    <ul class="auth-brand__proof">
                        <li>Accès strictement réservé aux administrateurs</li>
                        <li>Modifications en temps réel</li>
                        <li>Historique des actions protégé</li>
                    </ul>
                </div>

                <p class="auth-brand__foot">© 2026 Site de Vente</p>
            </aside>

            <!-- Panneau du formulaire. -->
            <div class="auth-panel">
                <div class="auth-panel__top">
                    <?php require __DIR__ . '/../partials/theme-toggle.php'; ?>
                </div>

                <div class="auth-card">
                    <header class="auth-header">
                        <p class="eyebrow">Espace administrateur</p>
                        <h1 id="admin-login-title" class="auth-title">Connexion admin.</h1>
                        <p class="auth-subtitle">Identifiants réservés à l'équipe interne.</p>
                    </header>

                    <form method="post" class="login-form">
                        <!-- Jeton de sécurité. -->
                        <?= csrf_field($session) ?>

                        <!-- Message global. -->
                        <?= $feedbackHtml ?>

                        <!-- Champ nom d'utilisateur. -->
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

                        <!-- Champ mot de passe. -->
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

                        <!-- Bouton d'envoi. -->
                        <div class="login-form__actions">
                            <button type="submit">Se connecter</button>
                        </div>

                        <p class="auth-switch">
                            <a href="../index.php">Retour à l'espace client</a>
                        </p>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <!-- Scripts. -->
    <script src="<?= e(asset_url('scripts/ui/theme.js', '../')) ?>" defer></script>
    <script src="<?= e(asset_url('scripts/password-toggle.js', '../')) ?>" defer></script>
    <script src="<?= e(asset_url('scripts/form-validation.js', '../')) ?>" defer></script>
</body>
</html>
