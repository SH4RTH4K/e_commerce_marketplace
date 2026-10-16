<?php

namespace App\Services;

class ShoppingGuideService
{
    public function all(): array
    {
        return [
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
