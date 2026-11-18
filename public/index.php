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
$pageTitle = 'ATLAS — Votre nouvelle vie commence ici';
$pageDescription = 'Connectez-vous à ATLAS pour trouver les essentiels tech et les repères concrets de la vie étudiante en France.';
$assetPrefix = '';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body class="atlas-page atlas-auth">
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

            <nav class="experience__nav" aria-label="Navigation d’accueil">
                <a href="#connexion">Se connecter</a>
                <a href="#arrivee">L’arrivée</a>
                <a href="register.php">Créer un compte</a>
                <a href="admin/login.php">Accès équipe</a>
                <?php require __DIR__ . '/partials/theme-toggle.php'; ?>
            </nav>
        </header>

        <main id="main" class="auth-stage">
            <section class="auth-hero" aria-labelledby="login-title">
                <div class="auth-hero__copy" data-reveal>
                    <p class="eyebrow">Pour étudiants internationaux · France</p>
                    <h1 id="login-title" class="atlas-title">
                        Votre nouvelle vie
                        <em>commence ici.</em>
                    </h1>
                    <p class="atlas-lead">
                        Les essentiels pour équiper votre quotidien, et les repères concrets pour comprendre
                        les démarches d’arrivée en France. Sans bruit. Sans zone d’ombre.
                    </p>

                    <div class="atlas-actions">
                        <a class="btn btn--primary" href="register.php">Créer mon compte</a>
                        <a class="btn btn--ghost" href="#arrivee">Découvrir le guide</a>
                    </div>

                    <ul class="signal-list" aria-label="Thèmes couverts">
                        <li>Visa VLS-TS</li>
                        <li>Logement</li>
                        <li>Santé</li>
                        <li>Banque</li>
                        <li>Campus</li>
                    </ul>
                </div>

                <div class="arrival-visual" data-reveal data-parallax="0.035" aria-label="Carte d’arrivée ATLAS">
                    <div class="arrival-visual__halo" aria-hidden="true"></div>

                    <svg class="arrival-visual__orbit" viewBox="0 0 620 620" fill="none" aria-hidden="true">
                        <circle cx="310" cy="310" r="245" stroke="currentColor" stroke-opacity=".14" stroke-dasharray="2 10"/>
                        <circle cx="310" cy="310" r="176" stroke="currentColor" stroke-opacity=".1"/>
                        <path d="M78 390C190 330 255 395 330 318s140-106 212-39" stroke="currentColor" stroke-opacity=".4" stroke-dasharray="4 12"/>
                        <circle cx="78" cy="390" r="4" fill="currentColor"/>
                        <circle cx="542" cy="279" r="4" fill="currentColor"/>
                    </svg>

                    <article class="pass-card">
                        <div class="pass-card__head">
                            <span>ATLAS / ARRIVAL PASS</span>
                            <span>FR · 2026</span>
                        </div>
                        <div class="pass-card__body">
                            <p class="pass-card__kicker">Premier chapitre</p>
                            <h2>Paris,<br>France.</h2>
                            <span class="pass-card__stamp">VLS-TS</span>
                        </div>
                        <div class="pass-card__foot">
                            <span>Visa</span>
                            <span>Logement</span>
                            <span>Banque</span>
                        </div>
                    </article>

                    <article class="route-card">
                        <p class="route-card__label">Votre parcours</p>
                        <svg viewBox="0 0 240 112" fill="none" aria-hidden="true">
                            <path class="route-card__line" d="M12 84C48 84 54 34 94 34s44 50 82 50 43-38 52-38"/>
                            <circle cx="12" cy="84" r="4"/>
                            <circle cx="94" cy="34" r="4"/>
                            <circle cx="176" cy="84" r="4"/>
                            <circle cx="228" cy="46" r="4"/>
                        </svg>
                        <ol>
                            <li><span>01</span>Comprendre</li>
                            <li><span>02</span>S’installer</li>
                            <li><span>03</span>Vivre</li>
                        </ol>
                    </article>

                    <div class="floating-note floating-note--one">Campus France</div>
                    <div class="floating-note floating-note--two">CROUS · Visale</div>
                    <div class="floating-note floating-note--three">Ameli · CAF</div>
                </div>
            </section>

            <aside class="access-panel" id="connexion" aria-label="Connexion à votre espace">
                <div class="access-panel__top">
                    <span class="pill"><span class="pill__dot" aria-hidden="true"></span> Espace personnel</span>
                    <span class="access-panel__index">01 / 03</span>
                </div>

                <div class="access-card" data-reveal>
                    <header class="access-card__head">
                        <p class="eyebrow">Déjà membre</p>
                        <h2>Bon retour.</h2>
                        <p>Retrouvez votre sélection et vos repères d’arrivée.</p>
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
                            <button type="submit" class="btn btn--primary btn--block">Entrer dans ATLAS</button>
                        </div>

                        <p class="auth-switch">
                            Pas encore de compte ?
                            <a href="register.php">Créer un compte</a>
                        </p>
                    </form>

                    <p class="access-card__note">
                        Un doute sur vos démarches ? Les ressources officielles sont réunies dans le guide.
                    </p>
                </div>

                <p class="access-panel__foot">ATLAS · La vie étudiante sans frontières</p>
            </aside>
        </main>

        <section class="arrival-band" id="arrivee" aria-labelledby="arrival-title">
            <div class="arrival-band__intro" data-reveal>
                <p class="eyebrow">Le premier mois</p>
                <h2 id="arrival-title">Trois repères pour ne pas avancer à l’aveugle.</h2>
            </div>

            <div class="arrival-band__grid">
                <article class="arrival-item" data-reveal>
                    <span class="arrival-item__number">01</span>
                    <h3>Visa & séjour</h3>
                    <p>VLS-TS à valider en ligne, titre de séjour, renouvellement : les étapes officielles, sans rumeur.</p>
                    <a href="https://administration-etrangers-en-france.interieur.gouv.fr/" target="_blank" rel="noopener noreferrer">Administration étrangers en France</a>
                </article>

                <article class="arrival-item" data-reveal>
                    <span class="arrival-item__number">02</span>
                    <h3>Logement & caution</h3>
                    <p>CROUS, résidences privées, Visale : comprendre les garanties et éviter les fausses annonces.</p>
                    <a href="https://www.visale.fr/" target="_blank" rel="noopener noreferrer">Visale</a>
                </article>

                <article class="arrival-item" data-reveal>
                    <span class="arrival-item__number">03</span>
                    <h3>Santé & budget</h3>
                    <p>Sécurité sociale étudiante, Ameli, compte bancaire et aides au logement.</p>
                    <a href="https://www.ameli.fr/" target="_blank" rel="noopener noreferrer">Ameli</a>
                </article>
            </div>

            <p class="arrival-band__note">
                Informations indicatives. Vérifiez toujours les démarches sur les sites officiels.
            </p>
        </section>

        <footer class="atlas-footer">
            <p>ATLAS · Student living</p>
            <nav aria-label="Liens de pied de page">
                <a href="register.php">Créer un compte</a>
                <a href="admin/login.php">Accès équipe</a>
            </nav>
        </footer>
    </div>

    <!-- Scripts : thème, affichage du mot de passe et validation des champs. -->
    <script src="<?= e(asset_url('scripts/ui/theme.js')) ?>" defer></script>
    <script src="<?= e(asset_url('scripts/password-toggle.js')) ?>" defer></script>
    <script src="<?= e(asset_url('scripts/form-validation.js')) ?>" defer></script>

    <!-- Globe décoratif : chargé seulement si le thème est sombre. -->
    <script type="module" src="<?= e(asset_url('scripts/globe-background.js')) ?>"></script>
</body>
</html>
