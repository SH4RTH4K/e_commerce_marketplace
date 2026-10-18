<?php

namespace App\Services\Dropshipping;

use RuntimeException;

class SupplierOwnershipException extends RuntimeException
{
    public function __construct(public readonly string $reason, string $message)
    {
        parent::__construct($message);
    }
}
