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
            use App\Infrastructure\Persistence\DAO\UserRepository;
            use Db_config\Database;

            if ($_SERVER['REQUEST_METHOD'] === 'POST') {
                $username = $_POST['username'];
                $password = $_POST['password'];

                // Encapsulate the login request data in a DTO
                $LoginDto = new LoginRequestDTO($username, $password);
    
                $connection = Database::connect();
                echo '<p class="feedback feedback-info" role="status">Attempting to connect to the database...</p>';
                $userRepository = new UserRepository($connection);
                $auth = new Auth($userRepository);

                try {
                    // Attempt to authenticate the user
                    $auth_response = $auth->login($LoginDto->getUsername(), $LoginDto->getPassword());

                    if ($auth_response) {
                        echo '<p class="feedback feedback-success" role="status">Login successful!</p>';
                    } else {
                        echo '<p class="feedback feedback-error" role="alert">Invalid username or password.</p>';
                    }
                } catch (Throwable $exception) {
                    // Send server error response
                    echo '<p class="feedback feedback-error" role="alert">Unable to complete login. Please try again.</p>';
                } finally {
                    // Finally close the database connection
                    $connection->close();
                }
            }
            ?>
        </section>
    </main>

</body>

</html>