<?php

// On active les types stricts de PHP.
declare(strict_types=1);

// On charge le fichier de démarrage commun (autoload, .env, sécurité, erreurs).
require_once __DIR__ . '/../config/bootstrap.php';

// On importe les classes nécessaires au fonctionnement de la page.
use App\Application\Services\Auth\LoginHandler;   // Gère la connexion.
use App\Application\Services\Shared\Validation;    // Valide les champs du formulaire.
use App\Infrastructure\Http\NativeSessionStore;    // Session PHP sécurisée.
use App\Infrastructure\Persistence\DAO\NullUserRepository; // Dépôt vide pour la simple lecture de session.
use App\Infrastructure\Persistence\DAO\UserRepository;     // Dépôt réel MySQL.
use Db_config\Database;                             // Connexion à la base de données.

// On ouvre la session de l'utilisateur.
$session = new NativeSessionStore();

// On prépare le service de validation.
$validation = new Validation();

// Ce texte contiendra un éventuel message d'erreur affiché en haut du formulaire.
$feedbackHtml = '';

// Ce tableau contiendra les erreurs champ par champ.
$fieldErrors = [];

// On garde la valeur du login saisie pour ne pas la faire retaper à l'utilisateur.
$values = [
    'user_login' => '',
];

// On garde la connexion à la base pour la fermer proprement à la fin.
$connection = null;

// On protège tout le traitement contre les erreurs imprévues.
try {
    // On vérifie si l'utilisateur a envoyé le formulaire.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // On mémorise le login saisi s'il s'agit bien d'un texte.
        $values['user_login'] = is_string($_POST['user_login'] ?? null)
            ? $_POST['user_login']
            : '';

        // On vérifie le jeton CSRF avant tout traitement.
        if (!csrf_verify($session)) {
            // On répond avec le code HTTP 419 (session expirée).
            http_response_code(419);

            // On prépare un message d'erreur visible.
            $feedbackHtml = '<p class="feedback feedback-error" role="alert">Votre session a expiré. Rechargez la page et réessayez.</p>';
        } else {
            // On valide les champs login et mot de passe.
            $validationResult = $validation->validateLogin(
                $_POST['user_login'] ?? null,
                $_POST['user_password'] ?? null
            );

            // Si un champ est invalide...
            if (!$validationResult->isValid()) {
                // ...on récupère les messages d'erreur par champ.
                $fieldErrors = $validationResult->getErrors();
            } else {
                // Sinon, on ouvre la connexion à la base de données.
                $connection = Database::connect();

                // On crée le gestionnaire de connexion.
                $handler = new LoginHandler(
                    new UserRepository($connection),
                    $session
                );

                // On tente la connexion.
                $feedbackHtml = $handler->handle('POST', $validationResult->getLoginRequest());

                // Si le gestionnaire renvoie null, la connexion a réussi.
                if ($feedbackHtml === null) {
                    // On redirige vers le catalogue.
                    header('Location: products.php');
                    exit;
                }
            }
        }
    } else {
        // Si l'utilisateur arrive simplement sur la page (méthode GET)...
        $handler = new LoginHandler(
            new NullUserRepository(),
            $session
        );

        // On demande au gestionnaire s'il est déjà connecté.
        $feedbackHtml = $handler->handle('GET');

        // S'il est déjà connecté, on l'envoie directement au catalogue.
        if ($feedbackHtml === null) {
            header('Location: products.php');
            exit;
        }
    }
} catch (Exception $e) {
    // En cas d'échec technique (base de données, etc.), on affiche un message générique.
    $feedbackHtml = '<p class="feedback feedback-error" role="alert">Connexion à la base de données impossible. Réessayez plus tard.</p>';
} finally {
    // On ferme la connexion si elle a été ouverte.
    if (isset($connection) && $connection instanceof mysqli) {
        $connection->close();
    }
}

// On prépare les messages d'erreur de chaque champ.
$userLoginError = $fieldErrors['user_login'] ?? null;
$userPasswordError = $fieldErrors['user_password'] ?? null;

// On prépare la page et ses fichiers communs.
$pageTitle = 'Connexion — Site de Vente';
$assetPrefix = '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body>
    <!-- Lien d'évitement pour les utilisateurs au clavier. -->
    <a class="skip-link" href="#main">Aller au contenu principal</a>

    <main id="main" class="auth-page">
        <!-- Carte en deux colonnes : univers de marque à gauche, formulaire à droite. -->
        <section class="auth-split" aria-labelledby="login-title">
            <!-- Colonne de gauche : présentation de la marque. -->
            <aside class="auth-brand">
                <!-- Marque cliquable qui ramène à l'accueil. -->
                <a class="brand" href="index.php" aria-label="Site de Vente, accueil">
                    <span class="brand__mark" aria-hidden="true">SV</span>
                    <span class="brand__name">Site de Vente</span>
                </a>

                <!-- Texte éditorial au centre. -->
                <div class="auth-brand__content">
                    <p class="eyebrow">Commerce curaté</p>
                    <h2 class="auth-brand__title">L'essentiel,<br>sans compromis.</h2>
                    <p class="auth-brand__lead">
                        Une sélection resserrée, des prix justes et une expérience
                        pensée pour durer. Retrouvez vos produits favoris en quelques secondes.
                    </p>
                    <!-- Trois arguments de confiance. -->
                    <ul class="auth-brand__proof">
                        <li>Sélection vérifiée par nos équipes</li>
                        <li>Paiement et données protégés</li>
                        <li>Livraison suivie partout en France</li>
                    </ul>
                </div>

                <!-- Pied du panneau de marque. -->
                <p class="auth-brand__foot">© 2026 Site de Vente</p>
            </aside>

            <!-- Colonne de droite : formulaire de connexion. -->
            <div class="auth-panel">
                <!-- Barre supérieure avec le bouton de thème. -->
                <div class="auth-panel__top">
                    <?php require __DIR__ . '/partials/theme-toggle.php'; ?>
                </div>

                <!-- Carte du formulaire. -->
                <div class="auth-card">
                    <!-- En-tête du formulaire. -->
                    <header class="auth-header">
                        <p class="eyebrow">Espace client</p>
                        <h1 id="login-title" class="auth-title">Bon retour.</h1>
                        <p class="auth-subtitle">Connectez-vous pour accéder à votre sélection.</p>
                    </header>

                    <!-- Formulaire de connexion. -->
                    <form method="post" class="login-form">
                        <!-- Jeton de sécurité invisible. -->
                        <?= csrf_field($session) ?>

                        <!-- Message d'erreur global, s'il y en a un. -->
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

                            <!-- Bouton pour afficher ou masquer le mot de passe. -->
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

                        <!-- Lien vers l'inscription. -->
                        <p class="auth-switch">
                            Pas encore de compte ?
                            <a href="register.php">Créer un compte</a>
                        </p>
                    </form>
                </div>
            </div>
        </section>
    </main>

    <!-- Scripts : thème, affichage du mot de passe et validation des champs. -->
    <script src="<?= e(asset_url('scripts/ui/theme.js')) ?>" defer></script>
    <script src="<?= e(asset_url('scripts/password-toggle.js')) ?>" defer></script>
    <script src="<?= e(asset_url('scripts/form-validation.js')) ?>" defer></script>
</body>
</html>
