<?php

// Ce script remplit la table `product` avec des données de démonstration.
// Il ne s'exécute que depuis la ligne de commande.
declare(strict_types=1);

// On refuse un appel depuis le navigateur.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('Ce script doit être lancé en ligne de commande.');
}

// On charge l'autoload de Composer.
$autoload = __DIR__ . '/../vendor/autoload.php';
if (!is_file($autoload)) {
    fwrite(STDERR, "Dépendances manquantes : lancez « composer install ».\n");
    exit(1);
}
require_once $autoload;

// On importe les classes utiles.
use Db_config\Database;
use Faker\Factory;

// On lit les options : « --fresh » vide la table avant de la remplir.
$options = array_slice($argv, 1);
$fresh = in_array('--fresh', $options, true);

// On ouvre la connexion à la base.
try {
    $db = Database::connect();
} catch (\Throwable $e) {
    fwrite(STDERR, "Connexion à la base impossible. Vérifiez votre fichier .env.\n");
    exit(1);
}

// On compte les produits déjà présents.
$existingCount = (int) $db->query('SELECT COUNT(*) FROM `product`')->fetch_row()[0];

// Si la table contient déjà des produits et que l'option --fresh est absente,
// on s'arrête pour éviter les doublons.
if ($existingCount > 0 && !$fresh) {
    echo "La table contient déjà {$existingCount} produit(s).\n";
    echo "Utilisez « php database/seed.php --fresh » pour la vider et la remplir à nouveau.\n";
    $db->close();
    exit(0);
}

// Avec --fresh, on supprime les produits existants.
if ($fresh) {
    $db->query('DELETE FROM `product`');
    echo "Table produit vidée.\n";
}

// On crée un générateur de fausses données en français.
$faker = Factory::create('fr_FR');

// On fixe la graine pour obtenir des données reproductibles.
$faker->seed(20261117);

// Catalogue de démonstration : catégories, marques, gammes, stockage et prix.
$catalog = [
    'Smartphone' => [
        'brands'  => ['Apple', 'Samsung', 'Google', 'Xiaomi', 'OnePlus', 'Sony'],
        'lines'   => ['Pro', 'Ultra', 'Plus', 'Max', 'FE'],
        'storage' => ['128 Go', '256 Go', '512 Go'],
        'price'   => [299.99, 1299.99],
    ],
    'Ordinateur portable' => [
        'brands'  => ['Apple', 'Lenovo', 'ASUS', 'HP', 'Dell', 'MSI'],
        'lines'   => ['Pro', 'Air', 'Ultra', 'Plus', 'Carbon'],
        'storage' => ['256 Go', '512 Go', '1 To'],
        'price'   => [499.99, 2499.99],
    ],
    'Casque audio' => [
        'brands'  => ['Sony', 'Apple', 'Bose', 'Sennheiser', 'JBL', 'Audio-Technica'],
        'lines'   => ['Pro', 'Elite', 'Plus', 'Premium', 'Ultra'],
        'storage' => [],
        'price'   => [49.99, 599.99],
    ],
    'Montre connectée' => [
        'brands'  => ['Apple', 'Samsung', 'Garmin', 'Google', 'Fitbit'],
        'lines'   => ['Series', 'Classic', 'Active', 'Sport', 'Sense'],
        'storage' => [],
        'price'   => [149.99, 599.99],
    ],
    'Tablette' => [
        'brands'  => ['Apple', 'Samsung', 'Lenovo', 'Xiaomi', 'Microsoft'],
        'lines'   => ['Pro', 'Air', 'Plus', 'FE', 'Ultra'],
        'storage' => ['128 Go', '256 Go', '512 Go'],
        'price'   => [199.99, 1299.99],
    ],
    'Caméra' => [
        'brands'  => ['Sony', 'Canon', 'Nikon', 'Fujifilm', 'GoPro'],
        'lines'   => ['Mark II', 'Mark III', 'Black', 'Silver', 'Classic'],
        'storage' => [],
        'price'   => [299.99, 3499.99],
    ],
];

// Couleurs et pourcentages de promotion possibles.
$colors = ['Noir', 'Blanc', 'Argent', 'Graphite', 'Bleu', 'Violet', 'Or', 'Titane'];
$promoValues = [5.00, 10.00, 15.00, 20.00, 25.00, 30.00, 50.00];

// On prépare la requête d'insertion.
$statement = $db->prepare(
    'INSERT INTO `product`
        (`product_type`, `product_designation`, `product_price_ht`, `product_stock`, `product_date_in`, `ppromo`)
     VALUES (?, ?, ?, ?, ?, ?)'
);

// Variables liées à la requête (mysqli exige des références).
$type = '';
$designation = '';
$price = 0.0;
$stock = 0;
$date = '';
$promo = null;

// On associe les variables aux paramètres : texte, texte, décimal, entier, date, texte.
$statement->bind_param('ssdiss', $type, $designation, $price, $stock, $date, $promo);

// Compteur de produits créés.
$count = 0;

// On parcourt chaque catégorie.
foreach ($catalog as $categoryName => $configuration) {
    // On crée cinq produits par catégorie.
    for ($index = 0; $index < 5; $index++) {
        // On choisit une marque, une gamme, un numéro et une couleur.
        $brand = $faker->randomElement($configuration['brands']);
        $line = $faker->randomElement($configuration['lines']);
        $version = $faker->numberBetween(5, 15);
        $color = $faker->randomElement($colors);

        // On construit le nom du produit.
        $designation = "{$brand} {$line} {$version}";

        // On ajoute le stockage si la catégorie en propose.
        if (!empty($configuration['storage'])) {
            $designation .= ' ' . $faker->randomElement($configuration['storage']);
        }

        // On termine par la couleur.
        $designation .= " {$color}";

        // On prépare les valeurs du produit.
        [$minimumPrice, $maximumPrice] = $configuration['price'];
        $type = $categoryName;
        $price = $faker->randomFloat(2, $minimumPrice, $maximumPrice);
        $stock = $faker->numberBetween(0, 200);
        $date = $faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d');
        $promo = $faker->boolean(30)
            ? (string) $faker->randomElement($promoValues)
            : null;

        // On enregistre le produit.
        $statement->execute();
        $count++;
    }
}

// On ferme la requête et la connexion.
$statement->close();
$db->close();

// On affiche le résultat.
echo "{$count} produit(s) de démonstration créé(s).\n";
