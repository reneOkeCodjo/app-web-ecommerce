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
    private string $promotion;


    public function __construct(
        string $type,
        string $designation,
        string $priceHt,
        string $stock,
        string $dateIn
        
    ) {
        $this->type = trim($type);
        $this->designation = trim($designation);
        $this->priceHt = trim($priceHt);
        $this->stock = trim($stock);
        $this->dateIn = trim($dateIn);
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
}