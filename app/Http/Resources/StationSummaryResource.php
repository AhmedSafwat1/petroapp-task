<?php

namespace App\Http\Resources;

use App\Models\StationSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin StationSummary
 */
class StationSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'station_id' => $this->station_id,
            'total_approved_amount' => round((float) $this->total_approved_amount, 2),
            'events_count' => (int) $this->events_count,
        ];
    }
}
