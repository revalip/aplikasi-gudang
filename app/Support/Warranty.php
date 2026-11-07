<?php

namespace App\Support;

/**
 * Warranty is stored as a number of months (12 = 1 year, 24 = 2 years, 0 = no warranty).
 * Legacy text values ("1 Years", "6 Months", "not warranty") are converted by normalize().
 */
class Warranty
{
    public static function normalize(?string $value): ?string
    {
        $value = trim((string) $value);
        if ($value === '') {
            return null;
        }
        if (ctype_digit($value)) {
            return (string) (int) $value;
        }
        $lower = mb_strtolower($value);
        if (str_contains($lower, 'not') || str_contains($lower, 'no warranty') || str_contains($lower, 'tanpa')) {
            return '0';
        }
        if (preg_match('/^(\d+)\s*(year|years|yr|tahun|th)\b/i', $value, $m)) {
            return (string) ((int) $m[1] * 12);
        }
        if (preg_match('/^(\d+)\s*(month|months|mo|bulan|bln)\b/i', $value, $m)) {
            return (string) (int) $m[1];
        }

        return null;
    }

    public static function label($value): string
    {
        $months = static::normalize($value === null ? null : (string) $value);
        if ($months === null) {
            return filled($value) ? (string) $value : '-';
        }

        return $months === '0' ? 'No warranty' : $months . ' ' . ($months === '1' ? 'month' : 'months');
    }
}
