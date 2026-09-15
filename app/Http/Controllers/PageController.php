<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\ContactFormField;
use Illuminate\Http\Request;
use Inertia\Response;

class PageController extends Controller
{
    public function terms(): Response
    {
        return Inertia::render('Storefront/Page', [
            'title'   => 'Terms of Service',
            'heading' => 'Terms of Service',
            'body'    => setting('terms_content') ?: $this->defaultTerms(),
        ]);
    }

    public function privacy(): Response
    {
        return Inertia::render('Storefront/Page', [
            'title'   => 'Privacy Policy',
            'heading' => 'Privacy Policy',
            'body'    => setting('privacy_content') ?: $this->defaultPrivacy(),
        ]);
    }

    public function refund(): Response
    {
        return Inertia::render('Storefront/Page', [
            'title'   => 'Refund Policy',
            'heading' => 'Refund Policy',
            'body'    => setting('refund_content') ?: $this->defaultRefund(),
            'showPageTitle' => false,
        ]);
    }

    public function contact()
    {
        return Inertia::render('Storefront/Contact', [
            'title'   => 'Contact',
            'fields'  => ContactFormField::active()->ordered()->get(),
            'phone'   => trim((string) setting('contact_phone', '')),
            'email'   => trim((string) setting('contact_email', '')),
            'address' => trim((string) setting('contact_address', '')),
            'hours'   => trim((string) setting('contact_hours', '')),
        ]);
    }

    /**
     * Generic CMS-style page by slug (contact redirects to dedicated view).
     */
    public function show(string $slug): mixed
    {
        if ($slug === 'contact') {
            return $this->contact();
        }

        if ($slug === 'terms') {
            return $this->terms();
        }

        if ($slug === 'privacy') {
            return $this->privacy();
        }

        if ($slug === 'refund-policy' || $slug === 'refund') {
            return $this->refund();
        }

        abort(404);
    }

    private function defaultTerms(): string
    {
        $site = site_name();

        return "Welcome to {$site}. By placing an order you agree to provide accurate delivery and payment details, accept our shipping timelines, and understand that product availability may change. Orders may be cancelled if payment verification fails. For returns and support, contact us using the details on this website.";
    }

    private function defaultPrivacy(): string
    {
        $site = site_name();

        return "{$site} collects account, order, and delivery information needed to fulfill purchases. We do not sell your personal data. Payment transaction IDs for mobile banking are stored only to verify your order. Contact us to request account updates or deletion where applicable.";
    }

    private function defaultRefund(): string
    {
        $site = site_name();

        return "If you are not entirely satisfied with your purchase, we're here to help. Contact {$site} support to initiate a return or exchange. Products must be in their original condition and packaging. Refunds are processed to the original method of payment after we receive and inspect the returned item.";
    }
}
