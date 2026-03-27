<?php

namespace App\Repositories;

use App\Contracts\Repositories\TransferEventRepositoryInterface;
use App\DataTransferObjects\IngestResult;
use App\DataTransferObjects\StationSummary;
use Illuminate\Support\Facades\DB;

class DatabaseTransferEventRepository implements TransferEventRepositoryInterface
{
    /**
     * Chunk size keeps  parameter lists and lock duration reasonable for large batches.
     */
    private const INSERT_CHUNK_SIZE = 500;

    /**
     * Insert a batch of transfer events.
     *
     * @param  array<int, array{event_id: string, station_id: string, amount: string, status: string, created_at: string}>  $rows
     */
    public function insertBatch(array $rows): IngestResult
    {
        if ($rows === []) {
            return new IngestResult(0, 0);
        }

        $total = count($rows);
        $inserted = 0;

        DB::transaction(function () use ($rows, &$inserted): void {
            foreach (array_chunk($rows, self::INSERT_CHUNK_SIZE) as $chunk) {
                $inserted += (int) DB::table('transfer_events')->insertOrIgnore($chunk);
            }
        });

        return new IngestResult($inserted, $total - $inserted);
    }

    /**
     * Get the summary for a single station.
     */
    public function getStationSummary(string $stationId): StationSummary
    {
        $row = DB::table('transfer_events')
            ->where('station_id', $stationId)
            ->selectRaw(
                'COUNT(*) as events_count, COALESCE(SUM(CASE WHEN status = ? THEN amount ELSE 0 END), 0) as total_approved',
                ['approved']
            )
            ->first();

        return new StationSummary(
            stationId: $stationId,
            totalApprovedAmount: (string) ($row->total_approved ?? '0'),
            eventsCount: (int) ($row->events_count ?? 0),
        );
    }

    /**
     * Refresh the summary for a single station.
     */
    public function refreshStationSummary(string $stationId): void
    {
        DB::statement("CALL refresh_station_summary(?)", [$stationId]);
    }
}
