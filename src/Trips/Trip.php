<?php

declare(strict_types=1);

namespace Surf4Miles\Trips;

final class Trip
{
    public function __construct(
        public readonly \DateTimeImmutable $startTime,
        public readonly \DateTimeImmutable $endTime,
        public readonly float $milesDriven,
        public readonly float $energyUsedKwh,
        public readonly float $durationMinutes,
    ) {
    }

    public function efficiencyMilesPerKwh(): float
    {
        return $this->energyUsedKwh > 0.0 ? $this->milesDriven / $this->energyUsedKwh : 0.0;
    }
}
