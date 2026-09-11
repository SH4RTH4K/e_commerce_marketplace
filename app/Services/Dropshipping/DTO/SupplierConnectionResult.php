<?php

namespace App\Services\Dropshipping\DTO;

final readonly class SupplierConnectionResult
{
    /** @param list<string> $warnings */
    public function __construct(
        public bool $successful,
        public string $message,
        public ?int $statusCode = null,
        public array $warnings = [],
    ) {
    }
}
