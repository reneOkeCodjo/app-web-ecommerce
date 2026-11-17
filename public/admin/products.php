<?php

// On active les types stricts de PHP.
declare(strict_types=1);

// On charge le démarrage commun.
require_once __DIR__ . '/../../config/bootstrap.php';

// On importe les classes nécessaires.
use App\Application\DTO\ProductRequestDTO;              // Objet de transfert produit.
use App\Application\Presenter\ProductPresenter;         // Transforme les produits pour l'affichage.
use App\Application\Services\Auth\Auth;                 // Service d'authentification.
use App\Application\Services\Product\ProductService;    // Service métier des produits.
use App\Application\Support\Flash;                      // Messages temporaires.
use App\Domain\Pricing\ProductPricing;                  // Calcul des prix.
use App\Infrastructure\Http\NativeSessionStore;         // Session sécurisée.
use App\Infrastructure\Persistence\DAO\NullUserRepository; // Dépôt vide pour lire la session.
use App\Infrastructure\Persistence\DAO\ProductRepository;  // Dépôt produits MySQL.
use App\Infrastructure\Storage\ProductImageUploader;    // Gestion des images.
use Db_config\Database;                                 // Connexion à la base.

// On ouvre la session.
$session = new NativeSessionStore();

// On crée le service d'authentification en lecture seule.
$auth = new Auth(new NullUserRepository(), $session);

// Si l'utilisateur n'est pas administrateur, on le renvoie à la connexion admin.
if (!$auth->isAdmin()) {
    header('Location: login.php');
    exit;
}

// On récupère les messages laissés par l'action précédente.
$flashMessages = Flash::consume($session);

// On récupère l'identifiant de l'administrateur affiché dans la barre du haut.
$adminUserId = $auth->getAuthenticatedUserId();

// On charge les catégories autorisées depuis le JSON.
$categoriesPath = __DIR__ . '/../data/product-categories.json';
$categoriesRaw = is_file($categoriesPath) ? file_get_contents($categoriesPath) : false;
$categoriesDecoded = is_string($categoriesRaw) ? json_decode($categoriesRaw, true) : null;
$categories = is_array($categoriesDecoded) ? $categoriesDecoded : [];

// Message global affiché dans la page.
$feedback = '';

// Erreurs par champ.
$fieldErrors = [];

// Valeurs du formulaire conservées après une erreur.
$formValues = [
    'action' => 'create',
    'id_p' => '',
    'type_p' => '',
    'designation_p' => '',
    'prix_ht' => '',
    'ppromo' => '',
    'date_in' => '',
    'stock_p' => '0',
    'image' => null,
];

// Indique si le formulaire doit être rouvert après une erreur.
$reopenForm = false;

// Liste des produits.
$products = [];

// Version JSON des produits pour le JavaScript.
$productsJson = [];

// Connexion à fermer à la fin.
$connection = null;

// Petite fonction qui recopie un DTO en remplaçant l'image.
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

// On protège tout le traitement.
try {
    // On ouvre la connexion à la base.
    $connection = Database::connect();

    // On crée le service produits.
    $productService = new ProductService(new ProductRepository($connection));

    // On prépare le dossier d'upload des images.
    $imageUploader = new ProductImageUploader(
        __DIR__ . '/../uploads/products'
    );

    // Si un formulaire a été envoyé.
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // On lit l'action demandée (créer, modifier, supprimer).
        $action = is_string($_POST['action'] ?? null) ? $_POST['action'] : '';

        // On lit le fichier image envoyé, ou on prépare une absence de fichier.
        /** @var array{name?: string, type?: string, tmp_name?: string, error?: int, size?: int} $uploadedImage */
        $uploadedImage = is_array($_FILES['product_image'] ?? null)
            ? $_FILES['product_image']
            : ['error' => UPLOAD_ERR_NO_FILE];

        // Petite fonction qui lit un champ texte du formulaire.
        $postString = static function (string $key, string $default = ''): string {
            $value = $_POST[$key] ?? $default;

            return is_scalar($value) ? (string) $value : $default;
        };

        // On mémorise les valeurs saisies pour les réafficher si besoin.
        $formValues = [
            'action' => $action === 'update' ? 'update' : 'create',
            'id_p' => $postString('id_p'),
            'type_p' => $postString('type_p'),
            'designation_p' => $postString('designation_p'),
            'prix_ht' => $postString('prix_ht'),
            'ppromo' => $postString('ppromo'),
            'date_in' => $postString('date_in'),
            'stock_p' => $postString('stock_p', '0'),
            'image' => null,
        ];

        // Chemin de la nouvelle image éventuellement enregistrée.
        $newImagePath = null;

        // Actions autorisées.
        $allowedActions = ['create', 'update', 'delete'];

        // On protège les opérations produit.
        try {
            // On refuse une action inconnue.
            if (!in_array($action, $allowedActions, true)) {
                throw new \InvalidArgumentException('Action non autorisée.');
            }

            // On vérifie le jeton CSRF pour toute action.
            if (!csrf_verify($session)) {
                throw new \RuntimeException('Votre session a expiré. Rechargez la page et réessayez.');
            }

            // Pour une création ou une modification.
            if ($action === 'create' || $action === 'update') {
                // On prépare les variables du produit existant.
                $existing = null;
                $existingImage = null;

                // En modification, on doit retrouver le produit existant.
                if ($action === 'update') {
                    // On lit l'identifiant.
                    $id = (int) ($_POST['id_p'] ?? 0);

                    // On cherche le produit.
                    $existing = $productService->findById($id);

                    // S'il n'existe pas, on s'arrête.
                    if ($existing === null) {
                        throw new \RuntimeException('Produit introuvable.');
                    }

                    // On garde son image actuelle.
                    $existingImage = $existing->getImage();
                    $formValues['image'] = $existingImage;
                }

                // On valide les champs avant de téléverser l'image (évite les fichiers orphelins).
                $validationResult = $productService->validateInput(
                    $_POST['type_p'] ?? null,
                    $_POST['designation_p'] ?? null,
                    $_POST['prix_ht'] ?? null,
                    $_POST['stock_p'] ?? null,
                    $_POST['date_in'] ?? ($existing?->getDateIn() ?? date('Y-m-d')),
                    $_POST['ppromo'] ?? null,
                    $existingImage
                );

                // Si la validation échoue, on garde les erreurs et on rouvre le formulaire.
                if (!$validationResult->isValid()) {
                    $fieldErrors = $validationResult->getErrors();
                    $reopenForm = true;
                } else {
                    // On récupère les données validées.
                    $baseDto = $validationResult->getProductRequest();

                    // Si les données sont incohérentes, on s'arrête.
                    if ($baseDto === null) {
                        throw new \RuntimeException('Validation produit incohérente.');
                    }

                    // On téléverse la nouvelle image si un fichier a été envoyé.
                    $newImagePath = $imageUploader->storeFromUpload($uploadedImage);

                    // On garde la nouvelle image, sinon l'ancienne.
                    $imagePath = $newImagePath ?? $existingImage;

                    // En création, on enregistre un nouveau produit.
                    if ($action === 'create') {
                        $persisted = $productService->createFromDto(
                            $dtoWithImage($baseDto, $imagePath)
                        );

                        if (!$persisted) {
                            throw new \RuntimeException('Impossible d’enregistrer le produit.');
                        }
                    } else {
                        // En modification, on met à jour le produit existant.
                        $id = (int) ($_POST['id_p'] ?? 0);
                        $updated = $productService->updateFromDto(
                            $id,
                            $dtoWithImage($baseDto, $imagePath)
                        );

                        if (!$updated) {
                            throw new \RuntimeException('Impossible de modifier le produit.');
                        }

                        // Si une nouvelle image remplace l'ancienne, on supprime l'ancienne.
                        if (
                            $newImagePath !== null
                            && $existing !== null
                            && $existing->getImage() !== $newImagePath
                        ) {
                            $imageUploader->deleteByPublicPath($existing->getImage());
                        }
                    }
                }
            } elseif ($action === 'delete') {
                // On lit l'identifiant du produit à supprimer.
                $id = (int) ($_POST['id_p'] ?? 0);

                // On cherche le produit.
                $existing = $productService->findById($id);

                // S'il n'existe pas, on s'arrête.
                if ($existing === null) {
                    throw new \RuntimeException('Produit introuvable.');
                }

                // On supprime le produit.
                $deleted = $productService->delete($id);

                if (!$deleted) {
                    throw new \RuntimeException('Impossible de supprimer le produit.');
                }

                // On supprime aussi son image.
                $imageUploader->deleteByPublicPath($existing->getImage());
            }
        } catch (\Throwable $e) {
            // Si une image vient d'être enregistrée mais que la suite échoue, on la supprime.
            if ($newImagePath !== null) {
                $imageUploader->deleteByPublicPath($newImagePath);
            }

            // On prépare le message d'erreur.
            $feedback = '<p class="feedback feedback-error" role="alert">'
                . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8')
                . '</p>';

            // On rouvre le formulaire pour une création ou une modification.
            if ($action === 'create' || $action === 'update') {
                $reopenForm = true;
            }
        }

        // S'il n'y a ni message ni erreur de champ, tout s'est bien passé : on redirige.
        if ($feedback === '' && $fieldErrors === []) {
            // On choisit un message clair selon l'action réalisée.
            $successMessage = match ($action) {
                'create' => 'Produit créé avec succès.',
                'update' => 'Produit mis à jour avec succès.',
                'delete' => 'Produit supprimé avec succès.',
                default => 'Action effectuée avec succès.',
            };

            // On stocke le message pour la page suivante.
            Flash::add($session, 'success', $successMessage);

            header('Location: products.php');
            exit;
        }
    }

    // On recharge la liste des produits pour l'affichage.
    $products = $productService->findAll();

    // On transforme chaque produit en tableau pour le JavaScript.
    $productsJson = ProductPresenter::collection($products);
} catch (\Throwable $e) {
    // En cas de problème général, on affiche un message générique.
    $feedback = '<p class="feedback feedback-error" role="alert">'
        . 'Connexion à la base impossible. Réessayez plus tard.'
        . '</p>';
} finally {
    // On ferme la connexion si elle existe.
    if ($connection !== null) {
        $connection->close();
    }
}

// On prépare la page et ses fichiers communs.
$pageTitle = 'Produits — Administration';
$assetPrefix = '../';
$activeAdmin = 'products';
$breadcrumbLabel = 'Produits';
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require __DIR__ . '/../partials/head.php'; ?>
</head>
<body>
    <a class="skip-link" href="#main">Aller au contenu principal</a>

    <!-- Messages temporaires. -->
    <?php require __DIR__ . '/../partials/toasts.php'; ?>

    <div class="admin-shell">
        <!-- Menu latéral commun. -->
        <?php require __DIR__ . '/../partials/admin-sidebar.php'; ?>

        <!-- Zone principale. -->
        <div class="admin-main">
            <?php require __DIR__ . '/../partials/admin-topbar.php'; ?>

            <main id="main" class="admin-content">
                <!-- En-tête de page. -->
                <div class="page-head">
                    <div>
                        <p class="eyebrow">Catalogue</p>
                        <h1 class="page-title">Gestion des produits</h1>
                        <p class="page-lead">
                            Créez, modifiez ou supprimez une référence. Les prix TTC et promotions sont calculés automatiquement.
                        </p>
                    </div>
                    <button type="button" class="btn btn--primary" data-open-dialog="product-form-dialog" data-form-mode="create">
                        Ajouter un produit
                    </button>
                </div>

                <!-- Message global. -->
                <?php if ($feedback !== '' && !$reopenForm): ?>
                    <?= $feedback ?>
                <?php endif; ?>

                <!-- Barre d'outils. -->
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

                    <label class="admin-filter">
                        <span>Par page</span>
                        <select id="admin-page-size" aria-label="Produits par page">
                            <option value="10" selected>10</option>
                            <option value="25">25</option>
                            <option value="50">50</option>
                        </select>
                    </label>
                </div>

                <!-- Tableau des produits. -->
                <div class="admin-table-wrap">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th scope="col">Image</th>
                                <th scope="col" data-sort-key="id">Id</th>
                                <th scope="col" data-sort-key="type">Catégorie</th>
                                <th scope="col" data-sort-key="designation">Désignation</th>
                                <th scope="col" data-sort-key="prixHt">Prix HT</th>
                                <th scope="col" data-sort-key="prixDisponible">Prix dispo.</th>
                                <th scope="col" data-sort-key="stock">Stock</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if ($products === []): ?>
                                <tr class="admin-table__empty-row">
                                    <td colspan="7" class="admin-table__empty">Aucun produit pour le moment.</td>
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
                                            <img class="admin-thumb" src="<?= htmlspecialchars($imagePath, ENT_QUOTES, 'UTF-8') ?>" alt="" width="48" height="48">
                                        <?php else: ?>
                                            <span class="admin-thumb admin-thumb--empty" aria-hidden="true"></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string) $product->getId(), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($product->getType(), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars($product->getDesignation(), ENT_QUOTES, 'UTF-8') ?></td>
                                    <td><?= htmlspecialchars(number_format($priceHt, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</td>
                                    <td class="admin-price">
                                        <?php if ($hasPromo): ?>
                                            <s class="admin-price__was"><?= htmlspecialchars(number_format($prixTtc, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</s>
                                            <span class="admin-price__now"><?= htmlspecialchars(number_format($prixDispo, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €</span>
                                        <?php else: ?>
                                            <?= htmlspecialchars(number_format($prixDispo, 2, ',', ' '), ENT_QUOTES, 'UTF-8') ?> €
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars((string) $product->getStock(), ENT_QUOTES, 'UTF-8') ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Pagination. -->
                <nav id="admin-pagination" class="catalog-pagination" aria-label="Pagination des produits"></nav>
            </main>
        </div>
    </div>

    <!-- Dialogue de détail produit. -->
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
                <div><dt>Date d'entrée</dt><dd data-view-field="dateIn"></dd></div>
                <div><dt>Ajouté le</dt><dd data-view-field="timesIn"></dd></div>
                <div><dt>Stock</dt><dd data-view-field="stock"></dd></div>
            </dl>

            <div class="admin-dialog__actions">
                <button type="button" class="admin-btn" data-open-edit-from-view>Modifier</button>
                <form method="post" class="admin-inline-form" data-delete-form>
                    <?= csrf_field($session) ?>
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id_p" value="">
                    <button type="submit" class="admin-btn admin-btn--danger">Supprimer</button>
                </form>
                <button type="button" class="admin-btn admin-btn--ghost" data-close-dialog>Fermer</button>
            </div>
        </div>
    </dialog>

    <!-- Dialogue de création / modification. -->
    <dialog class="admin-dialog admin-dialog--wide" id="product-form-dialog" aria-labelledby="product-form-title">
        <div class="admin-dialog__panel">
            <header class="admin-dialog__header">
                <h2 id="product-form-title">Ajouter un produit</h2>
                <button type="button" class="admin-dialog__close" data-close-dialog aria-label="Fermer">&times;</button>
            </header>

            <form method="post" class="admin-form" id="product-form" enctype="multipart/form-data" novalidate>
                <?php if ($reopenForm && $feedback !== ''): ?>
                    <?= $feedback ?>
                <?php endif; ?>

                <!-- Jeton de sécurité. -->
                <?= csrf_field($session) ?>
                <input type="hidden" name="action" id="product-form-action" value="<?= htmlspecialchars($formValues['action'], ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="id_p" id="product-form-id" value="<?= htmlspecialchars($formValues['id_p'], ENT_QUOTES, 'UTF-8') ?>">

                <?php
                $typeError = $fieldErrors['type_p'] ?? null;
                $designationError = $fieldErrors['designation_p'] ?? null;
                $prixError = $fieldErrors['prix_ht'] ?? null;
                $promoError = $fieldErrors['ppromo'] ?? null;
                $dateError = $fieldErrors['date_in'] ?? null;
                $stockError = $fieldErrors['stock_p'] ?? null;
                $imageError = $fieldErrors['product_image'] ?? null;
                ?>

                <div class="form-grid">
                    <label class="form-field<?= $typeError ? ' form-field--error' : '' ?>">
                        <span>Catégorie</span>
                        <select name="type_p" id="product-form-type" required<?= $typeError ? ' aria-invalid="true" aria-describedby="type_p-error"' : '' ?>>
                            <option value="">Choisir…</option>
                            <?php foreach ($categories as $category): ?>
                                <?php
                                $label = is_array($category) ? (string) ($category['categorie'] ?? '') : '';
                                if ($label === '') {
                                    continue;
                                }
                                $selected = $formValues['type_p'] === $label ? ' selected' : '';
                                ?>
                                <option value="<?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>"<?= $selected ?>>
                                    <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if ($typeError): ?>
                            <span class="field-error" id="type_p-error" role="alert"><?= htmlspecialchars($typeError, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </label>

                    <label class="form-field<?= $designationError ? ' form-field--error' : '' ?>">
                        <span>Désignation</span>
                        <input type="text" name="designation_p" placeholder="Nom du produit" id="product-form-designation" maxlength="255" required value="<?= htmlspecialchars($formValues['designation_p'], ENT_QUOTES, 'UTF-8') ?>"<?= $designationError ? ' aria-invalid="true" aria-describedby="designation_p-error"' : '' ?>>
                        <?php if ($designationError): ?>
                            <span class="field-error" id="designation_p-error" role="alert"><?= htmlspecialchars($designationError, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </label>

                    <label class="form-field<?= $prixError ? ' form-field--error' : '' ?>">
                        <span>Prix HT</span>
                        <input type="number" name="prix_ht" id="product-form-prix" min="0" step="0.01" placeholder="0.00" required value="<?= htmlspecialchars($formValues['prix_ht'], ENT_QUOTES, 'UTF-8') ?>"<?= $prixError ? ' aria-invalid="true" aria-describedby="prix_ht-error"' : '' ?>>
                        <?php if ($prixError): ?>
                            <span class="field-error" id="prix_ht-error" role="alert"><?= htmlspecialchars($prixError, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </label>

                    <label class="form-field<?= $promoError ? ' form-field--error' : '' ?>">
                        <span>Promotion (%)</span>
                        <input type="number" name="ppromo" id="product-form-promo" min="0" max="100" step="0.01" placeholder="0" value="<?= htmlspecialchars($formValues['ppromo'], ENT_QUOTES, 'UTF-8') ?>"<?= $promoError ? ' aria-invalid="true" aria-describedby="ppromo-error"' : '' ?>>
                        <?php if ($promoError): ?>
                            <span class="field-error" id="ppromo-error" role="alert"><?= htmlspecialchars($promoError, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </label>

                    <label class="form-field<?= $dateError ? ' form-field--error' : '' ?>">
                        <span>Date d'entrée</span>
                        <input type="date" name="date_in" id="product-form-date" required value="<?= htmlspecialchars($formValues['date_in'], ENT_QUOTES, 'UTF-8') ?>"<?= $dateError ? ' aria-invalid="true" aria-describedby="date_in-error"' : '' ?>>
                        <?php if ($dateError): ?>
                            <span class="field-error" id="date_in-error" role="alert"><?= htmlspecialchars($dateError, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </label>

                    <label class="form-field<?= $stockError ? ' form-field--error' : '' ?>">
                        <span>Stock</span>
                        <input type="number" name="stock_p" id="product-form-stock" min="0" step="1" value="<?= htmlspecialchars($formValues['stock_p'], ENT_QUOTES, 'UTF-8') ?>"<?= $stockError ? ' aria-invalid="true" aria-describedby="stock_p-error"' : '' ?>>
                        <?php if ($stockError): ?>
                            <span class="field-error" id="stock_p-error" role="alert"><?= htmlspecialchars($stockError, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </label>

                    <label class="form-field form-field--full<?= $imageError ? ' form-field--error' : '' ?>">
                        <span>Image produit</span>
                        <input type="file" name="product_image" id="product-form-image" accept="image/jpeg,image/png,image/webp"<?= $imageError ? ' aria-invalid="true" aria-describedby="product_image-error"' : '' ?>>
                        <span class="form-hint">JPEG, PNG ou WebP — 2 Mo max. Laisser vide pour conserver l'image actuelle.</span>
                        <?php if ($imageError): ?>
                            <span class="field-error" id="product_image-error" role="alert"><?= htmlspecialchars($imageError, ENT_QUOTES, 'UTF-8') ?></span>
                        <?php endif; ?>
                    </label>
                </div>

                <!-- Aperçu de l'image actuelle / choisie. -->
                <div class="admin-image-preview" id="product-form-image-preview"<?= $formValues['image'] ? '' : ' hidden' ?>>
                    <img data-form-image-preview alt="Aperçu de l'image produit"<?= $formValues['image'] ? ' src="' . htmlspecialchars((string) $formValues['image'], ENT_QUOTES, 'UTF-8') . '"' : '' ?>>
                </div>

                <!-- Aperçu des prix. -->
                <p class="admin-price-preview" id="product-form-price-preview" aria-live="polite">
                    <span class="admin-price-preview__label">Prix TTC</span>
                    <span class="admin-price" data-preview-ttc>—</span>
                    <span class="admin-price-preview__label">Prix disponible</span>
                    <span data-preview-dispo>—</span>
                </p>

                <div class="admin-dialog__actions">
                    <button type="submit" class="admin-btn" id="product-form-submit"><?= $formValues['action'] === 'update' ? 'Enregistrer' : 'Créer' ?></button>
                    <button type="button" class="admin-btn admin-btn--ghost" data-close-dialog>Annuler</button>
                </div>
            </form>
        </div>
    </dialog>

    <!-- Données pour le JavaScript, sans script inline. -->
    <script type="application/json" id="admin-products-data" nonce="<?= csp_nonce() ?>"><?= ProductPresenter::json($productsJson) ?></script>
    <script type="application/json" id="admin-tva-rate" nonce="<?= csp_nonce() ?>"><?= json_encode(ProductPricing::TVA_RATE) ?></script>
    <script type="application/json" id="admin-form-state" nonce="<?= csp_nonce() ?>"><?= json_encode(['reopen' => $reopenForm], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

    <!-- Modules JavaScript de l'administration. -->
    <script src="<?= e(asset_url('scripts/ui/theme.js', '../')) ?>" defer></script>
    <script src="<?= e(asset_url('scripts/ui/toasts.js', '../')) ?>" defer></script>
    <script type="module" src="<?= e(asset_url('scripts/admin-products.js', '../')) ?>"></script>
</body>
</html>
