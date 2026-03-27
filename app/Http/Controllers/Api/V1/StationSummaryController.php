<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Repositories\StationSummaryRepositoryInterface;
use App\Contracts\Services\StationSummaryServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Resources\StationSummaryResource;
use Illuminate\Http\JsonResponse;
use OpenApi\Attributes as OA;

class StationSummaryController extends Controller
{
    public function __construct(
        private readonly StationSummaryServiceInterface $stationSummary,
        private readonly StationSummaryRepositoryInterface $stationSummaryRows,
    ) {}

    #[OA\Get(
        path: '/v1/stations/{station_id}/summary',
        summary: 'Live reconciliation summary (computed from transfer_events)',
        tags: ['Stations'],
        parameters: [
            new OA\PathParameter(
                name: 'station_id',
                schema: new OA\Schema(type: 'string'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Summary',
                content: new OA\JsonContent(ref: '#/components/schemas/StationSummary'),
            ),
        ],
    )]
    public function show(string $station_id): JsonResponse
    {
        return $this->jsonResponse(
            $this->stationSummary->summary($station_id)->toArray()
        );
    }

    #[OA\Get(
        path: '/v1/stations/{station_id}/summary-report',
        summary: 'Cached station summary (station_summaries); falls back to live summary when not cached',
        tags: ['Stations'],
        parameters: [
            new OA\PathParameter(
                name: 'station_id',
                schema: new OA\Schema(type: 'string'),
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'Summary',
                content: new OA\JsonContent(ref: '#/components/schemas/StationSummary'),
            ),
        ],
    )]
    public function summaryReport(string $station_id): JsonResponse
    {
        $row = $this->stationSummaryRows->findByStationId($station_id);
        if ($row === null) {
            return $this->jsonResponse($this->stationSummary->summary($station_id)->toArray());
        }

        return $this->jsonResponse((new StationSummaryResource($row))->resolve(request()));
    }
}
