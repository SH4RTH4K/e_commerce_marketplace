<?php

namespace App\Services\Dropshipping;

class SupplierProductImageResolver
{
    /**
     * @return list<string>
     */
    public function resolve(array $payload): array
    {
        $images = [];

        $this->collect($payload, $images);

        return array_values(array_unique($images));
    }

    /**
     * @param  list<string>  $images
     */
    private function collect(mixed $value, array &$images, bool $imageContainer = false, int $depth = 0): void
    {
        if ($depth > 8) {
            return;
        }

        if (is_string($value) && $imageContainer) {
            $decoded = $this->decodedArray($value);
            if ($decoded !== null) {
                $this->collect($decoded, $images, true, $depth + 1);

                return;
            }

            $candidate = trim($value);
            if ($this->isUsableCandidate($candidate)) {
                $images[] = $candidate;
            }

            return;
        }

        if (! is_array($value)) {
            return;
        }

        foreach ($value as $key => $item) {
            $normalizedKey = is_string($key) ? strtolower($key) : '';
            $isDirectImage = in_array($normalizedKey, [
                'thumbnail_img', 'thumbnail', 'image_url', 'product_image', 'image',
            ], true);
            $isImageContainer = $imageContainer || in_array($normalizedKey, [
                'images', 'product_images', 'gallery', 'media', 'photos',
            ], true);
            $isContainerImageValue = $imageContainer && in_array($normalizedKey, [
                'url', 'image', 'src', 'path',
            ], true);

            if (($isDirectImage || $isContainerImageValue) && is_string($item)) {
                $candidate = trim($item);
                if ($this->isUsableCandidate($candidate)) {
                    $images[] = $candidate;
                }
            }

            if (is_array($item) || ($isImageContainer && is_string($item))) {
                $this->collect($item, $images, $isImageContainer, $depth + 1);
            }
        }
    }

    /** @return array<mixed>|null */
    private function decodedArray(string $value): ?array
    {
        $trimmed = trim($value);
        if (! str_starts_with($trimmed, '[') && ! str_starts_with($trimmed, '{')) {
            return null;
        }

        $decoded = json_decode($trimmed, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function isUsableCandidate(string $candidate): bool
    {
        return $candidate !== ''
            && (str_starts_with($candidate, 'https://')
                || str_starts_with($candidate, 'http://')
                || str_starts_with($candidate, '/'));
    }
}
