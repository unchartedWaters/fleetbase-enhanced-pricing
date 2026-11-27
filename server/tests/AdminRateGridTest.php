<?php

use Fleetbase\EnhancedPricing\Admin\AdminRefused;
use Fleetbase\EnhancedPricing\Admin\RateGrid;
use Fleetbase\EnhancedPricing\Models\RateCard;
use Fleetbase\EnhancedPricing\Models\RateCardRow;
use Fleetbase\EnhancedPricing\Tests\Support\Database;
use Fleetbase\EnhancedPricing\Tests\Support\Stores;

// The console's rate table grid: save, import and export a customer's rows of one rate table.

beforeEach(function () {
    Database::fresh();
    Database::seed();
});

function grid(string $company = 'a'): RateGrid
{
    return new RateGrid(Database::company($company), static fn () => new DateTimeImmutable(Stores::NOW));
}

function gridRows(string ...$codes): array
{
    return array_map(static fn (string $code, int $n) => ['line' => $n + 1, 'values' => ['code' => $code, 'first' => 1000, 'second' => 900, 'third' => 800]], $codes, array_keys($codes));
}

function problemsOf(Closure $call): array
{
    try {
        $call();
    } catch (AdminRefused $refused) {
        return [$refused->body['refused'], array_map(static fn (array $p) => [$p['path'], str_replace('enhanced-pricing.', '', $p['key'])], $refused->body['problems'] ?? [])];
    }

    return ['accepted', []];
}

function storedCodes(?string $label = null): array
{
    $query = RateCardRow::query();
    if ($label !== null) {
        $query->where('rate_card_uuid', RateCard::query()->where('label', $label)->value('uuid'));
    }
    $codes = array_map(static fn (RateCardRow $row) => $row->values['code'], $query->get()->all());
    sort($codes);

    return $codes;
}

test('saving creates the card by its label and stores the rows', function () {
    $customer = Stores::customer('a');

    $saved = grid()->save($customer->uuid, 'North', 'rows', gridRows('R1', 'R2'), false, null);

    expect($saved['rows'])->toBe(2)->and(storedCodes('North'))->toBe(['R1', 'R2'])->and($saved['token'])->toBeString();
});

test('text is trimmed and blank rows are skipped', function () {
    $customer = Stores::customer('a');
    $rows     = [['line' => 1, 'values' => ['code' => ' R1 ', 'first' => 1, 'second' => 1, 'third' => 1]], ['line' => 2, 'values' => ['code' => '', 'first' => null]]];

    grid()->save($customer->uuid, 'North', 'rows', $rows, false, null);

    expect(storedCodes())->toBe(['R1']);
});

test('a problem is reported at the line the person sees, whatever rows were skipped', function () {
    $customer = Stores::customer('a');
    $rows     = [['line' => 1, 'values' => []], ['line' => 5, 'values' => ['code' => 'R1', 'first' => 'x', 'second' => 1, 'third' => 1]]];

    expect(problemsOf(fn () => grid()->save($customer->uuid, 'North', 'rows', $rows, false, null)))
        ->toBe(['invalid', [['5.values.first', 'store.row-wrong-type']]])
        ->and(storedCodes())->toBe([]);
});

test('replacing needs the token of the rows the grid was loaded with', function () {
    $customer = Stores::customer('a');
    $first    = grid()->save($customer->uuid, 'North', 'rows', gridRows('R1', 'R2'), false, null);
    grid()->save($customer->uuid, 'North', 'rows', gridRows('R3'), false, null);

    expect(problemsOf(fn () => grid()->save($customer->uuid, 'North', 'rows', gridRows('R1'), true, $first['token'])))
        ->toBe(['rows-changed', [['', 'admin.rows-changed']]])
        ->and(problemsOf(fn () => grid()->save($customer->uuid, 'North', 'rows', gridRows('R1'), true, null))[0])->toBe('rows-changed')
        ->and(storedCodes())->toBe(['R1', 'R2', 'R3']);

    $current = RateGrid::token(RateCardRow::query()->get()->all());
    grid()->save($customer->uuid, 'North', 'rows', gridRows('R1'), true, $current);

    expect(storedCodes())->toBe(['R1']);
});

test('a table with no rows can be replaced without a token', function () {
    $customer = Stores::customer('a');

    grid()->save($customer->uuid, 'North', 'rows', gridRows('R1'), true, null);

    expect(storedCodes())->toBe(['R1']);
});

test('an empty replace from a file is refused, an empty grid save is allowed', function () {
    $customer = Stores::customer('a');
    grid()->save($customer->uuid, 'North', 'rows', gridRows('R1'), false, null);
    $token = RateGrid::token(RateCardRow::query()->get()->all());

    expect(problemsOf(fn () => grid()->import($customer->uuid, 'North', 'rows', 'r.csv', base64_encode("code,first\n"), true))[0])->toBe('invalid')
        ->and(storedCodes())->toBe(['R1']);

    grid()->save($customer->uuid, 'North', 'rows', [], true, $token);

    expect(storedCodes())->toBe([]);
});

test('the card label is required and at most 100 characters', function (string $label) {
    $customer = Stores::customer('a');

    expect(problemsOf(fn () => grid()->save($customer->uuid, $label, 'rows', gridRows('R1'), false, null))[0])->toBe('invalid');
})->with(['empty' => [''], 'blank' => ['  '], 'long' => [str_repeat('a', 101)]]);

test('an unknown table is refused', function () {
    $customer = Stores::customer('a');

    expect(problemsOf(fn () => grid()->save($customer->uuid, 'North', 'nope', gridRows('R1'), false, null)))
        ->toBe(['invalid', [['table_key', 'store.row-unknown-table']]]);
});

test('another company\'s customer is not found', function () {
    $customer = Stores::customer('a');

    expect(problemsOf(fn () => grid('b')->save($customer->uuid, 'North', 'rows', gridRows('R1'), false, null))[0])->toBe('not-found');
});

test('an import merges by default and replaces on request', function () {
    $customer = Stores::customer('a');
    grid()->save($customer->uuid, 'North', 'rows', gridRows('R1', 'R2'), false, null);
    $csv = "code,first,second,third\nR2,1.00,2.00,3.00\nR3,4,5,6\n";

    $merged = grid()->import($customer->uuid, 'North', 'rows', 'r.csv', base64_encode($csv), false);

    expect($merged['rows'])->toBe(2)->and(storedCodes())->toBe(['R1', 'R2', 'R3'])
        ->and(RateCardRow::query()->where('row_key', RateCardRow::query()->get()->first(fn ($r) => $r->values['code'] === 'R2')->row_key)->first()->values['first'])->toBe(100);

    grid()->import($customer->uuid, 'North', 'rows', 'r.csv', base64_encode($csv), true);

    expect(storedCodes())->toBe(['R2', 'R3']);
});

test('a row the table rejects is reported at its row', function () {
    $customer = Stores::customer('a');
    $csv      = "code,first,second\nR1,1,2\n";

    expect(problemsOf(fn () => grid()->import($customer->uuid, 'North', 'rows', 'r.csv', base64_encode($csv), false)))
        ->toBe(['invalid', [['2.values.third', 'store.row-missing-value']]]);
});

test('a bad import row refuses the file at its row and writes nothing', function () {
    $customer = Stores::customer('a');
    $csv      = "code,first,second,third\nR1,1,2,3\nR2,1.234,2,3\n";

    expect(problemsOf(fn () => grid()->import($customer->uuid, 'North', 'rows', 'r.csv', base64_encode($csv), false)))
        ->toBe(['invalid', [['3.values.first', 'store.row-wrong-type']]])
        ->and(storedCodes())->toBe([]);
});

test('an import that is not base64 is refused', function () {
    $customer = Stores::customer('a');

    expect(problemsOf(fn () => grid()->import($customer->uuid, 'North', 'rows', 'r.csv', '***', false))[0])->toBe('invalid');
});

test('a table exports and imports back to the same rows', function (string $format) {
    $customer = Stores::customer('a');
    $rows     = [['line' => 1, 'values' => ['code' => '=cmd', 'first' => 1250, 'second' => 1, 'third' => 2, 'separate_price' => 99]], ...gridRows('R2')];
    grid()->save($customer->uuid, 'North', 'rows', $rows, false, null);
    $card = RateCard::query()->first();

    $file = grid()->export($customer->uuid, $card->uuid, 'rows', $format);
    expect($file['filename'])->toBe("rows-North.$format");

    grid()->import($customer->uuid, 'South', 'rows', $file['filename'], $file['content'], false);

    expect(storedCodes('South'))->toBe(storedCodes('North'));
})->with(['csv', 'xlsx']);

test('a card of another customer cannot be exported', function () {
    $profile = Stores::profile('a');
    $mine    = Stores::customer('a', $profile);
    $other   = Stores::for('a')->saveCustomer(Database::customer('a', 2), $profile);
    $card    = Stores::card('a', $other, 'Theirs');

    expect(problemsOf(fn () => grid()->export($mine->uuid, $card->uuid, 'rows', 'csv'))[0])->toBe('not-found');
});

test('an export format other than csv or xlsx is refused', function () {
    $customer = Stores::customer('a');
    $card     = Stores::card('a', $customer);

    expect(problemsOf(fn () => grid()->export($customer->uuid, $card->uuid, 'rows', 'pdf'))[0])->toBe('invalid');
});
