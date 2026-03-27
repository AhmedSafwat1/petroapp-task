<?php

namespace App\Contracts\Services;

use App\DataTransferObjects\StationSummary;

interface StationSummaryServiceInterface
{
    /**
     * Get the summary for a single station.
     */
    public function summary(string $stationId): StationSummary;

    /**
     * Get the summaries for multiple stations.
     *
     * @param  list<string>  $stationIds
     */
    public function summariesForStations(array $stationIds): void;

}

