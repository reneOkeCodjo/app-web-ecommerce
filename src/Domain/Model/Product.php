<?php

namespace App\Domain\Model;


class Product
{
    private ?int $id = null;
    private string $type;
    private string $designation;
    private string $price_ht;
    private int $stock;
    private string $date_in;
    private ?string $date_new = null;
    private ?string $promotion;


    public function __construct(?int $id, string $type, string $designation, string $price_ht, int $stock, string $date_in, ?string $date_new, ?string $promotion = null)
    {
        $this->id = $id;
        $this->type = $type;
        $this->designation = $designation;
        $this->price_ht = $price_ht;
        $this->stock = $stock;
        $this->date_in = $date_in;
        $this->date_new = $date_new;
        $this->promotion = $promotion;
    }

    public function getId(): ?int
    {
        return $this-> id;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function getDesignation(): string
    {
        return $this->designation;
    }

    public function getPriceHt(): string
    {
        return $this->price_ht;
    }

    public function getStock(): int
    {
        return $this->stock;
    }

    public function getDateIn(): string
    {
        return $this->date_in;
    }

    public function getDateNew(): ?string
    {
        return $this->date_new;
    }


    public function getPromotion(): ?string
    {
        return $this->promotion;
    }


}