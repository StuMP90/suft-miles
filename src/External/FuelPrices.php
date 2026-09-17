<?php

declare(strict_types=1);

namespace Surf4Miles\External;

final class FuelPrices
{
    public function __construct(
        public readonly float $petrolPricePerLitre,
        public readonly float $dieselPricePerLitre,
        public readonly \DateTimeImmutable $asOf,
    ) {
    }

    public function pricePerLitreFor(string $fuelType): float
    {
        return $fuelType === 'diesel' ? $this->dieselPricePerLitre : $this->petrolPricePerLitre;
    }
}
