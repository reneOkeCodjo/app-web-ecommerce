<?php

require_once __DIR__ . '/../vendor/autoload.php';

// Chargement de la configuration, dont le nom de fichier est database.php.
require_once __DIR__ . '/../config/database.php';

use App\Domain\Model\Product;
use App\Infrastructure\Persistence\DAO\ProductRepository;
use Db_config\Database;

// Préparation de la connexion et du produit de test.
$connection = Database::connect();
$repository = new ProductRepository($connection);


// Vérification de la lecture des produits.
$products = $repository->findAll();
echo 'Nombre de produits : ' . count($products) . PHP_EOL;

$productFound = $repository->findById(1);

if ($productFound !== null) {
    echo 'Produit 1 : ' . $productFound->getDesignation() . PHP_EOL;
} else {
    echo "Produit 1 introuvable.\n";
}

$product = new Product(
    null,
    'Casque audio',
    'Casque test CRUD',
    '30.00',
    5,
    date('Y-m-d'),
    null
);

// Vérification de l'ajout sans conserver les données de test.
$connection->begin_transaction();

try {
    $success = $repository->create($product);
    $id = $connection->insert_id;

    $stmt = $connection->prepare(
        'SELECT product_designation, product_stock
         FROM product WHERE product_id = ?'
    );
    $stmt->bind_param('i', $id);
    $stmt->execute();

    $row = $stmt->get_result()->fetch_assoc();

    if (
        $success
        && $row !== null
        && $row['product_designation'] === 'Casque test CRUD'
        && (int) $row['product_stock'] === 5
    ) {
        echo "Ajout vérifié : le produit est bien enregistré.\n";
    } else {
        throw new \RuntimeException("Le résultat de l'ajout est incorrect.");
    }


    // Vérification de la modification du produit de test.
$modifiedProduct = new Product(
    $id,
    'Casque audio',
    'Casque modifié',
    '25.00',
    10,
    date('Y-m-d'),
    null
);

$updated = $repository->update($modifiedProduct);
$found = $repository->findById($id);

if (
    !$updated
    || $found === null
    || $found->getDesignation() !== 'Casque modifié'
    || $found->getPriceHt() !== '25.00'
    || $found->getStock() !== 10
) {
    throw new \RuntimeException('Modification incorrecte.');
}

echo "Modification vérifiée.\n";

// Vérification de la suppression du produit de test.
$deleted = $repository->delete($id);

if (!$deleted || $repository->findById($id) !== null) {
    throw new \RuntimeException('Suppression incorrecte.');
}

echo "Suppression vérifiée.\n";


} finally {
    $connection->rollback();
    $connection->close();
}