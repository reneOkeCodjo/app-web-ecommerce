<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Application\Services\Auth\Auth;
use App\Infrastructure\Http\NativeSessionStore;
use App\Infrastructure\Persistence\DAO\NullUserRepository;

$session = new NativeSessionStore();
$auth = new Auth(new NullUserRepository(), $session);

if (!$auth->isAdmin()) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    $auth->logout();
    header('Location: login.php');
    exit;
}

$userId = $auth->getAuthenticatedUserId();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin</title>
    <link rel="stylesheet" href="../styles.css">
</head>

<body>
    <main class="auth-page">
        <section class="auth-card" aria-labelledby="admin-title">
            <div class="auth-header">
                <h1 id="admin-title" class="auth-title">
                    <span class="auth-title__text">Admin</span>
                </h1>
            </div>
            <p class="auth-switch">
                Signed in as admin #<?= htmlspecialchars((string) $userId, ENT_QUOTES, 'UTF-8') ?>.
            </p>
            <p class="auth-switch">
                <a href="products.php">Gérer les produits</a>
            </p>
            <form method="post" class="login-form">
                <input type="hidden" name="action" value="logout">
                <div class="login-form__actions">
                    <button type="submit">Log out</button>
                </div>
            </form>
        </section>
    </main>
</body>

</html>
