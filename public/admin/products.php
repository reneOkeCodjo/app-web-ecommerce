<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Application\Services\Auth;
use App\Domain\Model\Product;
use App\Infrastructure\Http\NativeSessionStore;
use App\Infrastructure\Persistence\DAO\NullUserRepository;
use App\Infrastructure\Persistence\DAO\SessionProductRepository;

$session = new NativeSessionStore();
$auth = new Auth(new NullUserRepository(), $session);

if (!$auth->isAdmin()) {
    header('Location: login.php');
    exit;
}

$repository = new SessionProductRepository($session);
$feedback = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

    try {
        if ($action === 'create') {
            $repository->create(
                trim((string) ($_POST['type_p'] ?? '')),
                trim((string) ($_POST['designation_p'] ?? '')),
                (float) ($_POST['prix_ht'] ?? 0),
                trim((string) ($_POST['date_in'] ?? date('Y-m-d'))),
                (int) ($_POST['stock_p'] ?? 0)
            );
        } elseif ($action === 'update') {
            $id = (int) ($_POST['id_p'] ?? 0);
            $existing = $repository->findById($id);
            if ($existing === null) {
                throw new RuntimeException('Product not found.');
            }

            $repository->update(new Product(
                $id,
                trim((string) ($_POST['type_p'] ?? '')),
                trim((string) ($_POST['designation_p'] ?? '')),
                (float) ($_POST['prix_ht'] ?? 0),
                trim((string) ($_POST['date_in'] ?? $existing->getDateIn())),
                $existing->getTimesIn(),
                (int) ($_POST['stock_p'] ?? 0)
            ));
        } elseif ($action === 'delete') {
            $repository->delete((int) ($_POST['id_p'] ?? 0));
        }
    } catch (Throwable $e) {
        $feedback = '<p class="feedback feedback-error" role="alert">'
            . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
            . '</p>';
    }

    if ($feedback === '') {
        header('Location: products.php');
        exit;
    }
}

$products = $repository->findAll();
$productsJson = array_map(
    static fn (Product $product): array => [
        'id' => $product->getId(),
        'type' => $product->getType(),
        'designation' => $product->getDesignation(),
        'prixHt' => $product->getPrixHt(),
        'dateIn' => $product->getDateIn(),
        'timesIn' => $product->getTimesIn(),
        'stock' => $product->getStock(),
    ],
    $products
);
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
                Mock UI on session catalog (table <code>produit</code> shape).
            </p>

            <?= $feedback ?>

            <div class="admin-toolbar">
                <button type="button" class="admin-btn" data-open-dialog="product-form-dialog" data-form-mode="create">
                    Add product
                </button>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">id_p</th>
                            <th scope="col">type_p</th>
                            <th scope="col">designation_p</th>
                            <th scope="col">prix_ht</th>
                            <th scope="col">stock_p</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($products === []): ?>
                            <tr>
                                <td colspan="5" class="admin-table__empty">No products yet.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($products as $product): ?>
                            <tr
                                class="admin-table__row"
                                tabindex="0"
                                role="button"
                                data-open-dialog="product-view-dialog"
                                data-product-id="<?= (int) $product->getId() ?>"
                            >
                                <td><?= htmlspecialchars((string) $product->getId(), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($product->getType(), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($product->getDesignation(), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(number_format($product->getPrixHt(), 2, '.', ''), ENT_QUOTES, 'UTF-8') ?></td>
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

    <dialog class="admin-dialog" id="product-view-dialog" aria-labelledby="product-view-title">
        <div class="admin-dialog__panel">
            <header class="admin-dialog__header">
                <h2 id="product-view-title">Product details</h2>
                <button type="button" class="admin-dialog__close" data-close-dialog aria-label="Close">&times;</button>
            </header>
            <dl class="admin-dialog__details">
                <div><dt>id_p</dt><dd data-view-field="id"></dd></div>
                <div><dt>type_p</dt><dd data-view-field="type"></dd></div>
                <div><dt>designation_p</dt><dd data-view-field="designation"></dd></div>
                <div><dt>prix_ht</dt><dd data-view-field="prixHt"></dd></div>
                <div><dt>date_in</dt><dd data-view-field="dateIn"></dd></div>
                <div><dt>times_in</dt><dd data-view-field="timesIn"></dd></div>
                <div><dt>stock_p</dt><dd data-view-field="stock"></dd></div>
            </dl>
            <div class="admin-dialog__actions">
                <button type="button" class="admin-btn" data-open-edit-from-view>Update</button>
                <form method="post" class="admin-inline-form" data-delete-form>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_p" value="">
                    <button type="submit" class="admin-btn admin-btn--danger">Remove</button>
                </form>
                <button type="button" class="admin-btn admin-btn--ghost" data-close-dialog>Close</button>
            </div>
        </div>
    </dialog>

    <dialog class="admin-dialog" id="product-form-dialog" aria-labelledby="product-form-title">
        <div class="admin-dialog__panel">
            <header class="admin-dialog__header">
                <h2 id="product-form-title">Add product</h2>
                <button type="button" class="admin-dialog__close" data-close-dialog aria-label="Close">&times;</button>
            </header>
            <form method="post" class="admin-form" id="product-form">
                <input type="hidden" name="action" id="product-form-action" value="create">
                <input type="hidden" name="id_p" id="product-form-id" value="">

                <label class="admin-form__field">
                    <span>type_p</span>
                    <input type="text" name="type_p" id="product-form-type" maxlength="100" required>
                </label>
                <label class="admin-form__field">
                    <span>designation_p</span>
                    <input type="text" name="designation_p" id="product-form-designation" maxlength="255" required>
                </label>
                <label class="admin-form__field">
                    <span>prix_ht</span>
                    <input type="number" name="prix_ht" id="product-form-prix" min="0" step="0.01" required>
                </label>
                <label class="admin-form__field">
                    <span>date_in</span>
                    <input type="date" name="date_in" id="product-form-date" required>
                </label>
                <label class="admin-form__field">
                    <span>stock_p</span>
                    <input type="number" name="stock_p" id="product-form-stock" min="0" step="1" value="0" required>
                </label>

                <div class="admin-dialog__actions">
                    <button type="submit" class="admin-btn" id="product-form-submit">Create</button>
                    <button type="button" class="admin-btn admin-btn--ghost" data-close-dialog>Cancel</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        window.__ADMIN_PRODUCTS__ = <?= json_encode($productsJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script src="../scripts/admin-products.js" defer></script>
</body>

</html>
