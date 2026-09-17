<?php

declare(strict_types=1);

namespace Surf4Miles\Trips;

/**
 * A trip to merge into the cumulative store, paired with the energy rates
 * that should be recorded against it if it's newly inserted. For a fresh
 * EC_database.db import that's the visitor's *current* rate settings; for a
 * restored backup it's each trip's own original historic rate, so merging a
 * backup never rewrites history with today's rates.
 */
final class MergeEntry
{
    public function __construct(
        public readonly Trip $trip,
        public readonly float $standardRate,
        public readonly float $peakSaveRate,
    ) {
    }
}
