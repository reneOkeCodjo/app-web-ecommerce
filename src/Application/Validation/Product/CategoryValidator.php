<?php

declare(strict_types=1);

namespace App\Application\Validation\Product;

use App\Application\Validation\Shared\FieldResult;
use App\Application\Validation\Shared\MaxLengthValidator;
use App\Application\Validation\Shared\RequiredValidator;
use App\Application\Validation\Shared\ValidatorInterface;
use JsonException;
use RuntimeException;

/**
 * Validates product category against the allowed JSON catalogue.
 */
final class CategoryValidator
{
    public const MAX_LENGTH = 100;

    private string $categoriesPath;

    /** @var list<ValidatorInterface> */
    private array $validators;

    public function __construct(?string $categoriesPath = null)
    {
        $this->categoriesPath = $categoriesPath
            ?? dirname(__DIR__, 4) . '/public/data/product-categories.json';

        $this->validators = [
            new RequiredValidator('La catégorie est obligatoire.'),
            new MaxLengthValidator(
                self::MAX_LENGTH,
                'La catégorie doit contenir entre 1 et 100 caractères.'
            ),
        ];
    }

    public function validate(mixed $value): FieldResult
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        foreach ($this->validators as $validator) {
            $error = $validator->validate($value);

            if ($error !== null) {
                return new FieldResult($error, $value);
            }
        }

        $allowed = $this->loadAllowedCategories();

        if (!in_array($value, $allowed, true)) {
            return new FieldResult(
                'Choisissez une catégorie proposée dans la liste.',
                $value
            );
        }

        return new FieldResult(null, $value);
    }

    /**
     * @return list<string>
     */
    private function loadAllowedCategories(): array
    {
        $json = file_get_contents($this->categoriesPath);

        if ($json === false) {
            throw new RuntimeException('Impossible de charger les catégories produits.');
        }

        try {
            $categories = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException $e) {
            throw new RuntimeException('Catalogue de catégories invalide.', 0, $e);
        }

        if (!is_array($categories)) {
            throw new RuntimeException('Catalogue de catégories invalide.');
        }

        /** @var list<string> */
        return array_values(array_filter(
            array_column($categories, 'categorie'),
            static fn (mixed $category): bool => is_string($category) && $category !== ''
        ));
    }
}
