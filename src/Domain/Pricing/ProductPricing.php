<?php

declare(strict_types=1);

namespace App\Domain\Pricing;

/**
 * Product price helpers: HT → TTC and optional promotion.
 */
final class ProductPricing
{
    public const TVA_RATE = 0.20;

    public static function priceTtc(float $priceHt): float
    {
        return round($priceHt * (1 + self::TVA_RATE), 2);
    }

    /**
     * TTC after optional promotion percentage (0–100).
     */
    public static function priceAvailable(float $priceHt, ?float $promotionPercent): float
    {
        $ttc = self::priceTtc($priceHt);

        if ($promotionPercent === null || $promotionPercent <= 0.0) {
            return $ttc;
        }

        return round($ttc * (1 - ($promotionPercent / 100)), 2);
    }

    public static function hasPromotion(?float $promotionPercent): bool
    {
        return $promotionPercent !== null && $promotionPercent > 0.0;
    }
}
