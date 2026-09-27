<?php

namespace App\Services\Dropshipping;

use App\Models\DropshipSupplierProduct;

final class SupplierDescriptionFormatter
{
    /** @var list<string> */
    private const FIELD_LABELS = [
        'Dial window material type', 'Water resistance depth', 'Band material type',
        'Movement brand', 'Model Number', 'Dial diameter', 'Case thickness',
        'Case material', 'Dial display', 'Case shape', 'Band length', 'Band width',
        'Clasp type', 'Water resistance', 'Master Copy', 'Package includes',
        'Wash & Care', 'Main Material', 'Measurement', 'Warranty', 'Feature',
        'Pointer', 'Quality', 'Stretch', 'Pocket', 'Gender', 'Brand', 'Waist',
        'Products details', 'Product details', 'Product Name', 'Size Measurement',
        'Fabrics', 'Type', 'Model', 'Material', 'Size',
    ];

    /** @var list<string> */
    private const BENEFIT_PHRASES = [
        '100% Authentic Satisfied Product',
        '100% Money Back Refund Policy',
        '10 Days Easy Return & Replace Policy',
        '1 Year Service Warranty',
        'Safe Online Payment & COD Available',
        'Quick Priority Support 24/7 Days',
        'Fastest Home Delivery For All orders',
    ];

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

        $plainDescription = $this->plainText($description);
        if ($this->joinedFieldCount($plainDescription) >= 3 || $this->joinedBenefitCount($plainDescription) >= 2) {
            return $this->formatJoinedFields($plainDescription);
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

    private function plainText(string $value): string
    {
        $text = preg_replace('/<br\s*\/?\s*>/i', "\n", $value) ?? $value;
        $text = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\/?p[^>]*>/i', '', $text) ?? $text;

        return trim(html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    private function joinedFieldCount(string $value): int
    {
        return preg_match_all($this->fieldPattern(), $value) ?: 0;
    }

    private function joinedBenefitCount(string $value): int
    {
        return preg_match_all($this->benefitPattern(), $value) ?: 0;
    }

    private function formatJoinedFields(string $value): string
    {
        $text = preg_replace_callback(
            $this->fieldPattern(),
            static fn (array $matches): string => "\n" . $matches[1],
            $value,
        ) ?? $value;
        $text = preg_replace('/(?<=[\p{Ll}])(?=[\p{Lu}])/u', "\n", $text) ?? $text;
        $text = preg_replace('/(?<=[\pL])(?=\d{1,3}%)/u', "\n", $text) ?? $text;
        $text = preg_replace('/(")(?=[A-Z]{1,4}\s*=)/', "$1\n", $text) ?? $text;
        $text = preg_replace_callback($this->benefitPattern(), static fn (array $matches): string => "\n" . $matches[1], $text) ?? $text;
        $text = preg_replace('/(?<!\s)#(?=[\pL\pN])/u', "\n#", $text) ?? $text;

        $lines = preg_split('/\R+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return implode('', array_map(function (string $line): string {
            $line = trim((string) preg_replace('/:\s*/', ': ', trim($line)));
            [$label, $content] = array_pad(explode(':', $line, 2), 2, null);
            $label = trim($label);

            if ($content !== null && $this->isFieldLabel($label)) {
                return '<p><strong>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ':</strong>'
                    . (trim($content) !== '' ? ' ' . htmlspecialchars(trim($content), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '')
                    . '</p>';
            }

            if ($this->isFieldLabel($line)) {
                return '<p><strong>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong></p>';
            }

            return '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }, $lines));
    }

    private function isFieldLabel(string $value): bool
    {
        return in_array(mb_strtolower($value), array_map(mb_strtolower(...), self::FIELD_LABELS), true);
    }

    private function fieldPattern(): string
    {
        $labels = self::FIELD_LABELS;
        usort($labels, static fn (string $first, string $second): int => strlen($second) <=> strlen($first));

        return '/(?<![\s,])(' . implode('|', array_map(static fn (string $label): string => preg_quote($label, '/'), $labels)) . ')(?=\s*(?::|[A-Z#])|$)/u';
    }

    private function benefitPattern(): string
    {
        return '/(' . implode('|', array_map(
            static fn (string $phrase): string => preg_quote($phrase, '/'),
            self::BENEFIT_PHRASES,
        )) . ')/iu';
    }
}
