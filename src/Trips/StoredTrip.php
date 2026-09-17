<?php

declare(strict_types=1);

namespace Surf4Miles\Trips;

/**
 * A trip as read back from the cumulative store, including the energy rates
 * that were in effect when it was imported (used for "historic cost").
 */
final class StoredTrip
{
    public function __construct(
        public readonly \DateTimeImmutable $startTime,
        public readonly \DateTimeImmutable $endTime,
        public readonly float $milesDriven,
        public readonly float $energyUsedKwh,
        public readonly float $durationMinutes,
        public readonly float $efficiencyMilesPerKwh,
        public readonly float $standardRateAtImport,
        public readonly float $peakSaveRateAtImport,
    ) {
    }

    public function historicStandardCost(): float
    {
        return $this->energyUsedKwh * $this->standardRateAtImport / 100.0;
    }

    public function historicPeakSaveCost(): float
    {
        return $this->energyUsedKwh * $this->peakSaveRateAtImport / 100.0;
    }
}
