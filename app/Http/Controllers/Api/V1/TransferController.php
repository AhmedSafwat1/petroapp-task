<?php

namespace App\Http\Controllers\Api\V1;

use App\Contracts\Services\TransferIngestionServiceInterface;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreTransferEventsRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use OpenApi\Attributes as OA;

class TransferController extends Controller
{
    public function __construct(
        private readonly TransferIngestionServiceInterface $ingestion,
    ) {}

    #[OA\Post(
        path: '/v1/transfers',
        summary: 'Ingest transfer events (idempotent by event_id)',
        tags: ['Transfers'],
        requestBody: new OA\RequestBody(
            required: true,
            content: new OA\JsonContent(ref: '#/components/schemas/IngestRequest'),
        ),
        responses: [
            new OA\Response(
                response: 200,
                description: 'Ingest result',
                content: new OA\JsonContent(ref: '#/components/schemas/IngestResponse'),
            ),
            new OA\Response(
                response: 400,
                description: 'Validation failed (fail-fast batch)',
                content: new OA\JsonContent(ref: '#/components/schemas/ValidationErrorResponse'),
            ),
        ],
    )]
    public function store(StoreTransferEventsRequest $request): JsonResponse
    {
        /** @var array<int, array<string, mixed>> $events */
        $events = $request->input('events');
        $result = $this->ingestion->ingest($events);

        Log::info('transfer_events.ingested', [
            'inserted' => $result->inserted,
            'duplicates' => $result->duplicates,
            'batch_size' => count($events),
        ]);

        return $this->jsonResponse($result->toArray());
    }
}
