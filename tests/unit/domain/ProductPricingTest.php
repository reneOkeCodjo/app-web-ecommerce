<?php

declare(strict_types=1);

namespace Tests\Unit\Domain;

use App\Domain\Pricing\ProductPricing;
use PHPUnit\Framework\TestCase;

final class ProductPricingTest extends TestCase
{
    public function testPriceTtcAppliesTwentyPercentVat(): void
    {
        self::assertSame(120.0, ProductPricing::priceTtc(100.0));
        self::assertSame(12.0, ProductPricing::priceTtc(10.0));
    }

    public function testPriceAvailableWithoutPromotionEqualsTtc(): void
    {
        self::assertSame(120.0, ProductPricing::priceAvailable(100.0, null));
        self::assertSame(120.0, ProductPricing::priceAvailable(100.0, 0.0));
    }

    public function testPriceAvailableAppliesPromotionOnTtc(): void
    {
        // TTC 120, promo 10 % → 108
        self::assertSame(108.0, ProductPricing::priceAvailable(100.0, 10.0));
    }

    public function testHasPromotion(): void
    {
        self::assertFalse(ProductPricing::hasPromotion(null));
        self::assertFalse(ProductPricing::hasPromotion(0.0));
        self::assertTrue(ProductPricing::hasPromotion(5.0));
    }
}
