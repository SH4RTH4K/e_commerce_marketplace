<?php

namespace App\Http\Controllers;

use Inertia\Inertia;
use App\Models\ContactFormField;
use Illuminate\Http\Request;
use Inertia\Response;

class PageController extends Controller
{
    public function about(): Response
    {
        return Inertia::render('Storefront/Page', [
            'title'   => 'About Us',
            'heading' => 'About Us',
            'body'    => setting('about_content') ?: $this->defaultAbout(),
        ]);
    }

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

    public function shipping(): Response
    {
        $additionalInformation = trim((string) setting('shipping_content', ''));

        return Inertia::render('Storefront/Page', [
            'title'   => 'Shipping Information',
            'heading' => 'Shipping Information',
            'body'    => $this->shippingCharges() . ($additionalInformation !== ''
                ? $additionalInformation
                : $this->defaultDeliveryInformation()),
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

        if ($slug === 'about') {
            return $this->about();
        }

        if ($slug === 'privacy') {
            return $this->privacy();
        }

        if ($slug === 'refund-policy' || $slug === 'refund') {
            return $this->refund();
        }

        if ($slug === 'shipping') {
            return $this->shipping();
        }

        abort(404);
    }

    private function defaultTerms(): string
    {
        $site = site_name();

        return "Welcome to {$site}. By placing an order you agree to provide accurate delivery and payment details, accept our shipping timelines, and understand that product availability may change. Orders may be cancelled if payment verification fails. For returns and support, contact us using the details on this website.";
    }

    private function defaultAbout(): string
    {
        $site = e(site_name());

        return "<h2>Our Story</h2><p>{$site} makes everyday shopping simple, personal, and dependable. We bring together useful products, clear information, fair prices, and reliable delivery in one welcoming store.</p><h2>Our Mission</h2><p>We focus on giving every customer a clear, useful shopping experience and helpful support from discovery through delivery.</p><p>Have a question? Visit our <a href=\"/contact\">contact page</a> and our team will be happy to help.</p>";
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

    private function shippingCharges(): string
    {
        $currency = e((string) setting('currency_symbol', '৳'));
        $inside = number_format((float) setting('shipping_inside_dhaka', 60), 2);
        $outside = number_format((float) setting('shipping_outside_dhaka', 120), 2);
        $insideLabel = e((string) setting('shipping_inside_label', 'Inside Dhaka'));
        $outsideLabel = e((string) setting('shipping_outside_label', 'Outside Dhaka'));
        $intro = e((string) setting('shipping_charge_intro', 'Your delivery charge is calculated at checkout from the delivery area you choose.'));

        return <<<HTML
<h2>Shipping Charges</h2>
<p>{$intro}</p>
<ul>
  <li><strong>{$insideLabel}:</strong> {$currency}{$inside}</li>
  <li><strong>{$outsideLabel}:</strong> {$currency}{$outside}</li>
</ul>
HTML;
    }

    private function defaultDeliveryInformation(): string
    {
        return <<<HTML
<h2>Delivery Information</h2>
<p>Please provide a complete delivery address and a reachable phone number when placing your order. Our team may contact you to confirm the order before dispatch.</p>
<p>Shipping charges and delivery availability are applied according to the current store configuration at checkout.</p>
HTML;
    }
}
