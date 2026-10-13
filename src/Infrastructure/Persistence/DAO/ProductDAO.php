<?php
namespace App\Infrastructure\Persistence\DAO;

use App\Domain\Model\Product;

interface ProductRepositoryInterface
{

    public function create(Product $product): bool;
    public function findAll(): array;
    public function findById(int $id): ?Product;
    public function update(Product $product): bool;
    public function delete(int $id): bool;
}


/**
 * Interaction avec la table product de la base de données.
 */
class ProductRepository implements ProductRepositoryInterface
{
    // Connexion utilisée pour accéder à la base de données.
    private \mysqli $connection;

    public function __construct(\mysqli $connection)
    {
         // La connexion est déjà créée : on la reçoit et on la garde dans cet objet.
        $this->connection = $connection;
    }

    public function create(Product $product): bool
    {
        
        $stmt = $this->connection->prepare(
            'INSERT INTO product (
                product_type,
                product_designation,
                product_price_ht,
                product_stock,
                product_date_in
            ) VALUES (?, ?, ?, ?, ?)'
        );
          
        // Récupération des informations du produit à enregistrer.
        $type = $product->getType();
        $designation = $product->getDesignation();
        $priceHt = $product->getPriceHt();
        $stock = $product->getStock();
        $dateIn = $product->getDateIn();


        // Association des valeurs aux emplacements de la requête.
        $stmt->bind_param(
            'sssis',
            $type,
            $designation,
            $priceHt,
            $stock,
            $dateIn
        );

        // Exécution de l'ajout et retour du résultat.
        return $stmt->execute();
       
    }




    // Lecture de tous les produits, du plus récent au plus ancien.
    public function findAll(): array
    {
        $result = $this->connection->query(
            'SELECT * FROM product ORDER BY product_id DESC'
        );

        $products = [];

        while ($row = $result->fetch_assoc()) {
            $products[] = $this->mapToProduct($row);
        }

        return $products;
    }



    // Recherche d'un produit à partir de son identifiant.
    public function findById(int $id): ?Product
    {
        $stmt = $this->connection->prepare(
            'SELECT * FROM product WHERE product_id = ?'
        );

        $stmt->bind_param('i', $id);
        $stmt->execute();

        $row = $stmt->get_result()->fetch_assoc();

        if ($row === null) {
            return null;
        }

        return $this->mapToProduct($row);
    }



    // Transformation d'une ligne de la base en objet Product.
    private function mapToProduct(array $row): Product
    {
        return new Product(
            (int) $row['product_id'],
            $row['product_type'],
            $row['product_designation'],
            (string) $row['product_price_ht'],
            (int) $row['product_stock'],
            $row['product_date_in'],
            $row['product_date_new']
        );
    }



    // Modification des informations du produit identifié par son ID.
    public function update(Product $product): bool
    {
        $id = $product->getId();

        if ($id === null) {
            throw new \InvalidArgumentException(
                'Un identifiant est nécessaire pour modifier un produit.'
            );
        }

        $stmt = $this->connection->prepare(
            'UPDATE product SET
                product_type = ?,
                product_designation = ?,
                product_price_ht = ?,
                product_stock = ?,
                product_date_in = ?
            WHERE product_id = ?'
        );

        $type = $product->getType();
        $designation = $product->getDesignation();
        $priceHt = $product->getPriceHt();
        $stock = $product->getStock();
        $dateIn = $product->getDateIn();

        $stmt->bind_param(
            'sssisi',
            $type,
            $designation,
            $priceHt,
            $stock,
            $dateIn,
            $id
        );

        return $stmt->execute();
    }

    // Suppression du produit identifié par son ID.
    public function delete(int $id): bool
    {
        $stmt = $this->connection->prepare(
            'DELETE FROM product WHERE product_id = ?'
        );

        $stmt->bind_param('i', $id);
        $stmt->execute();

        return $stmt->affected_rows === 1;
    }

    
}


