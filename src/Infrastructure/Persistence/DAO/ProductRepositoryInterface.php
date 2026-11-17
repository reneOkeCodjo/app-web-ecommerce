<?php

// On impose des types stricts pour éviter les conversions silencieuses.
declare(strict_types=1);

// Ce fichier vit dans la couche technique qui parle à la base de données.
namespace App\Infrastructure\Persistence\DAO;

// On utilise le modèle Product.
use App\Domain\Model\Product;

/**
 * Contrat que doit respecter tout dépôt de produits.
 *
 * Un « dépôt » (repository) est une classe qui range et retrouve les produits.
 * On peut ainsi changer le stockage (MySQL, session, mémoire) sans toucher au reste.
 */
interface ProductRepositoryInterface
{
    /**
     * Enregistre un nouveau produit.
     */
    public function create(Product $product): bool;

    /**
     * Renvoie tous les produits, du plus récent au plus ancien.
     *
     * @return list<Product>
     */
    public function findAll(): array;

    /**
     * Cherche un produit par son identifiant.
     */
    public function findById(int $id): ?Product;

    /**
     * Met à jour un produit existant.
     */
    public function update(Product $product): bool;

    /**
     * Supprime un produit par son identifiant.
     */
    public function delete(int $id): bool;
}
