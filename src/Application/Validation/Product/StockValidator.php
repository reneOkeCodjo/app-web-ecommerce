<?php

declare(strict_types=1);

namespace App\Application\Validation\Product;

use App\Application\Validation\Shared\FieldResult;

/**
 * Validates product stock as a non-negative integer.
 */
final class StockValidator
{
    public function validate(mixed $value): FieldResult
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || $value === null) {
            return new FieldResult(null, '0');
        }

        if (is_int($value)) {
            $value = (string) $value;
        }

        if (!is_string($value) || !preg_match('/^\d+$/D', $value)) {
            return new FieldResult(
                'Le stock doit être un entier positif ou égal à zéro.',
                $value
            );
        }

        $stockValue = filter_var(
            $value,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 0, 'max_range' => 2147483647]]
        );

        if ($stockValue === false) {
            return new FieldResult(
                'Le stock doit être un entier positif ou égal à zéro.',
                $value
            );
        }

        return new FieldResult(null, (string) $stockValue);
    }
}
