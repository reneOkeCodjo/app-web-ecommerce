<?php

// On active les types stricts de PHP.
declare(strict_types=1);

// On charge le démarrage commun.
require_once __DIR__ . '/../../config/bootstrap.php';

// On importe les classes nécessaires.
use App\Application\Services\Auth\Auth;
use App\Application\Services\Shared\Validation;
use App\Application\Support\Flash;
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

// Valeurs et erreurs du formulaire d'invitation.
$feedback = '';
$fieldErrors = [];
$formValues = [
    'user_login' => '',
    'user_mail' => '',
    'compte_type' => 'client',
];

// Données chargées depuis la base.
$users = [];
$totalUsers = 0;
$totalAdmins = 0;
$totalClients = 0;
$currentUserId = null;
$currentUsername = 'Admin';
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

    // On traite les actions envoyées depuis la page.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!csrf_verify($session)) {
            http_response_code(419);
            $feedback = 'Votre session a expiré. Rechargez la page et réessayez.';
        } else {
            $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

            if ($action === 'create') {
                // On mémorise les valeurs saisies.
                $formValues = [
                    'user_login' => is_string($_POST['user_login'] ?? null) ? $_POST['user_login'] : '',
                    'user_mail' => is_string($_POST['user_mail'] ?? null) ? $_POST['user_mail'] : '',
                    'compte_type' => is_string($_POST['compte_type'] ?? null) ? $_POST['compte_type'] : 'client',
                ];

                // On valide le login, l'e-mail et le mot de passe.
                $validation = new Validation();
                $validationResult = $validation->validateRegister(
                    $_POST['user_login'] ?? null,
                    $_POST['user_mail'] ?? null,
                    $_POST['user_password'] ?? null,
                    $_POST['password_confirm'] ?? null
                );

                if (!$validationResult->isValid()) {
                    $fieldErrors = $validationResult->getErrors();
                    $feedback = 'Corrigez les champs signalés avant de créer le compte.';
                } else {
                    $request = $validationResult->getRegisterRequest();

                    // On vérifie que le rôle demandé existe.
                    if (!isset($roleLabels[$formValues['compte_type']])) {
                        $fieldErrors['compte_type'] = 'Choisissez un rôle valide.';
                        $feedback = 'Le rôle sélectionné n’est pas autorisé.';
                    }

                    // On refuse les doublons de login ou d'e-mail.
                    if ($request !== null && $userRepository->findByUsername($request->getUsername()) !== null) {
                        $fieldErrors['user_login'] = 'Ce nom d’utilisateur est déjà utilisé.';
                    }

                    if ($request !== null && $userRepository->findByEmail($request->getEmail()) !== null) {
                        $fieldErrors['user_mail'] = 'Cette adresse e-mail est déjà utilisée.';
                    }

                    $compteId = $userRepository->findCompteIdByType($formValues['compte_type']);

                    if ($compteId === null) {
                        $fieldErrors['compte_type'] = 'Ce rôle n’existe pas en base.';
                    }

                    if ($request !== null && $fieldErrors === [] && $compteId !== null) {
                        $passwordHash = password_hash(
                            $request->getPassword(),
                            defined('PASSWORD_ARGON2ID') ? PASSWORD_ARGON2ID : PASSWORD_DEFAULT
                        );

                        $userRepository->create(
                            $request->getUsername(),
                            $request->getEmail(),
                            $passwordHash,
                            $compteId
                        );

                        Flash::add($session, 'success', 'Le compte a été créé.');
                        header('Location: users.php');
                        exit;
                    }

                    if ($feedback === '') {
                        $feedback = 'Impossible de créer le compte pour le moment.';
                    }
                }
            }

            if ($action === 'delete') {
                $targetId = (int) ($_POST['user_id'] ?? 0);
                $target = $targetId > 0 ? $userRepository->findById($targetId) : null;

                if ($target === null) {
                    $feedback = 'Utilisateur introuvable.';
                } elseif ($targetId === $currentUserId) {
                    $feedback = 'Vous ne pouvez pas supprimer votre propre compte.';
                } elseif ($target->isAdmin() && $userRepository->countByType('admin') <= 1) {
                    $feedback = 'Le dernier administrateur ne peut pas être supprimé.';
                } else {
                    $userRepository->delete($targetId);
                    Flash::add($session, 'success', 'Le compte a été supprimé.');
                    header('Location: users.php');
                    exit;
                }
            }
        }
    }

    // On charge la liste complète après les éventuelles actions.
    $users = $userRepository->findAll();
    $totalUsers = count($users);
    $totalAdmins = $userRepository->countByType('admin');
    $totalClients = $userRepository->countByType('client');
} catch (\Throwable $e) {
    $feedback = 'Impossible de charger la gestion des utilisateurs pour le moment.';
} finally {
    if ($connection !== null) {
        $connection->close();
    }
}

// On récupère les messages flash de la page précédente.
$flashMessages = Flash::consume($session);

// On prépare la page et ses fichiers communs.
$pageTitle = 'Utilisateurs — Administration ATLAS';
$pageDescription = 'Gérez les accès, les rôles et les comptes de la plateforme ATLAS.';
$assetPrefix = '../';
$activeAdmin = 'users';
$breadcrumbLabel = 'Utilisateurs';
$adminUserId = $currentUserId;
$adminUsername = $currentUsername;
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
                        <p class="eyebrow">Équipe & accès</p>
                        <h1 class="page-title">Utilisateurs</h1>
                        <p class="page-lead">
                            Créez des comptes, ajustez les rôles et gardez le contrôle sur les accès à la plateforme.
                        </p>
                    </div>
                    <a class="btn btn--primary" href="#invite">Inviter un utilisateur</a>
                </div>

                <section class="admin-stat-strip" aria-label="Résumé des utilisateurs" data-reveal>
                    <div><span>Comptes</span><strong><?= e((string) $totalUsers) ?></strong></div>
                    <div><span>Administrateurs</span><strong><?= e((string) $totalAdmins) ?></strong></div>
                    <div><span>Étudiants</span><strong><?= e((string) $totalClients) ?></strong></div>
                    <div><span>Protection</span><strong>CSRF</strong></div>
                </section>

                <?php foreach ($flashMessages as $message): ?>
                    <p class="feedback feedback-<?= e($message['type']) ?>" role="status">
                        <?= e($message['message']) ?>
                    </p>
                <?php endforeach; ?>

                <?php if ($feedback !== ''): ?>
                    <p class="feedback feedback-error" role="alert"><?= e($feedback) ?></p>
                <?php endif; ?>

                <div class="admin-users-layout">
                    <aside class="admin-panel admin-panel--sticky" id="invite" data-reveal>
                        <header class="admin-panel__head">
                            <p class="eyebrow">Nouveau compte</p>
                            <h2>Ouvrir un accès.</h2>
                            <p>Le compte reçoit immédiatement le rôle choisi. Communiquez-lui ensuite un mot de passe temporaire.</p>
                        </header>

                        <form method="post" class="admin-form">
                            <?= csrf_field($session) ?>
                            <input type="hidden" name="action" value="create">

                            <div class="admin-field<?= isset($fieldErrors['user_login']) ? ' admin-field--error' : '' ?>">
                                <label for="new-user-login">Nom d’utilisateur</label>
                                <input id="new-user-login" type="text" name="user_login" maxlength="255" autocomplete="off" value="<?= e($formValues['user_login']) ?>" required>
                                <?php if (isset($fieldErrors['user_login'])): ?>
                                    <small><?= e($fieldErrors['user_login']) ?></small>
                                <?php endif; ?>
                            </div>

                            <div class="admin-field<?= isset($fieldErrors['user_mail']) ? ' admin-field--error' : '' ?>">
                                <label for="new-user-mail">Adresse e-mail</label>
                                <input id="new-user-mail" type="email" name="user_mail" maxlength="255" autocomplete="off" value="<?= e($formValues['user_mail']) ?>" required>
                                <?php if (isset($fieldErrors['user_mail'])): ?>
                                    <small><?= e($fieldErrors['user_mail']) ?></small>
                                <?php endif; ?>
                            </div>

                            <div class="admin-field<?= isset($fieldErrors['compte_type']) ? ' admin-field--error' : '' ?>">
                                <label for="new-user-role">Rôle</label>
                                <select id="new-user-role" name="compte_type" required>
                                    <?php foreach ($roleLabels as $role => $label): ?>
                                        <option value="<?= e($role) ?>"<?= $formValues['compte_type'] === $role ? ' selected' : '' ?>><?= e($label) ?></option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (isset($fieldErrors['compte_type'])): ?>
                                    <small><?= e($fieldErrors['compte_type']) ?></small>
                                <?php endif; ?>
                            </div>

                            <div class="admin-field<?= isset($fieldErrors['user_password']) ? ' admin-field--error' : '' ?>">
                                <label for="new-user-password">Mot de passe temporaire</label>
                                <input id="new-user-password" type="password" name="user_password" minlength="8" autocomplete="new-password" required>
                                <?php if (isset($fieldErrors['user_password'])): ?>
                                    <small><?= e($fieldErrors['user_password']) ?></small>
                                <?php endif; ?>
                            </div>

                            <div class="admin-field<?= isset($fieldErrors['password_confirm']) ? ' admin-field--error' : '' ?>">
                                <label for="new-user-password-confirm">Confirmation</label>
                                <input id="new-user-password-confirm" type="password" name="password_confirm" minlength="8" autocomplete="new-password" required>
                                <?php if (isset($fieldErrors['password_confirm'])): ?>
                                    <small><?= e($fieldErrors['password_confirm']) ?></small>
                                <?php endif; ?>
                            </div>

                            <button type="submit" class="btn btn--primary btn--block">Créer le compte</button>
                            <p class="admin-form__note">8 caractères minimum, avec majuscule, minuscule, chiffre et caractère spécial.</p>
                        </form>
                    </aside>

                    <section class="admin-panel admin-panel--table" data-reveal>
                        <header class="admin-panel__head admin-panel__head--row">
                            <div>
                                <p class="eyebrow">Annuaire</p>
                                <h2>Comptes actifs.</h2>
                            </div>
                            <span class="pill"><?= e((string) $totalUsers) ?> au total</span>
                        </header>

                        <div class="admin-table-wrap">
                            <table class="admin-table">
                                <thead>
                                    <tr>
                                        <th scope="col">Utilisateur</th>
                                        <th scope="col">Rôle</th>
                                        <th scope="col">Dernière connexion</th>
                                        <th scope="col">Créé le</th>
                                        <th scope="col"><span class="sr-only">Actions</span></th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($users as $user): ?>
                                        <?php
                                        $isSelf = $user->getId() === $currentUserId;
                                        $isLastAdmin = $user->isAdmin() && $totalAdmins <= 1;
                                        $canDelete = !$isSelf && !$isLastAdmin;
                                        $initial = mb_strtoupper(mb_substr($user->getUsername(), 0, 1));
                                        ?>
                                        <tr>
                                            <td>
                                                <div class="table-user">
                                                    <span class="table-user__avatar" aria-hidden="true"><?= e($initial) ?></span>
                                                    <span class="table-user__copy">
                                                        <strong><?= e($user->getUsername()) ?><?= $isSelf ? ' (vous)' : '' ?></strong>
                                                        <small><?= e($user->getEmail()) ?></small>
                                                    </span>
                                                </div>
                                            </td>
                                            <td>
                                                <span class="role-badge role-badge--<?= e($user->getCompteType()) ?>">
                                                    <?= e($roleLabels[$user->getCompteType()] ?? $user->getCompteType()) ?>
                                                </span>
                                            </td>
                                            <td><?= e($formatDate($user->getDateLogin())) ?></td>
                                            <td><?= e($formatDate($user->getDateNew())) ?></td>
                                            <td>
                                                <div class="table-actions">
                                                    <a class="btn btn--soft btn--sm" href="user-edit.php?id=<?= e((string) $user->getId()) ?>">Modifier</a>
                                                    <form method="post" class="inline-form" data-confirm="Supprimer définitivement le compte de <?= e($user->getUsername()) ?> ?">
                                                        <?= csrf_field($session) ?>
                                                        <input type="hidden" name="action" value="delete">
                                                        <input type="hidden" name="user_id" value="<?= e((string) $user->getId()) ?>">
                                                        <button type="submit" class="btn btn--danger btn--sm"<?= $canDelete ? '' : ' disabled' ?>>Supprimer</button>
                                                    </form>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </section>
                </div>
            </main>
        </div>
    </div>

    <script src="<?= e(asset_url('scripts/ui/theme.js', '../')) ?>" defer></script>
    <script src="<?= e(asset_url('scripts/admin-users.js', '../')) ?>" defer></script>
</body>
</html>
