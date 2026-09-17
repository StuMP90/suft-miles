<?php

declare(strict_types=1);

namespace Surf4Miles\Report;

use Surf4Miles\External\FuelPrices;
use Surf4Miles\Trips\StoredTrip;

/**
 * Renders the report as an HTML fragment (stat-card grids + trip table),
 * which the front end drops straight into the page. Keeps the same visual
 * language and metric set as the original Go tool's surf-report.html.
 */
final class ReportRenderer
{
    /**
     * @param list<StoredTrip> $recentTripsDescending trips ordered newest-first, already limited to the "recent" count
     */
    public static function render(
        ReportStats $allTime,
        ReportStats $recent,
        array $recentTripsDescending,
        FuelPrices $fuelPrices,
        float $standardRate,
        float $peakSaveRate,
        string $comparisonFuelType,
        float $comparisonMpg,
        int $recentCount,
    ): string {
        $html = self::fuelPriceBanner($fuelPrices);

        $html .= '<section class="stats-block">';
        $html .= '<h2>All-Time Summary</h2>';
        $html .= self::statsGrid($allTime, $fuelPrices, $standardRate, $peakSaveRate, $comparisonFuelType, $comparisonMpg);
        $html .= '</section>';

        $html .= '<section class="stats-block">';
        $html .= '<h2>Recent Trips Summary (Last ' . $recentCount . ')</h2>';
        $html .= self::statsGrid($recent, $fuelPrices, $standardRate, $peakSaveRate, $comparisonFuelType, $comparisonMpg);
        $html .= '</section>';

        $html .= '<div class="trips-section">';
        $html .= '<h2>Recent Trips Detail</h2>';
        $html .= self::tripsTable($recentTripsDescending);
        $html .= '</div>';

        return $html;
    }

    private static function fuelPriceBanner(FuelPrices $fuelPrices): string
    {
        if ($fuelPrices->asOf->getTimestamp() <= 0) {
            return '<p class="fuel-price-banner">UK fuel prices are currently unavailable.</p>';
        }

        return '<p class="fuel-price-banner">UK average pump prices (' . htmlspecialchars($fuelPrices->asOf->format('d M Y')) . '): '
            . 'Petrol ' . self::num($fuelPrices->petrolPricePerLitre * 100, 1) . 'p/L &middot; '
            . 'Diesel ' . self::num($fuelPrices->dieselPricePerLitre * 100, 1) . 'p/L'
            . '</p>';
    }

    private static function statsGrid(
        ReportStats $s,
        FuelPrices $fuelPrices,
        float $standardRate,
        float $peakSaveRate,
        string $comparisonFuelType,
        float $comparisonMpg,
    ): string {
        $comparisonDate = $fuelPrices->asOf->getTimestamp() > 0
            ? htmlspecialchars($fuelPrices->asOf->format('d M'))
            : 'n/a';
        $comparisonLabel = $comparisonFuelType === 'diesel' ? 'Diesel' : 'Petrol';
        $comparisonPricePerLitre = $fuelPrices->pricePerLitreFor($comparisonFuelType);

        $cards = [
            ['Total Trips', (string) $s->totalTrips, ''],
            ['Miles Driven', self::num($s->totalMiles, 1), 'mi'],
            ['Energy Used', self::num($s->totalEnergyKwh, 1), 'kWh'],
            ['Efficiency', self::num($s->overallEfficiency, 2), 'mi/kWh'],
            ['Driving Time', self::num($s->totalMinutes / 60, 1), 'hrs'],
            ['Average MPH', self::num($s->averageMph, 1), 'mph'],
            ['Historic Cost', '£' . self::num($s->historicStandardCost, 2) . ' / £' . self::num($s->historicPeakSaveCost, 2), ''],
            [
                sprintf('Cost at Current Rate (%sp / %sp per kWh)', self::num($standardRate, 1), self::num($peakSaveRate, 1)),
                '£' . self::num($s->currentStandardCost, 2) . ' / £' . self::num($s->currentPeakSaveCost, 2),
                '',
            ],
            ['Cost per Mile (Standard)', self::num($s->costPerMileStandardPence, 2), 'p'],
            ['Cost per Mile (Peak Save)', self::num($s->costPerMilePeakSavePence, 2), 'p'],
            ['Gov E.Tax per Mile', self::num($s->govETaxPerMilePence, 2), 'p'],
            [
                sprintf('%s Equiv (%s MPG @ £%s/L - %s)', $comparisonLabel, self::num($comparisonMpg, 0), self::num($comparisonPricePerLitre, 2), $comparisonDate),
                '£' . self::num($s->totalComparisonCost, 2) . ' (' . self::num($s->comparisonCostPerMilePence, 2) . 'p/mi)',
                '',
            ],
        ];

        $html = '<div class="stats-grid">';
        foreach ($cards as [$label, $value, $unit]) {
            $html .= '<div class="stat-card">';
            $html .= '<div class="stat-label">' . htmlspecialchars($label) . '</div>';
            $html .= '<div class="stat-value">' . htmlspecialchars($value);
            if ($unit !== '') {
                $html .= '<span class="stat-unit">' . htmlspecialchars($unit) . '</span>';
            }
            $html .= '</div></div>';
        }
        $html .= '</div>';

        return $html;
    }

    /**
     * @param list<StoredTrip> $trips
     */
    private static function tripsTable(array $trips): string
    {
        $html = '<table><thead><tr>'
            . '<th>Date</th><th>Start Time</th><th>End Time</th><th>Miles</th><th>Energy (kWh)</th>'
            . '<th>Efficiency</th><th>Duration</th><th>Avg MPH</th><th>Std Cost</th><th>P.Save Cost</th>'
            . '</tr></thead><tbody>';

        foreach ($trips as $trip) {
            $efficiencyClass = 'efficiency-medium';
            if ($trip->efficiencyMilesPerKwh > 5.0) {
                $efficiencyClass = 'efficiency-good';
            } elseif ($trip->efficiencyMilesPerKwh < 3.5) {
                $efficiencyClass = 'efficiency-low';
            }

            $tripMph = $trip->durationMinutes > 0.0 ? $trip->milesDriven / ($trip->durationMinutes / 60.0) : 0.0;

            $html .= '<tr>';
            $html .= '<td>' . htmlspecialchars($trip->startTime->format('M d')) . '</td>';
            $html .= '<td>' . htmlspecialchars($trip->startTime->format('H:i T')) . '</td>';
            $html .= '<td>' . htmlspecialchars($trip->endTime->format('H:i T')) . '</td>';
            $html .= '<td>' . self::num($trip->milesDriven, 2) . '</td>';
            $html .= '<td>' . self::num($trip->energyUsedKwh, 1) . '</td>';
            $html .= '<td class="' . $efficiencyClass . '">' . self::num($trip->efficiencyMilesPerKwh, 2) . ' mi/kWh</td>';
            $html .= '<td>' . self::num($trip->durationMinutes, 0) . ' min</td>';
            $html .= '<td>' . self::num($tripMph, 1) . '</td>';
            $html .= '<td>£' . self::num($trip->historicStandardCost(), 2) . '</td>';
            $html .= '<td>£' . self::num($trip->historicPeakSaveCost(), 2) . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table>';

        return $html;
    }

    private static function num(float $value, int $decimals): string
    {
        if (!is_finite($value)) {
            return '-';
        }
        return number_format($value, $decimals, '.', '');
    }
}
