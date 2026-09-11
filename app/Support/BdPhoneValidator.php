<?php

namespace App\Support;

class BdPhoneValidator
{
    /**
     * Valid BD mobile prefixes (01X where X is 3-9).
     * Carrier mapping:
     *  013, 014, 015 → Teletalk / Robi / Banglalink
     *  016 → Airtel (Robi)
     *  017 → Grameenphone
     *  018 → Robi
     *  019 → Banglalink
     */
    public static function isValid(string $phone): bool
    {
        // Normalize: strip +88 or 88 prefix
        $clean = preg_replace('/^(?:\+?88)/', '', trim($phone));

        // Must be exactly 11 digits
        if (! preg_match('/^\d{11}$/', $clean)) {
            return false;
        }

        // Must start with 01[3-9]
        if (! preg_match('/^01[3-9]/', $clean)) {
            return false;
        }

        return true;
    }

    /**
     * Detect obviously fake/suspicious numbers:
     *  - All same trailing digits:  01333333333, 01777777777
     *  - Sequential repeating:      01234567890 (fails prefix anyway, but catch others)
     *  - Too many repeats: 8+ of same digit in last 8 chars
     */
    public static function isFake(string $phone): bool
    {
        $clean = preg_replace('/^(?:\+?88)/', '', trim($phone));

        // Strip the 01X prefix — check last 8 digits only
        $last8 = substr($clean, 3, 8);

        // All 8 trailing digits are the same  (e.g. 01333333333)
        if (preg_match('/^(.)\1{7}$/', $last8)) {
            return true;
        }

        // 7+ of the same digit in last 8
        $counts = array_count_values(str_split($last8));
        if (max($counts) >= 7) {
            return true;
        }

        // Sequential ascending: 12345678, 23456789, 34567890
        $ascending = true;
        for ($i = 0; $i < 7; $i++) {
            if ((int)$last8[$i + 1] !== ((int)$last8[$i] + 1) % 10) {
                $ascending = false;
                break;
            }
        }
        if ($ascending) {
            return true;
        }

        // Sequential descending: 87654321
        $descending = true;
        for ($i = 0; $i < 7; $i++) {
            if ((int)$last8[$i + 1] !== ((int)$last8[$i] - 1 + 10) % 10) {
                $descending = false;
                break;
            }
        }
        if ($descending) {
            return true;
        }

        return false;
    }

    public static function normalize(string $phone): string
    {
        return preg_replace('/^(?:\+?88)/', '', trim($phone));
    }
}
