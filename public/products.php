<?php

// On active les types stricts de PHP.
declare(strict_types=1);

// On charge le démarrage commun.
require_once __DIR__ . '/../config/bootstrap.php';

// On importe les classes nécessaires.
use App\Application\Presenter\ProductPresenter;           // Transforme les produits pour l'affichage.
use App\Application\Services\Auth\Auth;                   // Service d'authentification.
use App\Application\Services\Product\ProductService;      // Service des produits.
use App\Infrastructure\Http\NativeSessionStore;           // Session sécurisée.
use App\Infrastructure\Persistence\DAO\NullUserRepository; // Dépôt vide pour lire la session.
use App\Infrastructure\Persistence\DAO\ProductRepository;  // Dépôt produits MySQL.
use Db_config\Database;                                    // Connexion à la base.

// On ouvre la session.
$session = new NativeSessionStore();

// On crée le service d'authentification (dépôt vide : on lit seulement la session).
$auth = new Auth(new NullUserRepository(), $session);

// Si l'utilisateur n'est pas connecté, on le renvoie vers la page de connexion.
if (!$auth->isAuthenticated()) {
    header('Location: index.php');
    exit;
}

// Si l'utilisateur demande à se déconnecter (formulaire POST).
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'logout') {
    // On vérifie d'abord le jeton CSRF.
    if (!csrf_verify($session)) {
        http_response_code(419);
        exit('Session expirée. Rechargez la page.');
    }

    // On détruit la session et on redirige vers la connexion.
    $auth->logout();
    header('Location: index.php');
    exit;
}

// On récupère le rôle de l'utilisateur pour afficher éventuellement le lien admin.
$isAdmin = $auth->isAdmin();

// On charge la liste des catégories depuis le fichier JSON.
$categoriesPath = __DIR__ . '/data/product-categories.json';
$categoriesRaw = is_file($categoriesPath) ? file_get_contents($categoriesPath) : false;
$categoriesDecoded = is_string($categoriesRaw) ? json_decode($categoriesRaw, true) : null;
$categories = is_array($categoriesDecoded) ? $categoriesDecoded : [];

// On prépare la liste des produits et leur version JSON.
$products = [];
$productsJson = [];
$connection = null;
$error = '';

// On protège le chargement des produits.
try {
    // On ouvre la connexion à la base.
    $connection = Database::connect();

    // On crée le service produits.
    $productService = new ProductService(new ProductRepository($connection));

    // On récupère tous les produits.
    $products = $productService->findAll();

    // On transforme chaque produit en tableau simple pour le JavaScript.
    $productsJson = ProductPresenter::collection($products);
} catch (\Throwable $e) {
    // En cas d'erreur de base, on affiche un message propre.
    $error = 'Connexion à la base impossible. Réessayez plus tard.';
} finally {
    // On ferme la connexion si elle existe.
    if ($connection !== null) {
        $connection->close();
    }
}

// On prépare la page et ses fichiers communs.
$pageTitle = 'Catalogue — ATLAS';
$pageDescription = 'Les essentiels tech choisis pour les étudiants internationaux en France, avec un guide des démarches d’arrivée.';
$assetPrefix = '';

// Quelques compteurs utiles pour la narration de la page.
$productCount = count($products);
$categoryCount = count($categories);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <?php require __DIR__ . '/partials/head.php'; ?>
</head>
<body class="atlas-page atlas-catalog">
    <a class="skip-link" href="#main">Aller au contenu principal</a>
    <?php require __DIR__ . '/partials/preloader.php'; ?>

    <!-- En-tête commun du site. -->
    <?php require __DIR__ . '/partials/site-header.php'; ?>

    <main id="main">
        <!-- Hero éditorial. -->
        <section class="container catalog-hero">
            <div class="catalog-hero__copy" data-reveal>
                <p class="eyebrow">ATLAS essentials · France · 2026</p>
                <h1 class="atlas-title atlas-title--catalog">
                    Équipez votre
                    <em>nouveau chapitre.</em>
                </h1>
                <p class="atlas-lead">
                    Une sélection resserrée pour étudier, travailler et rester connecté.
                    Et un guide clair pour les démarches qui comptent vraiment.
                </p>

                <div class="atlas-actions">
                    <a class="btn btn--primary" href="#catalogue">Voir la sélection</a>
                    <a class="btn btn--ghost" href="#guide">Ouvrir le guide</a>
                </div>

                <dl class="catalog-stats">
                    <div><dt>Références</dt><dd><?= e((string) $productCount) ?></dd></div>
                    <div><dt>Catégories</dt><dd><?= e((string) $categoryCount) ?></dd></div>
                    <div><dt>Terrain</dt><dd>France</dd></div>
                </dl>
            </div>

            <div class="catalog-orbit" data-reveal data-parallax="0.04" aria-label="Univers ATLAS">
                <div class="catalog-orbit__ring catalog-orbit__ring--outer" aria-hidden="true"></div>
                <div class="catalog-orbit__ring catalog-orbit__ring--inner" aria-hidden="true"></div>
                <div class="catalog-orbit__core">
                    <span><?= e((string) $productCount) ?></span>
                    <small>essentiels<br>sélectionnés</small>
                </div>
                <span class="catalog-orbit__node catalog-orbit__node--one">Mobile</span>
                <span class="catalog-orbit__node catalog-orbit__node--two">Laptop</span>
                <span class="catalog-orbit__node catalog-orbit__node--three">Audio</span>
                <span class="catalog-orbit__node catalog-orbit__node--four">Photo</span>
                <span class="catalog-orbit__node catalog-orbit__node--five">Santé</span>
                <span class="catalog-orbit__node catalog-orbit__node--six">Banque</span>
                <span class="catalog-orbit__caption">Un catalogue utile,<br>pas un rayon de plus.</span>
            </div>
        </section>

        <!-- Bandeau de repères, animé en continu mais sobre. -->
        <div class="marquee" aria-hidden="true">
            <div class="marquee__track">
                <span>Visa VLS-TS</span><span>Logement CROUS</span><span>Visale</span><span>Ameli</span><span>CAF</span><span>Campus France</span><span>Compte bancaire</span>
                <span>Visa VLS-TS</span><span>Logement CROUS</span><span>Visale</span><span>Ameli</span><span>CAF</span><span>Campus France</span><span>Compte bancaire</span>
            </div>
        </div>

        <section class="container catalog-section" id="catalogue" aria-labelledby="catalog-title">
            <div class="catalog-section__head" data-reveal>
                <div>
                    <p class="eyebrow">Le catalogue</p>
                    <h2 id="catalog-title">Les essentiels, choisis pour durer.</h2>
                </div>
                <p>
                    Filtrez, triez, comparez. Chaque produit est sélectionné pour son usage réel :
                    cours, travail, appels à la maison et vie quotidienne.
                </p>
            </div>

            <?php if ($error !== ''): ?>
                <p class="feedback feedback-error" role="alert">
                    <?= htmlspecialchars($error, ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php endif; ?>

            <div class="catalog-dock" role="search">
                <div class="catalog-dock__lead">
                    <span class="pill"><span class="pill__dot" aria-hidden="true"></span> Sélection active</span>
                    <span class="catalog-dock__count"><?= e((string) $productCount) ?> références</span>
                </div>

                <div class="catalog-toolbar">
                    <label>
                        <span>Catégorie</span>
                        <select id="catalog-category-filter" aria-label="Filtrer par catégorie">
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

                    <label>
                        <span>Trier par</span>
                        <select id="catalog-sort" aria-label="Trier les produits">
                            <option value="recent">Plus récent</option>
                            <option value="price-asc">Prix croissant</option>
                            <option value="price-desc">Prix décroissant</option>
                            <option value="name-asc">Nom A-Z</option>
                            <option value="name-desc">Nom Z-A</option>
                            <option value="stock">Stock disponible</option>
                        </select>
                    </label>

                    <label class="catalog-toolbar__search">
                        <span>Rechercher</span>
                        <input type="text" id="catalog-search" placeholder="Nom du produit…" aria-label="Rechercher un produit">
                    </label>
                </div>
            </div>

            <!-- Grille des produits, remplie par JavaScript. -->
            <div class="catalog-grid" id="catalog-grid" aria-live="polite" data-csrf="<?= e(\App\Infrastructure\Http\Csrf::token($session)) ?>"></div>

            <!-- Pagination, remplie par JavaScript. -->
            <nav id="catalog-pagination" class="catalog-pagination" aria-label="Pagination du catalogue"></nav>
        </section>

        <!-- Repères concrets pour le premier mois. -->
        <section class="container arrival-section" id="arrival" aria-labelledby="arrival-title">
            <div class="arrival-section__head" data-reveal>
                <p class="eyebrow">Le premier mois</p>
                <h2 id="arrival-title">Votre arrivée,<br><em>sans zone d’ombre.</em></h2>
                <p>
                    Quatre dossiers à ouvrir tôt. Les liens renvoient vers les sources officielles,
                    pas vers des promesses.
                </p>
            </div>

            <div class="arrival-steps">
                <article class="arrival-step" data-reveal>
                    <span class="arrival-step__number">01</span>
                    <h3>Visa & titre de séjour</h3>
                    <p>Validez votre VLS-TS en ligne, conservez vos justificatifs et anticipez le renouvellement.</p>
                    <a href="https://administration-etrangers-en-france.interieur.gouv.fr/" target="_blank" rel="noopener noreferrer">Source officielle</a>
                </article>

                <article class="arrival-step" data-reveal>
                    <span class="arrival-step__number">02</span>
                    <h3>Logement & caution</h3>
                    <p>CROUS, résidences privées, Visale : comparez les garanties avant de signer.</p>
                    <a href="https://www.visale.fr/" target="_blank" rel="noopener noreferrer">Visale</a>
                </article>

                <article class="arrival-step" data-reveal>
                    <span class="arrival-step__number">03</span>
                    <h3>Santé & assurance</h3>
                    <p>Affiliation à la Sécurité sociale, carte Vitale, mutuelle : les bons réflexes dès la rentrée.</p>
                    <a href="https://www.ameli.fr/" target="_blank" rel="noopener noreferrer">Ameli</a>
                </article>

                <article class="arrival-step" data-reveal>
                    <span class="arrival-step__number">04</span>
                    <h3>Banque & budget</h3>
                    <p>Compte français, RIB, aides au logement : structurez votre budget sans stress.</p>
                    <a href="https://www.caf.fr/" target="_blank" rel="noopener noreferrer">CAF</a>
                </article>
            </div>
        </section>

        <!-- Guide des ressources officielles. -->
        <section class="container guide-section" id="guide" aria-labelledby="guide-title">
            <div class="guide-section__head" data-reveal>
                <p class="eyebrow">Le guide</p>
                <h2 id="guide-title">Les bonnes sources,<br><em>au même endroit.</em></h2>
            </div>

            <div class="guide-bento">
                <a class="guide-card guide-card--wide" href="https://www.campusfrance.org/" target="_blank" rel="noopener noreferrer" data-reveal>
                    <span class="guide-card__index">01</span>
                    <h3>Campus France</h3>
                    <p>Études, bourses, dossier d’inscription et préparation du départ.</p>
                    <span class="guide-card__link">Explorer</span>
                </a>

                <a class="guide-card" href="https://www.etudiant.gouv.fr/" target="_blank" rel="noopener noreferrer" data-reveal>
                    <span class="guide-card__index">02</span>
                    <h3>Étudiant.gouv</h3>
                    <p>Les services publics de la vie étudiante.</p>
                    <span class="guide-card__link">Explorer</span>
                </a>

                <a class="guide-card" href="https://www.service-public.fr/" target="_blank" rel="noopener noreferrer" data-reveal>
                    <span class="guide-card__index">03</span>
                    <h3>Service-public</h3>
                    <p>Les démarches françaises, expliquées simplement.</p>
                    <span class="guide-card__link">Explorer</span>
                </a>

                <a class="guide-card" href="https://www.cvec.etudiant.gouv.fr/" target="_blank" rel="noopener noreferrer" data-reveal>
                    <span class="guide-card__index">04</span>
                    <h3>CVEC</h3>
                    <p>La contribution obligatoire avant l’inscription.</p>
                    <span class="guide-card__link">Explorer</span>
                </a>

                <a class="guide-card guide-card--accent" href="https://www.crous.fr/" target="_blank" rel="noopener noreferrer" data-reveal>
                    <span class="guide-card__index">05</span>
                    <h3>CROUS</h3>
                    <p>Logement, restauration et aides étudiantes.</p>
                    <span class="guide-card__link">Explorer</span>
                </a>
            </div>

            <p class="guide-section__note">
                ATLAS ne remplace pas les administrations. Vérifiez toujours les informations sur les sites officiels.
            </p>
        </section>
    </main>

    <footer class="atlas-footer atlas-footer--wide">
        <div>
            <?php
            $brandHref = 'products.php';
            $brandLabel = 'ATLAS, catalogue';
            $brandTagline = 'Student living';
            require __DIR__ . '/partials/brand.php';
            ?>
            <p>La vie étudiante sans frontières.</p>
        </div>
        <nav aria-label="Liens de pied de page">
            <a href="#catalogue">Catalogue</a>
            <a href="#arrival">L’arrivée</a>
            <a href="#guide">Le guide</a>
            <a href="admin/login.php">Accès équipe</a>
        </nav>
        <p class="atlas-footer__meta">© <?= date('Y') ?> ATLAS · France</p>
    </footer>

    <!-- Fenêtre de détail rapide commune. -->
    <?php require __DIR__ . '/partials/quick-view-dialog.php'; ?>

    <!-- Données produits en JSON, lues par JavaScript sans script inline. -->
    <script type="application/json" id="catalog-products-data" nonce="<?= csp_nonce() ?>"><?= ProductPresenter::json($productsJson) ?></script>

    <!-- Scripts de la page. -->
    <script src="<?= e(asset_url('scripts/ui/theme.js')) ?>" defer></script>
    <script src="<?= e(asset_url('scripts/product-catalog.js')) ?>" defer></script>

    <!-- Globe décoratif : chargé seulement si le thème est sombre. -->
    <script type="module" src="<?= e(asset_url('scripts/globe-background.js')) ?>"></script>
</body>
</html>
