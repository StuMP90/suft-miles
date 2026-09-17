<?php

declare(strict_types=1);

namespace Surf4Miles\Report;

use Surf4Miles\Config;
use Surf4Miles\Trips\StoredTrip;

/**
 * Ports the statistics math from the Go tool's generateHTMLReport, without
 * its hardcoded cutoff date - the caller decides which slice of trips
 * ("all-time" vs "recent N") to pass in.
 */
final class ReportCalculator
{
    /**
     * @param list<StoredTrip> $trips
     */
    public static function calculate(
        array $trips,
        float $currentStandardRate,
        float $currentPeakSaveRate,
        float $comparisonFuelPricePerLitre,
        float $comparisonMpg,
    ): ReportStats {
        $totalTrips = count($trips);
        $totalMiles = 0.0;
        $totalEnergy = 0.0;
        $totalMinutes = 0.0;
        $historicStandardCost = 0.0;
        $historicPeakSaveCost = 0.0;

        foreach ($trips as $trip) {
            $totalMiles += $trip->milesDriven;
            $totalEnergy += $trip->energyUsedKwh;
            $totalMinutes += $trip->durationMinutes;
            $historicStandardCost += $trip->historicStandardCost();
            $historicPeakSaveCost += $trip->historicPeakSaveCost();
        }

        $overallEfficiency = $totalEnergy > 0.0 ? $totalMiles / $totalEnergy : 0.0;
        $averageMph = $totalMinutes > 0.0 ? $totalMiles / ($totalMinutes / 60.0) : 0.0;

        $currentStandardCost = $totalEnergy * $currentStandardRate / 100.0;
        $currentPeakSaveCost = $totalEnergy * $currentPeakSaveRate / 100.0;

        $costPerMileStandard = 0.0;
        $costPerMilePeakSave = 0.0;
        $comparisonCostPerMile = 0.0;
        $totalComparisonCost = 0.0;

        if ($totalMiles > 0.0) {
            $costPerMileStandard = ($historicStandardCost / $totalMiles) * 100.0;
            $costPerMilePeakSave = ($historicPeakSaveCost / $totalMiles) * 100.0;
            // pence per mile, for a petrol/diesel car doing $comparisonMpg
            $comparisonCostPerMile = ($comparisonFuelPricePerLitre / $comparisonMpg) * Config::LITRES_PER_GALLON * 100.0;
            $totalComparisonCost = ($comparisonCostPerMile / 100.0) * $totalMiles;
        }

        return new ReportStats(
            totalTrips: $totalTrips,
            totalMiles: $totalMiles,
            totalEnergyKwh: $totalEnergy,
            totalMinutes: $totalMinutes,
            overallEfficiency: $overallEfficiency,
            averageMph: $averageMph,
            historicStandardCost: $historicStandardCost,
            historicPeakSaveCost: $historicPeakSaveCost,
            currentStandardCost: $currentStandardCost,
            currentPeakSaveCost: $currentPeakSaveCost,
            costPerMileStandardPence: $costPerMileStandard,
            costPerMilePeakSavePence: $costPerMilePeakSave,
            govETaxPerMilePence: Config::GOV_ETAX_PENCE_PER_MILE,
            comparisonCostPerMilePence: $comparisonCostPerMile,
            totalComparisonCost: $totalComparisonCost,
        );
    }
}
