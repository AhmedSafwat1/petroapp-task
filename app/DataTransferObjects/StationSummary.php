<?php

namespace App\DataTransferObjects;

class StationSummary
{
    public function __construct(
        public readonly string $stationId,
        public readonly string $totalApprovedAmount,
        public readonly int $eventsCount,
    ) {}

    public function toArray(): array
    {
        return [
            'station_id' => $this->stationId,
            'total_approved_amount' => round((float) $this->totalApprovedAmount, 2),
            'events_count' => $this->eventsCount,
        ];
    }
}
