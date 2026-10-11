<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Application\Services\LoginHandler;
use App\Application\Services\Validation;
use App\Infrastructure\Http\NativeSessionStore;
use App\Infrastructure\Persistence\DAO\NullUserRepository;
use App\Infrastructure\Persistence\DAO\UserRepository;
use Db_config\Database;

$session = new NativeSessionStore();
$validation = new Validation();
$feedbackHtml = '';
$connection = null;

try {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $connection = Database::connect();
        $handler = new LoginHandler(
            $validation,
            new UserRepository($connection),
            $session
        );
        $feedbackHtml = $handler->handle('POST', $_POST);
    } else {
        $handler = new LoginHandler(
            $validation,
            new NullUserRepository(),
            $session
        );
        $feedbackHtml = $handler->handle('GET', []);
    }
} catch (Exception $e) {
    $feedbackHtml = '<p class="feedback feedback-error" role="alert">Database connection failed. Please try again later.</p>';
} finally {
    if (isset($connection)) {
        $connection->close();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login</title>
    <link rel="stylesheet" href="styles.css">
</head>

<body>
    <main class="auth-page">
        <section class="auth-card" aria-labelledby="login-title">
            <div class="auth-header">
                <h1 id="login-title" class="auth-title">
                    <span class="auth-title__text">Login</span>
                </h1>
            </div>

            <form method="post" class="login-form">
                <?= $feedbackHtml ?>
                <div class="field">
                    <input
                        placeholder="Enter your username"
                        type="text"
                        id="username"
                        name="user_login"
                        autocomplete="username"
                        maxlength="255"
                        required
                    />
                    <label for="username">Username</label>
                </div>
                <div class="field field--password">
                    <input
                        placeholder="Enter your password"
                        type="password"
                        id="password"
                        name="user_password"
                        autocomplete="current-password"
                        minlength="8"
                        maxlength="72"
                        required
                    />
                    <label for="password">Password</label>
                    <button
                        type="button"
                        class="password-toggle"
                        id="password-toggle"
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
                </div>
                <div class="login-form__actions">
                    <button type="submit">Log in</button>
                </div>
                <p class="auth-switch">
                    No account yet?
                    <a href="register.php">Register</a>
                </p>
            </form>
        </section>
    </main>

    <script src="scripts/password-toggle.js" defer></script>
</body>

</html>
