<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class TransferApiTest extends TestCase
{
    use RefreshDatabase;

    private function uri(string $path): string
    {
        return '/api'.$path;
    }

    public function test_batch_insert_returns_inserted_and_duplicates(): void
    {
        $response = $this->postJson($this->uri('/v1/transfers'), [
            'events' => [
                [
                    'event_id' => 'e1',
                    'station_id' => 'S1',
                    'amount' => 10,
                    'status' => 'approved',
                    'created_at' => '2026-02-19T10:00:00Z',
                ],
                [
                    'event_id' => 'e2',
                    'station_id' => 'S1',
                    'amount' => 5,
                    'status' => 'pending',
                    'created_at' => '2026-02-19T11:00:00Z',
                ],
            ],
        ]);

        $response->assertOk()->assertJson(['inserted' => 2, 'duplicates' => 0]);

        $response2 = $this->postJson($this->uri('/v1/transfers'), [
            'events' => [
                [
                    'event_id' => 'e1',
                    'station_id' => 'S1',
                    'amount' => 99,
                    'status' => 'approved',
                    'created_at' => '2026-02-19T10:00:00Z',
                ],
                [
                    'event_id' => 'e3',
                    'station_id' => 'S2',
                    'amount' => 1,
                    'status' => 'approved',
                    'created_at' => '2026-02-19T12:00:00Z',
                ],
            ],
        ]);

        $response2->assertOk()->assertJson(['inserted' => 1, 'duplicates' => 1]);
    }

    public function test_duplicate_event_does_not_change_approved_total(): void
    {
        $payload = [
            'event_id' => 'dup-1',
            'station_id' => 'S1',
            'amount' => 100.5,
            'status' => 'approved',
            'created_at' => '2026-02-19T10:00:00Z',
        ];

        $this->postJson($this->uri('/v1/transfers'), ['events' => [$payload]])->assertOk();
        $this->postJson($this->uri('/v1/transfers'), ['events' => [$payload]])->assertJson(['inserted' => 0, 'duplicates' => 1]);

        $this->getJson($this->uri('/v1/stations/S1/summary'))
            ->assertOk()
            ->assertJson([
                'station_id' => 'S1',
                'total_approved_amount' => 100.5,
                'events_count' => 1,
            ]);
    }

    public function test_out_of_order_arrival_produces_same_totals(): void
    {
        $a = [
            'event_id' => 'o1',
            'station_id' => 'S1',
            'amount' => 10,
            'status' => 'approved',
            'created_at' => '2026-02-19T10:00:00Z',
        ];
        $b = [
            'event_id' => 'o2',
            'station_id' => 'S1',
            'amount' => 20,
            'status' => 'approved',
            'created_at' => '2026-02-19T09:00:00Z',
        ];

        $this->postJson($this->uri('/v1/transfers'), ['events' => [$a]])->assertOk();
        $this->postJson($this->uri('/v1/transfers'), ['events' => [$b]])->assertOk();

        $this->getJson($this->uri('/v1/stations/S1/summary'))
            ->assertOk()
            ->assertJson([
                'events_count' => 2,
                'total_approved_amount' => 30.0,
            ]);
    }

    public function test_reverse_arrival_order_matches_same_station_totals(): void
    {
        $a = [
            'event_id' => 'o1',
            'station_id' => 'S1',
            'amount' => 10,
            'status' => 'approved',
            'created_at' => '2026-02-19T10:00:00Z',
        ];
        $b = [
            'event_id' => 'o2',
            'station_id' => 'S1',
            'amount' => 20,
            'status' => 'approved',
            'created_at' => '2026-02-19T09:00:00Z',
        ];

        $this->postJson($this->uri('/v1/transfers'), ['events' => [$b]])->assertOk();
        $this->postJson($this->uri('/v1/transfers'), ['events' => [$a]])->assertOk();

        $this->getJson($this->uri('/v1/stations/S1/summary'))
            ->assertOk()
            ->assertJson([
                'events_count' => 2,
                'total_approved_amount' => 30.0,
            ]);
    }

    public function test_concurrent_duplicate_post_same_event_id_single_row(): void
    {
        $payload = [
            'event_id' => 'race-1',
            'station_id' => 'S1',
            'amount' => 50,
            'status' => 'approved',
            'created_at' => '2026-02-19T10:00:00Z',
        ];

        $this->postJson($this->uri('/v1/transfers'), ['events' => [$payload]])->assertJson(['inserted' => 1, 'duplicates' => 0]);
        $this->postJson($this->uri('/v1/transfers'), ['events' => [$payload]])->assertJson(['inserted' => 0, 'duplicates' => 1]);

        $this->assertSame(1, (int) DB::table('transfer_events')->where('event_id', 'race-1')->count());
    }

    public function test_summary_endpoint_per_station(): void
    {
        $this->postJson($this->uri('/v1/transfers'), [
            'events' => [
                [
                    'event_id' => 's1',
                    'station_id' => 'A',
                    'amount' => 100,
                    'status' => 'approved',
                    'created_at' => '2026-02-19T10:00:00Z',
                ],
                [
                    'event_id' => 's2',
                    'station_id' => 'A',
                    'amount' => 50,
                    'status' => 'rejected',
                    'created_at' => '2026-02-19T10:00:00Z',
                ],
                [
                    'event_id' => 's3',
                    'station_id' => 'B',
                    'amount' => 25.25,
                    'status' => 'approved',
                    'created_at' => '2026-02-19T10:00:00Z',
                ],
            ],
        ])->assertOk();

        $this->getJson($this->uri('/v1/stations/A/summary'))
            ->assertOk()
            ->assertJson([
                'station_id' => 'A',
                'total_approved_amount' => 100.0,
                'events_count' => 2,
            ]);

        $this->getJson($this->uri('/v1/stations/B/summary'))
            ->assertOk()
            ->assertJson([
                'station_id' => 'B',
                'total_approved_amount' => 25.25,
                'events_count' => 1,
            ]);
    }

    public function test_validation_fail_fast_returns_400(): void
    {
        $this->postJson($this->uri('/v1/transfers'), [
            'events' => [
                [
                    'event_id' => 'x1',
                    'station_id' => 'S1',
                    'amount' => -1,
                    'status' => 'approved',
                    'created_at' => '2026-02-19T10:00:00Z',
                ],
            ],
        ])
            ->assertStatus(400)
            ->assertJson([
                'message' => 'Validation failed.',
            ])
            ->assertJsonStructure(['message', 'errors'])
            ->tap(function ($response): void {
                $errs = $response->json('errors');
                $this->assertIsArray($errs);
                $this->assertArrayHasKey('events.0.amount', $errs);
                $this->assertNotEmpty($errs['events.0.amount']);
            });

        $this->postJson($this->uri('/v1/transfers'), [
            'events' => [
                [
                    'event_id' => 'x2',
                    'station_id' => 'S1',
                    'amount' => 1,
                    'status' => 'approved',
                    'created_at' => 'not-a-date',
                ],
            ],
        ])
            ->assertStatus(400)
            ->tap(function ($response): void {
                $errs = $response->json('errors');
                $this->assertArrayHasKey('events.0.created_at', $errs);
                $this->assertNotEmpty($errs['events.0.created_at']);
            });

        $this->postJson($this->uri('/v1/transfers'), [
            'events' => [
                [
                    'event_id' => 'x3',
                    'station_id' => 'S1',
                    'amount' => 1,
                    'status' => 'approved',
                    'created_at' => '2026-02-19T10:00:00+00:00',
                ],
            ],
        ])
            ->assertStatus(400)
            ->tap(function ($response): void {
                $errs = $response->json('errors');
                $this->assertArrayHasKey('events.0.created_at', $errs);
                $this->assertNotEmpty($errs['events.0.created_at']);
            });
    }

    public function test_validation_rejects_whitespace_only_strings(): void
    {
        $base = [
            'station_id' => 'S1',
            'amount' => 1,
            'status' => 'approved',
            'created_at' => '2026-02-19T10:00:00Z',
        ];

        $this->postJson($this->uri('/v1/transfers'), [
            'events' => [array_merge($base, ['event_id' => '   '])],
        ])
            ->assertStatus(400)
            ->tap(function ($response): void {
                $errs = $response->json('errors');
                $this->assertArrayHasKey('events.0.event_id', $errs);
                $this->assertNotEmpty($errs['events.0.event_id']);
            });

        $this->postJson($this->uri('/v1/transfers'), [
            'events' => [array_merge($base, ['event_id' => 'ok', 'station_id' => "\t  \t"])],
        ])
            ->assertStatus(400)
            ->tap(function ($response): void {
                $errs = $response->json('errors');
                $this->assertArrayHasKey('events.0.station_id', $errs);
                $this->assertNotEmpty($errs['events.0.station_id']);
            });

        $this->postJson($this->uri('/v1/transfers'), [
            'events' => [array_merge($base, ['event_id' => 'ok', 'status' => '  '])],
        ])
            ->assertStatus(400)
            ->tap(function ($response): void {
                $errs = $response->json('errors');
                $this->assertArrayHasKey('events.0.status', $errs);
                $this->assertNotEmpty($errs['events.0.status']);
            });
    }

    public function test_duplicate_event_ids_in_single_batch_count_once(): void
    {
        $row = [
            'event_id' => 'same-batch',
            'station_id' => 'S1',
            'amount' => 10,
            'status' => 'approved',
            'created_at' => '2026-02-19T10:00:00Z',
        ];

        $this->postJson($this->uri('/v1/transfers'), [
            'events' => [$row, $row],
        ])->assertOk()->assertJson(['inserted' => 1, 'duplicates' => 1]);

        $this->getJson($this->uri('/v1/stations/S1/summary'))
            ->assertJson(['events_count' => 1, 'total_approved_amount' => 10.0]);
    }
}
