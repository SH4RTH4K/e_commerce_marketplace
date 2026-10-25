<?php

namespace Tests\Feature;

use App\Models\Setting;
use DateTimeImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecurityTxtTest extends TestCase
{
    use RefreshDatabase;

    public function test_security_txt_is_available_at_both_public_paths_with_rfc9116_fields(): void
    {
        Setting::put('contact_email', 'security@taqilife.com');

        $responses = [
            $this->get('/.well-known/security.txt')->assertOk(),
            $this->get('/security.txt')->assertOk(),
        ];

        $this->assertSame($responses[0]->getContent(), $responses[1]->getContent());
        $body = $responses[0]->getContent();
        $this->assertStringContainsString('Contact: mailto:security@taqilife.com', $body);
        $this->assertStringContainsString('Preferred-Languages: en, bn', $body);
        $this->assertStringContainsString('Canonical: https://taqilife.com/.well-known/security.txt', $body);
        $this->assertStringContainsString('text/plain', (string) $responses[0]->headers->get('Content-Type'));

        preg_match('/^Expires: (.+)$/m', $body, $matches);
        $this->assertNotEmpty($matches[1] ?? null);
        $expires = new DateTimeImmutable($matches[1]);
        $this->assertGreaterThan(new DateTimeImmutable('now'), $expires);
    }
}
