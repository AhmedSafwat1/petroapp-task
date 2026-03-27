<?php

namespace App\Services\Station;

use App\Contracts\Repositories\TransferEventRepositoryInterface;
use App\Contracts\Services\StationSummaryServiceInterface;
use App\DataTransferObjects\StationSummary;

class StationSummaryService implements StationSummaryServiceInterface
{
    public function __construct(
        private readonly TransferEventRepositoryInterface $transferEvents,
    ) {}

    /**
     * Get the summary for a single station.
     */
    public function summary(string $stationId): StationSummary
    {
        return $this->transferEvents->getStationSummary($stationId);
    }

    /**
     * Get the summaries for multiple stations.
     *
     * @param  list<string>  $stationIds
     * @return array<string, StationSummary> keyed by station id
     */
    public function summariesForStations(array $stationIds): void
    {
        foreach ($stationIds as $stationId) {
            $this->transferEvents->refreshStationSummary($stationId);
        }
    }
}

