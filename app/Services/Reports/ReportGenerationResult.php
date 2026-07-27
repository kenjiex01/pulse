<?php

namespace App\Services\Reports;

class ReportGenerationResult
{
    /**
     * @param  array<int, string>  $headers
     * @param  array<int, array<int, string|float|null>>  $rows
     * @param  array<string, mixed>  $meta
     */
    public function __construct(
        public readonly string $title,
        public readonly array $headers,
        public readonly array $rows,
        public readonly array $meta = [],
    ) {}
}
