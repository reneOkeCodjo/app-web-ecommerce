<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Domain\Model\Product;
use App\Infrastructure\Persistence\DAO\ProductRepository;
use Db_config\Database;

$connection = Database::connect();
$repository = new ProductRepository($connection);

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
    null,
    '10.00',
    '/uploads/products/casque.jpg'
);

$connection->begin_transaction();

try {
    $success = $repository->create($product);
    $id = $connection->insert_id;

    $found = $repository->findById((int) $id);

    if (
        !$success
        || $found === null
        || $found->getDesignation() !== 'Casque test CRUD'
        || $found->getStock() !== 5
        || $found->getPromotion() !== '10.00'
        || $found->getImage() !== '/uploads/products/casque.jpg'
    ) {
        throw new \RuntimeException("Le résultat de l'ajout est incorrect.");
    }

    echo "Ajout vérifié : le produit est bien enregistré.\n";

    $modifiedProduct = new Product(
        (int) $id,
        'Casque audio',
        'Casque modifié',
        '25.00',
        10,
        date('Y-m-d'),
        $found->getDateNew(),
        '15.50',
        '/uploads/products/casque-v2.jpg'
    );

    $updated = $repository->update($modifiedProduct);
    $afterUpdate = $repository->findById((int) $id);

    if (
        !$updated
        || $afterUpdate === null
        || $afterUpdate->getDesignation() !== 'Casque modifié'
        || $afterUpdate->getPriceHt() !== '25.00'
        || $afterUpdate->getStock() !== 10
        || $afterUpdate->getPromotion() !== '15.50'
        || $afterUpdate->getImage() !== '/uploads/products/casque-v2.jpg'
    ) {
        throw new \RuntimeException('Modification incorrecte.');
    }

    echo "Modification vérifiée.\n";

    $deleted = $repository->delete((int) $id);

    if (!$deleted || $repository->findById((int) $id) !== null) {
        throw new \RuntimeException('Suppression incorrecte.');
    }

    echo "Suppression vérifiée.\n";
} finally {
    $connection->rollback();
    $connection->close();
}
