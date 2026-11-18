<?php

declare(strict_types=1);

require_once __DIR__ . '/../config/bootstrap.php';

use App\Application\Services\Auth\Auth;
use App\Application\Validation\Auth\ConfirmPasswordValidator;
use App\Application\Validation\Auth\EmailValidator;
use App\Application\Validation\Auth\PasswordValidator;
use App\Application\Validation\Auth\UsernameValidator;
use App\Infrastructure\Http\Csrf;
use App\Infrastructure\Http\NativeSessionStore;
use App\Infrastructure\Persistence\DAO\NullUserRepository;
use App\Infrastructure\Persistence\DAO\UserRepository;
use Db_config\Database;

// On démarre la session et on vérifie l'authentification.
$session = new NativeSessionStore();
$auth = new Auth(new NullUserRepository(), $session);

// Un visiteur non connecté retourne à la page de connexion.
if (!$auth->isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$userId = $auth->getAuthenticatedUserId();
$isAdmin = $auth->isAdmin();
$connection = null;

// État du formulaire et retour affiché à l'utilisateur.
$feedback = '';
$isError = false;
$fieldErrors = [];
$values = [
    'user_login' => '',
    'user_mail' => '',
];

try {
    // On recharge l'utilisateur depuis la base, pas seulement depuis la session.
    $connection = Database::connect();
    $repository = new UserRepository($connection);
    $user = $userId !== null ? $repository->findById($userId) : null;

    // Si le compte n'existe plus, on ferme la session devenue invalide.
    if ($user === null || $userId === null) {
        $auth->logout();
        header('Location: index.php');
        exit;
    }

    // Valeurs affichées avant toute modification.
    $values = [
        'user_login' => $user->getUsername(),
        'user_mail' => $user->getEmail(),
    ];

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Toute modification exige un jeton CSRF valide.
        if (!csrf_verify($session)) {
            http_response_code(403);
            $isError = true;
            $feedback = 'Votre session a expiré. Rechargez la page et réessayez.';
        } else {
            $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : 'update_profile';

            // La déconnexion reste disponible depuis cette page.
            if ($action === 'logout') {
                $auth->logout();
                header('Location: index.php');
                exit;
            }

            // On nettoie les valeurs saisies avant de les valider.
            $values = [
                'user_login' => is_string($_POST['user_login'] ?? null) ? trim($_POST['user_login']) : '',
                'user_mail' => is_string($_POST['user_mail'] ?? null) ? trim($_POST['user_mail']) : '',
            ];
            $newPassword = is_string($_POST['user_password'] ?? null) ? $_POST['user_password'] : '';
            $passwordConfirm = is_string($_POST['password_confirm'] ?? null) ? $_POST['password_confirm'] : '';

            // Les validateurs existants appliquent les règles de l'inscription.
            $usernameResult = (new UsernameValidator())->validate($values['user_login']);
            $emailResult = (new EmailValidator())->validate($values['user_mail']);

            if (!$usernameResult->isValid()) {
                $fieldErrors['user_login'] = 'Le nom d’utilisateur est obligatoire (255 caractères maximum).';
            }

            if (!$emailResult->isValid()) {
                $fieldErrors['user_mail'] = 'Veuillez saisir une adresse e-mail valide.';
            }

            // Un nom ou un e-mail déjà pris par un autre compte est refusé.
            $existingLogin = $repository->findByUsername($values['user_login']);
            if ($existingLogin !== null && $existingLogin->getId() !== $userId) {
                $fieldErrors['user_login'] = 'Ce nom d’utilisateur est déjà utilisé.';
            }

            $existingEmail = $repository->findByEmail($values['user_mail']);
            if ($existingEmail !== null && $existingEmail->getId() !== $userId) {
                $fieldErrors['user_mail'] = 'Cette adresse e-mail est déjà utilisée.';
            }

            // Le mot de passe n'est modifié que si un nouveau est fourni.
            $passwordHash = null;
            if ($newPassword !== '') {
                $passwordResult = (new PasswordValidator())->validate($newPassword);
                $confirmResult = (new ConfirmPasswordValidator())->validate($newPassword, $passwordConfirm);

                if (!$passwordResult->isValid()) {
                    $fieldErrors['user_password'] =
                        'Le mot de passe doit contenir 8 à 72 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.';
                }

                if (!$confirmResult->isValid()) {
                    $fieldErrors['password_confirm'] = 'La confirmation ne correspond pas au mot de passe.';
                }

                if ($fieldErrors === []) {
                    $passwordHash = password_hash(
                        $newPassword,
                        defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT
                    );
                }
            }

            // Seule une saisie entièrement valide est enregistrée.
            if ($fieldErrors === []) {
                $compteId = $repository->findCompteIdByType($user->getCompteType());

                if ($compteId === null) {
                    throw new RuntimeException('Le rôle du compte est introuvable.');
                }

                // Le rôle existant est conservé lors de la mise à jour.
                $repository->updateProfile(
                    $userId,
                    $values['user_login'],
                    $values['user_mail'],
                    $compteId
                );

                if ($passwordHash !== null) {
                    $repository->updatePassword($userId, $passwordHash);
                }

                // On relit les valeurs réellement enregistrées.
                $user = $repository->findById($userId);
                $values = [
                    'user_login' => $user?->getUsername() ?? $values['user_login'],
                    'user_mail' => $user?->getEmail() ?? $values['user_mail'],
                ];
                $feedback = 'Vos informations ont été mises à jour.';
            } else {
                $isError = true;
                $feedback = 'Corrigez les champs signalés avant d’enregistrer.';
            }
        }
    }
} catch (Throwable $e) {
    // L'utilisateur reçoit un message simple, sans détail technique.
    $isError = true;
    $feedback = 'Impossible de mettre à jour le profil pour le moment.';
} finally {
    if ($connection !== null) {
        $connection->close();
    }
}

// Métadonnées communes lues par le partial d'en-tête.
$pageTitle = 'Mon profil — ATLAS';
$pageDescription = 'Modifiez votre nom, votre adresse e-mail et votre mot de passe ATLAS.';
$assetPrefix = '';
$userLoginError = $fieldErrors['user_login'] ?? null;
$userMailError = $fieldErrors['user_mail'] ?? null;
$userPasswordError = $fieldErrors['user_password'] ?? null;
$passwordConfirmError = $fieldErrors['password_confirm'] ?? null;
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body class="atlas-page atlas-profile">
    <a class="skip-link" href="#main">Aller au contenu principal</a>
    <?php require __DIR__ . '/partials/preloader.php'; ?>
    <?php require __DIR__ . '/partials/site-header.php'; ?>

    <!-- Formulaire de gestion du compte. -->
    <main id="main" class="profile-stage">
        <section class="access-card profile-card" aria-labelledby="profile-title" data-reveal>
            <header class="access-card__head">
                <p class="eyebrow">Mon compte</p>
                <h1 id="profile-title" class="atlas-title atlas-title--compact">Votre <em>profil.</em></h1>
                <p>Mettez à jour vos informations ou choisissez un nouveau mot de passe.</p>
            </header>

            <!-- Retour commun à toutes les actions du formulaire. -->
            <?php if ($feedback !== ''): ?>
                <p class="feedback <?= $isError ? 'feedback-error' : 'feedback-success' ?>" role="alert">
                    <?= e($feedback) ?>
                </p>
            <?php endif; ?>

            <form method="post" class="login-form login-form--atlas">
                <!-- Le jeton CSRF est requis pour toute modification. -->
                <?= csrf_field($session) ?>

                <!-- Informations générales du compte. -->
                <div class="field<?= $userLoginError ? ' field--error' : '' ?>">
                    <input
                        placeholder=" "
                        type="text"
                        id="user_login"
                        name="user_login"
                        autocomplete="username"
                        maxlength="255"
                        value="<?= e($values['user_login']) ?>"
                        <?php if ($userLoginError): ?>
                            aria-invalid="true"
                            aria-describedby="user_login-error"
                        <?php endif; ?>
                        required
                    >
                    <label for="user_login">Nom d’utilisateur</label>
                    <?php if ($userLoginError): ?>
                        <p class="field__error" id="user_login-error" role="alert"><?= e($userLoginError) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field<?= $userMailError ? ' field--error' : '' ?>">
                    <input
                        placeholder=" "
                        type="email"
                        id="user_mail"
                        name="user_mail"
                        autocomplete="email"
                        maxlength="255"
                        value="<?= e($values['user_mail']) ?>"
                        <?php if ($userMailError): ?>
                            aria-invalid="true"
                            aria-describedby="user_mail-error"
                        <?php endif; ?>
                        required
                    >
                    <label for="user_mail">Adresse e-mail</label>
                    <?php if ($userMailError): ?>
                        <p class="field__error" id="user_mail-error" role="alert"><?= e($userMailError) ?></p>
                    <?php endif; ?>
                </div>

                <!-- Le mot de passe reste facultatif : vide, il n'est pas modifié. -->
                <div class="field field--password<?= $userPasswordError ? ' field--error' : '' ?>">
                    <input
                        placeholder=" "
                        type="password"
                        id="user_password"
                        name="user_password"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="72"
                        <?php if ($userPasswordError): ?>
                            aria-invalid="true"
                            aria-describedby="user_password-error"
                        <?php endif; ?>
                    >
                    <label for="user_password">Nouveau mot de passe (facultatif)</label>
                    <?php if ($userPasswordError): ?>
                        <p class="field__error" id="user_password-error" role="alert"><?= e($userPasswordError) ?></p>
                    <?php endif; ?>
                </div>

                <div class="field field--password<?= $passwordConfirmError ? ' field--error' : '' ?>">
                    <input
                        placeholder=" "
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        autocomplete="new-password"
                        minlength="8"
                        maxlength="72"
                        <?php if ($passwordConfirmError): ?>
                            aria-invalid="true"
                            aria-describedby="password_confirm-error"
                        <?php endif; ?>
                    >
                    <label for="password_confirm">Confirmer le mot de passe</label>
                    <?php if ($passwordConfirmError): ?>
                        <p class="field__error" id="password_confirm-error" role="alert"><?= e($passwordConfirmError) ?></p>
                    <?php endif; ?>
                </div>

                <div class="login-form__actions">
                    <button type="submit" class="btn btn--primary btn--block">Enregistrer</button>
                </div>
            </form>

            <!-- Actions secondaires : déconnexion et retour au catalogue. -->
            <div class="profile-card__footer">
                <form method="post">
                    <?= csrf_field($session) ?>
                    <input type="hidden" name="action" value="logout">
                    <button type="submit" class="btn btn--ghost">Se déconnecter</button>
                </form>
                <a class="auth-switch" href="products.php">Retour au catalogue</a>
            </div>
        </section>
    </main>

    <!-- Pied de page commun. -->
    <footer class="atlas-footer atlas-footer--wide">
        <div>
            <?php
            $brandHref = 'products.php';
            $brandLabel = 'ATLAS, catalogue';
            $brandTagline = 'Student living';
            require __DIR__ . '/partials/brand.php';
            ?>
            <p>La vie étudiante sans frontières.</p>
        </div>
        <nav aria-label="Liens de pied de page">
            <a href="products.php">Catalogue</a>
            <a href="cart.php">Mon panier</a>
            <a href="admin/login.php">Accès équipe</a>
        </nav>
        <p class="atlas-footer__meta">© <?= date('Y') ?> ATLAS · France</p>
    </footer>

    <script src="<?= e(asset_url('scripts/ui/theme.js')) ?>" defer></script>
</body>
</html>
