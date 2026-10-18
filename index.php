<?php
session_start();
$feedback = '';
$page = $_GET['page'] ?? 'login';
$register_page = $page === 'register';

if (isset($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

if (isset($_POST['form_submitted_value'])) {
    $form_submitted_value = $_POST['form_submitted_value'];

    // Connect to the database using environment variables
    $db_host = getenv('DB_HOST');
    $db_name = getenv('DB_NAME');
    $db_user = getenv('DB_USER');
    $db_password = getenv('DB_PASSWORD');
    $db_port = getenv('DB_PORT');
    $dsn = "mysql:host=$db_host;dbname=$db_name;port=$db_port";

    // Try to connect to the database
    try {
        $pdo = new PDO($dsn, $db_user, $db_password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $username = $_POST['username'];
        $password = $_POST['password'];

        // Prepare the query to select the user from the database
        $query = "SELECT user_id as id, user_compte_id as compte_id, user_password as password FROM user WHERE user_login = :username";
        $stmt = $pdo->prepare($query);
        $stmt->execute([
            'username' => $username,
        ]);

        // Fetch the user from the database
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // If the user is found, verify the password
        if ($user) {
            if ($form_submitted_value == 'login') {
                $is_valid_password = password_verify($password, $user['password']);
                if ($is_valid_password) {
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['compte_id'] = $user['compte_id'];
                    header('Location: dashboard.php');
                    exit;
                }
            } else if ($form_submitted_value == 'register') {
                $feedback = 'User already exists';
            }
        } else {
            if ($form_submitted_value == 'register') {

                $password = password_hash($password, PASSWORD_DEFAULT);
                $email = $_POST['email'];
                $compte_id = 2;

                // Prepare the query to insert the user into the database
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
            // If the user is not found, show an error message
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
        <span id="feedback-server"></span>
        <span id="feedback-message"></span>

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

        <?php if ($register_page): ?>
            <form id="register-form" class="auth-form" method="post">
                <!-- Email field -->
                <div class="form-field">
                    <label for="register-email">Email</label>
                    <input type="email" name="email" id="register-email" class="register-input" placeholder="example@domain.com">
                    <span id="register-email-error" class="field-error"></span>
                </div>

                <!-- Username field -->
                <div class="form-field">
                    <label for="register-username">Username</label>
                    <input type="text" name="username" id="register-username" class="register-input" placeholder="Username">
                    <span id="register-username-error" class="field-error"></span>
                </div>
                <!-- Password field -->
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
                <!-- Confirm password field -->
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

<script>
    window.addEventListener('load', () => {
        const feedbackServer = document.querySelector('#feedback-server');
        const server_message = '<?php echo $feedback; ?>';
        if (server_message && feedbackServer) {
            feedbackServer.innerHTML = server_message;
            feedbackServer.classList.add('show');
        }
    });
</script>

<script>
    // Login script
    const form = document.querySelector('form');
    if (form) {
        const formPasswordField = form.querySelectorAll('div.form-field:has(input[name$="password"])');
        formPasswordField.forEach(field => {
            const passwordToggle = field.querySelector('.password-toggle');
            passwordToggle.addEventListener('click', () => {
                field.querySelector('input').type = field.querySelector('input').type === 'password' ? 'text' : 'password';
                passwordToggle.classList.toggle('closed');
            });
        });
    }
</script>

<?php if (!$register_page): ?>
    <script>
        const loginForm = document.querySelector('#login-form');
        const loginUsernameInput = document.querySelector('#login-username');
        const loginPasswordField = document.querySelector('#login-password');
        const feedbackMessage = document.querySelector('#feedback-message');
        const feedbackServer = document.querySelector('#feedback-server');

        loginForm.addEventListener('submit', (event) => {
            event.preventDefault();
            const username = loginUsernameInput.value.trim();
            const password = loginPasswordField.value.trim();
            if (!username || !password) {
                feedbackMessage.innerHTML = 'Username and password are required';
                feedbackMessage.classList.add('show');
                feedbackMessage.style.color = 'red';
                feedbackServer.classList.remove('show');
                return;
            }
            loginForm.submit();
        });
    </script>
<?php endif; ?>

<?php if ($register_page): ?>
    <script>
        const registerForm = document.querySelector('#register-form');
        const passwordInput = registerForm.querySelector('input[name="password"]');
        const confirmInput = registerForm.querySelector('input[name="confirm-password"]');
        const emailInput = registerForm.querySelector('input[name="email"]');

        const passwordRules = [{
                id: 'password-rule-length',
                label: 'At least 8 characters long',
                test: v => v.length >= 8
            },
            {
                id: 'password-rule-uppercase',
                label: 'At least one uppercase letter',
                test: v => /[A-Z]/.test(v)
            },
            {
                id: 'password-rule-lowercase',
                label: 'At least one lowercase letter',
                test: v => /[a-z]/.test(v)
            },
            {
                id: 'password-rule-number',
                label: 'At least one number',
                test: v => /[0-9]/.test(v)
            },
            {
                id: 'password-rule-special',
                label: 'At least one special character',
                test: v => /[!@#$%^&*]/.test(v)
            },
        ];

        const setFieldState = (input, message = '') => {
            const error = input.closest('.form-field').querySelector('.field-error');
            input.style.borderColor = message ? 'red' : 'green';
            error.textContent = message;
        };

        const getFailedPasswordRules = (value) =>
            passwordRules.filter(rule => !rule.test(value));

        const syncPasswordRules = (value) => {
            passwordRules.forEach(rule => {
                const item = registerForm.querySelector(`#${rule.id}`);
                if (!item) return;
                item.style.color = rule.test(value) ? 'green' : 'red';
            });
        };

        const syncEmailRules = (value) => {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            const emailInput = registerForm.querySelector('#register-email');
            if (!emailInput) return;
            emailInput.style.borderColor = emailRegex.test(value) ? 'green' : 'red';
        };

        emailInput.addEventListener('input', () => {
            syncEmailRules(emailInput.value);
        });


        passwordInput.addEventListener('input', () => {
            syncPasswordRules(passwordInput.value);
            if (!passwordInput.value.trim()) {
                setFieldState(passwordInput, 'This field is required');
                return;
            }
            const failed = getFailedPasswordRules(passwordInput.value);
            setFieldState(passwordInput, failed[0]?.label || '');
            // Password no more match confirm password
            if (passwordInput.value !== confirmInput.value) {
                setFieldState(confirmInput, 'Passwords do not match');
                return;
            }
        });

        confirmInput.addEventListener('input', () => {
            if (!confirmInput.value.trim()) {
                setFieldState(confirmInput, 'This field is required');
                return;
            }
            setFieldState(
                confirmInput,
                confirmInput.value !== passwordInput.value ? 'Passwords do not match' : ''
            );
        });

        registerForm.addEventListener('submit', (event) => {
            event.preventDefault();
            let isValid = true;

            ['email', 'username', 'password', 'confirm-password'].forEach(name => {
                const input = registerForm.querySelector(`input[name="${name}"]`);
                if (!input.value.trim()) {
                    setFieldState(input, 'This field is required');
                    isValid = false;
                    return;
                }

                if (name === 'email') {
                    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                    if (!emailRegex.test(input.value)) {
                        setFieldState(input, 'Invalid email address');
                        isValid = false;
                        return;
                    }
                }

                if (name === 'password') {
                    const failed = getFailedPasswordRules(input.value);
                    syncPasswordRules(input.value);
                    if (failed.length) {
                        setFieldState(input, failed[0].label);
                        isValid = false;
                        return;
                    }
                }

                if (name === 'confirm-password' && input.value !== passwordInput.value) {
                    setFieldState(input, 'Passwords do not match');
                    isValid = false;
                    return;
                }

                setFieldState(input);
            });

            if (isValid) {
                registerForm.submit();
            }
        });
    </script>
<?php endif; ?>