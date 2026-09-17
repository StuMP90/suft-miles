<?php

declare(strict_types=1);

namespace Surf4Miles;

final class Config
{
    private function __construct()
    {
    }

    public const KM_TO_MILES = 0.621371;
    public const LITRES_PER_GALLON = 4.54609;
    public const EPA_KWH_PER_GALLON_EQUIVALENT = 33.7;

    public const DEFAULT_COMPARISON_MPG = 40.0;
    public const MIN_COMPARISON_MPG = 10.0;
    public const MAX_COMPARISON_MPG = 120.0;

    /** Fixed UK government e-tax per mile policy figure (pence). */
    public const GOV_ETAX_PENCE_PER_MILE = 3.0;

    public const MAX_UPLOAD_BYTES = 200 * 1024;
    public const MAX_TRIP_ROWS = 5000;

    /**
     * A newly-delivered car's on-board log can include the factory/transit-boat/
     * dealer-prep period before the owner ever drove it - characterised by
     * near-zero movement stretched over hours (e.g. sitting on a boat while
     * something sips 12V power), not real driving. Filtering on average speed
     * rather than a calendar date catches that generically for every car/owner.
     * 1.2mph / 6h is chosen to comfortably survive a real trip stuck in a
     * severe motorway closure (e.g. 10mi crawled over 4h = 2.5mph, nowhere
     * close to being filtered).
     */
    public const MIN_AVERAGE_SPEED_MPH = 1.2;
    public const MAX_TRIP_DURATION_MINUTES = 6 * 60;

    public const RECENT_TRIP_COUNT = 20;

    /**
     * DESNZ "Weekly road fuel prices" official open data, published every
     * Monday. If gov.uk ever restructures this dataset (it has before - see
     * the separate 2003-2017 archive file), find the current CSV link from
     * https://www.gov.uk/government/statistics/weekly-road-fuel-prices
     */
    public const PETROL_PRICE_URL = 'https://assets.publishing.service.gov.uk/media/6aa801e097b321a2d34ee250/CSV__2018_-__.csv';
    public const PETROL_PRICE_CACHE_TTL_SECONDS = 24 * 3600;
    public const PETROL_PRICE_RETRY_BACKOFF_SECONDS = 15 * 60;
    public const PETROL_PRICE_HTTP_TIMEOUT_SECONDS = 6;

    public const MIN_RATE_PENCE_PER_KWH = 0.0;
    public const MAX_RATE_PENCE_PER_KWH = 100.0;

    public static function cacheDir(): string
    {
        return dirname(__DIR__) . '/var/cache';
    }
}
