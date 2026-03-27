<?php

namespace App\Contracts\Services;

use App\DataTransferObjects\IngestResult;

interface TransferIngestionServiceInterface
{
    /**
     * @param  array<int, array<string, mixed>>  $events
     */
    public function ingest(array $events): IngestResult;
}
