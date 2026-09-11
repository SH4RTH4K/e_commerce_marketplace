<?php

namespace Tests\Unit\Dropshipping;

use App\Services\Dropshipping\Security\SupplierImageUrlValidator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class SupplierImageUrlValidatorTest extends TestCase
{
    public function test_it_accepts_a_public_https_url_on_an_exact_allowed_host(): void
    {
        $validator = new SupplierImageUrlValidator(static fn (): array => ['93.184.216.34']);

        $validated = $validator->validate('https://cdn.supplier.example/images/1.webp', ['cdn.supplier.example']);

        $this->assertSame('cdn.supplier.example', $validated->host);
        $this->assertSame(['93.184.216.34'], $validated->resolvedIps);
    }

    public function test_it_rejects_insecure_untrusted_and_private_destinations(): void
    {
        $validator = new SupplierImageUrlValidator(static fn (): array => ['127.0.0.1']);

        foreach ([
            ['http://cdn.supplier.example/image.jpg', ['cdn.supplier.example']],
            ['https://evil.example/image.jpg', ['cdn.supplier.example']],
            ['https://cdn.supplier.example/image.jpg', ['cdn.supplier.example']],
            ['https://cdn.supplier.example@evil.example/image.jpg', ['cdn.supplier.example']],
        ] as [$url, $allowedHosts]) {
            try {
                $validator->validate($url, $allowedHosts);
                $this->fail("Expected {$url} to be rejected.");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_each_redirect_target_must_be_revalidated(): void
    {
        $validator = new SupplierImageUrlValidator(static fn (string $host): array => $host === 'cdn.supplier.example'
            ? ['93.184.216.34']
            : ['203.0.113.1']);

        $validator->validate('https://cdn.supplier.example/first.jpg', ['cdn.supplier.example']);

        $this->expectException(InvalidArgumentException::class);
        $validator->validate('https://redirected.example/private.jpg', ['cdn.supplier.example']);
    }
}
