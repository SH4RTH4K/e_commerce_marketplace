<?php

namespace App\Services;

use App\Contracts\CourierInterface;
use App\Services\Courier\PathaoService;
use App\Services\Courier\RedxService;
use App\Services\Courier\SteadfastService;
use InvalidArgumentException;

class CourierManagerService
{
    /**
     * Resolve the correct courier service class by provider name.
     *
     * @throws InvalidArgumentException
     */
    public function resolve(string $provider): CourierInterface
    {
        return match (strtolower($provider)) {
            'steadfast' => new SteadfastService(),
            'pathao'    => new PathaoService(),
            'redx'      => new RedxService(),
            default     => throw new InvalidArgumentException("Unknown courier provider: [{$provider}]"),
        };
    }

    /**
     * List of all supported providers (for validation & UI).
     */
    public static function providers(): array
    {
        return ['steadfast', 'pathao', 'redx'];
    }
}
