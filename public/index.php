<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="styles.css">
    <title>Login</title>
</head>

<body>
    <main class="auth-page">
        <section class="auth-card" aria-labelledby="login-title">
            <div class="auth-header">
                <p class="eyebrow">Welcome back</p>
                <h1 id="login-title">Login</h1>
                <p class="intro">Please enter your credentials to log in.</p>
            </div>

            <form method="post" class="login-form">
                <div class="field">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" autocomplete="username" required />
                </div>
                <div class="field">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" autocomplete="current-password" required />
                </div>
                <button type="submit">Log in</button>
            </form>

            <?php

            require_once __DIR__ . '/../vendor/autoload.php';

            use App\Application\DTO\LoginRequestDTO;
            use App\Application\Services\Auth;
            use App\Infrastructure\Http\NativeSessionStore;
            use App\Infrastructure\Persistence\DAO\UserRepository;
            use Db_config\Database;

            $session = new NativeSessionStore();

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';

                $LoginDto = new LoginRequestDTO($username, $password);
                try {
                    $connection = Database::connect();

                    try {
                        $userRepository = new UserRepository($connection);
                        $auth = new Auth($userRepository, $session);
                        $auth_response = $auth->login($LoginDto->getUsername(), $LoginDto->getPassword());

                        if ($auth_response) {
                            echo '<p class="feedback feedback-success" role="status">Login successful!</p>';
                        } else {
                            echo '<p class="feedback feedback-error" role="alert">Invalid username or password.</p>';
                        }
                    } catch (Throwable $e) {
                        echo '<p class="feedback feedback-error" role="alert">An error occurred during login. Please try again later.</p>';
                    }
                } catch (Exception $e) {
                    echo '<p class="feedback feedback-error" role="alert">Database connection failed. Please try again later.</p>';
                } finally {
                    if (isset($connection)) {
                        $connection->close();
                    }
                }
            } else {
                $userId = $session->get('user_id');
                if (is_numeric($userId) && (int) $userId > 0) {
                    echo '<p class="feedback feedback-success" role="status">You are already signed in.</p>';
                }
            }
            ?>
        </section>
    </main>

</body>

</html>
