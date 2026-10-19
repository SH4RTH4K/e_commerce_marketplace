<?php

namespace App\Services;

class ShoppingGuideService
{
    public function all(): array
    {
        return [
            'bluetooth-speaker-buying-guide-bangladesh' => [
                'title' => 'How to choose a Bluetooth speaker in Bangladesh',
                'summary' => 'Compare portable speakers by total price, size, battery life, connections and warranty before choosing a model for home or travel.',
                'category_slug' => 'bluetooth-speaker',
                'sections' => [
                    ['heading' => 'Set a budget for the complete order', 'text' => 'Compare the current price of each exact model, then include the delivery charge shown at checkout. A low price alone does not confirm the brand, features or warranty. Check the seller description and ask support to confirm anything unclear before paying. Prices and availability can change; use the live product page rather than an old social media post.'],
                    ['heading' => 'Choose for your room, desk or travel bag', 'text' => 'For a desk or small room, compare the speaker size, controls and charging connection. For travel, consider weight, a carrying strap and the stated battery life. More watts do not by themselves guarantee better sound. Ask whether photos show the exact supplied model, and use an actual listening demonstration when one is available.'],
                    ['heading' => 'Check battery and connectivity details', 'text' => 'Check how the speaker charges, whether a charging cable or adapter is included, and whether it supports the connection you need. Bluetooth, AUX, USB playback and memory-card support vary by model. Advertised battery life depends on volume and lighting settings; ask for the conditions behind a runtime claim. Do not assume two speakers can pair together unless that exact model supports it.'],
                    ['heading' => 'Confirm the item, warranty and return conditions', 'text' => 'Confirm the brand and model supplied, box contents and any written warranty before ordering. Similar-looking speakers can have different electronics and specifications. Read the shipping and return policies, keep your order details and check the delivery for a wrong item, missing parts or damage. Contact support promptly with the product name or SKU if something does not match.'],
                ],
            ],
            'bluetooth-speaker-battery-waterproof-guide' => [
                'title' => 'Bluetooth speaker battery life and water resistance: what to check',
                'summary' => 'Understand speaker runtime, charging, water-resistance ratings and warranty questions before buying a portable Bluetooth speaker.',
                'category_slug' => 'bluetooth-speaker',
                'sections' => [
                    ['heading' => 'Compare runtime under similar conditions', 'text' => 'Battery capacity and claimed hours are different measurements. Runtime varies with volume, bass, lighting and battery condition. Compare claims made at similar settings, and ask how long the supplied model is expected to play at your normal listening volume. A larger capacity does not automatically mean a longer runtime when speaker power and other features differ.'],
                    ['heading' => 'Check the charging connection and accessories', 'text' => 'Look for the input voltage, connector type and stated charging time for your exact speaker. Confirm whether the box contains a cable, a charger, both or neither. Use the charging method specified for that item. USB playback and USB charging are separate functions, so check the listing carefully if you need music playback from a pen drive.'],
                    ['heading' => 'Treat water-resistance claims as model-specific', 'text' => 'A waterproof-looking case or product photo does not establish a protection rating. Look for a documented rating for the actual supplied model and read its instructions and exclusions. Do not infer that another version has the same rating, or assume that water damage is covered by the warranty. Ask support before exposing an item to water when the rating is unclear.'],
                    ['heading' => 'Ask these questions before checkout', 'text' => 'Is this the exact brand and version shown? What is included in the box? What warranty, if any, is supplied in writing, and who handles a claim? Which delivery and return conditions apply? Confirm these details on the product page or with support, then keep the order confirmation. The available products below link to current stock and pricing.'],
                ],
            ],
            'clothing-size-guide' => [
                'title' => 'How to choose clothing sizes when shopping online',
                'summary' => 'Compare garment measurements, size options and product details before ordering clothing from '.site_name().'.',
                'sections' => [
                    ['heading' => 'Start with the product details', 'text' => 'A size label alone does not tell you how a garment will fit. Open the product page and read the description and specifications. Look for the material, cut and any measurements supplied for that item. A medium from one product can differ from a medium in another. If measurements are missing, contact our team with the product name or SKU before placing your order.'],
                    ['heading' => 'Compare with a garment you already wear', 'text' => 'Lay a similar garment flat and measure the parts that matter for its fit, such as chest width, shoulder width and length. Compare using the same units and measurement method as the product information. Allow for how you prefer to wear the garment. Do not assume that a fabric stretches unless the description states that it does.'],
                    ['heading' => 'Check the selected option and price', 'text' => 'Choose the size and colour you want on the product page. Some options have different prices, so check the displayed price after selecting them. Review the selected variant and quantity in your cart. If you change your mind, update the cart before proceeding to checkout.'],
                    ['heading' => 'Understand exchange conditions before checkout', 'text' => 'Read the current return and exchange policy before ordering. Keep packaging and tags while checking an eligible item. Contact our team promptly if the delivered item is damaged, incorrect or incomplete. Exchanges depend on the policy conditions and available stock; a preferred fit is not a guaranteed reason for a free exchange.'],
                ],
            ],
            'gadget-specification-guide' => [
                'title' => 'How to compare gadget specifications before ordering',
                'summary' => 'Check compatibility, included accessories, variant prices and stated warranty details when comparing gadgets and electronics.',
                'sections' => [
                    ['heading' => 'Choose the job the gadget needs to do', 'text' => 'Start with your intended use rather than the product picture alone. Search by a product name or SKU, then open the items you want to compare. Read the description and specifications for each one. Use only the features listed for that exact product; a similar-looking model may have different connectors, controls or accessories.'],
                    ['heading' => 'Check compatibility and what is included', 'text' => 'Compare the stated connector type, device requirements, dimensions and power requirements with your existing equipment. Check whether the listing includes a cable, adapter, stand or other accessory you need. If a specification or included item is unclear, send the product name or SKU to our support team for clarification before ordering.'],
                    ['heading' => 'Compare the option you will actually buy', 'text' => 'Select the colour, size, capacity or other available option before comparing prices. An option can add to or reduce the displayed price. Check your chosen variant in the cart and the delivery charge at checkout. Product availability can change, so use the current product page rather than an old screenshot or shared price.'],
                    ['heading' => 'Read warranty and return information', 'text' => 'Check the product description for a stated warranty and its conditions. Do not assume every electronic item has the same warranty. Read the return policy, including exclusions and how to report an incorrect, damaged or incomplete delivery. Keep the order information and packaging needed for support.'],
                ],
            ],
            'ordering-delivery-returns' => [
                'title' => 'Ordering, delivery and returns at '.site_name(),
                'summary' => 'A practical guide to choosing products, reviewing checkout charges, tracking your order and contacting support.',
                'sections' => [
                    ['heading' => 'Find and review your products', 'text' => 'Browse the store categories or search by product name or SKU. Open a product to review its description, images, specifications and available options. Choose the variant and quantity you need, then add it to your cart. Review every cart line before checkout and remove anything you no longer want.'],
                    ['heading' => 'Review your delivery and payment details', 'text' => 'Enter a complete delivery address and a reachable phone number. Choose the correct delivery area and check the displayed shipping charge and total. The shipping information page explains current charges. Some weight-based products may need a revised delivery charge; the published shipping policy explains the confirmation process. Use one of the payment methods currently offered at checkout.'],
                    ['heading' => 'Keep your order confirmation', 'text' => 'Keep the order number shown after checkout. Use Track Order on the website to check an order, and provide the order number when asking our team for assistance. The team may contact you to verify your details before dispatch. For a change or cancellation, contact support promptly; the applicable process depends on whether the order has already been dispatched.'],
                    ['heading' => 'Check the delivery and use the published policy', 'text' => 'Check that your delivery matches the ordered product and selected variant. Report damage, a wrong item or missing parts promptly, with the order number and relevant details. Read the current return policy for request windows, eligibility, exclusions, delivery-cost responsibilities and refund processing. Keep original packaging and tags where required. Contact details are available on our Contact page.'],
                ],
            ],
        ];
    }

    public function links(): array
    {
        return array_map(fn ($slug, $guide) => ['slug' => $slug, 'title' => $guide['title'], 'summary' => $guide['summary']], array_keys($this->all()), array_values($this->all()));
    }
}
