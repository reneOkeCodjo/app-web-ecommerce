<?php

declare(strict_types=1);

namespace App\Application\Services;

use App\Application\DTO\ProductRequestDTO;
use App\Domain\Model\Product;
use App\Infrastructure\Persistence\DAO\ProductRepositoryInterface;
use InvalidArgumentException;
use RuntimeException;

class ProductService
{
    public function __construct(
        private ProductRepositoryInterface $repository,
        private ValidationInterface $validation = new Validation()
    ) {
    }

    /**
     * @return list<Product>
     */
    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    public function findById(int $id): ?Product
    {
        return $this->repository->findById($id);
    }

    /**
     * Validate raw input, then create the product.
     */
    public function create(
        mixed $type,
        mixed $designation,
        mixed $priceHt,
        mixed $stock,
        mixed $dateIn,
        mixed $promotion = null,
        mixed $image = null
    ): bool {
        $dto = $this->requireValidProductDto(
            $type,
            $designation,
            $priceHt,
            $stock,
            $dateIn,
            $promotion,
            $image
        );

        return $this->repository->create($this->toProduct($dto));
    }

    /**
     * Validate raw input, then update an existing product.
     */
    public function update(
        int $id,
        mixed $type,
        mixed $designation,
        mixed $priceHt,
        mixed $stock,
        mixed $dateIn,
        mixed $promotion = null,
        mixed $image = null
    ): bool {
        $existing = $this->repository->findById($id);

        if ($existing === null) {
            throw new RuntimeException('Produit introuvable.');
        }

        $dto = $this->requireValidProductDto(
            $type,
            $designation,
            $priceHt,
            $stock,
            $dateIn,
            $promotion,
            $image
        );

        return $this->repository->update(
            $this->toProduct($dto, $id, $existing->getDateNew())
        );
    }

    public function delete(int $id): bool
    {
        if ($id <= 0) {
            throw new InvalidArgumentException('Identifiant invalide.');
        }

        return $this->repository->delete($id);
    }

    private function requireValidProductDto(
        mixed $type,
        mixed $designation,
        mixed $priceHt,
        mixed $stock,
        mixed $dateIn,
        mixed $promotion,
        mixed $image
    ): ProductRequestDTO {
        $result = $this->validation->validateProduct(
            $type,
            $designation,
            $priceHt,
            $stock,
            $dateIn,
            $promotion,
            $image
        );

        if (!$result->isValid()) {
            throw new InvalidArgumentException((string) $result->getFirstError());
        }

        $dto = $result->getProductRequest();

        if ($dto === null) {
            throw new RuntimeException('Validation produit incohérente.');
        }

        return $dto;
    }

    private function toProduct(
        ProductRequestDTO $dto,
        ?int $id = null,
        ?string $dateNew = null
    ): Product {
        return new Product(
            $id,
            $dto->getType(),
            $dto->getDesignation(),
            $dto->getPriceHt(),
            (int) $dto->getStock(),
            $dto->getDateIn(),
            $dateNew,
            $dto->getPromotion(),
            $dto->getImage()
        );
    }
}
