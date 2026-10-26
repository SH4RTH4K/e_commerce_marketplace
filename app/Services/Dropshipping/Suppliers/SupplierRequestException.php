<?php

namespace App\Services\Dropshipping\Suppliers;

use LogicException;

final class SupplierRequestException extends LogicException
{
    public function __construct(
        public readonly int $status,
        string $message,
    ) {
        parent::__construct($message);
    }
}
