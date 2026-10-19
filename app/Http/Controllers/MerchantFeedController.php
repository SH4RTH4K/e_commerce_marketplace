<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Services\CategoryHierarchyService;
use App\Services\ProductOfferService;
use Illuminate\Support\Str;

class MerchantFeedController extends Controller
{
    public function __invoke(ProductOfferService $offers, CategoryHierarchyService $hierarchy)
    {
        return response()->stream(function () use ($offers, $hierarchy) {
            $xml = new \XMLWriter;
            $xml->openMemory();
            $xml->startDocument('1.0', 'UTF-8');
            $xml->startElement('rss');
            $xml->writeAttribute('version', '2.0');
            $xml->writeAttribute('xmlns:g', 'http://base.google.com/ns/1.0');
            $xml->startElement('channel');
            $xml->writeElement('title', site_name().' product catalogue');
            $xml->writeElement('link', route('home'));
            $xml->writeElement('description', 'Current published products, prices and availability in BDT.');
            echo $xml->flush();
            foreach (Product::published()->with(['images', 'variants', 'category', 'supplierLinks.supplierProduct'])->lazyById(100) as $product) {
                $image = $product->primaryImage();
                if (! $image) continue; // Merchant listings require a real image.
                $product->sku = $product->sku ?: $product->supplierLinks->first()?->supplierProduct?->product_code;
                $path = $product->category ? $hierarchy->path($product->category) : [];
                // Use the existing English SEO copy when the source catalogue
                // name/description is Bengali. Keep all stored content intact.
                $feedName = trim(preg_replace('/\s*\|\s*'.preg_quote(site_name(), '/').'\s*$/iu', '', (string) ($product->meta_title ?: $product->name))) ?: $product->name;
                foreach ($offers->all($product) as $offer) {
                    $xml->startElement('item');
                    $fields = [
                        'id' => $offer['id'], 'title' => Str::limit($feedName.($offer['label'] ? ' — '.$offer['label'] : ''), 150, ''),
                        'description' => Str::limit(trim(html_entity_decode(strip_tags((string) ($product->meta_description ?: $product->description ?: $product->short_description ?: $product->name)), ENT_QUOTES | ENT_HTML5, 'UTF-8')), 5000, ''),
                        'link' => $offer['url'], 'image_link' => image_url($image->path, $product->name),
                        'availability' => $offer['in_stock'] ? 'in_stock' : 'out_of_stock',
                        'price' => number_format($offer['price'], 2, '.', '').' BDT',
                        'condition' => 'new',
                    ];
                    if ($path !== []) $fields['product_type'] = implode(' > ', array_column($path, 'name'));
                    if ($product->brand) $fields['brand'] = $product->brand;
                    if ($offer['label']) {
                        $fields['item_group_id'] = (string) $product->id;
                        $fields['item_group_title'] = Str::limit($feedName, 150, '');
                    }
                    foreach ($offer['selection'] as $type => $value) {
                        $name = strtolower($type) === 'agegroup' ? 'age_group' : strtolower($type);
                        if (in_array($name, ['color', 'size', 'material', 'pattern', 'gender', 'age_group'], true)) $fields[$name] = $value;
                    }
                    // Never invent GTIN/MPN or assert identifier_exists=no from
                    // missing data. Google may request genuine manufacturer IDs.
                    foreach ($fields as $name => $value) $xml->writeElement('g:'.$name, $value);
                    // Google now supports custom dimensions (for example weight
                    // or capacity) through the repeatable variant_option group.
                    foreach ($offer['selection'] as $type => $value) {
                        $xml->startElement('g:variant_option');
                        $xml->writeElement('g:name', $type);
                        $xml->writeElement('g:value', $value);
                        $xml->endElement();
                    }
                    foreach ($product->images->where('id', '!=', $image->id)->take(10) as $extra) {
                        $xml->writeElement('g:additional_image_link', image_url($extra->path, $product->name));
                    }
                    if ($product->is_free_shipping) {
                        $xml->startElement('g:shipping');
                        $xml->writeElement('g:country', 'BD');
                        $xml->writeElement('g:price', '0.00 BDT');
                        $xml->endElement();
                    }
                    $xml->endElement();
                    echo $xml->flush();
                }
            }
            $xml->endElement(); $xml->endElement(); $xml->endDocument();
            echo $xml->flush();
        }, 200, ['Content-Type' => 'application/xml; charset=UTF-8', 'Cache-Control' => 'public, max-age=300', 'X-Content-Type-Options' => 'nosniff']);
    }
}
