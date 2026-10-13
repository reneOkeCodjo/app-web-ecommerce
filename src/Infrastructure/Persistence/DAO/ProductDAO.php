<?php
namespace App\Infrastructure\Persistence\DAO;

use App\Domain\Model\Product;

interface ProductRepositoryInterface
{

    /**
     * Création d'un produit.
     * 
     * @param Product $product Le produit à créer.
     * @return bool
     */
    public function create(Product $product): bool;

    /**
     * Recherche de tous les produits.
     * 
     * @return array La liste des produits. Retourne un tableau vide si aucun produit n'est trouvé.
     */
    public function findAll(): array;

    /**
     * Recherche d'un produit à partir de son identifiant.
     * 
     * @param int $id L'identifiant du produit.
     * @return Product|null Le produit trouvé ou null si aucun produit n'est trouvé.
     */
    public function findById(int $id): ?Product;
    
    /**
     * Modification d'un produit.
     * 
     * @param Product $product Le produit à modifier.
     * @return bool
     */
    public function update(Product $product): bool;

    /**
     * Suppression d'un produit.
     * 
     * @param int $id L'identifiant du produit à supprimer.
     * @return bool
     */
    public function delete(int $id): bool;
}


/**
 * Interaction avec la table product de la base de données.
 */
class ProductRepository implements ProductRepositoryInterface
{
    private \mysqli $connection;

    public function __construct(\mysqli $connection)
    {
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
        $promotion = $row['ppromo'] ?? null;
        $image = $row['product_image'] ?? null;

        return new Product(
            (int) $row['product_id'],
            $row['product_type'],
            $row['product_designation'],
            (string) $row['product_price_ht'],
            (int) $row['product_stock'],
            $row['product_date_in'],
            $row['product_date_new'],
            $promotion !== null ? (string) $promotion : null,
            $image !== null ? (string) $image : null
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


