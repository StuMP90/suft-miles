<?php

declare(strict_types=1);

namespace Surf4Miles\External;

final class FuelPrices
{
    public function __construct(
        public readonly float $petrolPricePerLitre,
        public readonly float $dieselPricePerLitre,
        public readonly \DateTimeImmutable $asOf,
        /** Percentage change vs. the previous published week, or null if unknown. */
        public readonly ?float $petrolChangePercent = null,
        public readonly ?float $dieselChangePercent = null,
    ) {
    }

    public function pricePerLitreFor(string $fuelType): float
    {
        return $fuelType === 'diesel' ? $this->dieselPricePerLitre : $this->petrolPricePerLitre;
    }

    public function changePercentFor(string $fuelType): ?float
    {
        return $fuelType === 'diesel' ? $this->dieselChangePercent : $this->petrolChangePercent;
    }
}
