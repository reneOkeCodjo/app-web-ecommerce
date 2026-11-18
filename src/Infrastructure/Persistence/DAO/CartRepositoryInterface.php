<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence\DAO;

/**
 * Contrat de persistance du panier d'un utilisateur connecté.
 */
interface CartRepositoryInterface
{
    /**
     * Renvoie les quantités par identifiant de produit.
     *
     * @return array<int, int>
     */
    public function findItems(int $userId): array;

    /**
     * Remplace tout le panier de l'utilisateur.
     *
     * @param array<int, int> $items
     */
    public function replaceItems(int $userId, array $items): void;

    /**
     * Vide le panier sans supprimer la ligne principale.
     */
    public function clear(int $userId): void;
}
