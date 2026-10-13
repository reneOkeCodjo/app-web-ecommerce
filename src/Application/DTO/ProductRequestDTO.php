<?php

namespace App\Application\DTO;

// Informations reçues du formulaire pour ajouter ou modifier un produit.
class ProductRequestDTO
{
    private string $type;
    private string $designation;
    private string $priceHt;
    private string $stock;
    private string $dateIn;
    private ?string $promotion;
    private ?string $image;

    public function __construct(
        string $type,
        string $designation,
        string $priceHt,
        string $stock,
        string $dateIn,
        ?string $promotion = null,
        ?string $image = null
    ) {
        $this->type = trim($type);
        $this->designation = trim($designation);
        $this->priceHt = trim($priceHt);
        $this->stock = trim($stock);
        $this->dateIn = trim($dateIn);
        $this->promotion = $promotion !== null ? trim($promotion) : null;
        $this->image = $image !== null ? trim($image) : null;

        if ($this->promotion === '') {
            $this->promotion = null;
        }

        if ($this->image === '') {
            $this->image = null;
        }
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
        return $this->priceHt;
    }

    public function getStock(): string
    {
        return $this->stock;
    }

    public function getDateIn(): string
    {
        return $this->dateIn;
    }

    public function getPromotion(): ?string
    {
        return $this->promotion;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }
}
