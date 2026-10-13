<?php

declare(strict_types=1);

namespace App\Application\Validation\Product;

use App\Application\Validation\Shared\FieldResult;

/**
 * Validates an optional promotion percentage (0–100, max 2 decimals).
 */
final class PromotionValidator
{
    public const PATTERN = '/^\d{1,3}(?:\.\d{1,2})?$/D';

    public function validate(mixed $value): FieldResult
    {
        if ($value === null) {
            return new FieldResult(null, null);
        }

        if (is_int($value) || is_float($value)) {
            $value = number_format((float) $value, 2, '.', '');
        }

        if (is_string($value)) {
            $value = trim($value);
        }

        if ($value === '' || $value === null) {
            return new FieldResult(null, null);
        }

        if (!is_string($value) || preg_match(self::PATTERN, $value) !== 1) {
            return new FieldResult(
                'Promotion invalide : pourcentage avec au maximum deux décimales.',
                $value
            );
        }

        if ((float) $value > 100) {
            return new FieldResult(
                'La promotion ne peut pas dépasser 100 %.',
                $value
            );
        }

        return new FieldResult(null, $value);
    }
}
