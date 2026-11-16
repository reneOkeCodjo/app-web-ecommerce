<?php

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use Faker\Factory;
use Db_config\Database;

$faker = Factory::create('fr_FR');
$db    = Database::connect();

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

$colors      = ['Noir', 'Blanc', 'Argent', 'Graphite', 'Bleu', 'Violet', 'Or', 'Titane'];
$promoValues = [5.00, 10.00, 15.00, 20.00, 25.00, 30.00, 50.00];

$stmt = $db->prepare(
    'INSERT INTO `product` (`product_type`, `product_designation`, `product_price_ht`, `product_stock`, `product_date_in`, `ppromo`)
     VALUES (?, ?, ?, ?, ?, ?)'
);

// 's' for ppromo so PHP null maps to SQL NULL safely
$type = $designation = $date = $promo = null;
$price = 0.0;
$stock = 0;

$stmt->bind_param('ssdiss', $type, $designation, $price, $stock, $date, $promo);

$count = 0;

foreach ($catalog as $categoryName => $cfg) {
    for ($i = 0; $i < 5; $i++) {
        $brand   = $faker->randomElement($cfg['brands']);
        $line    = $faker->randomElement($cfg['lines']);
        $version = $faker->numberBetween(5, 15);
        $color   = $faker->randomElement($colors);

        $type        = $categoryName;
        $designation = "$brand $line $version";

        if (!empty($cfg['storage'])) {
            $designation .= ' ' . $faker->randomElement($cfg['storage']);
        }

        $designation .= " $color";

        [$minPrice, $maxPrice] = $cfg['price'];
        $price = $faker->randomFloat(2, $minPrice, $maxPrice);
        $stock = $faker->numberBetween(0, 200);
        $date  = $faker->dateTimeBetween('-2 years', 'now')->format('Y-m-d');
        $promo = $faker->boolean(30) ? (string) $faker->randomElement($promoValues) : null;

        $stmt->execute();
        $count++;
    }
}

$stmt->close();
$db->close();

echo "Seeded {$count} products successfully.\n";
