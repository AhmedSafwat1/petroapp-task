<?php

namespace App\Services\Transfer;

use App\Contracts\Repositories\TransferEventRepositoryInterface;
use App\Contracts\Services\TransferIngestionServiceInterface;
use App\DataTransferObjects\IngestResult;
use Carbon\Carbon;
use App\Events\StationsUpdateEvent;

class TransferIngestionService implements TransferIngestionServiceInterface
{
    public function __construct(
        private readonly TransferEventRepositoryInterface $transferEvents,
    ) {}

    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    public function ingest(array $events): IngestResult
    {
        $count = count($events);
        $normalized = array_fill(0, $count, null);
        $uniqueStationIds = [];

        foreach ($events as $i => $event) {
            $row = $this->normalizeEvent($event);
            $normalized[$i] = $row;
            $uniqueStationIds[$row['station_id']] = true;
        }

        $result = $this->transferEvents->insertBatch($normalized);

        if ($uniqueStationIds !== []) {
            event(new StationsUpdateEvent(array_keys($uniqueStationIds)));
        }

        return $result;
    }

    /**
     * @param  array<string, mixed>  $event
     * @return array{event_id: string, station_id: string, amount: string, status: string, created_at: string}
     */
    private function normalizeEvent(array $event): array
    {
        return [
            'event_id' => (string) $event['event_id'],
            'station_id' => (string) $event['station_id'],
            'amount' => number_format((float) $event['amount'], 2, '.', ''),
            'status' => (string) $event['status'],
            'created_at' => Carbon::parse($event['created_at'])->toDateTimeString(),
        ];
    }
}
