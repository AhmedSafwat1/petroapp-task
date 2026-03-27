<?php

namespace App\OpenApi;

use OpenApi\Attributes as OA;

#[OA\Schema(
    schema: 'TransferEventInput',
    required: ['event_id', 'station_id', 'amount', 'status', 'created_at'],
    properties: [
        new OA\Property(
            property: 'event_id',
            type: 'string',
            maxLength: 191,
            pattern: '.*\S.*',
            example: '123e4567-e89b-12d3-a456-426614174000',
            description: 'Non-empty; whitespace-only values are rejected.',
        ),
        new OA\Property(
            property: 'station_id',
            type: 'string',
            maxLength: 191,
            pattern: '.*\S.*',
            example: '1234567890',
            description: 'Non-empty; whitespace-only values are rejected.',
        ),
        new OA\Property(property: 'amount', type: 'number', minimum: 0),
        new OA\Property(
            property: 'status',
            type: 'string',
            maxLength: 64,
            pattern: '.*\S.*',
            example: 'approved',
            description: 'Non-empty; whitespace-only values are rejected.',
        ),
        new OA\Property(
            property: 'created_at',
            type: 'string',
            pattern: '^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$',
            example: '2026-02-19T10:00:00Z',
            description: 'UTC instant, format Y-m-d\\TH:i:s\\Z (no fractional seconds).',
        ),
    ],
)]
#[OA\Schema(
    schema: 'IngestRequest',
    required: ['events'],
    properties: [
        new OA\Property(
            property: 'events',
            type: 'array',
            items: new OA\Items(ref: '#/components/schemas/TransferEventInput'),
        ),
    ],
)]
#[OA\Schema(
    schema: 'IngestResponse',
    required: ['inserted', 'duplicates'],
    properties: [
        new OA\Property(property: 'inserted', type: 'integer'),
        new OA\Property(property: 'duplicates', type: 'integer'),
    ],
)]
#[OA\Schema(
    schema: 'StationSummary',
    required: ['station_id', 'total_approved_amount', 'events_count'],
    properties: [
        new OA\Property(property: 'station_id', type: 'string'),
        new OA\Property(property: 'total_approved_amount', type: 'number'),
        new OA\Property(
            property: 'events_count',
            type: 'integer',
            description: 'Count of all stored events for the station (all statuses)',
        ),
    ],
)]
#[OA\Schema(
    schema: 'ValidationErrorResponse',
    required: ['message', 'errors'],
    properties: [
        new OA\Property(property: 'message', type: 'string', example: 'Validation failed.'),
        new OA\Property(
            property: 'errors',
            description: 'Map of dotted paths (e.g. events.0.amount) to string message arrays.',
            type: 'object',
            additionalProperties: new OA\AdditionalProperties(
                type: 'array',
                items: new OA\Items(type: 'string'),
            ),
        ),
    ],
)]
final class OpenApiSchemas {}
