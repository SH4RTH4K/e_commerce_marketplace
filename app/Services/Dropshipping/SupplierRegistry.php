<?php

namespace App\Services\Dropshipping;

use App\Services\Dropshipping\Contracts\SupplierClient;
use InvalidArgumentException;
use LogicException;

class SupplierRegistry
{
    /** @var array<string, SupplierClient> */
    private array $clients = [];

    /** @param iterable<SupplierClient> $clients */
    public function __construct(iterable $clients = [])
    {
        foreach ($clients as $client) {
            $this->register($client);
        }
    }

    public function register(SupplierClient $client): void
    {
        $key = trim($client->driverKey());
        if ($key === '' || ! preg_match('/^[a-z0-9][a-z0-9_-]*$/', $key)) {
            throw new InvalidArgumentException('Supplier driver keys must be stable lowercase identifiers.');
        }
        if (isset($this->clients[$key])) {
            throw new LogicException("Supplier driver [{$key}] is already registered.");
        }

        $this->clients[$key] = $client;
    }

    public function forDriver(string $key): SupplierClient
    {
        $key = trim($key);
        if (! isset($this->clients[$key])) {
            throw new LogicException("Supplier driver [{$key}] is not registered.");
        }

        return $this->clients[$key];
    }

    public function hasDriver(string $key): bool
    {
        return isset($this->clients[trim($key)]);
    }

    /** @return list<string> */
    public function driverKeys(): array
    {
        return array_keys($this->clients);
    }
}
