<?php

namespace App\Services\Dropshipping\Security;

final readonly class ValidatedRemoteUrl
{
    /** @param list<string> $resolvedIps */
    public function __construct(
        public string $url,
        public string $host,
        public array $resolvedIps,
    ) {
    }
}
