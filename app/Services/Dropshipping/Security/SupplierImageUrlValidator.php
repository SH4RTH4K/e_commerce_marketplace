<?php

namespace App\Services\Dropshipping\Security;

use Closure;
use InvalidArgumentException;

/**
 * Validates one URL at a time. Call validate again for every redirect before
 * issuing a follow-up request, and pin the resulting public IPs in the image
 * transport to prevent a post-validation DNS rebind.
 */
class SupplierImageUrlValidator
{
    /** @var Closure(string): list<string> */
    private Closure $resolver;

    /**
     * @param Closure(string): list<string>|null $resolver
     */
    public function __construct(?Closure $resolver = null)
    {
        $this->resolver = $resolver ?? static function (string $host): array {
            $records = dns_get_record($host, DNS_A | DNS_AAAA);
            if (! is_array($records)) {
                return [];
            }

            return array_values(array_unique(array_filter(array_map(
                static fn (array $record): ?string => $record['ip'] ?? $record['ipv6'] ?? null,
                $records,
            ))));
        };
    }

    /**
     * @param list<string> $allowedHosts Exact driver-owned host names only.
     */
    public function validate(string $url, array $allowedHosts): ValidatedRemoteUrl
    {
        $url = trim($url);
        if ($url === '' || strlen($url) > 2_048 || filter_var($url, FILTER_VALIDATE_URL) === false) {
            throw new InvalidArgumentException('Supplier image URL is invalid.');
        }

        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https') {
            throw new InvalidArgumentException('Supplier image URL must use HTTPS.');
        }
        if (isset($parts['user']) || isset($parts['pass'])) {
            throw new InvalidArgumentException('Supplier image URL must not include user information.');
        }
        if (isset($parts['port']) && $parts['port'] !== 443) {
            throw new InvalidArgumentException('Supplier image URL must use the default HTTPS port.');
        }

        $host = $this->normalizedHost($parts['host'] ?? null);
        if ($host === null || filter_var($host, FILTER_VALIDATE_IP) !== false) {
            throw new InvalidArgumentException('Supplier image URL must use a trusted DNS host.');
        }
        if (! in_array($host, $this->normalizedAllowedHosts($allowedHosts), true)) {
            throw new InvalidArgumentException('Supplier image URL host is not allowed for this driver.');
        }

        $ips = ($this->resolver)($host);
        if ($ips === [] || array_filter($ips, fn (mixed $ip): bool => ! is_string($ip) || ! $this->isPublicIp($ip)) !== []) {
            throw new InvalidArgumentException('Supplier image URL does not resolve exclusively to public IP addresses.');
        }

        return new ValidatedRemoteUrl($url, $host, array_values(array_unique($ips)));
    }

    private function normalizedHost(mixed $host): ?string
    {
        if (! is_string($host)) {
            return null;
        }

        $host = strtolower(rtrim(trim($host), '.'));

        return $host !== '' && preg_match('/^[a-z0-9](?:[a-z0-9.-]{0,251}[a-z0-9])?$/', $host)
            ? $host
            : null;
    }

    /** @param list<string> $allowedHosts */
    private function normalizedAllowedHosts(array $allowedHosts): array
    {
        return array_values(array_unique(array_filter(array_map(
            fn (mixed $host): ?string => is_string($host) ? $this->normalizedHost($host) : null,
            $allowedHosts,
        ))));
    }

    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
        ) !== false;
    }
}
