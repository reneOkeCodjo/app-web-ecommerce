<?php

// On active les types stricts de PHP.
declare(strict_types=1);

// On charge le démarrage commun.
require_once __DIR__ . '/../../config/bootstrap.php';

// On importe les classes nécessaires.
use App\Application\Services\Auth\Auth;
use App\Application\Support\Flash;
use App\Application\Validation\Auth\ConfirmPasswordValidator;
use App\Application\Validation\Auth\EmailValidator;
use App\Application\Validation\Auth\PasswordValidator;
use App\Application\Validation\Auth\UsernameValidator;
use App\Infrastructure\Http\NativeSessionStore;
use App\Infrastructure\Persistence\DAO\NullUserRepository;
use App\Infrastructure\Persistence\DAO\UserRepository;
use Db_config\Database;

// On ouvre la session.
$session = new NativeSessionStore();

// On vérifie rapidement la session avant d'ouvrir la base.
$auth = new Auth(new NullUserRepository(), $session);

if (!$auth->isAdmin()) {
    header('Location: login.php');
    exit;
}

// Libellés lisibles pour les rôles.
$roleLabels = [
    'client' => 'Étudiant',
    'vendeur' => 'Partenaire',
    'admin' => 'Administrateur',
];

// Identifiant demandé.
$userId = (int) ($_GET['id'] ?? $_POST['user_id'] ?? 0);

// Valeurs et erreurs des formulaires.
$feedback = '';
$fieldErrors = [];
$profileValues = [
    'user_login' => '',
    'user_mail' => '',
    'compte_type' => 'client',
];
$targetUser = null;
$currentUserId = null;
$currentUsername = 'Admin';
$totalAdmins = 0;
$connection = null;

// Formatage compact des dates.
$formatDate = static function (?string $date): string {
    if ($date === null || $date === '') {
        return 'Jamais';
    }

    $timestamp = strtotime($date);

    return $timestamp !== false ? date('d/m/Y H:i', $timestamp) : '—';
};

try {
    // On ouvre la connexion à la base.
    $connection = Database::connect();

    // On revalide le rôle admin directement en base.
    $userRepository = new UserRepository($connection);
    $auth = new Auth($userRepository, $session);

    if (!$auth->isAdminFresh()) {
        header('Location: login.php');
        exit;
    }

    // On récupère l'administrateur courant.
    $currentUserId = $auth->getAuthenticatedUserId();
    $currentUser = $currentUserId !== null
        ? $userRepository->findById($currentUserId)
        : null;
    $currentUsername = $currentUser?->getUsername() ?? 'Admin';

    // On charge l'utilisateur à modifier.
    $targetUser = $userId > 0 ? $userRepository->findById($userId) : null;

    if ($targetUser !== null) {
        $profileValues = [
            'user_login' => $targetUser->getUsername(),
            'user_mail' => $targetUser->getEmail(),
            'compte_type' => $targetUser->getCompteType(),
        ];
    }

    $totalAdmins = $userRepository->countByType('admin');

    // On traite les formulaires.
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && $targetUser !== null) {
        if (!csrf_verify($session)) {
            http_response_code(419);
            $feedback = 'Votre session a expiré. Rechargez la page et réessayez.';
        } else {
            $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

            if ($action === 'update_profile') {
                $profileValues = [
                    'user_login' => is_string($_POST['user_login'] ?? null) ? trim($_POST['user_login']) : '',
                    'user_mail' => is_string($_POST['user_mail'] ?? null) ? trim($_POST['user_mail']) : '',
                    'compte_type' => is_string($_POST['compte_type'] ?? null) ? $_POST['compte_type'] : 'client',
                ];

                $usernameResult = (new UsernameValidator())->validate($profileValues['user_login']);
                $emailResult = (new EmailValidator())->validate($profileValues['user_mail']);

                if (!$usernameResult->isValid()) {
                    $fieldErrors['user_login'] = (string) $usernameResult->getError();
                }

                if (!$emailResult->isValid()) {
                    $fieldErrors['user_mail'] = (string) $emailResult->getError();
                }

                if (!isset($roleLabels[$profileValues['compte_type']])) {
                    $fieldErrors['compte_type'] = 'Choisissez un rôle valide.';
                }

                if ($profileValues['compte_type'] !== 'admin' && $targetUser->isAdmin() && $totalAdmins <= 1) {
                    $fieldErrors['compte_type'] = 'Le dernier administrateur doit conserver son rôle.';
                }

                if ($targetUser->getId() === $currentUserId && $profileValues['compte_type'] !== 'admin') {
                    $fieldErrors['compte_type'] = 'Vous ne pouvez pas retirer votre propre rôle administrateur.';
                }

                $existingLogin = $userRepository->findByUsername($profileValues['user_login']);
                if ($existingLogin !== null && $existingLogin->getId() !== $targetUser->getId()) {
                    $fieldErrors['user_login'] = 'Ce nom d’utilisateur est déjà utilisé.';
                }

                $existingEmail = $userRepository->findByEmail($profileValues['user_mail']);
                if ($existingEmail !== null && $existingEmail->getId() !== $targetUser->getId()) {
                    $fieldErrors['user_mail'] = 'Cette adresse e-mail est déjà utilisée.';
                }

                $compteId = $userRepository->findCompteIdByType($profileValues['compte_type']);

                if ($compteId === null) {
                    $fieldErrors['compte_type'] = 'Ce rôle n’existe pas en base.';
                }

                if ($fieldErrors === [] && $compteId !== null) {
                    $userRepository->updateProfile(
                        $targetUser->getId(),
                        $profileValues['user_login'],
                        $profileValues['user_mail'],
                        $compteId
                    );

                    Flash::add($session, 'success', 'Le profil a été mis à jour.');
                    header('Location: user-edit.php?id=' . $targetUser->getId());
                    exit;
                }

                $feedback = 'Corrigez les champs signalés avant d’enregistrer.';
            }

            if ($action === 'update_password') {
                $password = $_POST['user_password'] ?? null;
                $passwordConfirm = $_POST['password_confirm'] ?? null;

                $passwordResult = (new PasswordValidator())->validate($password);
                $confirmResult = (new ConfirmPasswordValidator())->validate($password, $passwordConfirm);

                if (!$passwordResult->isValid()) {
                    $fieldErrors['user_password'] = (string) $passwordResult->getError();
                }

                if (!$confirmResult->isValid()) {
                    $fieldErrors['password_confirm'] = (string) $confirmResult->getError();
                }

                if ($fieldErrors === []) {
                    $passwordHash = password_hash(
                        (string) $password,
                        defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT
                    );

                    $userRepository->updatePassword($targetUser->getId(), $passwordHash);

                    Flash::add($session, 'success', 'Le mot de passe a été remplacé.');
                    header('Location: user-edit.php?id=' . $targetUser->getId());
                    exit;
                }

                $feedback = 'Le nouveau mot de passe doit respecter les règles de sécurité.';
            }

            if ($action === 'delete') {
                if ($targetUser->getId() === $currentUserId) {
                    $feedback = 'Vous ne pouvez pas supprimer votre propre compte.';
                } elseif ($targetUser->isAdmin() && $totalAdmins <= 1) {
                    $feedback = 'Le dernier administrateur ne peut pas être supprimé.';
                } else {
                    $userRepository->delete($targetUser->getId());
                    Flash::add($session, 'success', 'Le compte a été supprimé.');
                    header('Location: users.php');
                    exit;
                }
            }
        }
    }

    // On recharge la cible après une éventuelle mise à jour.
    $targetUser = $userId > 0 ? $userRepository->findById($userId) : null;

    if ($targetUser !== null && $fieldErrors === []) {
        $profileValues = [
            'user_login' => $targetUser->getUsername(),
            'user_mail' => $targetUser->getEmail(),
            'compte_type' => $targetUser->getCompteType(),
        ];
    }
} catch (\Throwable $e) {
    $feedback = 'Impossible de charger ce compte pour le moment.';
} finally {
    if ($connection !== null) {
        $connection->close();
    }
}

// On récupère les messages flash de la page précédente.
$flashMessages = Flash::consume($session);

// On prépare la page et ses fichiers communs.
$pageTitle = 'Modifier un utilisateur — Administration ATLAS';
$pageDescription = 'Modifiez le profil, le rôle et le mot de passe d’un compte ATLAS.';
$assetPrefix = '../';
$activeAdmin = 'users';
$breadcrumbLabel = 'Modifier un utilisateur';
$adminUserId = $currentUserId;
$adminUsername = $currentUsername;
$targetInitial = $targetUser !== null
    ? mb_strtoupper(mb_substr($targetUser->getUsername(), 0, 1))
    : '?';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require __DIR__ . '/../partials/head.php'; ?>
</head>
<body class="atlas-admin">
    <a class="skip-link" href="#main">Aller au contenu principal</a>
    <?php require __DIR__ . '/../partials/preloader.php'; ?>

    <div class="admin-shell">
        <?php require __DIR__ . '/../partials/admin-sidebar.php'; ?>

        <div class="admin-main">
            <?php require __DIR__ . '/../partials/admin-topbar.php'; ?>

            <main id="main" class="admin-content admin-content--wide">
                <div class="page-head" data-reveal>
                    <div>
                        <a class="back-link" href="users.php">← Retour aux utilisateurs</a>
                        <p class="eyebrow">Profil & permissions</p>
                        <h1 class="page-title">
                            <?= $targetUser !== null ? e($targetUser->getUsername()) : 'Compte introuvable' ?>
                        </h1>
                        <p class="page-lead">
                            Ajustez les informations du compte, son rôle ou son mot de passe.
                        </p>
                    </div>
                    <?php if ($targetUser !== null): ?>
                        <span class="role-badge role-badge--<?= e($targetUser->getCompteType()) ?>">
                            <?= e($roleLabels[$targetUser->getCompteType()] ?? $targetUser->getCompteType()) ?>
                        </span>
                    <?php endif; ?>
                </div>

                <?php foreach ($flashMessages as $message): ?>
                    <p class="feedback feedback-<?= e($message['type']) ?>" role="status">
                        <?= e($message['message']) ?>
                    </p>
                <?php endforeach; ?>

                <?php if ($feedback !== ''): ?>
                    <p class="feedback feedback-error" role="alert"><?= e($feedback) ?></p>
                <?php endif; ?>

                <?php if ($targetUser === null): ?>
                    <section class="admin-panel" data-reveal>
                        <p class="admin-empty">Ce compte n’existe pas ou a déjà été supprimé.</p>
                        <a class="btn btn--primary" href="users.php">Revenir à la liste</a>
                    </section>
                <?php else: ?>
                    <div class="admin-edit-layout">
                        <section class="admin-panel" data-reveal>
                            <header class="admin-profile-head">
                                <span class="admin-profile-head__avatar" aria-hidden="true"><?= e($targetInitial) ?></span>
                                <div>
                                    <p class="eyebrow">Compte</p>
                                    <h2><?= e($targetUser->getUsername()) ?></h2>
                                    <p><?= e($targetUser->getEmail()) ?></p>
                                </div>
                            </header>

                            <div class="admin-meta-grid">
                                <div><span>Identifiant</span><strong>#<?= e((string) $targetUser->getId()) ?></strong></div>
                                <div><span>Créé le</span><strong><?= e($formatDate($targetUser->getDateNew())) ?></strong></div>
                                <div><span>Dernière connexion</span><strong><?= e($formatDate($targetUser->getDateLogin())) ?></strong></div>
                                <div><span>Rôle actuel</span><strong><?= e($roleLabels[$targetUser->getCompteType()] ?? $targetUser->getCompteType()) ?></strong></div>
                            </div>

                            <form method="post" class="admin-form">
                                <?= csrf_field($session) ?>
                                <input type="hidden" name="action" value="update_profile">
                                <input type="hidden" name="user_id" value="<?= e((string) $targetUser->getId()) ?>">

                                <div class="admin-form__grid">
                                    <div class="admin-field<?= isset($fieldErrors['user_login']) ? ' admin-field--error' : '' ?>">
                                        <label for="edit-user-login">Nom d’utilisateur</label>
                                        <input id="edit-user-login" type="text" name="user_login" maxlength="255" value="<?= e($profileValues['user_login']) ?>" required>
                                        <?php if (isset($fieldErrors['user_login'])): ?>
                                            <small><?= e($fieldErrors['user_login']) ?></small>
                                        <?php endif; ?>
                                    </div>

                                    <div class="admin-field<?= isset($fieldErrors['user_mail']) ? ' admin-field--error' : '' ?>">
                                        <label for="edit-user-mail">Adresse e-mail</label>
                                        <input id="edit-user-mail" type="email" name="user_mail" maxlength="255" value="<?= e($profileValues['user_mail']) ?>" required>
                                        <?php if (isset($fieldErrors['user_mail'])): ?>
                                            <small><?= e($fieldErrors['user_mail']) ?></small>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="admin-field<?= isset($fieldErrors['compte_type']) ? ' admin-field--error' : '' ?>">
                                    <label for="edit-user-role">Rôle</label>
                                    <select id="edit-user-role" name="compte_type" required>
                                        <?php foreach ($roleLabels as $role => $label): ?>
                                            <option value="<?= e($role) ?>"<?= $profileValues['compte_type'] === $role ? ' selected' : '' ?>><?= e($label) ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <?php if (isset($fieldErrors['compte_type'])): ?>
                                        <small><?= e($fieldErrors['compte_type']) ?></small>
                                    <?php endif; ?>
                                </div>

                                <button type="submit" class="btn btn--primary">Enregistrer le profil</button>
                            </form>
                        </section>

                        <div class="admin-edit-side">
                            <section class="admin-panel" data-reveal>
                                <header class="admin-panel__head">
                                    <p class="eyebrow">Sécurité</p>
                                    <h2>Nouveau mot de passe.</h2>
                                    <p>Remplacez le mot de passe sans connaître l’ancien.</p>
                                </header>

                                <form method="post" class="admin-form">
                                    <?= csrf_field($session) ?>
                                    <input type="hidden" name="action" value="update_password">
                                    <input type="hidden" name="user_id" value="<?= e((string) $targetUser->getId()) ?>">

                                    <div class="admin-field<?= isset($fieldErrors['user_password']) ? ' admin-field--error' : '' ?>">
                                        <label for="edit-user-password">Mot de passe</label>
                                        <input id="edit-user-password" type="password" name="user_password" minlength="8" autocomplete="new-password" required>
                                        <?php if (isset($fieldErrors['user_password'])): ?>
                                            <small><?= e($fieldErrors['user_password']) ?></small>
                                        <?php endif; ?>
                                    </div>

                                    <div class="admin-field<?= isset($fieldErrors['password_confirm']) ? ' admin-field--error' : '' ?>">
                                        <label for="edit-user-password-confirm">Confirmation</label>
                                        <input id="edit-user-password-confirm" type="password" name="password_confirm" minlength="8" autocomplete="new-password" required>
                                        <?php if (isset($fieldErrors['password_confirm'])): ?>
                                            <small><?= e($fieldErrors['password_confirm']) ?></small>
                                        <?php endif; ?>
                                    </div>

                                    <button type="submit" class="btn btn--soft btn--block">Remplacer le mot de passe</button>
                                </form>
                            </section>

                            <section class="admin-panel admin-panel--danger" data-reveal>
                                <p class="eyebrow">Zone sensible</p>
                                <h2>Supprimer ce compte.</h2>
                                <p>Cette action est définitive. Le dernier administrateur et votre propre compte sont protégés.</p>
                                <form method="post" data-confirm="Supprimer définitivement le compte de <?= e($targetUser->getUsername()) ?> ?">
                                    <?= csrf_field($session) ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="user_id" value="<?= e((string) $targetUser->getId()) ?>">
                                    <button type="submit" class="btn btn--danger btn--block"<?= ($targetUser->getId() === $currentUserId || ($targetUser->isAdmin() && $totalAdmins <= 1)) ? ' disabled' : '' ?>>
                                        Supprimer le compte
                                    </button>
                                </form>
                            </section>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <script src="<?= e(asset_url('scripts/ui/theme.js', '../')) ?>" defer></script>
    <script src="<?= e(asset_url('scripts/admin-users.js', '../')) ?>" defer></script>
</body>
</html>
