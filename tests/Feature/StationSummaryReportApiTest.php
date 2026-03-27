<?php

namespace Tests\Feature;

use App\Models\StationSummary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StationSummaryReportApiTest extends TestCase
{
    use RefreshDatabase;

    private function uri(string $path): string
    {
        return '/api'.$path;
    }

    public function test_summary_report_returns_station_summaries_row_when_cached(): void
    {
        StationSummary::query()->create([
            'station_id' => 'CACHED-1',
            'total_approved_amount' => 99.99,
            'events_count' => 4,
        ]);

        $this->getJson($this->uri('/v1/stations/CACHED-1/summary-report'))
            ->assertOk()
            ->assertJson([
                'station_id' => 'CACHED-1',
                'total_approved_amount' => 99.99,
                'events_count' => 4,
            ]);
    }

    public function test_summary_report_matches_live_summary_when_no_cache_row(): void
    {
        $this->postJson($this->uri('/v1/transfers'), [
            'events' => [
                [
                    'event_id' => 'sr-1',
                    'station_id' => 'LIVE-S1',
                    'amount' => 10.5,
                    'status' => 'approved',
                    'created_at' => '2026-02-19T10:00:00Z',
                ],
                [
                    'event_id' => 'sr-2',
                    'station_id' => 'LIVE-S1',
                    'amount' => 2,
                    'status' => 'pending',
                    'created_at' => '2026-02-19T11:00:00Z',
                ],
            ],
        ])->assertOk();

        $this->assertDatabaseMissing('station_summaries', ['station_id' => 'LIVE-S1']);

        $live = $this->getJson($this->uri('/v1/stations/LIVE-S1/summary'))->assertOk();

        $this->getJson($this->uri('/v1/stations/LIVE-S1/summary-report'))
            ->assertOk()
            ->assertJson($live->json());
    }
}
