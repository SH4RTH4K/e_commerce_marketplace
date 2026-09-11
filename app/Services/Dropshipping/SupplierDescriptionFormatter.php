<?php

namespace App\Services\Dropshipping;

use App\Models\DropshipSupplierProduct;

final class SupplierDescriptionFormatter
{
    public function format(DropshipSupplierProduct $source): ?string
    {
        $payload = is_array($source->raw_payload) ? $source->raw_payload : [];
        $description = null;

        foreach (['details', 'description', 'product_description', 'long_description', 'short_description'] as $field) {
            $value = $payload[$field] ?? null;
            if (is_scalar($value) && trim((string) $value) !== '') {
                $description = trim((string) $value);
                break;
            }
        }

        if ($description === null) {
            return null;
        }

        $description = trim(html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if ($description === '') {
            return null;
        }

        if (preg_match('/<\/?[a-z][^>]*>/i', $description)) {
            return $this->sanitizeHtml($description);
        }

        $paragraphs = preg_split('/\r?\n\s*\r?\n/', $description, -1, PREG_SPLIT_NO_EMPTY) ?: [$description];

        return implode('', array_map(
            fn (string $paragraph): string => '<p>' . str_replace(
                ["\r\n", "\r", "\n"],
                '<br />',
                htmlspecialchars(trim($paragraph), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'),
            ) . '</p>',
            $paragraphs,
        ));
    }

    private function sanitizeHtml(string $html): string
    {
        $allowed = '<p><br><strong><b><em><i><u><s><ul><ol><li><h2><h3><h4><blockquote><a><hr>';
        $html = strip_tags($html, $allowed);

        return trim(preg_replace(
            '/<\s*(\/?)\s*(p|br|strong|b|em|i|u|s|ul|ol|li|h2|h3|h4|blockquote|a|hr)\b[^>]*>/i',
            '<$1$2>',
            $html,
        ) ?? $html);
    }
}
