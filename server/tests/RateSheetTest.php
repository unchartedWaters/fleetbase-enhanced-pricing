<?php

use Fleetbase\EnhancedPricing\Admin\AdminRefused;
use Fleetbase\EnhancedPricing\Admin\RateSheet;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

// Reading and writing a rate table as CSV or XLSX. Pure: no database. Money is a decimal in the
// profile's currency in the file and an integer of minor units in the values.

function sheetTable(): array
{
    return [
        'key'     => 'rows',
        'columns' => [
            ['key' => 'code', 'name' => 'Route', 'type' => 'text', 'required' => true],
            ['key' => 'first', 'type' => 'money', 'required' => true],
            ['key' => 'included', 'type' => 'integer', 'required' => false],
            ['key' => 'rate', 'type' => 'number', 'required' => false],
        ],
        'row_key' => ['code'],
    ];
}

function xlsxBytes(array $rows, ?callable $tweak = null): string
{
    $book  = new Spreadsheet();
    $sheet = $book->getActiveSheet();
    foreach ($rows as $r => $row) {
        foreach ($row as $c => $value) {
            if ($value === null) {
                continue;
            }
            $sheet->setCellValueExplicit([$c + 1, $r + 1], $value, is_string($value) ? DataType::TYPE_STRING : DataType::TYPE_NUMERIC);
        }
    }
    if ($tweak !== null) {
        $tweak($sheet);
    }
    $path = tempnam(sys_get_temp_dir(), 'x');
    (new Xlsx($book))->save($path);
    $bytes = (string) file_get_contents($path);
    unlink($path);

    return $bytes;
}

function parsed(string $name, string $bytes, string $currency = 'USD'): array
{
    return RateSheet::parse($name, $bytes, sheetTable(), $currency);
}

function refusedKeys(Closure $call): array
{
    try {
        $call();
    } catch (AdminRefused $refused) {
        return array_map(static fn (array $p) => str_replace('enhanced-pricing.', '', $p['key']), $refused->body['problems'] ?? []);
    }

    return [];
}

test('a csv is read by header, with money in minor units and blank rows skipped', function () {
    $csv = "\xEF\xBB\xBFRoute,first,included,rate\r\nNorth,12.5,3,1.25\r\n\r\nSouth,0.30,,\r\n";

    expect(parsed('r.csv', $csv))->toBe([
        ['line' => 2, 'values' => ['code' => 'North', 'first' => 1250, 'included' => 3, 'rate' => '1.25']],
        ['line' => 4, 'values' => ['code' => 'South', 'first' => 30]],
    ]);
});

test('money follows the currency decimals', function (string $currency, string $text, ?int $minor) {
    $csv = "code,first\nA,$text\n";

    if ($minor === null) {
        expect(refusedKeys(fn () => parsed('r.csv', $csv, $currency)))->toBe(['store.row-wrong-type']);
    } else {
        expect(parsed('r.csv', $csv, $currency)[0]['values']['first'])->toBe($minor);
    }
})->with([
    'usd'              => ['USD', '12.34', 1234],
    'usd whole'        => ['USD', '12', 1200],
    'usd trailing 0'   => ['USD', '12.500', 1250],
    'usd 3 decimals'   => ['USD', '12.345', null],
    'jpy'              => ['JPY', '500', 500],
    'jpy decimals'     => ['JPY', '5.5', null],
    'kwd'              => ['KWD', '1.234', 1234],
    'grouping'         => ['USD', '"1,234.50"', null],
    'symbol'           => ['USD', '$5', null],
    'negative'         => ['USD', '-5', null],
    'exponent'         => ['USD', '1e3', null],
    'huge'             => ['USD', '1234567890123456789012', null],
]);

test('a header that is a column name works, an unknown or repeated header is refused', function () {
    expect(parsed('r.csv', "route,FIRST\nA,1\n")[0]['values'])->toBe(['code' => 'A', 'first' => 100])
        ->and(refusedKeys(fn () => parsed('r.csv', "code,price\nA,1\n")))->toBe(['admin.import-unknown-column'])
        ->and(refusedKeys(fn () => parsed('r.csv', "code,Route,first\nA,A,1\n")))->toBe(['admin.import-duplicate-column']);
});

test('a value past the last header is refused rather than dropped', function () {
    expect(refusedKeys(fn () => parsed('r.csv', "code,first\nA,1,extra\n")))->toBe(['admin.import-extra-cell']);
});

test('only csv and xlsx files of a sane size and content are read', function (string $name, string $bytes, string $key) {
    expect(refusedKeys(fn () => parsed($name, $bytes)))->toBe([$key]);
})->with([
    'extension'    => ['r.xls', "code,first\nA,1\n", 'admin.import-file-type'],
    'no extension' => ['rows', "code,first\nA,1\n", 'admin.import-file-type'],
    'nul bytes'    => ['r.csv', "code,first\nA,\0\n", 'admin.import-not-text'],
    'not utf8'     => ['r.csv', "code,first\nA\xFF,1\n", 'admin.import-not-text'],
    'fake xlsx'    => ['r.xlsx', "code,first\nA,1\n", 'admin.import-not-text'],
    'empty'        => ['r.csv', '', 'admin.import-empty'],
    'too big'      => ['r.csv', str_repeat('a', RateSheet::MAX_BYTES + 1), 'admin.import-too-large'],
]);

test('at most 2,000 data rows are read', function () {
    $csv = "code,first\n" . implode("\n", array_map(static fn (int $n) => "R$n,1", range(1, 2001)));

    expect(refusedKeys(fn () => parsed('r.csv', $csv)))->toBe(['admin.too-many']);
});

test('an xlsx is read from its first sheet, numbers as exact text', function () {
    $bytes = xlsxBytes([['Route', 'first', 'included', 'rate'], ['North', 0.3, 3, 1.25], ['00123', 12.5, 0.0, null]]);

    expect(parsed('r.xlsx', $bytes))->toBe([
        ['line' => 2, 'values' => ['code' => 'North', 'first' => 30, 'included' => 3, 'rate' => '1.25']],
        ['line' => 3, 'values' => ['code' => '00123', 'first' => 1250, 'included' => 0]],
    ]);
});

test('float noise from a spreadsheet formula result is read at the precision Excel shows', function () {
    $bytes = xlsxBytes([['code', 'first'], ['A', 0.1 + 0.2]]);

    expect(parsed('r.xlsx', $bytes)[0]['values']['first'])->toBe(30);
});

test('an xlsx formula is refused, never evaluated', function () {
    $bytes = xlsxBytes([['code', 'first'], ['A', 1]], static fn ($sheet) => $sheet->setCellValue('B2', '=1+1'));

    expect(refusedKeys(fn () => parsed('r.xlsx', $bytes)))->toBe(['admin.import-formula']);
});

test('an xlsx date is refused, not read as its serial number', function () {
    $bytes = xlsxBytes([['code', 'first'], ['A', 46023]], static fn ($sheet) => $sheet->getStyle('B2')->getNumberFormat()->setFormatCode('yyyy-mm-dd'));

    expect(refusedKeys(fn () => parsed('r.xlsx', $bytes)))->toBe(['admin.import-date-cell']);
});

test('an xlsx whose parts unzip to too much is refused', function () {
    $zip  = new ZipArchive();
    $path = tempnam(sys_get_temp_dir(), 'z');
    $zip->open($path, ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<x/>');
    $zip->addFromString('xl/big.xml', str_repeat('a', RateSheet::MAX_UNZIPPED + 1));
    $zip->close();
    $bytes = (string) file_get_contents($path);
    unlink($path);

    expect(strlen($bytes))->toBeLessThan(RateSheet::MAX_BYTES)
        ->and(refusedKeys(fn () => parsed('r.xlsx', $bytes)))->toBe(['admin.import-too-large']);
});

test('a table is written as csv with money decimals, and read back unchanged', function () {
    $rows = [
        ['code' => 'North', 'first' => 1250, 'included' => 3, 'rate' => '1.25'],
        ['code' => 'a,"b"', 'first' => 5],
        ['code' => "line\nbreak", 'first' => 7],
    ];

    $file = RateSheet::render('csv', sheetTable(), 'USD', $rows);

    expect($file['mime'])->toBe('text/csv')
        ->and($file['bytes'])->toStartWith("\xEF\xBB\xBFcode,first,included,rate\r\nNorth,12.50,3,1.25\r\n")
        ->and(array_column(parsed('rows.csv', $file['bytes']), 'values'))->toBe($rows);
});

test('csv text that a spreadsheet would run as a formula is quoted on export and restored on import', function (string $text) {
    $rows = [['code' => $text, 'first' => 100]];

    $file = RateSheet::render('csv', sheetTable(), 'USD', $rows);

    expect(ltrim(explode("\r\n", $file['bytes'])[1], '"'))->toStartWith("'")
        ->and(parsed('rows.csv', $file['bytes'])[0]['values']['code'])->toBe($text);
})->with(['=1+1', '+1', '-1 north', '@sum', "\tx", "'=already", "''@x"]);

test('csv text that only looks quoted is left alone', function () {
    $rows = [['code' => "'plain", 'first' => 100]];

    $file = RateSheet::render('csv', sheetTable(), 'USD', $rows);

    expect(explode("\r\n", $file['bytes'])[1])->toStartWith("'plain")
        ->and(parsed('rows.csv', $file['bytes'])[0]['values']['code'])->toBe("'plain");
});

test('a table is written as xlsx with text as strings and numbers as numbers, and read back unchanged', function () {
    $rows = [['code' => '=1+1', 'first' => 1250, 'included' => 3, 'rate' => '1.25'], ['code' => '00123', 'first' => 5]];

    $file = RateSheet::render('xlsx', sheetTable(), 'USD', $rows);
    $path = tempnam(sys_get_temp_dir(), 'x');
    file_put_contents($path, $file['bytes']);
    $sheet = (new PhpOffice\PhpSpreadsheet\Reader\Xlsx())->load($path)->getActiveSheet();
    unlink($path);

    expect($file['mime'])->toBe('application/vnd.openxmlformats-officedocument.spreadsheetml.sheet')
        ->and($sheet->getCell('A2')->getDataType())->toBe(DataType::TYPE_STRING)
        ->and($sheet->getCell('B2')->getValue())->toBe(12.5)
        ->and(array_column(parsed('rows.xlsx', $file['bytes']), 'values'))->toBe($rows);
});
