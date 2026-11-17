<?php

// On impose des types stricts pour éviter les conversions silencieuses.
declare(strict_types=1);

// Ce fichier vit dans la couche technique qui parle à la base de données.
namespace App\Infrastructure\Persistence\DAO;

// On utilise le modèle Product.
use App\Domain\Model\Product;
use InvalidArgumentException;

/**
 * Dépôt de produits stockés dans la table MySQL `product`.
 *
 * Toutes les requêtes utilisent des paramètres préparés : les valeurs
 * envoyées par l'utilisateur ne sont jamais collées directement dans le SQL.
 */
final class ProductRepository implements ProductRepositoryInterface
{
    // Connexion MySQL partagée avec le reste de la page.
    private \mysqli $connection;

    /**
     * On reçoit une connexion déjà ouverte.
     */
    public function __construct(\mysqli $connection)
    {
        $this->connection = $connection;
    }

    /**
     * Enregistre un nouveau produit.
     */
    public function create(Product $product): bool
    {
        // On prépare la requête avec des points d'interrogation à la place des valeurs.
        $statement = $this->connection->prepare(
            'INSERT INTO product (
                product_type,
                product_designation,
                product_price_ht,
                product_stock,
                product_date_in,
                ppromo,
                product_image
            ) VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        // On lit chaque valeur depuis l'objet produit.
        $type = $product->getType();
        $designation = $product->getDesignation();
        $priceHt = $product->getPriceHt();
        $stock = $product->getStock();
        $dateIn = $product->getDateIn();
        $promotion = $product->getPromotion();
        $image = $product->getImage();

        // On associe les valeurs : texte, texte, texte (décimal), entier, date, texte, texte.
        $statement->bind_param(
            'sssisss',
            $type,
            $designation,
            $priceHt,
            $stock,
            $dateIn,
            $promotion,
            $image
        );

        // On exécute et on renvoie vrai si tout s'est bien passé.
        return $statement->execute();
    }

    /**
     * Renvoie tous les produits, du plus récent au plus ancien.
     *
     * @return list<Product>
     */
    public function findAll(): array
    {
        // On demande toutes les lignes, triées par identifiant décroissant.
        $result = $this->connection->query(
            'SELECT * FROM product ORDER BY product_id DESC'
        );

        // On prépare la liste qui sera renvoyée.
        $products = [];

        // On transforme chaque ligne SQL en objet Product.
        while ($row = $result->fetch_assoc()) {
            $products[] = $this->mapToProduct($row);
        }

        // On renvoie la liste complète.
        return $products;
    }

    /**
     * Cherche un produit par son identifiant.
     */
    public function findById(int $id): ?Product
    {
        // On prépare la recherche.
        $statement = $this->connection->prepare(
            'SELECT * FROM product WHERE product_id = ?'
        );

        // On remplace le point d'interrogation par l'identifiant.
        $statement->bind_param('i', $id);
        $statement->execute();

        // On lit la première ligne trouvée.
        $row = $statement->get_result()->fetch_assoc();

        // Aucun produit : on renvoie null.
        if ($row === null) {
            return null;
        }

        // Sinon, on renvoie l'objet correspondant.
        return $this->mapToProduct($row);
    }

    /**
     * Transforme une ligne SQL en objet Product.
     *
     * @param array<string, mixed> $row
     */
    private function mapToProduct(array $row): Product
    {
        // La promotion et l'image peuvent être absentes (NULL).
        $promotion = $row['ppromo'] ?? null;
        $image = $row['product_image'] ?? null;

        // On construit le produit avec des types propres.
        return new Product(
            (int) $row['product_id'],
            (string) $row['product_type'],
            (string) $row['product_designation'],
            (string) $row['product_price_ht'],
            (int) $row['product_stock'],
            (string) $row['product_date_in'],
            (string) $row['product_date_new'],
            $promotion !== null ? (string) $promotion : null,
            $image !== null ? (string) $image : null
        );
    }

    /**
     * Met à jour un produit existant.
     */
    public function update(Product $product): bool
    {
        // Un produit à modifier doit avoir un identifiant.
        $id = $product->getId();

        if ($id === null) {
            throw new InvalidArgumentException(
                'Un identifiant est nécessaire pour modifier un produit.'
            );
        }

        // On prépare la mise à jour.
        $statement = $this->connection->prepare(
            'UPDATE product SET
                product_type = ?,
                product_designation = ?,
                product_price_ht = ?,
                product_stock = ?,
                product_date_in = ?,
                ppromo = ?,
                product_image = ?
            WHERE product_id = ?'
        );

        // On lit les nouvelles valeurs.
        $type = $product->getType();
        $designation = $product->getDesignation();
        $priceHt = $product->getPriceHt();
        $stock = $product->getStock();
        $dateIn = $product->getDateIn();
        $promotion = $product->getPromotion();
        $image = $product->getImage();

        // On associe les valeurs, en terminant par l'identifiant.
        $statement->bind_param(
            'sssisssi',
            $type,
            $designation,
            $priceHt,
            $stock,
            $dateIn,
            $promotion,
            $image,
            $id
        );

        // On exécute la mise à jour.
        return $statement->execute();
    }

    /**
     * Supprime un produit par son identifiant.
     */
    public function delete(int $id): bool
    {
        // On prépare la suppression.
        $statement = $this->connection->prepare(
            'DELETE FROM product WHERE product_id = ?'
        );

        // On remplace le point d'interrogation par l'identifiant.
        $statement->bind_param('i', $id);
        $statement->execute();

        // On confirme qu'une seule ligne a bien été supprimée.
        return $statement->affected_rows === 1;
    }
}
