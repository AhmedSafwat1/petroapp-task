<?php

namespace Tests\Unit;

use App\Http\Resources\StationSummaryResource;
use App\Models\StationSummary;
use Illuminate\Http\Request;
use Tests\TestCase;

class StationSummaryResourceTest extends TestCase
{
    public function test_to_array_exposes_station_id_amounts_and_counts(): void
    {
        $model = new StationSummary([
            'station_id' => 'ST-1',
            'total_approved_amount' => '123.456',
            'events_count' => 7,
        ]);
        $model->exists = true;

        $resource = new StationSummaryResource($model);

        $this->assertSame([
            'station_id' => 'ST-1',
            'total_approved_amount' => 123.46,
            'events_count' => 7,
        ], $resource->resolve(Request::create('/')));
    }
}
