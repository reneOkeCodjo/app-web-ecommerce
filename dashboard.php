<?php
session_start();

if (empty($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit;
}

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php?page=login');
    exit;
}
$is_admin = $_SESSION['compte_id'] === 1;

$page = isset($_GET['page']) ? $_GET['page'] : 'dashboard';

if ($page === 'users') {
    $title = 'Manage Users';
} elseif ($page === 'products') {
    $title = 'Manage Products';
} else {
    $title = 'Dashboard';
}

$pdo = null;
$result = null;
$comptes = [];
$categories = [];
$category_filter = trim($_GET['category'] ?? '');
$feedback = '';

$categories_file = __DIR__ . '/product_categories.json';
if (is_readable($categories_file)) {
    $decoded_categories = json_decode(file_get_contents($categories_file), true);
    if (is_array($decoded_categories)) {
        $categories = $decoded_categories;
    }
}

$db_host = getenv('DB_HOST');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_password = getenv('DB_PASSWORD');
$db_port = getenv('DB_PORT');

try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name", $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if ($page === 'users' && isset($_POST['form_submitted_value'])) {
        $form_action = $_POST['form_submitted_value'];
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $compte_id = (int) ($_POST['compte_id'] ?? 0);
        $user_id = (int) ($_POST['user_id'] ?? 0);

        $needs_fields = $form_action === 'add_user' || $form_action === 'update_user';
        $needs_id = $form_action === 'update_user' || $form_action === 'delete_user';
        $needs_password = $form_action === 'add_user';
        $fields_ok = !$needs_fields || ($username !== '' && $email !== '' && $compte_id > 0);
        $password_ok = !$needs_password || $password !== '';
        $id_ok = !$needs_id || $user_id > 0;

        if (!$fields_ok) {
            $feedback = 'Username, email and compte are required';
        } elseif (!$password_ok) {
            $feedback = 'Password is required';
        } elseif (!$id_ok) {
            $feedback = 'Invalid user id';
        } else {
            switch ($form_action) {
                case 'add_user':
                    $stmt = $pdo->prepare(
                        'INSERT INTO user (user_login, user_password, user_mail, user_compte_id)
                         VALUES (:username, :password, :email, :compte_id)'
                    );
                    $stmt->execute([
                        'username' => $username,
                        'password' => password_hash($password, PASSWORD_DEFAULT),
                        'email' => $email,
                        'compte_id' => $compte_id,
                    ]);
                    break;
                case 'update_user':
                    if ($password !== '') {
                        $stmt = $pdo->prepare(
                            'UPDATE user
                             SET user_login = :username,
                                 user_mail = :email,
                                 user_compte_id = :compte_id,
                                 user_password = :password
                             WHERE user_id = :user_id'
                        );
                        $stmt->execute([
                            'username' => $username,
                            'email' => $email,
                            'compte_id' => $compte_id,
                            'password' => password_hash($password, PASSWORD_DEFAULT),
                            'user_id' => $user_id,
                        ]);
                    } else {
                        $stmt = $pdo->prepare(
                            'UPDATE user
                             SET user_login = :username,
                                 user_mail = :email,
                                 user_compte_id = :compte_id
                             WHERE user_id = :user_id'
                        );
                        $stmt->execute([
                            'username' => $username,
                            'email' => $email,
                            'compte_id' => $compte_id,
                            'user_id' => $user_id,
                        ]);
                    }
                    break;
                case 'delete_user':
                    $stmt = $pdo->prepare('DELETE FROM user WHERE user_id = :user_id');
                    $stmt->execute(['user_id' => $user_id]);
                    break;
            }

            if (in_array($form_action, ['add_user', 'update_user', 'delete_user'], true)) {
                header('Location: dashboard.php?page=users');
                exit;
            }
        }
    }

    if ($page === 'products' && isset($_POST['form_submitted_value'])) {
        $form_action = $_POST['form_submitted_value'];
        $type = trim($_POST['type_p'] ?? '');
        $designation = trim($_POST['designation_p'] ?? '');
        $prix_ht = $_POST['prix_ht'] ?? '';
        $date_in = $_POST['date_in'] ?? '';
        $stock_p = (int) ($_POST['stock_p'] ?? 0);
        $product_id = (int) ($_POST['id_p'] ?? 0);

        $needs_fields = $form_action === 'add_product' || $form_action === 'update_product';
        $needs_id = $form_action === 'update_product' || $form_action === 'delete_product';
        $fields_ok = !$needs_fields || ($type !== '' && $designation !== '' && $prix_ht !== '' && $date_in !== '');
        $id_ok = !$needs_id || $product_id > 0;

        if (!$fields_ok) {
            $feedback = 'All product fields are required';
        } elseif (!$id_ok) {
            $feedback = 'Invalid product id';
        } else {
            $product_params = [
                'type_p' => $type,
                'designation_p' => $designation,
                'prix_ht' => $prix_ht,
                'date_in' => $date_in,
                'stock_p' => $stock_p,
            ];

            switch ($form_action) {
                case 'add_product':
                    $stmt = $pdo->prepare(
                        'INSERT INTO product (type_p, designation_p, prix_ht, date_in, stock_p)
                         VALUES (:type_p, :designation_p, :prix_ht, :date_in, :stock_p)'
                    );
                    $stmt->execute($product_params);
                    break;
                case 'update_product':
                    $stmt = $pdo->prepare(
                        'UPDATE product
                         SET type_p = :type_p, designation_p = :designation_p, prix_ht = :prix_ht,
                             date_in = :date_in, stock_p = :stock_p
                         WHERE id_p = :id_p'
                    );
                    $stmt->execute($product_params + ['id_p' => $product_id]);
                    break;
                case 'delete_product':
                    $stmt = $pdo->prepare('DELETE FROM product WHERE id_p = :id_p');
                    $stmt->execute(['id_p' => $product_id]);
                    break;
            }

            if (in_array($form_action, ['add_product', 'update_product', 'delete_product'], true)) {
                header('Location: dashboard.php?page=products');
                exit;
            }
        }
    }


    if ($page === 'users') {
        $comptes = $pdo->query('SELECT compte_id as id, compte_name as name FROM compte ORDER BY compte_id')->fetchAll(PDO::FETCH_ASSOC);
    }

    $stmt = null;
    switch ($page) {
        case 'users':
            $stmt = $pdo->prepare(
                'SELECT u.user_id as id, u.user_login as name, u.user_mail as email,
                        u.user_compte_id as compte_id, c.compte_name as compte
                 FROM user u
                 INNER JOIN compte c ON c.compte_id = u.user_compte_id
                 ORDER BY u.user_id DESC'
            );
            break;
        case 'products':
            if ($category_filter !== '') {
                $stmt = $pdo->prepare(
                    'SELECT id_p as id, designation_p as name, prix_ht as price, date_in as date, type_p as type, stock_p as stock
                     FROM product
                     WHERE type_p = :category
                     ORDER BY id_p DESC'
                );
                $stmt->execute(['category' => $category_filter]);
            } else {
                $stmt = $pdo->prepare(
                    'SELECT id_p as id, designation_p as name, prix_ht as price, date_in as date, type_p as type, stock_p as stock
                     FROM product
                     ORDER BY id_p DESC'
                );
                $stmt->execute();
            }
            $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $stmt = null;
            break;
    }
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $feedback = 'Error: ' . $e->getMessage();
}
?>

<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $title; ?></title>
    <link rel="stylesheet" href="./style/common.css">
    <link rel="stylesheet" href="./style/dashboard/index.css">
</head>

<body>
    <nav id="dashboard-nav" class="dashboard-nav">
        <a href="/dashboard.php?action=logout" id="logout-link" class="nav-link logout-link">Logout</a>
    </nav>
    <!-- Sidebar overlay -->
    <div id="sidebar-overlay" class="sidebar-overlay" aria-hidden="true"></div>
    <!-- Sidebar -->
    <aside id="dashboard-sidebar" class="dashboard-sidebar">
        <!-- Sidebar menu -->
        <ul id="sidebar-menu" class="sidebar-menu">
            <li class="sidebar-item">
                <a id="toggle-btn" class="sidebar-link toggle-btn" href="javascript:void(0);">
                    <!-- Toggle icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-toggle-right">
                        <rect width="18" height="18" x="3" y="3" rx="2" ry="2" />
                        <path d="M12 3v18" />
                    </svg>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="/dashboard.php?page=users" id="users-link" class="sidebar-link users-link">
                    <!-- User icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                        <circle cx="12" cy="7" r="4" />
                    </svg>
                    <span class="sidebar-text closed">Manage Users</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="/dashboard.php?page=products" id="products-link" class="sidebar-link products-link">
                    <!-- Product icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-product">
                        <rect width="20" height="14" x="2" y="5" rx="2" />
                        <line x1="2" y1="10" x2="22" y2="10" />
                    </svg>
                    <span class="sidebar-text closed">Manage Products</span>
                </a>
            </li>
        </ul>

        <!-- Account menu -->
        <ul>
            <li>
                <a href="/dashboard.php?page=users" id="users-link" class="sidebar-link users-link">
                    <!-- Account icon -->
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-account">
                        <path d="M12 12m-9 0a9 9 0 1 0 18 0a9 9 0 1 0 -18 0" />
                        <path d="M12 10m-3 0a3 3 0 1 0 6 0a3 3 0 1 0 -6 0" />
                        <path d="M6.168 18.849a4 4 0 0 1 3.832 -2.849h4a4 4 0 0 1 3.834 2.855" />
                    </svg>
                    <span class="sidebar-text closed">Account</span>
                </a>
            </li>
        </ul>
    </aside>
    <main>
        <?php if ($feedback !== ''): ?>
            <p id="dashboard-feedback" class="dashboard-feedback"><?php echo htmlspecialchars($feedback); ?></p>
        <?php endif; ?>

        <?php if ($page === 'users'): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Email</th>
                        <th>Compte</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result): ?>
                        <?php foreach ($result as $row): ?>
                            <tr
                                class="user-row"
                                data-id="<?php echo htmlspecialchars($row['id']); ?>"
                                data-name="<?php echo htmlspecialchars($row['name']); ?>"
                                data-email="<?php echo htmlspecialchars($row['email']); ?>"
                                data-compte-id="<?php echo htmlspecialchars($row['compte_id']); ?>"
                                data-compte="<?php echo htmlspecialchars($row['compte']); ?>">
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['email']); ?></td>
                                <td><?php echo htmlspecialchars($row['compte']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <button type="button" class="btn btn-primary" id="add-user-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus">
                    <path d="M12 5v14" />
                    <path d="M5 12h14" />
                </svg>
                <span class="btn-text closed">Add User</span>
            </button>

            <dialog id="add-user-dialog" class="product-dialog">
                <form method="post" id="add-user-form" class="auth-form product-form">
                    <h2>Add User</h2>

                    <div class="form-field">
                        <label for="user_username">Username</label>
                        <input type="text" name="username" id="user_username" placeholder="Username" required>
                    </div>

                    <div class="form-field">
                        <label for="user_email">Email</label>
                        <input type="email" name="email" id="user_email" placeholder="Email" required>
                    </div>

                    <div class="form-field">
                        <label for="user_password">Password</label>
                        <input type="password" name="password" id="user_password" placeholder="Password" required>
                    </div>

                    <div class="form-field">
                        <!-- <label for="user_compte_id">Compte</label> -->
                        <select name="compte_id" id="user_compte_id" required>
                            <option value="">Select compte</option>
                            <?php foreach ($comptes as $compte): ?>
                                <option value="<?php echo htmlspecialchars($compte['id']); ?>">
                                    <?php echo htmlspecialchars($compte['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <input type="hidden" name="form_submitted_value" value="add_user">

                    <div class="dialog-actions">
                        <button type="button" id="cancel-user-btn" class="btn">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </dialog>

            <dialog id="user-detail-dialog" class="product-dialog">
                <form method="post" id="user-detail-form" class="auth-form product-form">
                    <h2 id="user-detail-title">User details</h2>

                    <input type="hidden" name="user_id" id="detail_user_id" value="">
                    <input type="hidden" name="form_submitted_value" id="detail_user_form_action" value="update_user">

                    <div class="form-field">
                        <label for="detail_user_username">Username</label>
                        <input type="text" name="username" id="detail_user_username" placeholder="Username" required readonly>
                    </div>

                    <div class="form-field">
                        <label for="detail_user_email">Email</label>
                        <input type="email" name="email" id="detail_user_email" placeholder="Email" required readonly>
                    </div>

                    <div class="form-field">
                        <label for="detail_user_password">Password</label>
                        <input type="password" name="password" id="detail_user_password" placeholder="Leave blank to keep" readonly>
                    </div>

                    <div class="form-field">
                        <!-- <label for="detail_user_compte_id">Compte</label> -->
                        <select name="compte_id" id="detail_user_compte_id" required disabled>
                            <?php foreach ($comptes as $compte): ?>
                                <option value="<?php echo htmlspecialchars($compte['id']); ?>">
                                    <?php echo htmlspecialchars($compte['name']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="dialog-actions" id="user-detail-actions-view">
                        <button type="button" id="close-user-detail-btn" class="btn">Close</button>
                        <button type="button" id="edit-user-btn" class="btn btn-primary">Update</button>
                        <button type="button" id="delete-user-btn" class="btn btn-danger">Delete</button>
                    </div>

                    <div class="dialog-actions hidden" id="user-detail-actions-edit">
                        <button type="button" id="cancel-edit-user-btn" class="btn">Cancel</button>
                        <button type="submit" id="save-user-btn" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </dialog>

            <dialog id="delete-user-confirm-dialog" class="product-dialog confirm-dialog">
                <div class="confirm-dialog-content">
                    <h2>Delete user</h2>
                    <p id="delete-user-confirm-message">Are you sure you want to delete this user?</p>
                    <div class="dialog-actions">
                        <button type="button" id="cancel-delete-user-btn" class="btn">Cancel</button>
                        <button type="button" id="confirm-delete-user-btn" class="btn btn-danger">Delete</button>
                    </div>
                </div>
            </dialog>
        <?php endif; ?>

        <?php if ($page === 'products'): ?>
            <form method="get" id="product-category-filter" class="category-filter">
                <input type="hidden" name="page" value="products">
                <label for="category">Category</label>
                <select name="category" id="category" onchange="this.form.submit()">
                    <option value="">All categories</option>
                    <?php foreach ($categories as $category): ?>
                        <option
                            value="<?php echo htmlspecialchars($category['categorie']); ?>"
                            <?php echo $category_filter === $category['categorie'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($category['categorie']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </form>

            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category</th>
                        <th>Name</th>
                        <th>Price HT</th>
                        <th>Date</th>
                        <th>Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result): ?>
                        <?php foreach ($result as $row): ?>
                            <tr
                                class="product-row"
                                data-id="<?php echo htmlspecialchars($row['id']); ?>"
                                data-type="<?php echo htmlspecialchars($row['type']); ?>"
                                data-name="<?php echo htmlspecialchars($row['name']); ?>"
                                data-price="<?php echo htmlspecialchars($row['price']); ?>"
                                data-date="<?php echo htmlspecialchars($row['date']); ?>"
                                data-stock="<?php echo htmlspecialchars($row['stock']); ?>">
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['type']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td><?php echo htmlspecialchars($row['price']); ?></td>
                                <td><?php echo htmlspecialchars($row['date']); ?></td>
                                <td><?php echo htmlspecialchars($row['stock']); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <button type="button" class="btn btn-primary" id="add-product-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus">
                    <path d="M12 5v14" />
                    <path d="M5 12h14" />
                </svg>
                <span class="btn-text closed">Add Product</span>
            </button>

            <dialog id="add-product-dialog" class="product-dialog">
                <form method="post" id="add-product-form" class="auth-form product-form">
                    <h2>Add Product</h2>

                    <div class="form-field">
                        <label for="type_p">Product type</label>
                        <select name="type_p" id="type_p" required>
                            <option value="">Select type</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['categorie']); ?>">
                                    <?php echo htmlspecialchars($category['categorie']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="designation_p">Designation</label>
                        <input type="text" name="designation_p" id="designation_p" placeholder="Designation" required>
                    </div>

                    <div class="form-field">
                        <label for="prix_ht">Price HT</label>
                        <input type="number" name="prix_ht" id="prix_ht" step="0.01" min="0" placeholder="0.00" required>
                    </div>

                    <div class="form-field">
                        <label for="date_in">Date</label>
                        <input type="date" name="date_in" id="date_in" required>
                    </div>

                    <div class="form-field">
                        <label for="stock_p">Stock</label>
                        <input type="number" name="stock_p" id="stock_p" min="0" value="0" placeholder="0">
                    </div>

                    <input type="hidden" name="form_submitted_value" value="add_product">

                    <div class="dialog-actions">
                        <button type="button" id="cancel-product-btn" class="btn">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </dialog>

            <dialog id="product-detail-dialog" class="product-dialog">
                <form method="post" id="product-detail-form" class="auth-form product-form">
                    <h2 id="product-detail-title">Product details</h2>

                    <input type="hidden" name="id_p" id="detail_id_p" value="">
                    <input type="hidden" name="form_submitted_value" id="detail_form_action" value="update_product">

                    <div class="form-field">
                        <label for="detail_type_p">Product type</label>
                        <select name="type_p" id="detail_type_p" required disabled>
                            <option value="">Select type</option>
                            <?php foreach ($categories as $category): ?>
                                <option value="<?php echo htmlspecialchars($category['categorie']); ?>">
                                    <?php echo htmlspecialchars($category['categorie']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <div class="form-field">
                        <label for="detail_designation_p">Designation</label>
                        <input type="text" name="designation_p" id="detail_designation_p" placeholder="Designation" required readonly>
                    </div>

                    <div class="form-field">
                        <label for="detail_prix_ht">Price HT</label>
                        <input type="number" name="prix_ht" id="detail_prix_ht" step="0.01" min="0" placeholder="0.00" required readonly>
                    </div>

                    <div class="form-field">
                        <label for="detail_date_in">Date</label>
                        <input type="date" name="date_in" id="detail_date_in" required readonly>
                    </div>

                    <div class="form-field">
                        <label for="detail_stock_p">Stock</label>
                        <input type="number" name="stock_p" id="detail_stock_p" min="0" placeholder="0" readonly>
                    </div>

                    <div class="dialog-actions" id="product-detail-actions-view">
                        <button type="button" id="close-product-detail-btn" class="btn">Close</button>
                        <button type="button" id="edit-product-btn" class="btn btn-primary">Update</button>
                        <button type="button" id="delete-product-btn" class="btn btn-danger">Delete</button>
                    </div>

                    <div class="dialog-actions hidden" id="product-detail-actions-edit">
                        <button type="button" id="cancel-edit-product-btn" class="btn">Cancel</button>
                        <button type="submit" id="save-product-btn" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </dialog>

            <dialog id="delete-confirm-dialog" class="product-dialog confirm-dialog">
                <div class="confirm-dialog-content">
                    <h2>Delete product</h2>
                    <p id="delete-confirm-message">Are you sure you want to delete this product?</p>
                    <div class="dialog-actions">
                        <button type="button" id="cancel-delete-btn" class="btn">Cancel</button>
                        <button type="button" id="confirm-delete-btn" class="btn btn-danger">Delete</button>
                    </div>
                </div>
            </dialog>
        <?php endif; ?>
    </main>
</body>

<footer>
    <p>© 2026 Login Page. All rights reserved.</p>
</footer>

<script>
    const toggleBtn = document.querySelector('#toggle-btn');
    const sidebar = document.querySelector('#dashboard-sidebar');
    const sidebarOverlay = document.querySelector('#sidebar-overlay');
    const sidebarTexts = document.querySelectorAll('.sidebar-text');

    const setSidebarOpen = (isOpen) => {
        sidebarTexts.forEach((text) => {
            text.classList.toggle('closed', !isOpen);
        });
        sidebar.classList.toggle('expanded', isOpen);
        sidebarOverlay.classList.toggle('open', isOpen);
        sidebarOverlay.setAttribute('aria-hidden', String(!isOpen));
    };

    toggleBtn.addEventListener('click', () => {
        const isOpen = !sidebar.classList.contains('expanded');
        setSidebarOpen(isOpen);
    });

    sidebarOverlay.addEventListener('click', () => {
        setSidebarOpen(false);
    });
</script>
<!-- Add Product -->
<script>
    const addProductBtn = document.querySelector('#add-product-btn');
    const addProductDialog = document.querySelector('#add-product-dialog');
    const cancelProductBtn = document.querySelector('#cancel-product-btn');

    if (addProductBtn && addProductDialog) {
        addProductBtn.addEventListener('click', () => {
            addProductDialog.showModal();
        });

        cancelProductBtn?.addEventListener('click', () => {
            addProductDialog.close();
        });

        addProductDialog.addEventListener('click', (event) => {
            if (event.target === addProductDialog) {
                addProductDialog.close();
            }
        });
    }

    const addUserBtn = document.querySelector('#add-user-btn');
    const addUserDialog = document.querySelector('#add-user-dialog');
    const cancelUserBtn = document.querySelector('#cancel-user-btn');

    if (addUserBtn && addUserDialog) {
        addUserBtn.addEventListener('click', () => {
            addUserDialog.showModal();
        });

        cancelUserBtn?.addEventListener('click', () => {
            addUserDialog.close();
        });

        addUserDialog.addEventListener('click', (event) => {
            if (event.target === addUserDialog) {
                addUserDialog.close();
            }
        });
    }
</script>

<!-- Product Detail -->
<script>
    const productDetailDialog = document.querySelector('#product-detail-dialog');
    const productDetailForm = document.querySelector('#product-detail-form');
    const productDetailTitle = document.querySelector('#product-detail-title');
    const detailActionInput = document.querySelector('#detail_form_action');
    const detailTypeSelect = document.querySelector('#detail_type_p');
    const detailFields = [
        document.querySelector('#detail_designation_p'),
        document.querySelector('#detail_prix_ht'),
        document.querySelector('#detail_date_in'),
        document.querySelector('#detail_stock_p'),
    ];
    const viewActions = document.querySelector('#product-detail-actions-view');
    const editActions = document.querySelector('#product-detail-actions-edit');
    const editProductBtn = document.querySelector('#edit-product-btn');
    const deleteProductBtn = document.querySelector('#delete-product-btn');
    const closeProductDetailBtn = document.querySelector('#close-product-detail-btn');
    const cancelEditProductBtn = document.querySelector('#cancel-edit-product-btn');
    const deleteConfirmDialog = document.querySelector('#delete-confirm-dialog');
    const deleteConfirmMessage = document.querySelector('#delete-confirm-message');
    const confirmDeleteBtn = document.querySelector('#confirm-delete-btn');
    const cancelDeleteBtn = document.querySelector('#cancel-delete-btn');
    const productRows = document.querySelectorAll('.product-row');

    let currentProduct = null;

    const setDetailMode = (mode) => {
        const isEdit = mode === 'edit';
        detailFields.forEach((field) => {
            if (!field) return;
            field.readOnly = !isEdit;
        });
        if (detailTypeSelect) {
            detailTypeSelect.disabled = !isEdit;
        }
        viewActions?.classList.toggle('hidden', isEdit);
        editActions?.classList.toggle('hidden', !isEdit);
        productDetailTitle.textContent = isEdit ? 'Update product' : 'Product details';
        detailActionInput.value = 'update_product';
        productDetailForm?.classList.toggle('editing', isEdit);
    };

    const fillProductDetail = (product) => {
        document.querySelector('#detail_id_p').value = product.id;
        if (detailTypeSelect) {
            const hasOption = [...detailTypeSelect.options].some((option) => option.value === product.type);
            if (product.type && !hasOption) {
                const option = document.createElement('option');
                option.value = product.type;
                option.textContent = product.type;
                detailTypeSelect.appendChild(option);
            }
            detailTypeSelect.value = product.type;
        }
        document.querySelector('#detail_designation_p').value = product.name;
        document.querySelector('#detail_prix_ht').value = product.price;
        document.querySelector('#detail_date_in').value = product.date;
        document.querySelector('#detail_stock_p').value = product.stock;
    };

    productDetailForm?.addEventListener('submit', () => {
        if (detailTypeSelect) {
            detailTypeSelect.disabled = false;
        }
    });

    const openProductDetail = (row) => {
        currentProduct = {
            id: row.dataset.id,
            type: row.dataset.type,
            name: row.dataset.name,
            price: row.dataset.price,
            date: row.dataset.date,
            stock: row.dataset.stock,
        };
        fillProductDetail(currentProduct);
        setDetailMode('view');
        productDetailDialog.showModal();
    };

    productRows.forEach((row) => {
        row.addEventListener('click', () => openProductDetail(row));
    });

    editProductBtn?.addEventListener('click', () => {
        setDetailMode('edit');
    });

    cancelEditProductBtn?.addEventListener('click', () => {
        if (currentProduct) {
            fillProductDetail(currentProduct);
        }
        setDetailMode('view');
    });

    closeProductDetailBtn?.addEventListener('click', () => {
        productDetailDialog.close();
    });

    deleteProductBtn?.addEventListener('click', () => {
        if (!currentProduct || !deleteConfirmDialog) return;
        deleteConfirmMessage.textContent =
            `Are you sure you want to delete "${currentProduct.name}" (#${currentProduct.id})? This cannot be undone.`;
        deleteConfirmDialog.showModal();
    });

    cancelDeleteBtn?.addEventListener('click', () => {
        deleteConfirmDialog?.close();
    });

    confirmDeleteBtn?.addEventListener('click', () => {
        if (!currentProduct) return;
        detailActionInput.value = 'delete_product';
        deleteConfirmDialog?.close();
        productDetailForm.submit();
    });

    deleteConfirmDialog?.addEventListener('click', (event) => {
        if (event.target === deleteConfirmDialog) {
            deleteConfirmDialog.close();
        }
    });

    productDetailDialog?.addEventListener('click', (event) => {
        if (event.target === productDetailDialog) {
            productDetailDialog.close();
        }
    });

    productDetailDialog?.addEventListener('close', () => {
        setDetailMode('view');
    });
</script>

<!-- User Detail -->
<script>
    const userDetailDialog = document.querySelector('#user-detail-dialog');
    const userDetailForm = document.querySelector('#user-detail-form');
    const userDetailTitle = document.querySelector('#user-detail-title');
    const detailUserActionInput = document.querySelector('#detail_user_form_action');
    const detailUserFields = [
        document.querySelector('#detail_user_username'),
        document.querySelector('#detail_user_email'),
        document.querySelector('#detail_user_password'),
    ];
    const detailUserCompte = document.querySelector('#detail_user_compte_id');
    const userViewActions = document.querySelector('#user-detail-actions-view');
    const userEditActions = document.querySelector('#user-detail-actions-edit');
    const editUserBtn = document.querySelector('#edit-user-btn');
    const deleteUserBtn = document.querySelector('#delete-user-btn');
    const closeUserDetailBtn = document.querySelector('#close-user-detail-btn');
    const cancelEditUserBtn = document.querySelector('#cancel-edit-user-btn');
    const deleteUserConfirmDialog = document.querySelector('#delete-user-confirm-dialog');
    const deleteUserConfirmMessage = document.querySelector('#delete-user-confirm-message');
    const confirmDeleteUserBtn = document.querySelector('#confirm-delete-user-btn');
    const cancelDeleteUserBtn = document.querySelector('#cancel-delete-user-btn');
    const userRows = document.querySelectorAll('.user-row');

    let currentUser = null;

    const setUserDetailMode = (mode) => {
        const isEdit = mode === 'edit';
        detailUserFields.forEach((field) => {
            if (!field) return;
            field.readOnly = !isEdit;
        });
        if (detailUserCompte) {
            detailUserCompte.disabled = !isEdit;
        }
        userViewActions?.classList.toggle('hidden', isEdit);
        userEditActions?.classList.toggle('hidden', !isEdit);
        userDetailTitle.textContent = isEdit ? 'Update user' : 'User details';
        detailUserActionInput.value = 'update_user';
        userDetailForm?.classList.toggle('editing', isEdit);
    };

    const fillUserDetail = (user) => {
        document.querySelector('#detail_user_id').value = user.id;
        document.querySelector('#detail_user_username').value = user.name;
        document.querySelector('#detail_user_email').value = user.email;
        document.querySelector('#detail_user_password').value = '';
        if (detailUserCompte) {
            detailUserCompte.value = user.compteId;
        }
    };

    const openUserDetail = (row) => {
        currentUser = {
            id: row.dataset.id,
            name: row.dataset.name,
            email: row.dataset.email,
            compteId: row.dataset.compteId,
            compte: row.dataset.compte,
        };
        fillUserDetail(currentUser);
        setUserDetailMode('view');
        userDetailDialog.showModal();
    };

    userRows.forEach((row) => {
        row.addEventListener('click', () => openUserDetail(row));
    });

    editUserBtn?.addEventListener('click', () => {
        setUserDetailMode('edit');
    });

    cancelEditUserBtn?.addEventListener('click', () => {
        if (currentUser) {
            fillUserDetail(currentUser);
        }
        setUserDetailMode('view');
    });

    closeUserDetailBtn?.addEventListener('click', () => {
        userDetailDialog.close();
    });

    deleteUserBtn?.addEventListener('click', () => {
        if (!currentUser || !deleteUserConfirmDialog) return;
        deleteUserConfirmMessage.textContent =
            `Are you sure you want to delete "${currentUser.name}" (#${currentUser.id})? This cannot be undone.`;
        deleteUserConfirmDialog.showModal();
    });

    cancelDeleteUserBtn?.addEventListener('click', () => {
        deleteUserConfirmDialog?.close();
    });

    confirmDeleteUserBtn?.addEventListener('click', () => {
        if (!currentUser) return;
        detailUserActionInput.value = 'delete_user';
        deleteUserConfirmDialog?.close();
        userDetailForm.submit();
    });

    deleteUserConfirmDialog?.addEventListener('click', (event) => {
        if (event.target === deleteUserConfirmDialog) {
            deleteUserConfirmDialog.close();
        }
    });

    userDetailDialog?.addEventListener('click', (event) => {
        if (event.target === userDetailDialog) {
            userDetailDialog.close();
        }
    });

    userDetailDialog?.addEventListener('close', () => {
        setUserDetailMode('view');
    });
</script>

</html>