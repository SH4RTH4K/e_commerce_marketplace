<?php

namespace App\Services\Dropshipping;

use App\Models\DropshipSupplierProduct;

final class SupplierDescriptionFormatter
{
    /** @var list<string> */
    private const FIELD_LABELS = [
        'Dial window material type', 'Water resistance depth', 'Band material type',
        'Movement brand', 'Movement', 'Brand Name', 'Model Number', 'Dial diameter', 'Case thickness',
        'Case material', 'Dial display', 'Case shape', 'Band length', 'Band width',
        'Clasp type', 'Water resistance', 'Master Copy', 'Package includes',
        'Specification', 'Master Chip', 'Screen Display', 'Product Size', 'Body Material',
        'Strap Material', 'Charging Type', 'Battery Capacity', 'Waterproof Level', 'Functions',
        'Certification', 'Origin', 'Style', 'Boxes & Cases Material', 'APP',
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

        return $this->formatDescription($description);
    }

    public function formatDescription(?string $description): ?string
    {
        if ($description === null) {
            return null;
        }

        $description = trim($description);
        if ($description === '') {
            return null;
        }

        $decodedDescription = html_entity_decode($description, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $plainDescription = $this->plainText($decodedDescription);
        $hasHtmlMarkup = preg_match('/<\/?[a-z][^>]*>/i', $description) === 1
            || preg_match('/<\/?[a-z][^>]*>/i', $decodedDescription) === 1;
        $hasStructuredHtml = $hasHtmlMarkup && $this->hasStructuredHtml($decodedDescription);
        if ((! $hasHtmlMarkup && $this->joinedFieldCount($plainDescription) >= 3)
            || $this->joinedBenefitCount($plainDescription) >= 2
            || (! $hasStructuredHtml && ($this->hasSpecificationHeading($plainDescription)
                || $this->compactSpecificationFieldCount($plainDescription) >= 5))) {
            return $this->formatJoinedFields($plainDescription);
        }

        if (preg_match('/<\/?[a-z][^>]*>/i', $description)) {
            return $this->sanitizeHtml($description);
        }

        if (preg_match('/<\/?[a-z][^>]*>/i', $decodedDescription)) {
            return $this->sanitizeHtml($decodedDescription);
        }

        $paragraphs = preg_split('/\r?\n\s*\r?\n/', $decodedDescription, -1, PREG_SPLIT_NO_EMPTY) ?: [$decodedDescription];

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

    private function compactSpecificationFieldCount(string $value): int
    {
        return count(array_filter(
            $this->compactDescriptionLines($value),
            fn (string $line): bool => $this->isSpecificationFieldLine($line),
        ));
    }

    private function hasSpecificationHeading(string $value): bool
    {
        return preg_match('/\bSpecifications?\s*:/i', $value) === 1;
    }

    private function hasStructuredHtml(string $value): bool
    {
        return preg_match('/<(?:ul|ol|li|h[1-6]|blockquote)\b/i', $value) === 1
            || preg_match_all('/<p\b[^>]*>/i', $value) > 1;
    }

    private function formatJoinedFields(string $value): string
    {
        $lines = $this->compactDescriptionLines($value);

        return implode('', array_map(function (string $line): string {
            $line = trim((string) preg_replace('/:\s*/', ': ', trim($line)));
            [$label, $content] = array_pad(explode(':', $line, 2), 2, null);
            $label = trim($label);

            if ($content !== null && strcasecmp($label, 'Functions') === 0) {
                $functionItems = preg_split('/(?<=\.)\s*(?=[A-Z])/', trim($content), -1, PREG_SPLIT_NO_EMPTY) ?: [];
                if (count($functionItems) >= 2) {
                    return '<p><strong>Functions:</strong></p><ul>' . implode('', array_map(
                        static fn (string $item): string => '<li>' . htmlspecialchars(trim($item), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</li>',
                        $functionItems,
                    )) . '</ul>';
                }
            }

            if ($content !== null && $this->isFieldLabel($label)) {
                return '<p><strong>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ':</strong>'
                    . (trim($content) !== '' ? ' ' . htmlspecialchars(trim($content), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '')
                    . '</p>';
            }

            if ($this->isFieldLabel($line)) {
                return '<p><strong>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</strong></p>';
            }

            if ($content !== null && $this->isSpecificationLabel($label)) {
                return '<p><strong>' . htmlspecialchars($label, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . ':</strong>'
                    . (trim($content) !== '' ? ' ' . htmlspecialchars(trim($content), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') : '')
                    . '</p>';
            }

            return '<p>' . htmlspecialchars($line, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') . '</p>';
        }, $lines));
    }

    /** @return list<string> */
    private function compactDescriptionLines(string $value): array
    {
        $text = preg_replace_callback(
            $this->fieldPattern(),
            static fn (array $matches): string => "\n" . $matches[1],
            $value,
        ) ?? $value;
        $text = preg_replace('/(?<=[a-z]{4})(?=[A-Z][a-z]{2,})/', "\n", $text) ?? $text;
        $text = preg_replace('/(?<=[A-Za-z0-9])(?=(?:[A-Z][a-z][a-z0-9]*[ \t]+){1,3}[A-Z][a-z0-9]*:)/', "\n", $text) ?? $text;
        $text = preg_replace('/(?<=[\pL])(?=\d{1,3}%)/u', "\n", $text) ?? $text;
        $text = preg_replace('/:[ \t]*(?=(?:[A-Z][a-z][a-z0-9]*[ \t]+){1,3}[A-Z][a-z0-9]*:)/', ":\n", $text) ?? $text;
        $text = preg_replace('/(")(?=[A-Z]{1,4}\s*=)/', "$1\n", $text) ?? $text;
        $text = preg_replace_callback($this->benefitPattern(), static fn (array $matches): string => "\n" . $matches[1], $text) ?? $text;
        $text = preg_replace('/(?<!\s)#(?=[\pL\pN])/u', "\n#", $text) ?? $text;

        return array_values(array_map(
            static fn (string $line): string => trim((string) preg_replace('/:\s*/', ': ', trim($line))),
            preg_split('/\R+/', $text, -1, PREG_SPLIT_NO_EMPTY) ?: [],
        ));
    }

    private function isFieldLabel(string $value): bool
    {
        return in_array(mb_strtolower($value), array_map(mb_strtolower(...), self::FIELD_LABELS), true);
    }

    private function isSpecificationFieldLine(string $value): bool
    {
        [$label] = array_pad(explode(':', $value, 2), 1, '');

        return str_contains($value, ':') && $this->isSpecificationLabel(trim($label));
    }

    private function isSpecificationLabel(string $value): bool
    {
        return preg_match('/^[A-Z][A-Za-z0-9&\/(). -]{0,48}$/', $value) === 1;
    }

    private function fieldPattern(): string
    {
        $labels = self::FIELD_LABELS;
        usort($labels, static fn (string $first, string $second): int => strlen($second) <=> strlen($first));

        return '/(?<![\s,])(' . implode('|', array_map(static fn (string $label): string => preg_quote($label, '/'), $labels)) . ')(?=\s*(?::|[A-Z#])|$)/iu';
    }

    private function benefitPattern(): string
    {
        return '/(' . implode('|', array_map(
            static fn (string $phrase): string => preg_quote($phrase, '/'),
            self::BENEFIT_PHRASES,
        )) . ')/iu';
    }
}
