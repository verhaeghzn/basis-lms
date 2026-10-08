<?php

namespace App\Support;

final class LabValues
{
    /**
     * Keys used when a source material stores its mill plate number in properties.
     *
     * @var list<string>
     */
    public const PLATE_NUMBER_KEYS = ['plate_number', 'plate_no', 'plate_nr', 'platenumber'];

    /**
     * @return list<array{kind: string, label: ?string, text?: string, rows?: list<array{label: string, value: string}>, columns?: list<string>, table?: list<array{label: string, cells: list<string>}>}>
     */
    public static function blocks(mixed $value, array $except = []): array
    {
        $value = self::unwrap($value);
        $except = array_map(self::normalizeKey(...), $except);

        if ($value === []) {
            return [];
        }

        $blocks = [];
        $facts = [];

        $flush = function () use (&$facts, &$blocks): void {
            if ($facts === []) {
                return;
            }

            $blocks[] = [
                'kind' => 'facts',
                'label' => null,
                'rows' => $facts,
            ];
            $facts = [];
        };

        foreach ($value as $key => $item) {
            if (in_array(self::normalizeKey((string) $key), $except, true)) {
                continue;
            }

            $label = is_int($key) ? 'Item '.($key + 1) : self::label((string) $key);

            if (is_array($item)) {
                $flush();

                if (self::isRowTable($item)) {
                    $blocks[] = self::tableBlock($label, $item);

                    continue;
                }

                $rows = self::factRows($item);

                if ($rows !== []) {
                    $blocks[] = [
                        'kind' => 'facts',
                        'label' => $label,
                        'rows' => $rows,
                    ];
                }

                continue;
            }

            if ($item === null || $item === '') {
                continue;
            }

            if (is_string($item) && mb_strlen($item) > 80) {
                $flush();
                $blocks[] = [
                    'kind' => 'prose',
                    'label' => $label,
                    'text' => $item,
                ];

                continue;
            }

            $facts[] = [
                'label' => $label,
                'value' => self::scalar($item),
            ];
        }

        $flush();

        return $blocks;
    }

    /**
     * Flat element/value pairs. Returns an empty list when the value is not a simple map.
     *
     * @return list<array{label: string, value: string}>
     */
    public static function entries(mixed $value): array
    {
        $value = self::unwrap($value);
        $rows = [];

        foreach ($value as $key => $item) {
            if (is_array($item) || $item === null || $item === '') {
                continue;
            }

            if (is_string($item) && mb_strlen($item) > 80) {
                continue;
            }

            $rows[] = [
                'label' => is_int($key) ? 'Item '.($key + 1) : self::label((string) $key),
                'value' => self::scalar($item),
            ];
        }

        return $rows;
    }

    public static function property(mixed $value, array $keys): ?string
    {
        $value = self::unwrap($value);
        $wanted = array_map(self::normalizeKey(...), $keys);

        foreach ($value as $key => $item) {
            if (is_array($item) || $item === null || $item === '') {
                continue;
            }

            if (! in_array(self::normalizeKey((string) $key), $wanted, true)) {
                continue;
            }

            return self::scalar($item);
        }

        return null;
    }

    public static function millimetres(mixed $width, mixed $height, mixed $thickness): ?string
    {
        $width = self::dimension($width);
        $height = self::dimension($height);
        $thickness = self::dimension($thickness);

        if ($width && $height && $thickness) {
            return "{$width} × {$height} × {$thickness} mm";
        }

        if ($width && $height) {
            return "{$width} × {$height} mm";
        }

        if ($thickness && ! $width && ! $height) {
            return "{$thickness} mm thick";
        }

        if ($width && $thickness) {
            return "{$width} mm wide, {$thickness} mm thick";
        }

        if ($height && $thickness) {
            return "{$height} mm high, {$thickness} mm thick";
        }

        if ($width) {
            return "{$width} mm wide";
        }

        if ($height) {
            return "{$height} mm high";
        }

        return null;
    }

    /**
     * @param  array<mixed, mixed>  $rows
     * @return array{kind: string, label: string, columns: list<string>, table: list<array{label: string, cells: list<string>}>}
     */
    private static function tableBlock(string $label, array $rows): array
    {
        $columnKeys = [];

        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }

            foreach (array_keys($row) as $key) {
                if (! in_array($key, $columnKeys, true)) {
                    $columnKeys[] = $key;
                }
            }
        }

        $table = [];

        foreach ($rows as $rowKey => $row) {
            if (! is_array($row)) {
                continue;
            }

            $cells = [];

            foreach ($columnKeys as $key) {
                $cell = $row[$key] ?? null;
                $cells[] = ($cell === null || $cell === '' || is_array($cell)) ? '—' : self::scalar($cell);
            }

            $table[] = [
                'label' => is_int($rowKey) ? 'Item '.($rowKey + 1) : self::label((string) $rowKey),
                'cells' => $cells,
            ];
        }

        return [
            'kind' => 'table',
            'label' => $label,
            'columns' => array_map(fn (int|string $key): string => self::label((string) $key), $columnKeys),
            'table' => $table,
        ];
    }

    /**
     * @param  array<mixed, mixed>  $value
     * @return list<array{label: string, value: string}>
     */
    private static function factRows(array $value): array
    {
        $rows = [];

        foreach ($value as $key => $item) {
            if (is_array($item) || $item === null || $item === '') {
                continue;
            }

            $rows[] = [
                'label' => is_int($key) ? 'Item '.($key + 1) : self::label((string) $key),
                'value' => self::scalar($item),
            ];
        }

        return $rows;
    }

    /**
     * @param  array<mixed, mixed>  $value
     */
    private static function isRowTable(array $value): bool
    {
        if ($value === []) {
            return false;
        }

        foreach ($value as $row) {
            if (! is_array($row) || $row === []) {
                return false;
            }

            foreach ($row as $cell) {
                if (is_array($cell)) {
                    return false;
                }
            }
        }

        return true;
    }

    private static function normalizeKey(string $key): string
    {
        $key = mb_strtolower(trim($key));
        $key = preg_replace('/[^a-z0-9]+/', '_', $key) ?? $key;

        return trim($key, '_');
    }

    private static function label(string $key): string
    {
        $key = preg_replace('/_pct$/', ' %', $key) ?? $key;
        $key = preg_replace('/_mm$/', ' mm', $key) ?? $key;

        return str_replace('_', ' ', $key);
    }

    private static function scalar(mixed $item): string
    {
        if (is_bool($item)) {
            return $item ? 'Yes' : 'No';
        }

        if (is_int($item) || is_float($item)) {
            return self::number((float) $item);
        }

        $text = (string) $item;

        if (! str_contains($text, ' ') && str_contains($text, '_')) {
            return str_replace('_', ' ', $text);
        }

        return $text;
    }

    private static function number(float $item): string
    {
        $decimals = abs($item) > 0 && abs($item) < 0.0001 ? 6 : 4;
        $formatted = number_format($item, $decimals, '.', '');

        return rtrim(rtrim($formatted, '0'), '.');
    }

    private static function dimension(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return self::number((float) $value);
    }

    /**
     * @return array<mixed, mixed>
     */
    private static function unwrap(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        return is_array($value) ? $value : [];
    }
}
