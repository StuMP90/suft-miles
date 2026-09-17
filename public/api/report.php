<?php

declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';

use Surf4Miles\Config;
use Surf4Miles\External\PetrolPriceCache;
use Surf4Miles\Http\TempFile;
use Surf4Miles\Report\ReportCalculator;
use Surf4Miles\Report\ReportRenderer;
use Surf4Miles\Sqlite\SqliteValidator;
use Surf4Miles\Sqlite\UploadValidationException;
use Surf4Miles\Trips\CumulativeStore;
use Surf4Miles\Trips\TripExtractor;

header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function fail(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['ok' => false, 'error' => $message]);
    exit;
}

function clampRate(mixed $value): float
{
    if (!is_numeric($value)) {
        return 0.0;
    }
    $rate = (float) $value;
    return max(Config::MIN_RATE_PENCE_PER_KWH, min(Config::MAX_RATE_PENCE_PER_KWH, $rate));
}

function clampMpg(mixed $value): float
{
    if (!is_numeric($value)) {
        return Config::DEFAULT_COMPARISON_MPG;
    }
    $mpg = (float) $value;
    return max(Config::MIN_COMPARISON_MPG, min(Config::MAX_COMPARISON_MPG, $mpg));
}

function fuelType(mixed $value): string
{
    return $value === 'diesel' ? 'diesel' : 'petrol';
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    fail(405, 'This endpoint only accepts POST requests');
}

$standardRate = clampRate($_POST['standard_rate'] ?? null);
$peakSaveRate = clampRate($_POST['peak_save_rate'] ?? null);
$comparisonFuelType = fuelType($_POST['comparison_fuel_type'] ?? null);
$comparisonMpg = clampMpg($_POST['comparison_mpg'] ?? null);

/** @var list<\Surf4Miles\Trips\Trip> $newTrips */
$newTrips = [];
$tripsImported = 0;

try {
    if (isset($_FILES['ec_database']) && $_FILES['ec_database']['error'] === UPLOAD_ERR_OK) {
        $ecDb = SqliteValidator::validateAndOpen($_FILES['ec_database']['tmp_name'], TripExtractor::requiredSchema());
        try {
            $newTrips = TripExtractor::extract($ecDb);
        } finally {
            $ecDb->close();
        }
    } elseif (isset($_FILES['ec_database']) && $_FILES['ec_database']['error'] !== UPLOAD_ERR_NO_FILE) {
        throw new UploadValidationException('Upload failed - the file may be too large');
    }

    $workingCumulative = new TempFile('.db');

    if (isset($_FILES['cumulative_db']) && $_FILES['cumulative_db']['error'] === UPLOAD_ERR_OK) {
        $existingDb = SqliteValidator::validateAndOpen($_FILES['cumulative_db']['tmp_name'], CumulativeStore::requiredSchema());
        $existingDb->close();
        if (!copy($_FILES['cumulative_db']['tmp_name'], $workingCumulative->path)) {
            throw new \RuntimeException('Failed to prepare working copy of cumulative database');
        }
    } else {
        CumulativeStore::createEmpty($workingCumulative->path);
    }

    if ($newTrips !== []) {
        $tripsImported = CumulativeStore::merge($workingCumulative->path, $newTrips, $standardRate, $peakSaveRate);
    }

    $allTrips = CumulativeStore::readAll($workingCumulative->path);
    $recentTrips = array_slice(array_reverse($allTrips), 0, Config::RECENT_TRIP_COUNT);

    $fuelPrices = PetrolPriceCache::get();
    $comparisonPricePerLitre = $fuelPrices->pricePerLitreFor($comparisonFuelType);

    $allStats = ReportCalculator::calculate($allTrips, $standardRate, $peakSaveRate, $comparisonPricePerLitre, $comparisonMpg);
    $recentStats = ReportCalculator::calculate($recentTrips, $standardRate, $peakSaveRate, $comparisonPricePerLitre, $comparisonMpg);

    $reportHtml = ReportRenderer::render(
        $allStats,
        $recentStats,
        $recentTrips,
        $fuelPrices,
        $standardRate,
        $peakSaveRate,
        $comparisonFuelType,
        $comparisonMpg,
        Config::RECENT_TRIP_COUNT,
    );

    $cumulativeBytes = file_get_contents($workingCumulative->path);
    if ($cumulativeBytes === false) {
        throw new \RuntimeException('Failed to read back cumulative database');
    }

    echo json_encode([
        'ok' => true,
        'report_html' => $reportHtml,
        'trips_imported' => $tripsImported,
        'total_trips' => $allStats->totalTrips,
        'generated_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
        'cumulative_db_base64' => base64_encode($cumulativeBytes),
    ]);
} catch (UploadValidationException $e) {
    fail(422, $e->getMessage());
} catch (\Throwable $e) {
    error_log('surf4miles report.php error: ' . $e->getMessage());
    fail(500, 'Something went wrong processing your data');
}
