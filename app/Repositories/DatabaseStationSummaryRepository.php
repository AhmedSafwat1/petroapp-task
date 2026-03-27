<?php

namespace App\Repositories;

use App\Contracts\Repositories\StationSummaryRepositoryInterface;
use App\Models\StationSummary;

class DatabaseStationSummaryRepository implements StationSummaryRepositoryInterface
{
    public function findByStationId(string $stationId): ?StationSummary
    {
        return StationSummary::query()->find($stationId);
    }
}
