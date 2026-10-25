<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;

class SecurityTxtController extends Controller
{
    public function __invoke(): Response
    {
        // Keep this aligned with the email published on the contact page.
        $contactEmail = trim((string) setting('contact_email', '')) ?: 'support@taqilife.com';
        $expires = now('UTC')->addYear()->format('Y-m-d\TH:i:s\Z');
        $body = implode("\n", [
            'Contact: mailto:'.$contactEmail,
            'Expires: '.$expires,
            'Preferred-Languages: en, bn',
            'Canonical: https://taqilife.com/.well-known/security.txt',
            '',
        ]);

        return response($body, 200, [
            'Cache-Control' => 'public, max-age=3600',
            'Content-Type' => 'text/plain; charset=utf-8',
        ]);
    }
}
