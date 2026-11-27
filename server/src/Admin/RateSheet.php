<?php

namespace Fleetbase\EnhancedPricing\Admin;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Fleetbase\EnhancedPricing\Store\StoreRefused;
use Fleetbase\EnhancedPricing\Support\MinorUnits;
use PhpOffice\PhpSpreadsheet\Cell\Coordinate;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Reader\IReadFilter;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx as Reader;
use PhpOffice\PhpSpreadsheet\Shared\Date;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

/**
 * A rate table as a CSV or XLSX file, and back. Pure: no database. In a file, money is a decimal in
 * the profile's currency and the header row names the columns; in values it is an integer of minor
 * units. Reading refuses what it can't read exactly (formulas, dates, grouped or over-precise
 * money) instead of guessing; whether a row fits the table is the store's check, not this one's.
 *
 * shortcut: CSV is comma-separated UTF-8 only; add delimiter sniffing if staff exports from a
 * semicolon locale.
 */
final class RateSheet
{
    public const MAX_BYTES = 2097152;

    public const MAX_UNZIPPED = 20000000;

    public const MAX_ROWS = 2000;

    public const MAX_COLUMNS = 30;

    private const TRIGGER = '/^\'*[=+\-@\t\r]/';

    /**
     * @param array<string, mixed> $table a normalized rate table
     *
     * @return list<array{line: int, values: array<string, mixed>}> the non-blank data rows, with
     *                                                              their row number in the file
     *
     * @throws AdminRefused
     */
    public static function parse(string $filename, string $bytes, array $table, string $currency): array
    {
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if (in_array($extension, ['csv', 'xlsx'], true) === false) {
            throw AdminRefused::invalid('import-file-type');
        }
        if (strlen($bytes) > self::MAX_BYTES) {
            throw AdminRefused::invalid('import-too-large', ['max' => intdiv(self::MAX_BYTES, 1048576) . ' MB']);
        }
        $cells = $extension === 'csv' ? self::csvCells($bytes) : self::xlsxCells($bytes);
        if ($cells === [] || self::blank($cells[0])) {
            throw AdminRefused::invalid('import-empty');
        }

        $columns = self::columns($table);
        $header  = $cells[0];
        while ($header !== [] && ($header[array_key_last($header)] ?? '') === '') {
            array_pop($header);
        }
        $map     = self::headers($header, $columns);
        $rows    = [];
        $found   = [];
        foreach (array_slice($cells, 1, null, true) as $index => $row) {
            if (self::blank($row)) {
                continue;
            }
            $line = $index + 1;
            if (count($rows) >= self::MAX_ROWS) {
                throw AdminRefused::invalid('too-many', ['max' => self::MAX_ROWS]);
            }
            if (self::blank(array_slice($row, count($map))) === false) {
                throw AdminRefused::invalid('import-extra-cell', ['line' => $line]);
            }
            $values = [];
            foreach ($map as $position => $key) {
                $text = $row[$position] ?? null;
                if ($text === null || $text === '') {
                    continue;
                }
                $value = self::value($text, $columns[$key]['type'], $currency);
                if ($value === null) {
                    $found[] = StoreRefused::problem('row-wrong-type', ['column' => $key, 'type' => $columns[$key]['type']], "$line.values.$key");
                    continue;
                }
                $values[$key] = $value;
            }
            $rows[] = ['line' => $line, 'values' => $values];
        }
        if ($found !== []) {
            throw AdminRefused::problems($found);
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $table a normalized rate table
     * @param list<array<mixed>>   $rows  values, money in minor units
     *
     * @return array{mime: string, bytes: string}
     */
    public static function render(string $format, array $table, string $currency, array $rows): array
    {
        $columns = self::columns($table);
        $keys    = array_keys($columns);
        if ($format === 'xlsx') {
            return ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'bytes' => self::xlsx($columns, $currency, $rows)];
        }
        $handle = self::memory();
        fputcsv($handle, array_map(static fn (string $key): string => self::guard($key), $keys), ',', '"', '', "\r\n");
        foreach ($rows as $row) {
            fputcsv($handle, array_map(
                static fn (string $key): string => self::guard(self::text($columns[$key]['type'], $row[$key] ?? null, $currency), $columns[$key]['type'] === 'text'),
                $keys,
            ), ',', '"', '', "\r\n");
        }
        rewind($handle);

        return ['mime' => 'text/csv', 'bytes' => "\xEF\xBB\xBF" . stream_get_contents($handle)];
    }

    /**
     * @param array<string, array{type: string, name: string|null}> $columns
     * @param list<array<mixed>>                                    $rows
     */
    private static function xlsx(array $columns, string $currency, array $rows): string
    {
        $book  = new Spreadsheet();
        $sheet = $book->getActiveSheet();
        $keys  = array_keys($columns);
        foreach ($keys as $c => $key) {
            $sheet->setCellValueExplicit([$c + 1, 1], $key, DataType::TYPE_STRING);
        }
        foreach ($rows as $r => $row) {
            foreach ($keys as $c => $key) {
                $value = $row[$key] ?? null;
                if ($value === null || $value === '') {
                    continue;
                }
                $type = $columns[$key]['type'];
                // Text is always a string cell, so "=1+1" is never a formula; numbers stay numbers.
                $sheet->setCellValueExplicit(
                    [$c + 1, $r + 2],
                    $type === 'text' ? self::text($type, $value, $currency) : (float) self::text($type, $value, $currency),
                    $type === 'text' ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC,
                );
            }
        }
        $path = tempnam(sys_get_temp_dir(), 'ratesheet');
        try {
            (new Xlsx($book))->save($path);

            return (string) file_get_contents($path);
        } finally {
            @unlink($path);
        }
    }

    /**
     * @return resource
     */
    private static function memory()
    {
        return fopen('php://temp', 'r+') ?: throw new \RuntimeException('php://temp can not be opened');
    }

    private static function text(string $type, mixed $value, string $currency): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if ($type === 'money' && is_int($value)) {
            return MinorUnits::decimal($value, $currency);
        }

        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * Text a spreadsheet could run as a formula gets a leading quote; reading strips exactly one.
     */
    private static function guard(string $text, bool $isText = true): string
    {
        return $isText && preg_match(self::TRIGGER, $text) === 1 ? "'" . $text : $text;
    }

    /**
     * @return list<list<string|null>> rows of trimmed cells, row 1 first
     *
     * @throws AdminRefused
     */
    private static function csvCells(string $bytes): array
    {
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $bytes = substr($bytes, 3);
        }
        if (str_contains($bytes, "\0") || mb_check_encoding($bytes, 'UTF-8') === false) {
            throw AdminRefused::invalid('import-not-text');
        }
        $handle = self::memory();
        fwrite($handle, $bytes);
        rewind($handle);
        $rows = [];
        // One more row than allowed (header, MAX_ROWS, one to detect an overflow), then stop.
        while (count($rows) <= self::MAX_ROWS + 1 && ($record = fgetcsv($handle, 0, ',', '"', '')) !== false) {
            $rows[] = array_map(static fn (?string $cell): ?string => $cell === null ? null : self::unguard(trim($cell)), $record);
        }

        return $rows;
    }

    private static function unguard(string $text): string
    {
        return str_starts_with($text, "'") && preg_match(self::TRIGGER, substr($text, 1)) === 1 ? substr($text, 1) : $text;
    }

    /**
     * @return list<list<string|null>>
     *
     * @throws AdminRefused
     */
    private static function xlsxCells(string $bytes): array
    {
        if (str_starts_with($bytes, "PK\x03\x04") === false) {
            throw AdminRefused::invalid('import-not-text');
        }
        $path = tempnam(sys_get_temp_dir(), 'ratesheet');
        try {
            file_put_contents($path, $bytes);
            self::checkZip($path);
            try {
                $reader = new Reader();
                $reader->setReadEmptyCells(false);
                $names = $reader->listWorksheetNames($path);
                $reader->setLoadSheetsOnly([$names[0] ?? 0]);
                $reader->setReadFilter(new class implements IReadFilter {
                    public function readCell($columnAddress, $row, $worksheetName = ''): bool
                    {
                        return $row <= RateSheet::MAX_ROWS + 2 && Coordinate::columnIndexFromString($columnAddress) <= RateSheet::MAX_COLUMNS + 1;
                    }
                });
                $sheet = $reader->load($path)->getActiveSheet();
            } catch (\Throwable) {
                throw AdminRefused::invalid('import-not-text');
            }
            $rows = [];
            foreach ($sheet->getRowIterator() as $row) {
                $cells = $row->getCellIterator();
                $cells->setIterateOnlyExistingCells(true);
                foreach ($cells as $cell) {
                    if ($cell->isFormula()) {
                        throw AdminRefused::invalid('import-formula', ['cell' => $cell->getCoordinate()]);
                    }
                    $value = $cell->getValue();
                    if ((is_int($value) || is_float($value)) && Date::isDateTime($cell)) {
                        throw AdminRefused::invalid('import-date-cell', ['cell' => $cell->getCoordinate()]);
                    }
                    $rows[$row->getRowIndex() - 1][Coordinate::columnIndexFromString($cell->getColumn()) - 1] = self::cellText($value);
                }
            }
            $table = [];
            for ($i = 0, $last = $rows === [] ? -1 : max(array_keys($rows)); $i <= $last; $i++) {
                $row  = $rows[$i] ?? [];
                $line = [];
                for ($c = 0, $end = $row === [] ? -1 : max(array_keys($row)); $c <= $end; $c++) {
                    $line[] = $row[$c] ?? null;
                }
                $table[] = $line;
            }

            return $table;
        } finally {
            @unlink($path);
        }
    }

    private static function cellText(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            // Excel keeps 15 significant digits, so 0.1+0.2 is 0.3 to the person who typed it.
            return floor($value) === $value && abs($value) < 1e15 ? (string) (int) $value : sprintf('%.15G', $value);
        }

        return is_scalar($value) || $value instanceof \Stringable ? trim((string) $value) : null;
    }

    /**
     * The sizes a zip declares can be forged, so this only stops the plain bombs; the file size cap
     * and the read filter bound the rest.
     *
     * @throws AdminRefused
     */
    private static function checkZip(string $path): void
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true || $zip->numFiles > 200) {
            throw AdminRefused::invalid('import-not-text');
        }
        $total = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $total += (int) ($zip->statIndex($i)['size'] ?? 0);
        }
        $zip->close();
        if ($total > self::MAX_UNZIPPED) {
            throw AdminRefused::invalid('import-too-large', ['max' => intdiv(self::MAX_BYTES, 1048576) . ' MB']);
        }
    }

    private static function value(string $text, string $type, string $currency): int|string|null
    {
        return match ($type) {
            'text'    => $text,
            'money'   => self::money($text, MinorUnits::exponent($currency)),
            'integer' => preg_match('/^\d{1,9}$/', $text) === 1 ? (int) $text : null,
            default   => $text,
        };
    }

    private static function money(string $text, int $exponent): ?int
    {
        if (preg_match('/^\d{1,12}(\.\d+)?$/', $text) !== 1) {
            return null;
        }
        try {
            return BigDecimal::of($text)->toScale($exponent, RoundingMode::UNNECESSARY)->getUnscaledValue()->toInt();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * @param list<string|null>                                     $header
     * @param array<string, array{type: string, name: string|null}> $columns
     *
     * @return list<string> the column key at each position
     *
     * @throws AdminRefused
     */
    private static function headers(array $header, array $columns): array
    {
        $byName = [];
        foreach ($columns as $key => $column) {
            $byName[mb_strtolower($key)] = $key;
            if ($column['name'] !== null && $column['name'] !== '') {
                $byName[mb_strtolower($column['name'])] = $key;
            }
        }
        $map = [];
        foreach ($header as $position => $name) {
            $name = (string) $name;
            if (count($map) >= self::MAX_COLUMNS) {
                throw AdminRefused::invalid('too-many', ['max' => self::MAX_COLUMNS]);
            }
            $key = $byName[mb_strtolower($name)] ?? null;
            if ($key === null) {
                throw AdminRefused::invalid('import-unknown-column', ['column' => $name]);
            }
            if (in_array($key, $map, true)) {
                throw AdminRefused::invalid('import-duplicate-column', ['column' => $name]);
            }
            $map[] = $key;
        }

        return $map;
    }

    /**
     * @param array<string, mixed> $table
     *
     * @return array<string, array{type: string, name: string|null}>
     */
    private static function columns(array $table): array
    {
        $columns = [];
        foreach (is_array($table['columns'] ?? null) ? $table['columns'] : [] as $column) {
            if (is_array($column) && is_string($column['key'] ?? null) && is_string($column['type'] ?? null)) {
                $columns[$column['key']] = ['type' => $column['type'], 'name' => is_string($column['name'] ?? null) ? $column['name'] : null];
            }
        }

        return $columns;
    }

    /**
     * @param array<int, string|null> $row
     */
    private static function blank(array $row): bool
    {
        foreach ($row as $cell) {
            if ($cell !== null && $cell !== '') {
                return false;
            }
        }

        return true;
    }
}
