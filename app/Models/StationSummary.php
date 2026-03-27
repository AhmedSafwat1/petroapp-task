<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Cached per-station aggregates (refreshed asynchronously after transfer ingest).
 *
 * @property string $station_id
 * @property string $total_approved_amount
 * @property int $events_count
 */
class StationSummary extends Model
{
    protected $table = 'station_summaries';

    protected $primaryKey = 'station_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'station_id',
        'total_approved_amount',
        'events_count',
    ];

    protected function casts(): array
    {
        return [
            'total_approved_amount' => 'decimal:2',
            'events_count' => 'integer',
        ];
    }
}
