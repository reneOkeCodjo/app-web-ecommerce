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
$feedback = '';

$db_host = getenv('DB_HOST');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_password = getenv('DB_PASSWORD');
$db_port = getenv('DB_PORT');

try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name", $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    if (
        $page === 'products'
        && isset($_POST['form_submitted_value'])
        && $_POST['form_submitted_value'] === 'add_product'
    ) {
        $type = trim($_POST['type_p'] ?? '');
        $designation = trim($_POST['designation_p'] ?? '');
        $prix_ht = $_POST['prix_ht'] ?? '';
        $date_in = $_POST['date_in'] ?? '';
        $stock_p = $_POST['stock_p'] ?? 0;

        if ($type === '' || $designation === '' || $prix_ht === '' || $date_in === '') {
            $feedback = 'All product fields are required';
        } else {
            $stmt = $pdo->prepare(
                'INSERT INTO product (type_p, designation_p, prix_ht, date_in, stock_p)
                 VALUES (:type_p, :designation_p, :prix_ht, :date_in, :stock_p)'
            );
            $stmt->execute([
                'type_p' => $type,
                'designation_p' => $designation,
                'prix_ht' => $prix_ht,
                'date_in' => $date_in,
                'stock_p' => (int) $stock_p,
            ]);
            header('Location: dashboard.php?page=products');
            exit;
        }
    }

    $stmt = null;
    switch ($page) {
        case 'users':
            $stmt = $pdo->prepare('SELECT user_id as id, user_login as name, user_mail as email FROM user');
            break;
        case 'products':
            $stmt = $pdo->prepare(
                'SELECT id_p as id, designation_p as name, prix_ht as price, date_in as date, type_p as type, stock_p as stock
                 FROM product
                 ORDER BY id_p DESC'
            );
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
    <link rel="stylesheet" href="./style/dashboard.css">
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
                        <th>Name</th>
                        <th>Email</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result as $row): ?>
                        <tr class="user-row">
                            <td><?php echo htmlspecialchars($row['id']); ?></td>
                            <td><?php echo htmlspecialchars($row['name']); ?></td>
                            <td><?php echo htmlspecialchars($row['email']); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>

        <?php if ($page === 'products'): ?>
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Type</th>
                        <th>Name</th>
                        <th>Price HT</th>
                        <th>Date</th>
                        <th>Stock</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($result): ?>
                        <?php foreach ($result as $row): ?>
                            <tr class="product-row">
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
                        <label for="type_p">Type</label>
                        <input type="text" name="type_p" id="type_p" placeholder="Type" required>
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


</script>

</html>