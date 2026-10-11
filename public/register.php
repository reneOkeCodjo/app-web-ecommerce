<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Application\Services\RegisterHandler;
use App\Application\Services\Validation;
use App\Infrastructure\Http\NativeSessionStore;
use App\Infrastructure\Persistence\DAO\NullUserRepository;
use App\Infrastructure\Persistence\DAO\UserRepository;
use Db_config\Database;

$session = new NativeSessionStore();
$validation = new Validation();
$feedbackHtml = '';
$fieldErrors = [];
$values = [
    'user_login' => '',
    'user_mail' => '',
];
$connection = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $values['user_login'] = is_string($_POST['user_login'] ?? null)
            ? $_POST['user_login']
            : '';
        $values['user_mail'] = is_string($_POST['user_mail'] ?? null)
            ? $_POST['user_mail']
            : '';

        $validationResult = $validation->validateRegister(
            $_POST['user_login'] ?? null,
            $_POST['user_mail'] ?? null,
            $_POST['user_password'] ?? null,
            $_POST['password_confirm'] ?? null
        );

        if (!$validationResult->isValid()) {
            $fieldErrors = $validationResult->getErrors();
        } else {
            $connection = Database::connect();
            $handler = new RegisterHandler(
                new UserRepository($connection),
                $session
            );
            $feedbackHtml = $handler->handle('POST', $validationResult->getRegisterRequest());

            if ($feedbackHtml === null) {
                header('Location: products.php');
                exit;
            }
        }
    } else {
        $handler = new RegisterHandler(
            new NullUserRepository(),
            $session
        );
        $feedbackHtml = $handler->handle('GET');

        if ($feedbackHtml === null) {
            header('Location: products.php');
            exit;
        }
    }
} catch (Exception $e) {
    $feedbackHtml = '<p class="feedback feedback-error" role="alert">Database connection failed. Please try again later.</p>';
} finally {
    if (isset($connection)) {
        $connection->close();
    }
}

$userLoginError = $fieldErrors['user_login'] ?? null;
$userMailError = $fieldErrors['user_mail'] ?? null;
$userPasswordError = $fieldErrors['user_password'] ?? null;
$passwordConfirmError = $fieldErrors['password_confirm'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <main class="auth-page">
        <section class="auth-card" aria-labelledby="register-title">
            <div class="auth-header">
                <h1 id="register-title" class="auth-title">
                    <span class="auth-title__text">Register</span>
                </h1>
            </div>

            <form method="post" class="login-form" novalidate>
                <?= $feedbackHtml ?>
                <div class="field<?= $userLoginError ? ' field--error' : '' ?>">
                    <input
                        placeholder="Enter your username"
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
                    <label for="username">Username</label>
                    <?php if ($userLoginError): ?>
                        <p class="field__error" id="user_login-error" role="alert">
                            <?= htmlspecialchars($userLoginError, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="field<?= $userMailError ? ' field--error' : '' ?>">
                    <input
                        placeholder="Enter your email"
                        type="email"
                        id="email"
                        name="user_mail"
                        autocomplete="email"
                        maxlength="255"
                        value="<?= htmlspecialchars($values['user_mail'], ENT_QUOTES, 'UTF-8') ?>"
                        <?php if ($userMailError): ?>
                            aria-invalid="true"
                            aria-describedby="user_mail-error"
                        <?php endif; ?>
                        required
                    />
                    <label for="email">Email</label>
                    <?php if ($userMailError): ?>
                        <p class="field__error" id="user_mail-error" role="alert">
                            <?= htmlspecialchars($userMailError, ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    <?php endif; ?>
                </div>
                <div class="field field--password<?= $userPasswordError ? ' field--error' : '' ?>">
                    <input
                        placeholder="Enter your password"
                        type="password"
                        id="password"
                        name="user_password"
                        autocomplete="new-password"
                        aria-describedby="password-hint<?= $userPasswordError ? ' user_password-error' : '' ?>"
                        minlength="8"
                        maxlength="72"
                        <?php if ($userPasswordError): ?>
                            aria-invalid="true"
                        <?php endif; ?>
                        required
                    />
                    <label for="password">Password</label>
                    <span class="tooltip">
                        <button
                            type="button"
                            class="tooltip__trigger"
                            aria-label="Password requirements"
                            aria-describedby="password-hint"
                        >
                            <svg width="18" height="18" viewBox="0 0 20 20" fill="none" aria-hidden="true" focusable="false">
                                <circle cx="10" cy="10" r="9" stroke="currentColor" stroke-width="1.5"/>
                                <path d="M10 9v5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/>
                                <circle cx="10" cy="6.25" r="0.9" fill="currentColor" aria-hidden="true"/>
                            </svg>
                        </button>
                        <span class="tooltip__content" id="password-hint" role="tooltip">
                            <ul>
                                <li>At least 8 characters</li>
                                <li>At most 72 characters</li>
                                <li>At least one uppercase letter</li>
                                <li>At least one lowercase letter</li>
                                <li>At least one number</li>
                                <li>At least one special character</li>
                            </ul>
                        </span>
                    </span>
                    <button
                        type="button"
                        class="password-toggle"
                        data-password-target="password"
                        aria-label="Show password"
                        aria-controls="password"
                        aria-pressed="false"
                    >
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
                        placeholder="Confirm your password"
                        type="password"
                        id="password_confirm"
                        name="password_confirm"
                        autocomplete="new-password"
                        <?php if ($passwordConfirmError): ?>
                            aria-invalid="true"
                            aria-describedby="password_confirm-error"
                        <?php endif; ?>
                        required
                    />
                    <label for="password_confirm">Confirm password</label>
                    <button
                        type="button"
                        class="password-toggle"
                        data-password-target="password_confirm"
                        aria-label="Show password"
                        aria-controls="password_confirm"
                        aria-pressed="false"
                    >
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
                    <button type="submit">Create account</button>
                </div>
                <p class="auth-switch">
                    Already have an account?
                    <a href="index.php">Log in</a>
                </p>
            </form>
        </section>
    </main>

    <script src="scripts/password-toggle.js" defer></script>
</body>

</html>
