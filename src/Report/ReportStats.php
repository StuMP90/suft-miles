<?php

declare(strict_types=1);

namespace Surf4Miles\Report;

final class ReportStats
{
    public function __construct(
        public readonly int $totalTrips,
        public readonly float $totalMiles,
        public readonly float $totalEnergyKwh,
        public readonly float $totalMinutes,
        public readonly float $overallEfficiency,
        public readonly float $averageMph,
        public readonly float $historicStandardCost,
        public readonly float $historicPeakSaveCost,
        public readonly float $currentStandardCost,
        public readonly float $currentPeakSaveCost,
        public readonly float $costPerMileStandardPence,
        public readonly float $costPerMilePeakSavePence,
        public readonly float $govETaxPerMilePence,
        public readonly float $comparisonCostPerMilePence,
        public readonly float $totalComparisonCost,
    ) {
    }
}
