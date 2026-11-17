<?php

declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Application\Services\Cart\CartService;
use App\Domain\Model\Product;
use App\Infrastructure\Http\SessionStoreInterface;
use App\Infrastructure\Persistence\DAO\ProductRepositoryInterface;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class CartServiceTest extends TestCase
{
    public function testCartCalculatesPromotionVatAndFreeShipping(): void
    {
        // Vérifie la promotion, la TVA et la gratuité de l'expédition.
        $cart = $this->cart([
            new Product(1, 'Audio', 'Casque', '100.00', 3, '2026-01-01', null, '10.00'),
        ]);

        $cart->add(1, 2);
        $snapshot = $cart->snapshot();

        self::assertSame(2, $snapshot['totals']['quantity']);
        self::assertSame(180.0, $snapshot['totals']['subtotalHt']);
        self::assertSame(36.0, $snapshot['totals']['vat']);
        self::assertSame(216.0, $snapshot['totals']['subtotalTtc']);
        self::assertSame(0.0, $snapshot['totals']['shipping']);
        self::assertSame(216.0, $snapshot['totals']['total']);
    }

    public function testCartChargesShippingBelowThreshold(): void
    {
        // Vérifie les frais appliqués sous le seuil de livraison gratuite.
        $cart = $this->cart([
            new Product(1, 'Audio', 'Casque', '10.00', 3, '2026-01-01', null),
        ]);

        $cart->add(1);
        $snapshot = $cart->snapshot();

        self::assertSame(12.0, $snapshot['totals']['subtotalTtc']);
        self::assertSame(5.99, $snapshot['totals']['shipping']);
        self::assertSame(17.99, $snapshot['totals']['total']);
    }

    public function testCartRejectsQuantityAboveStock(): void
    {
        // Le stock doit être contrôlé par le service, pas seulement par le navigateur.
        $cart = $this->cart([
            new Product(1, 'Audio', 'Casque', '10.00', 2, '2026-01-01', null),
        ]);

        $this->expectException(InvalidArgumentException::class);
        $cart->add(1, 3);
    }

    /**
     * @param list<Product> $products
     */
    private function cart(array $products): CartService
    {
        // Les faux repository et session rendent le test indépendant de Docker et MySQL.
        $repository = new class ($products) implements ProductRepositoryInterface {
            /** @param list<Product> $products */
            public function __construct(private array $products)
            {
            }

            public function create(Product $product): bool
            {
                return true;
            }

            public function findAll(): array
            {
                return $this->products;
            }

            public function findById(int $id): ?Product
            {
                foreach ($this->products as $product) {
                    if ($product->getId() === $id) {
                        return $product;
                    }
                }

                return null;
            }

            public function update(Product $product): bool
            {
                return true;
            }

            public function delete(int $id): bool
            {
                return true;
            }
        };

        $session = new class implements SessionStoreInterface {
            /** @var array<string, mixed> */
            private array $values = [];

            public function set(string $key, mixed $value): void
            {
                $this->values[$key] = $value;
            }

            public function get(string $key): mixed
            {
                return $this->values[$key] ?? null;
            }

            public function remove(string $key): void
            {
                unset($this->values[$key]);
            }

            public function regenerate(): void
            {
            }

            public function destroy(): void
            {
                $this->values = [];
            }
        };

        return new CartService($repository, $session);
    }
}
