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

$db_host = getenv('DB_HOST');
$db_name = getenv('DB_NAME');
$db_user = getenv('DB_USER');
$db_password = getenv('DB_PASSWORD');
$db_port = getenv('DB_PORT');

try {
    $pdo = new PDO("mysql:host=$db_host;port=$db_port;dbname=$db_name", $db_user, $db_password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = null;
    switch ($page) {
        case 'users':
            $stmt = $pdo->prepare('SELECT user_id as id, user_login as name, user_mail as email FROM user');
            break;
        case 'products':
            $stmt = $pdo->prepare('SELECT id_p as id, designation_p as name, prix_ht as price FROM product');
            break;
    }
    if ($stmt) {
        $stmt->execute();
        $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
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
    <aside id="dashboard-sidebar" class="dashboard-sidebar">
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
    </aside>
    <main>
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
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['name']; ?></td>
                            <td><?php echo $row['email']; ?></td>
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
                        <th>Name</th>
                        <th>Price</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($result as $row): ?>
                        <tr>
                            <td><?php echo $row['id']; ?></td>
                            <td><?php echo $row['name']; ?></td>
                            <td><?php echo $row['price']; ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </main>
</body>

<footer>
    <p>© 2026 Login Page. All rights reserved.</p>
</footer>

<script>
    const toggleBtn = document.querySelector('#toggle-btn');
    const sidebarTexts = document.querySelectorAll('.sidebar-text');

    toggleBtn.addEventListener('click', () => {
        sidebarTexts.forEach((text) => {
            text.classList.toggle('closed');
        });
    });
</script>

</html>