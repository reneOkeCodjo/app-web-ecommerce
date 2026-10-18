<?php
/**
 * Dashboard administrateur / gestion.
 *
 * - Contrôle d'accès (session obligatoire) et déconnexion
 * - CRUD utilisateurs (?page=users) et produits (?page=products)
 * - Filtre catégories produits + promotions (promotion_p 0–100 %)
 */
session_start();

// --- Accès : session obligatoire, sinon redirection login ---
if (empty($_SESSION['user_id'])) {
    header('Location: index.php?page=login');
    exit;
}

// --- Déconnexion ---
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    session_destroy();
    header('Location: index.php?page=login');
    exit;
}

// --- Routage de page et titre ---
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

// --- Catégories produits (JSON) : filtre + selects des formulaires ---
$categories_file = __DIR__ . '/product_categories.json';
if (is_readable($categories_file)) {
    $decoded_categories = json_decode(file_get_contents($categories_file), true);
    if (is_array($decoded_categories)) {
        $categories = $decoded_categories;
    }
}

// --- Connexion BDD (variables d'environnement Docker) ---
$db_host = getenv('DB_HOST');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_password = getenv('DB_PASSWORD');
$db_port = getenv('DB_PORT');

try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name", $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // ========== CRUD utilisateurs (ajout / mise à jour / suppression) ==========
    if ($page === 'users' && isset($_POST['form_submitted_value'])) {
        $form_action = $_POST['form_submitted_value'];
        $username = trim($_POST['username'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $password = $_POST['password'] ?? '';
        $compte_id = (int) ($_POST['compte_id'] ?? 0);
        $user_id = (int) ($_POST['user_id'] ?? 0);

        // Règles de validation selon l'action demandée
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
                    // Création : hash bcrypt du mot de passe
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
                    // Mot de passe vide = conserver le hash existant
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

            // PRG : redirection après succès pour éviter le re-POST
            if (in_array($form_action, ['add_user', 'update_user', 'delete_user'], true)) {
                header('Location: dashboard.php?page=users');
                exit;
            }
        }
    }

    // ========== CRUD produits (ajout / mise à jour / suppression + promotion_p) ==========
    if ($page === 'products' && isset($_POST['form_submitted_value'])) {
        $form_action = $_POST['form_submitted_value'];
        $type = trim($_POST['type_p'] ?? '');
        $designation = trim($_POST['designation_p'] ?? '');
        $prix_ht = $_POST['prix_ht'] ?? '';
        $date_in = $_POST['date_in'] ?? '';
        $stock_p = (int) ($_POST['stock_p'] ?? 0);
        $promotion_p = (int) ($_POST['promotion_p'] ?? 0);
        $product_id = (int) ($_POST['id_p'] ?? 0);

        // Borner le pourcentage de promotion entre 0 et 100
        if ($promotion_p < 0) {
            $promotion_p = 0;
        }
        if ($promotion_p > 100) {
            $promotion_p = 100;
        }

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
                'promotion_p' => $promotion_p,
            ];

            switch ($form_action) {
                case 'add_product':
                    $stmt = $pdo->prepare(
                        'INSERT INTO product (type_p, designation_p, prix_ht, date_in, stock_p, promotion_p)
                         VALUES (:type_p, :designation_p, :prix_ht, :date_in, :stock_p, :promotion_p)'
                    );
                    $stmt->execute($product_params);
                    break;
                case 'update_product':
                    $stmt = $pdo->prepare(
                        'UPDATE product
                         SET type_p = :type_p, designation_p = :designation_p, prix_ht = :prix_ht,
                             date_in = :date_in, stock_p = :stock_p, promotion_p = :promotion_p
                         WHERE id_p = :id_p'
                    );
                    $stmt->execute($product_params + ['id_p' => $product_id]);
                    break;
                case 'delete_product':
                    $stmt = $pdo->prepare('DELETE FROM product WHERE id_p = :id_p');
                    $stmt->execute(['id_p' => $product_id]);
                    break;
            }

            // PRG : redirection après succès
            if (in_array($form_action, ['add_product', 'update_product', 'delete_product'], true)) {
                header('Location: dashboard.php?page=products');
                exit;
            }
        }
    }

    // --- Options de compte pour les selects create/edit utilisateur ---
    if ($page === 'users') {
        $comptes = $pdo->query('SELECT compte_id as id, compte_name as name FROM compte ORDER BY compte_id')->fetchAll(PDO::FETCH_ASSOC);
    }

    // ========== Chargement des listes pour la page courante ==========
    $stmt = null;
    switch ($page) {
        case 'users':
            // Utilisateurs + nom du compte (jointure)
            $stmt = $pdo->prepare(
                'SELECT u.user_id as id, u.user_login as name, u.user_mail as email,
                        u.user_compte_id as compte_id, c.compte_name as compte
                 FROM user u
                 INNER JOIN compte c ON c.compte_id = u.user_compte_id
                 ORDER BY u.user_id DESC'
            );
            break;
        case 'products':
            // Filtre optionnel : ?category= correspond à product.type_p
            if ($category_filter !== '') {
                $stmt = $pdo->prepare(
                    'SELECT id_p as id, designation_p as name, prix_ht as price, date_in as date,
                            type_p as type, stock_p as stock, promotion_p as promotion
                     FROM product
                     WHERE type_p = :category
                     ORDER BY id_p DESC'
                );
                $stmt->execute(['category' => $category_filter]);
            } else {
                $stmt = $pdo->prepare(
                    'SELECT id_p as id, designation_p as name, prix_ht as price, date_in as date,
                            type_p as type, stock_p as stock, promotion_p as promotion
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
    <!-- Barre du haut : déconnexion -->
    <nav id="dashboard-nav" class="dashboard-nav">
        <a href="/dashboard.php?action=logout" id="logout-link" class="nav-link logout-link">Logout</a>
    </nav>

    <!-- Fond assombri quand la barre latérale est ouverte -->
    <div id="sidebar-overlay" class="sidebar-overlay" aria-hidden="true"></div>

    <!-- Barre latérale repliable (utilisateurs / produits / compte) -->
    <aside id="dashboard-sidebar" class="dashboard-sidebar">
        <ul id="sidebar-menu" class="sidebar-menu">
            <!-- Bouton d'ouverture / fermeture de la sidebar -->
            <li class="sidebar-item">
                <a id="toggle-btn" class="sidebar-link toggle-btn" href="javascript:void(0);">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-toggle-right">
                        <rect width="18" height="18" x="3" y="3" rx="2" ry="2" />
                        <path d="M12 3v18" />
                    </svg>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="/dashboard.php?page=users" id="users-link" class="sidebar-link users-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-user">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2" />
                        <circle cx="12" cy="7" r="4" />
                    </svg>
                    <span class="sidebar-text closed">Manage Users</span>
                </a>
            </li>
            <li class="sidebar-item">
                <a href="/dashboard.php?page=products" id="products-link" class="sidebar-link products-link">
                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-product">
                        <rect width="20" height="14" x="2" y="5" rx="2" />
                        <line x1="2" y1="10" x2="22" y2="10" />
                    </svg>
                    <span class="sidebar-text closed">Manage Products</span>
                </a>
            </li>
        </ul>

        <ul>
            <li>
                <a href="/dashboard.php?page=users" id="users-link" class="sidebar-link users-link">
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
        <!-- Message d'erreur / feedback serveur -->
        <?php if ($feedback !== ''): ?>
            <p id="dashboard-feedback" class="dashboard-feedback"><?php echo htmlspecialchars($feedback); ?></p>
        <?php endif; ?>

        <!-- ========== Page utilisateurs ========== -->
        <?php if ($page === 'users'): ?>
            <!-- Tableau des utilisateurs (clic → dialog détail, data-* pour le JS) -->
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

            <!-- Bouton d'ouverture de la dialog d'ajout -->
            <button type="button" class="btn btn-primary" id="add-user-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus">
                    <path d="M12 5v14" />
                    <path d="M5 12h14" />
                </svg>
                <span class="btn-text closed">Add User</span>
            </button>

            <!-- Dialog : créer un utilisateur -->
            <dialog id="add-user-dialog" class="product-dialog">
                <form method="post" id="add-user-form" class="auth-form product-form">
                    <h2>Add User</h2>

                    <div class="form-field">
                        <label for="user_username">Username</label>
                        <input type="text" name="username" id="user_username" placeholder="Username" required>
                        <span class="field-error"></span>
                    </div>

                    <div class="form-field">
                        <label for="user_email">Email</label>
                        <input type="email" name="email" id="user_email" placeholder="Email" required>
                        <span class="field-error"></span>
                    </div>

                    <div class="form-field">
                        <label for="user_password">Password</label>
                        <input type="password" name="password" id="user_password" placeholder="Password" required>
                        <span class="field-error"></span>
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
                        <span class="field-error"></span>
                    </div>

                    <input type="hidden" name="form_submitted_value" value="add_user">

                    <div class="dialog-actions">
                        <button type="button" id="cancel-user-btn" class="btn">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </dialog>

            <!-- Dialog : consulter / modifier / supprimer un utilisateur -->
            <dialog id="user-detail-dialog" class="product-dialog">
                <form method="post" id="user-detail-form" class="auth-form product-form">
                    <h2 id="user-detail-title">User details</h2>

                    <input type="hidden" name="user_id" id="detail_user_id" value="">
                    <input type="hidden" name="form_submitted_value" id="detail_user_form_action" value="update_user">

                    <div class="form-field">
                        <label for="detail_user_username">Username</label>
                        <input type="text" name="username" id="detail_user_username" placeholder="Username" required readonly>
                        <span class="field-error"></span>
                    </div>

                    <div class="form-field">
                        <label for="detail_user_email">Email</label>
                        <input type="email" name="email" id="detail_user_email" placeholder="Email" required readonly>
                        <span class="field-error"></span>
                    </div>

                    <div class="form-field">
                        <label for="detail_user_password">Password</label>
                        <input type="password" name="password" id="detail_user_password" placeholder="Leave blank to keep" readonly>
                        <span class="field-error"></span>
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
                        <span class="field-error"></span>
                    </div>

                    <!-- Boutons mode consultation -->
                    <div class="dialog-actions" id="user-detail-actions-view">
                        <button type="button" id="close-user-detail-btn" class="btn">Close</button>
                        <button type="button" id="edit-user-btn" class="btn btn-primary">Update</button>
                        <button type="button" id="delete-user-btn" class="btn btn-danger">Delete</button>
                    </div>

                    <!-- Boutons mode édition -->
                    <div class="dialog-actions hidden" id="user-detail-actions-edit">
                        <button type="button" id="cancel-edit-user-btn" class="btn">Cancel</button>
                        <button type="submit" id="save-user-btn" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </dialog>

            <!-- Dialog : confirmation avant suppression d'un utilisateur -->
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

        <!-- ========== Page produits ========== -->
        <?php if ($page === 'products'): ?>
            <!-- Filtre par catégorie (type_p, depuis product_categories.json) -->
            <form method="get" id="product-category-filter" class="category-filter">
                <input type="hidden" name="page" value="products">
                <!-- <label for="category">Category</label> -->
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

            <!-- Tableau des produits (clic → dialog détail, data-* pour le JS) -->
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category</th>
                        <th>Name</th>
                        <th>Price HT</th>
                        <th>Date</th>
                        <th>Stock</th>
                        <th>Promotion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result): ?>
                        <?php foreach ($result as $row): ?>
                            <?php
                                // Affichage promo : prix barré + prix remisé ; dernière colonne = %
                                $promo = (int) ($row['promotion'] ?? 0);
                                $price = (float) $row['price'];
                                $promo_price = $promo > 0 ? $price * (1 - ($promo / 100)) : $price;
                            ?>
                            <tr
                                class="product-row"
                                data-id="<?php echo htmlspecialchars($row['id']); ?>"
                                data-type="<?php echo htmlspecialchars($row['type']); ?>"
                                data-name="<?php echo htmlspecialchars($row['name']); ?>"
                                data-price="<?php echo htmlspecialchars($row['price']); ?>"
                                data-date="<?php echo htmlspecialchars($row['date']); ?>"
                                data-stock="<?php echo htmlspecialchars($row['stock']); ?>"
                                data-promotion="<?php echo htmlspecialchars((string) $promo); ?>">
                                <td><?php echo htmlspecialchars($row['id']); ?></td>
                                <td><?php echo htmlspecialchars($row['type']); ?></td>
                                <td><?php echo htmlspecialchars($row['name']); ?></td>
                                <td class="price-cell">
                                    <?php if ($promo > 0): ?>
                                        <span class="price-old"><?php echo number_format($price, 2, '.', ''); ?></span>
                                        <span class="price-new"><?php echo number_format($promo_price, 2, '.', ''); ?></span>
                                    <?php else: ?>
                                        <span class="price-current"><?php echo number_format($price, 2, '.', ''); ?></span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($row['date']); ?></td>
                                <td><?php echo htmlspecialchars($row['stock']); ?></td>
                                <td>
                                    <?php echo $promo > 0 ? '-' . htmlspecialchars((string) $promo) . '%' : '—'; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>

            <!-- Bouton d'ouverture de la dialog d'ajout -->
            <button type="button" class="btn btn-primary" id="add-product-btn">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-plus">
                    <path d="M12 5v14" />
                    <path d="M5 12h14" />
                </svg>
                <span class="btn-text closed">Add Product</span>
            </button>

            <!-- Dialog : créer un produit (types issus du JSON catégories) -->
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

                    <div class="form-field">
                        <label for="promotion_p">Promotion (%)</label>
                        <input type="number" name="promotion_p" id="promotion_p" min="0" max="100" value="0" placeholder="0">
                    </div>

                    <input type="hidden" name="form_submitted_value" value="add_product">

                    <div class="dialog-actions">
                        <button type="button" id="cancel-product-btn" class="btn">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </dialog>

            <!-- Dialog : consulter / modifier / supprimer un produit -->
            <dialog id="product-detail-dialog" class="product-dialog">
                <form method="post" id="product-detail-form" class="auth-form product-form">
                    <h2 id="product-detail-title">Product details</h2>

                    <input type="hidden" name="id_p" id="detail_id_p" value="">
                    <input type="hidden" name="form_submitted_value" id="detail_form_action" value="update_product">

                    <div class="form-field">
                        <!-- Select type : désactivé en vue, réactivé avant POST (JS) -->
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

                    <div class="form-field">
                        <label for="detail_promotion_p">Promotion (%)</label>
                        <input type="number" name="promotion_p" id="detail_promotion_p" min="0" max="100" value="0" placeholder="0" readonly>
                    </div>

                    <!-- Boutons mode consultation -->
                    <div class="dialog-actions" id="product-detail-actions-view">
                        <button type="button" id="close-product-detail-btn" class="btn">Close</button>
                        <button type="button" id="edit-product-btn" class="btn btn-primary">Update</button>
                        <button type="button" id="delete-product-btn" class="btn btn-danger">Delete</button>
                    </div>

                    <!-- Boutons mode édition -->
                    <div class="dialog-actions hidden" id="product-detail-actions-edit">
                        <button type="button" id="cancel-edit-product-btn" class="btn">Cancel</button>
                        <button type="submit" id="save-product-btn" class="btn btn-primary">Save</button>
                    </div>
                </form>
            </dialog>

            <!-- Dialog : confirmation avant suppression d'un produit -->
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

<!-- Scripts dashboard : validation commune + sidebar + dialogs + produits + utilisateurs -->
<script src="script/common/validation.js"></script>
<script src="script/dashboard/sidebar.js"></script>
<script src="script/dashboard/dialogs.js"></script>
<script src="script/dashboard/products.js"></script>
<script src="script/dashboard/users.js"></script>

</html>