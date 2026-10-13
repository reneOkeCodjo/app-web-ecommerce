<?php

require_once __DIR__ . '/../../vendor/autoload.php';

use App\Application\DTO\ProductRequestDTO;
use App\Application\Services\Auth\Auth;
use App\Application\Services\Product\ProductService;
use App\Domain\Model\Product;
use App\Domain\Pricing\ProductPricing;
use App\Infrastructure\Http\NativeSessionStore;
use App\Infrastructure\Persistence\DAO\NullUserRepository;
use App\Infrastructure\Persistence\DAO\ProductRepository;
use App\Infrastructure\Storage\ProductImageUploader;
use Db_config\Database;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

$session = new NativeSessionStore();
$auth = new Auth(new NullUserRepository(), $session);

if (!$auth->isAdmin()) {
    header('Location: login.php');
    exit;
}

$categoriesPath = __DIR__ . '/../data/product-categories.json';
$categoriesRaw = is_file($categoriesPath) ? file_get_contents($categoriesPath) : false;
$categoriesDecoded = is_string($categoriesRaw) ? json_decode($categoriesRaw, true) : null;
$categories = is_array($categoriesDecoded) ? $categoriesDecoded : [];

$connection = Database::connect();
$productService = new ProductService(new ProductRepository($connection));
$imageUploader = new ProductImageUploader(
    __DIR__ . '/../uploads/products'
);
$feedback = '';

/**
 * @param ProductRequestDTO $dto
 * @param string|null $imagePath
 */
$dtoWithImage = static function (ProductRequestDTO $dto, ?string $imagePath): ProductRequestDTO {
    return new ProductRequestDTO(
        $dto->getType(),
        $dto->getDesignation(),
        $dto->getPriceHt(),
        $dto->getStock(),
        $dto->getDateIn(),
        $dto->getPromotion(),
        $imagePath
    );
};

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';
    /** @var array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $uploadedImage */
    $uploadedImage = is_array($_FILES['product_image'] ?? null)
        ? $_FILES['product_image']
        : ['error' => UPLOAD_ERR_NO_FILE];

    $newImagePath = null;

    try {
        if ($action === 'create') {
            // Validate fields before upload to avoid orphan files on invalid input.
            $validationResult = $productService->validateInput(
                $_POST['type_p'] ?? null,
                $_POST['designation_p'] ?? null,
                $_POST['prix_ht'] ?? null,
                $_POST['stock_p'] ?? null,
                $_POST['date_in'] ?? date('Y-m-d'),
                $_POST['ppromo'] ?? null,
                null
            );

            if (!$validationResult->isValid()) {
                throw new InvalidArgumentException((string) $validationResult->getFirstError());
            }

            $baseDto = $validationResult->getProductRequest();
            if ($baseDto === null) {
                throw new RuntimeException('Validation produit incohérente.');
            }

            $newImagePath = $imageUploader->storeFromUpload($uploadedImage);
            $persisted = $productService->createFromDto(
                $dtoWithImage($baseDto, $newImagePath)
            );

            if (!$persisted) {
                throw new RuntimeException('Impossible d’enregistrer le produit.');
            }
        } elseif ($action === 'update') {
            $id = (int) ($_POST['id_p'] ?? 0);
            $existing = $productService->findById($id);
            if ($existing === null) {
                throw new RuntimeException('Produit introuvable.');
            }

            $validationResult = $productService->validateInput(
                $_POST['type_p'] ?? null,
                $_POST['designation_p'] ?? null,
                $_POST['prix_ht'] ?? null,
                $_POST['stock_p'] ?? null,
                $_POST['date_in'] ?? $existing->getDateIn(),
                $_POST['ppromo'] ?? null,
                $existing->getImage()
            );

            if (!$validationResult->isValid()) {
                throw new InvalidArgumentException((string) $validationResult->getFirstError());
            }

            $baseDto = $validationResult->getProductRequest();
            if ($baseDto === null) {
                throw new RuntimeException('Validation produit incohérente.');
            }

            $newImagePath = $imageUploader->storeFromUpload($uploadedImage);
            $imagePath = $newImagePath ?? $existing->getImage();

            $updated = $productService->updateFromDto(
                $id,
                $dtoWithImage($baseDto, $imagePath)
            );

            if (!$updated) {
                throw new RuntimeException('Impossible de modifier le produit.');
            }

            if ($newImagePath !== null && $existing->getImage() !== $newImagePath) {
                $imageUploader->deleteByPublicPath($existing->getImage());
            }
        } elseif ($action === 'delete') {
            $id = (int) ($_POST['id_p'] ?? 0);
            $existing = $productService->findById($id);
            $deleted = $productService->delete($id);
            if ($deleted && $existing !== null) {
                $imageUploader->deleteByPublicPath($existing->getImage());
            }
        }
    } catch (Throwable $e) {
        if ($newImagePath !== null) {
            $imageUploader->deleteByPublicPath($newImagePath);
        }

        $feedback = '<p class="feedback feedback-error" role="alert">'
            . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
            . '</p>';
    }

    if ($feedback === '') {
        header('Location: products.php');
        exit;
    }
}

$products = $productService->findAll();
$productsJson = array_map(
    static function (Product $product): array {
        $priceHt = (float) $product->getPriceHt();
        $promotion = $product->getPromotion() !== null
            ? (float) $product->getPromotion()
            : null;

        return [
            'id' => $product->getId(),
            'type' => $product->getType(),
            'designation' => $product->getDesignation(),
            'prixHt' => $priceHt,
            'promotion' => $promotion,
            'prixTtc' => ProductPricing::priceTtc($priceHt),
            'prixDisponible' => ProductPricing::priceAvailable($priceHt, $promotion),
            'dateIn' => $product->getDateIn(),
            'timesIn' => $product->getDateNew() ?? '',
            'stock' => $product->getStock(),
            'image' => $product->getImage(),
        ];
    },
    $products
);
?>
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Produits admin</title>
    <link rel="stylesheet" href="../styles.css">
</head>

<body>
    <main class="auth-page">
        <section class="auth-card auth-card--wide" aria-labelledby="admin-products-title">
            <div class="auth-header">
                <h1 id="admin-products-title" class="auth-title">
                    <span class="auth-title__text">Produits</span>
                </h1>
            </div>

            <p class="auth-switch">
                Catalogue MySQL, filtré par catégories JSON.
            </p>

            <?= $feedback ?>

            <div class="admin-toolbar">
                <label class="admin-filter">
                    <span>Catégorie</span>
                    <select id="product-category-filter" aria-label="Filtrer par catégorie">
                        <option value="">Toutes</option>
                        <?php foreach ($categories as $category): ?>
                            <?php
                            $label = is_array($category) ? (string) ($category['categorie'] ?? '') : '';
                            if ($label === '') {
                                continue;
                            }
                            ?>
                            <option value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <button type="button" class="admin-btn" data-open-dialog="product-form-dialog" data-form-mode="create">
                    Ajouter
                </button>
            </div>

            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th scope="col">Image</th>
                            <th scope="col">Id</th>
                            <th scope="col">Catégorie</th>
                            <th scope="col">Désignation</th>
                            <th scope="col">Prix HT</th>
                            <th scope="col">Prix dispo.</th>
                            <th scope="col">Stock</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($products === []): ?>
                            <tr class="admin-table__empty-row">
                                <td colspan="7" class="admin-table__empty">Aucun produit.</td>
                            </tr>
                        <?php endif; ?>
                        <?php foreach ($products as $product): ?>
                            <?php
                            $priceHt = (float) $product->getPriceHt();
                            $promotion = $product->getPromotion() !== null
                                ? (float) $product->getPromotion()
                                : null;
                            $prixTtc = ProductPricing::priceTtc($priceHt);
                            $prixDispo = ProductPricing::priceAvailable($priceHt, $promotion);
                            $hasPromo = ProductPricing::hasPromotion($promotion);
                            $imagePath = $product->getImage();
                            ?>
                            <tr
                                class="admin-table__row"
                                tabindex="0"
                                role="button"
                                data-open-dialog="product-view-dialog"
                                data-product-id="<?= (int) $product->getId() ?>"
                                data-product-type="<?= htmlspecialchars($product->getType(), ENT_QUOTES, 'UTF-8') ?>"
                            >
                                <td class="admin-table__thumb">
                                    <?php if ($imagePath !== null && $imagePath !== ''): ?>
                                        <img
                                            class="admin-thumb"
                                            src="<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') ?>"
                                            alt=""
                                            width="48"
                                            height="48"
                                        >
                                    <?php else: ?>
                                        <span class="admin-thumb admin-thumb--empty" aria-hidden="true"></span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars((string) $product->getId(), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($product->getType(), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars($product->getDesignation(), ENT_QUOTES, 'UTF-8') ?></td>
                                <td><?= htmlspecialchars(number_format($priceHt, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?></td>
                                <td class="admin-price">
                                    <?php if ($hasPromo): ?>
                                        <s class="admin-price__was"><?= htmlspecialchars(number_format($prixTtc, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?></s>
                                        <span class="admin-price__now"><?= htmlspecialchars(number_format($prixDispo, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?></span>
                                    <?php else: ?>
                                        <?= htmlspecialchars(number_format($prixDispo, 2, '.', ''), ENT_QUOTES, 'UTF-8') ?>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars((string) $product->getStock(), ENT_QUOTES, 'UTF-8') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <p class="auth-switch">
                <a href="index.php">Retour admin</a>
            </p>
        </section>
    </main>

    <dialog class="admin-dialog" id="product-view-dialog" aria-labelledby="product-view-title">
        <div class="admin-dialog__panel">
            <header class="admin-dialog__header">
                <h2 id="product-view-title">Détail produit</h2>
                <button type="button" class="admin-dialog__close" data-close-dialog aria-label="Fermer">&times;</button>
            </header>
            <div class="admin-dialog__media" data-view-image-wrap hidden>
                <img class="admin-dialog__image" data-view-image alt="">
            </div>
            <dl class="admin-dialog__details">
                <div><dt>Id</dt><dd data-view-field="id"></dd></div>
                <div><dt>Catégorie</dt><dd data-view-field="type"></dd></div>
                <div><dt>Désignation</dt><dd data-view-field="designation"></dd></div>
                <div><dt>Prix HT</dt><dd data-view-field="prixHt"></dd></div>
                <div><dt>Promotion</dt><dd data-view-field="promotionLabel"></dd></div>
                <div><dt>Prix TTC</dt><dd data-view-prix-ttc class="admin-price"></dd></div>
                <div><dt>Prix disponible</dt><dd data-view-prix-dispo></dd></div>
                <div><dt>Date d’entrée</dt><dd data-view-field="dateIn"></dd></div>
                <div><dt>Ajouté le</dt><dd data-view-field="timesIn"></dd></div>
                <div><dt>Stock</dt><dd data-view-field="stock"></dd></div>
            </dl>
            <div class="admin-dialog__actions">
                <button type="button" class="admin-btn" data-open-edit-from-view>Modifier</button>
                <form method="post" class="admin-inline-form" data-delete-form>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_p" value="">
                    <button type="submit" class="admin-btn admin-btn--danger">Supprimer</button>
                </form>
                <button type="button" class="admin-btn admin-btn--ghost" data-close-dialog>Fermer</button>
            </div>
        </div>
    </dialog>

    <dialog class="admin-dialog" id="product-form-dialog" aria-labelledby="product-form-title">
        <div class="admin-dialog__panel">
            <header class="admin-dialog__header">
                <h2 id="product-form-title">Ajouter un produit</h2>
                <button type="button" class="admin-dialog__close" data-close-dialog aria-label="Fermer">&times;</button>
            </header>
            <form method="post" class="admin-form" id="product-form" enctype="multipart/form-data">
                <input type="hidden" name="action" id="product-form-action" value="create">
                <input type="hidden" name="id_p" id="product-form-id" value="">

                <label class="admin-form__field">
                    <span>Catégorie</span>
                    <select name="type_p" id="product-form-type" required>
                        <option value="">Choisir…</option>
                        <?php foreach ($categories as $category): ?>
                            <?php
                            $label = is_array($category) ? (string) ($category['categorie'] ?? '') : '';
                            if ($label === '') {
                                continue;
                            }
                            ?>
                            <option value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>">
                                <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
                <label class="admin-form__field">
                    <span>Désignation</span>
                    <input type="text" name="designation_p" placeholder="Type the name" id="product-form-designation" maxlength="255" required>
                </label>
                <label class="admin-form__field">
                    <span>Prix HT</span>
                    <input type="number" name="prix_ht" id="product-form-prix" min="0" step="0.01" placeholder="0.00" required>
                </label>
                <label class="admin-form__field">
                    <span>Promotion (%)</span>
                    <input type="number" name="ppromo" id="product-form-promo" min="0" max="100" step="0.01" placeholder="0">
                </label>
                <p class="admin-price-preview" id="product-form-price-preview" aria-live="polite">
                    <span class="admin-price-preview__label">Prix TTC</span>
                    <span class="admin-price" data-preview-ttc>—</span>
                    <span class="admin-price-preview__label">Prix disponible</span>
                    <span data-preview-dispo>—</span>
                </p>
                <label class="admin-form__field">
                    <span>Date d’entrée</span>
                    <input type="date" name="date_in" id="product-form-date" placeholder="JJ/MM/AAAA" required>
                </label>
                <label class="admin-form__field">
                    <span>Stock</span>
                    <input type="number" name="stock_p" id="product-form-stock" min="0" step="1" value="0">
                </label>
                <label class="admin-form__field">
                    <span>Image produit</span>
                    <input
                        type="file"
                        name="product_image"
                        id="product-form-image"
                        accept="image/jpeg,image/png,image/webp"
                    >
                    <span class="admin-form__hint">JPEG, PNG ou WebP — 2 Mo max. Laisser vide pour conserver l’image actuelle. Stockage temporaire sous /uploads (fichiers accessibles en URL, usage dev uniquement).</span>
                </label>
                <div class="admin-image-preview" id="product-form-image-preview" hidden>
                    <img data-form-image-preview alt="Aperçu de l’image produit">
                </div>

                <div class="admin-dialog__actions">
                    <button type="submit" class="admin-btn" id="product-form-submit">Créer</button>
                    <button type="button" class="admin-btn admin-btn--ghost" data-close-dialog>Annuler</button>
                </div>
            </form>
        </div>
    </dialog>

    <script>
        window.__ADMIN_TVA_RATE__ = <?= json_encode(ProductPricing::TVA_RATE) ?>;
        window.__ADMIN_PRODUCTS__ = <?= json_encode($productsJson, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    </script>
    <script type="module" src="../scripts/admin-products.js"></script>
</body>

</html>
