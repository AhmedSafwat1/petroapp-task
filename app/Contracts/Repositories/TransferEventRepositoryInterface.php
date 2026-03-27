<?php

namespace App\Contracts\Repositories;

use App\DataTransferObjects\IngestResult;
use App\DataTransferObjects\StationSummary;

interface TransferEventRepositoryInterface
{
    /**
     * Rows ready for persistence (no further shaping in the repository).
     *
     * @param  array<int, array{event_id: string, station_id: string, amount: string, status: string, created_at: string}>  $rows
     */
    public function insertBatch(array $rows): IngestResult;

    /**
     * Get the summary for a single station.
     */
    public function getStationSummary(string $stationId): StationSummary;

    /**
     * Refresh the summary for a single station.
     *
     * @param  string  $stationId
     */
    public function refreshStationSummary(string $stationId): void;

}
