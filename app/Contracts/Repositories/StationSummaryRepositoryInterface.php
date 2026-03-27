<?php

namespace App\Contracts\Repositories;

use App\Models\StationSummary;

interface StationSummaryRepositoryInterface
{
    public function findByStationId(string $stationId): ?StationSummary;
}
