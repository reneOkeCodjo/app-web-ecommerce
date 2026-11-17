<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\DAO;

use RuntimeException;

interface CartRepositoryInterface
{
    /**
     * @return array<int, int>
     */
    public function findItems(int $userId): array;

    /**
     * @param array<int, int> $items
     */
    public function replaceItems(int $userId, array $items): void;

    public function clear(int $userId): void;
}

final class CartRepository implements CartRepositoryInterface
{
    public function __construct(private \mysqli $connection)
    {
    }

    public function findItems(int $userId): array
    {
        $stmt = $this->connection->prepare(
            'SELECT ci.product_id, ci.cart_item_quantity
             FROM cart_items ci
             INNER JOIN carts c ON c.cart_id = ci.cart_id
             WHERE c.cart_user_id = ?'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $result = $stmt->get_result();
        $items = [];

        while ($row = $result->fetch_assoc()) {
            $items[(int) $row['product_id']] = (int) $row['cart_item_quantity'];
        }

        $stmt->close();

        return $items;
    }

    public function replaceItems(int $userId, array $items): void
    {
        $this->connection->begin_transaction();

        try {
            $cartId = $this->getOrCreateCartId($userId);

            $delete = $this->connection->prepare(
                'DELETE FROM cart_items WHERE cart_id = ?'
            );
            $delete->bind_param('i', $cartId);
            $delete->execute();
            $delete->close();

            if ($items !== []) {
                $insert = $this->connection->prepare(
                    'INSERT INTO cart_items (cart_id, product_id, cart_item_quantity)
                     VALUES (?, ?, ?)'
                );

                foreach ($items as $productId => $quantity) {
                    $insert->bind_param('iii', $cartId, $productId, $quantity);
                    $insert->execute();
                }

                $insert->close();
            }

            $this->connection->commit();
        } catch (\Throwable $e) {
            $this->connection->rollback();
            throw new RuntimeException('Impossible de sauvegarder le panier.', 0, $e);
        }
    }

    public function clear(int $userId): void
    {
        $stmt = $this->connection->prepare(
            'DELETE ci
             FROM cart_items ci
             INNER JOIN carts c ON c.cart_id = ci.cart_id
             WHERE c.cart_user_id = ?'
        );
        $stmt->bind_param('i', $userId);
        $stmt->execute();
        $stmt->close();
    }

    private function getOrCreateCartId(int $userId): int
    {
        $insert = $this->connection->prepare(
            'INSERT INTO carts (cart_user_id)
             VALUES (?)
             ON DUPLICATE KEY UPDATE cart_date_updated = CURRENT_TIMESTAMP'
        );
        $insert->bind_param('i', $userId);
        $insert->execute();
        $insert->close();

        $select = $this->connection->prepare(
            'SELECT cart_id FROM carts WHERE cart_user_id = ?'
        );
        $select->bind_param('i', $userId);
        $select->execute();
        $row = $select->get_result()->fetch_assoc();
        $select->close();

        if ($row === null) {
            throw new RuntimeException('Panier utilisateur introuvable.');
        }

        return (int) $row['cart_id'];
    }
}
