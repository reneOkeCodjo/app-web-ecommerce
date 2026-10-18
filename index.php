<?php
/**
 * Page d'authentification (connexion / inscription).
 *
 * Routage via ?page=login|register (login par défaut).
 * Traite les POST login et register, puis affiche le formulaire correspondant.
 */
session_start();

$feedback = '';
$page = $_GET['page'] ?? 'login';
$register_page = $page === 'register';

// --- Redirection : déjà connecté → dashboard ---
if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

// --- Traitement des formulaires (login / register) ---
if (isset($_POST['form_submitted_value'])) {
    $form_submitted_value = $_POST['form_submitted_value'];

    // Connexion BDD via variables d'environnement Docker
    $db_host = getenv('DB_HOST');
    $db_name = getenv('DB_NAME');
    $db_user = getenv('DB_USER');
    $db_password = getenv('DB_PASSWORD');
    $db_port = getenv('DB_PORT');
    $dsn = "mysql:host=$db_host;dbname=$db_name;port=$db_port";

    try {
        $pdo = new PDO($dsn, $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $username = $_POST['username'];
        $password = $_POST['password'];

        // Recherche de l'utilisateur par login
        $query = "SELECT user_id as id, user_compte_id as compte_id, user_password as password FROM user WHERE user_login = :username";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'username' => $username,
        ]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user) {
            // --- Connexion : vérifier le hash bcrypt ---
            if ($form_submitted_value == 'login') {
                $is_valid_password = password_verify($password, $user['password']);
                if ($is_valid_password) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['compte_id'] = $user['compte_id'];
                    header('Location: dashboard.php');
                    exit;
                }
            } elseif ($form_submitted_value == 'register') {
                // Login déjà pris
                $feedback = 'User already exists';
            }
        } else {
            // --- Inscription : créer le compte (rôle client = compte_id 2) ---
            if ($form_submitted_value == 'register') {
                $password = password_hash($password, PASSWORD_DEFAULT);
                $email = $_POST['email'];
                $compte_id = 2;

                $query = "INSERT INTO user (user_login, user_password, user_mail, user_compte_id) VALUES (:username, :password, :mail, :compte_id)";
                $stmt = $pdo->prepare($query);
                $stmt->execute([
                    'username' => $username,
                    'password' => $password,
                    'mail' => $email,
                    'compte_id' => $compte_id,
                ]);
                header('Location: index.php?page=login');
                exit;
            }
            // Login : utilisateur introuvable
            $feedback = 'Invalid username or password';
        }
    } catch (PDOException $e) {
        $feedback = 'Connection failed: ' . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <?php if (!$register_page): ?>
        <title>Login Page</title>
        <link rel="stylesheet" href="./style/login.css">
    <?php else: ?>
        <title>Register Page</title>
        <link rel="stylesheet" href="./style/register.css">
    <?php endif; ?>
    <link rel="stylesheet" href="./style/common.css">
</head>

<body>
    <!-- Navigation : bascule login ↔ register -->
    <nav>
        <?php if (!$register_page): ?>
            <a href="index.php?page=register">Sign in</a>
        <?php else: ?>
            <a href="index.php?page=login">Login</a>
        <?php endif; ?>
    </nav>

    <main>
        <h1><span>
            <?php if (!$register_page): ?>
                Login
            <?php else: ?>
                Register
            <?php endif; ?>
        </span></h1>

        <!-- Feedback serveur (PHP) et message client (JS) -->
        <span id="feedback-server" data-message="<?php echo htmlspecialchars($feedback, ENT_QUOTES, 'UTF-8'); ?>"></span>
        <span id="feedback-message"></span>

        <!-- ========== Formulaire de connexion ========== -->
        <?php if (!$register_page): ?>
            <form id="login-form" class="auth-form" method="post">
                <div class="form-field">
                    <label for="login-username">Username</label>
                    <input type="text" name="username" id="login-username" class="login-input" placeholder="Username">
                </div>
                <div class="form-field password-field">
                    <label for="login-password">Password</label>
                    <input type="password" name="password" id="login-password" class="login-input" placeholder="Password">
                    <button type="button" class="password-toggle closed" aria-label="Show password">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye">
                            <path class="lid-top" d="M1 12s4-8 11-8 11 8 11 8" />
                            <circle cx="12" cy="12" r="3" />
                            <path class="lid-bottom" d="M23 12s-4 8-11 8-11-8-11-8" />
                        </svg>
                    </button>
                </div>
                <input type="hidden" name="form_submitted_value" value="login">
                <button type="submit" id="login-submit" class="auth-submit">Login</button>
                <p>Forgot your password? <a href="index.php?page=forgot-password">Reset password</a></p>
            </form>
        <?php endif; ?>

        <!-- ========== Formulaire d'inscription ========== -->
        <?php if ($register_page): ?>
            <form id="register-form" class="auth-form" method="post">
                <!-- Champ e-mail -->
                <div class="form-field">
                    <label for="register-email">Email</label>
                    <input type="email" name="email" id="register-email" class="register-input" placeholder="example@domain.com">
                    <span id="register-email-error" class="field-error"></span>
                </div>

                <!-- Champ nom d'utilisateur -->
                <div class="form-field">
                    <label for="register-username">Username</label>
                    <input type="text" name="username" id="register-username" class="register-input" placeholder="Username">
                    <span id="register-username-error" class="field-error"></span>
                </div>

                <!-- Champ mot de passe + checklist des règles (validées en JS) -->
                <div class="form-field password-field">
                    <label for="register-password">Password</label>
                    <input type="password" name="password" id="register-password" autocomplete="new-password" class="register-input" placeholder="Password">
                    <span id="register-password-error" class="field-error"></span>
                    <div class="password-helper">
                        <div class="password-rules">
                            <span id="password-rules-icon" class="password-rules-icon">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye">
                                    <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                                    <path d="M12 10v4" />
                                    <path d="M12 12h.01" />
                                </svg>
                            </span>
                            <div class="password-rules-list">
                                <ul>
                                    <li id="password-rule-length">At least 8 characters long</li>
                                    <li id="password-rule-uppercase">At least one uppercase letter</li>
                                    <li id="password-rule-lowercase">At least one lowercase letter</li>
                                    <li id="password-rule-number">At least one number</li>
                                    <li id="password-rule-special">At least one special character</li>
                                </ul>
                            </div>
                        </div>
                        <button type="button" class="password-toggle closed" aria-label="Show password">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye">
                                <path class="lid-top" d="M1 12s4-8 11-8 11 8 11 8" />
                                <circle cx="12" cy="12" r="3" />
                                <path class="lid-bottom" d="M23 12s-4 8-11 8-11-8-11-8" />
                            </svg>
                        </button>
                    </div>
                </div>

                <!-- Confirmation du mot de passe -->
                <div class="form-field password-field">
                    <label for="register-confirm-password">Confirm Password</label>
                    <input type="password" name="confirm-password" id="register-confirm-password" class="register-input" placeholder="Confirm Password">
                    <span id="register-confirm-password-error" class="field-error"></span>
                    <button type="button" class="password-toggle closed" aria-label="Show password">
                        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-eye">
                            <path class="lid-top" d="M1 12s4-8 11-8 11 8 11 8" />
                            <circle cx="12" cy="12" r="3" />
                            <path class="lid-bottom" d="M23 12s-4 8-11 8-11-8-11-8" />
                        </svg>
                    </button>
                </div>
                <input type="hidden" name="form_submitted_value" value="register">
                <button type="submit" id="register-submit" class="auth-submit">Register</button>
            </form>
        <?php endif; ?>
    </main>

</body>
<footer>
    <p>© 2026 Login Page. All rights reserved.</p>
</footer>

<!-- Scripts auth : validation commune + feedback + toggle œil + login ou register -->
<script src="script/common/validation.js"></script>
<script src="script/auth/feedback.js"></script>
<script src="script/auth/password-toggle.js"></script>
<?php if (!$register_page): ?>
    <script src="script/auth/login.js"></script>
<?php endif; ?>
<?php if ($register_page): ?>
    <script src="script/auth/register.js"></script>
<?php endif; ?>
