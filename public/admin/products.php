<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Application\Services\Auth;
use App\Infrastructure\Http\NativeSessionStore;
use App\Infrastructure\Persistence\DAO\InMemoryProductRepository;
use App\Infrastructure\Persistence\DAO\NullUserRepository;

$session = new NativeSessionStore();
$auth = new Auth(new NullUserRepository(), $session);

if (!$auth->isAdmin()) {
    header('Location: login.php');
    exit;
}

// Temporary mock until the real products DAO is available.
$products = (new InMemoryProductRepository())->findAll();
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin products</title>
    <link rel="stylesheet" href="../styles.css">
</head>

<body>
    <main class="auth-page">
        <section class="auth-card auth-card--wide" aria-labelledby="admin-products-title">
            <div class="auth-header">
                <h1 id="admin-products-title" class="auth-title">
                    <span class="auth-title__text">Products</span>
                </h1>
            </div>

            <p class="auth-switch">
                Mock catalog (in-memory) shaped like table <code>produit</code>.
            </p>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">id_p</th>
                            <th scope="col">type_p</th>
                            <th scope="col">designation_p</th>
                            <th scope="col">prix_ht</th>
                            <th scope="col">date_in</th>
                            <th scope="col">times_in</th>
                            <th scope="col">stock_p</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td><?= htmlspecialchars((string) $product->getId(), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($product->getType(), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($product->getDesignation(), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(number_format($product->getPrixHt(), 2, '.', ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($product->getDateIn(), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($product->getTimesIn(), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars((string) $product->getStock(), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="auth-switch">
                <a href="index.php">Back to admin</a>
            </p>
        </section>
    </main>
</body>

</html>
