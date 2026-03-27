<?php

namespace App\DataTransferObjects;

class IngestResult
{
    public function __construct(
        public readonly int $inserted,
        public readonly int $duplicates,
    ) {}

    public function toArray(): array
    {
        return [
            'inserted' => $this->inserted,
            'duplicates' => $this->duplicates,
        ];
    }
}
