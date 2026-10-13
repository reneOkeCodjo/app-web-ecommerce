<?php

namespace App\Application\Services;

use App\Application\DTO\ProductRequestDTO;
use App\Domain\Model\Product;
use App\Infrastructure\Persistence\DAO\ProductRepositoryInterface;

class ProductService
{
    private ProductRepositoryInterface $repository;

    public function __construct(ProductRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    // Lecture des produits pour la liste et le formulaire de modification.
    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    public function findById(int $id): ?Product
    {
        return $this->repository->findById($id);
    }

    // Validation des données, puis ajout du produit.
    public function create(ProductRequestDTO $dto): bool
    {
        $product = $this->buildProduct($dto);

        return $this->repository->create($product);
    }

    // Vérification de l'existence du produit avant sa modification.
    public function update(int $id, ProductRequestDTO $dto): bool
    {
        $existing = $this->repository->findById($id);

        if ($existing === null) {
            throw new \RuntimeException('Produit introuvable.');
        }

        $product = $this->buildProduct(
            $dto,
            $id,
            $existing->getDateNew()
        );

        return $this->repository->update($product);
    }

    // Suppression du produit demandé.
    public function delete(int $id): bool
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Identifiant invalide.');
        }

        return $this->repository->delete($id);
    }

    // Vérification des saisies et construction de l'objet Product.
    private function buildProduct(
        ProductRequestDTO $dto,
        ?int $id = null,
        ?string $dateNew = null
    ): Product {
        $type = $dto->getType();
        $designation = $dto->getDesignation();
        $price = $dto->getPriceHt();
        $stock = $dto->getStock();
        $dateIn = $dto->getDateIn();
        $promotion = $dto->getPromotion();
        $image = $dto->getImage();

        if ($type === '' || mb_strlen($type) > 100) {
            throw new \InvalidArgumentException(
                'La catégorie doit contenir entre 1 et 100 caractères.'
            );
        }

        // Vérification que la catégorie appartient à la liste autorisée.
        $categoriesPath = __DIR__ . '/../../../public/data/product-categories.json';
        $categoriesJson = file_get_contents($categoriesPath);

        if ($categoriesJson === false) {
            throw new \RuntimeException('Impossible de charger les catégories produits.');
        }

        $categories = json_decode(
            $categoriesJson,
            true,
            512,
            JSON_THROW_ON_ERROR
        );

        $allowedTypes = array_column($categories, 'categorie');

        if (!in_array($type, $allowedTypes, true)) {
            throw new \InvalidArgumentException(
                'Choisissez une catégorie proposée dans la liste.'
            );
        }

        if ($designation === '' || mb_strlen($designation) > 255) {
            throw new \InvalidArgumentException(
                'La désignation doit contenir entre 1 et 255 caractères.'
            );
        }

        // Le prix doit tenir dans DECIMAL(10,2), sans valeur négative.
        if (!preg_match('/^\d{1,8}(?:\.\d{1,2})?$/D', $price)) {
            throw new \InvalidArgumentException(
                'Prix invalide : utilisez un point et au maximum deux décimales.'
            );
        }

        $stockValue = filter_var(
            $stock,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 2147483647]]
        );

        if ($stockValue === false) {
            throw new \InvalidArgumentException(
                'Le stock doit être un entier positif ou égal à zéro.'
            );
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $dateIn);

        if (
            $date === false
            || $date->format('Y-m-d') !== $dateIn
            || $dateIn < '1000-01-01'
        ) {
            throw new \InvalidArgumentException('Date invalide.');
        }

        if (
            $promotion !== null
            && !preg_match('/^\d{1,3}(?:\.\d{1,2})?$/D', $promotion)
        ) {
            throw new \InvalidArgumentException(
                'Promotion invalide : pourcentage avec au maximum deux décimales.'
            );
        }

        if ($promotion !== null && (float) $promotion > 100) {
            throw new \InvalidArgumentException(
                'La promotion ne peut pas dépasser 100 %.'
            );
        }

        if ($image !== null && mb_strlen($image) > 255) {
            throw new \InvalidArgumentException(
                'Le chemin ou l’URL de l’image doit faire au plus 255 caractères.'
            );
        }

        return new Product(
            $id,
            $type,
            $designation,
            $price,
            $stockValue,
            $dateIn,
            $dateNew,
            $promotion,
            $image
        );
    }
}
