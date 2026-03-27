<?php

namespace App\Listeners;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use App\Events\StationsUpdateEvent;
use App\Contracts\Services\StationSummaryServiceInterface;
use Illuminate\Support\Facades\Log;
use Throwable;

class StationsSummariesUpdate implements ShouldQueue
{
    /**
     * Create the event listener.
     */
    public function __construct(private readonly StationSummaryServiceInterface $stationSummary)
    {
        //
    }

    /**
     * Handle the event.
     */
    public function handle(StationsUpdateEvent $event): void
    {
        try {
            $this->stationSummary->summariesForStations($event->stationIds);
        } catch (Throwable $e) {
            Log::error('StationsSummariesUpdate failed', [
                'station_ids' => $event->stationIds,
                'exception' => $e,
            ]);
        }
    }
}
