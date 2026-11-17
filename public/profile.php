<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Application\Services\Auth\Auth;
use App\Infrastructure\Http\NativeSessionStore;
use App\Infrastructure\Persistence\DAO\NullUserRepository;
use App\Infrastructure\Persistence\DAO\UserRepository;
use Db_config\Database;

$session = new NativeSessionStore();
$auth = new Auth(new NullUserRepository(), $session);

if (!$auth->isAuthenticated()) {
    header('Location: index.php');
    exit;
}

$userId = $auth->getAuthenticatedUserId();
$connection = null;
$feedback = '';
$isError = false;
$user = null;

try {
    $connection = Database::connect();
    $repository = new UserRepository($connection);
    $user = $repository->findById($userId);

    if ($user === null) {
        $auth->logout();
        header('Location: index.php');
        exit;
    }

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (($_POST['action'] ?? '') === 'logout') {
            $auth->logout();
            header('Location: index.php');
            exit;
        }

        $username = trim((string) ($_POST['user_login'] ?? ''));
        $email = trim((string) ($_POST['user_mail'] ?? ''));
        $newPassword = (string) ($_POST['user_password'] ?? '');

        if ($username === '' || strlen($username) > 255) {
            throw new InvalidArgumentException('Le nom d’utilisateur est obligatoire.');
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('L’adresse e-mail est invalide.');
        }

        $existingUsername = $repository->findByUsername($username);
        if ($existingUsername !== null && $existingUsername->getId() !== $userId) {
            throw new InvalidArgumentException('Ce nom d’utilisateur est déjà utilisé.');
        }

        $existingEmail = $repository->findByEmail($email);
        if ($existingEmail !== null && $existingEmail->getId() !== $userId) {
            throw new InvalidArgumentException('Cette adresse e-mail est déjà utilisée.');
        }

        $passwordHash = null;
        if ($newPassword !== '') {
            if (
                strlen($newPassword) < 8
                || strlen($newPassword) > 72
                || !preg_match('/[a-z]/', $newPassword)
                || !preg_match('/[A-Z]/', $newPassword)
                || !preg_match('/\d/', $newPassword)
                || !preg_match('/[@$!%*?&]/', $newPassword)
            ) {
                throw new InvalidArgumentException(
                    'Le mot de passe doit contenir 8 à 72 caractères, une majuscule, une minuscule, un chiffre et un caractère spécial.'
                );
            }
            $passwordHash = password_hash($newPassword, PASSWORD_DEFAULT);
        }

        $user = $repository->updateProfile($userId, $username, $email, $passwordHash);
        $feedback = 'Vos informations ont été mises à jour.';
    }
} catch (Throwable $e) {
    $isError = true;
    $feedback = $e->getMessage();
} finally {
    if ($connection !== null) {
        $connection->close();
    }
}
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mon profil</title>
    <link rel="stylesheet" href="styles.css">
</head>
<body>
    <main class="auth-page">
        <section class="auth-card" aria-labelledby="profile-title">
            <div class="auth-header">
                <h1 id="profile-title" class="auth-title">
                    <span class="auth-title__text">Mon profil</span>
                </h1>
            </div>

            <?php if ($feedback !== ''): ?>
                <p class="feedback <?= $isError ? 'feedback-error' : 'feedback-success' ?>" role="alert">
                    <?= htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <form method="post" class="login-form">
                <div class="field">
                    <input type="text" id="user_login" name="user_login" maxlength="255" required
                        value="<?= htmlspecialchars($user?->getUsername() ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <label for="user_login">Nom d’utilisateur</label>
                </div>
                <div class="field">
                    <input type="email" id="user_mail" name="user_mail" maxlength="255" required
                        value="<?= htmlspecialchars($user?->getEmail() ?? '', ENT_QUOTES, 'UTF-8') ?>">
                    <label for="user_mail">Adresse e-mail</label>
                </div>
                <div class="field">
                    <input type="password" id="user_password" name="user_password" minlength="8" maxlength="72">
                    <label for="user_password">Nouveau mot de passe (facultatif)</label>
                </div>
                <div class="login-form__actions">
                    <button type="submit">Enregistrer</button>
                </div>
            </form>

            <form method="post" class="login-form">
                <input type="hidden" name="action" value="logout">
                <div class="login-form__actions">
                    <button type="submit">Se déconnecter</button>
                </div>
            </form>

            <p class="auth-switch">
                <a href="products.php">Retour au catalogue</a>
            </p>
        </section>
    </main>
</body>
</html>
