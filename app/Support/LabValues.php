<?php

namespace App\Support;

final class LabValues
{
    /**
     * @return array<string, string>
     */
    public static function pairs(mixed $value): array
    {
        $value = self::unwrap($value);

        if ($value === []) {
            return [];
        }

        $pairs = [];

        foreach ($value as $key => $item) {
            $label = is_int($key) ? 'Item '.($key + 1) : str_replace('_', ' ', (string) $key);
            $pairs[$label] = self::stringify($item);
        }

        return $pairs;
    }

    public static function millimetres(mixed $width, mixed $height, mixed $thickness): ?string
    {
        if ($width === null && $height === null && $thickness === null) {
            return null;
        }

        $part = function (mixed $value): string {
            if ($value === null || $value === '') {
                return '—';
            }

            $formatted = number_format((float) $value, 2, '.', '');

            return rtrim(rtrim($formatted, '0'), '.');
        };

        return $part($width).' × '.$part($height).' × '.$part($thickness).' mm';
    }

    /**
     * @return array<string, mixed>
     */
    private static function unwrap(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }

    private static function stringify(mixed $item): string
    {
        if ($item === null || $item === '') {
            return '—';
        }

        if (is_bool($item)) {
            return $item ? 'Yes' : 'No';
        }

        if (is_int($item) || is_float($item)) {
            $formatted = number_format((float) $item, 4, '.', '');

            return rtrim(rtrim($formatted, '0'), '.');
        }

        if (is_string($item)) {
            return $item;
        }

        return json_encode($item, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '—';
    }
}
