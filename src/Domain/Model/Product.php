<?php

declare(strict_types=1);

namespace App\Domain\Model;

/**
 * Product entity aligned with table `produit`
 * (id_p, type_p, designation_p, prix_ht, date_in, times_in, stock_p).
 */
final class Product
{
    public function __construct(
        private int $id,
        private string $type,
        private string $designation,
        private float $prixHt,
        private string $dateIn,
        private string $timesIn,
        private int $stock = 0
    ) {
    }

    /** Column: id_p */
    public function getId(): int
    {
        return $this->id;
    }

    /** Column: type_p */
    public function getType(): string
    {
        return $this->type;
    }

    /** Column: designation_p */
    public function getDesignation(): string
    {
        return $this->designation;
    }

    /** Column: prix_ht */
    public function getPrixHt(): float
    {
        return $this->prixHt;
    }

    /** Column: date_in (Y-m-d) */
    public function getDateIn(): string
    {
        return $this->dateIn;
    }

    /** Column: times_in (Y-m-d H:i:s) */
    public function getTimesIn(): string
    {
        return $this->timesIn;
    }

    /** Column: stock_p */
    public function getStock(): int
    {
        return $this->stock;
    }
}
