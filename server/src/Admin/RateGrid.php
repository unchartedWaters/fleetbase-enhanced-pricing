<?php

namespace Fleetbase\EnhancedPricing\Admin;

use Fleetbase\EnhancedPricing\Models\Customer;
use Fleetbase\EnhancedPricing\Models\RateCard;
use Fleetbase\EnhancedPricing\Models\RateCardRow;
use Fleetbase\EnhancedPricing\Profile\Problem;
use Fleetbase\EnhancedPricing\Store\PricingStore;
use Fleetbase\EnhancedPricing\Store\RowCheck;
use Fleetbase\EnhancedPricing\Store\StoreContext;
use Fleetbase\EnhancedPricing\Store\StoreRefused;

/**
 * The console's rate table grid: one customer's rows of one rate table, saved from the grid or a
 * file, and written out as a file. Rows are checked by the store, the same rules pricing uses, so
 * a refused save names the grid or file row at fault and writes nothing.
 *
 * A grid save replaces the table's rows, so it must come with the token of the rows it was loaded
 * with: a save from a grid another admin has since changed is refused (rows-changed), not allowed
 * to delete their rows.
 *
 * shortcut: the token is checked before the store's transaction, so two saves within the same
 * moment can both pass; move the check inside the profile lock if that ever matters.
 */
final class RateGrid
{
    public const MAX_LABEL = 100;

    private readonly StoreContext $context;

    private readonly PricingStore $store;

    /**
     * @param (callable(): \DateTimeImmutable)|null $clock
     */
    public function __construct(private readonly string $company, ?callable $clock = null)
    {
        $this->context = new StoreContext($company, $clock);
        $this->store   = new PricingStore($company, $clock);
    }

    /**
     * A fingerprint of a table's rows on a card: which rows there are and when each last changed.
     *
     * @param list<RateCardRow> $rows
     */
    public static function token(array $rows): string
    {
        $parts = array_map(static fn (RateCardRow $row): string => $row->uuid . '|' . $row->updated_at, $rows);
        sort($parts);

        return hash('sha256', implode("\n", $parts));
    }

    /**
     * @param list<array<mixed>> $rows
     *
     * @return array{rows: int, token: string}
     */
    public function save(string $customerUuid, string $label, string $tableKey, array $rows, bool $replace, ?string $token): array
    {
        return $this->apply($customerUuid, $label, $tableKey, $rows, $replace, $token, true, true);
    }

    /**
     * @return array{rows: int, token: string}
     */
    public function import(string $customerUuid, string $label, string $tableKey, string $filename, string $content, bool $replace): array
    {
        $bytes = base64_decode($content, true);
        if ($bytes === false) {
            throw AdminRefused::invalid('import-not-text');
        }
        [, $table, $currency] = $this->target($customerUuid, $tableKey);
        $rows                 = RateSheet::parse($filename, $bytes, $table, $currency);

        // A file replaces on request only, and never with nothing.
        return $this->apply($customerUuid, $label, $tableKey, $rows, $replace, null, false, false);
    }

    /**
     * @return array{filename: string, mime: string, content: string}
     */
    public function export(string $customerUuid, string $cardUuid, string $tableKey, string $format): array
    {
        if (in_array($format, ['csv', 'xlsx'], true) === false) {
            throw AdminRefused::invalid('field-invalid', [], 'format');
        }
        [$customer, $table, $currency] = $this->target($customerUuid, $tableKey);
        $card                          = RateCard::firstOf(RateCard::ofCompany($this->company)->whereKey($cardUuid));
        if ($card === null || $card->pricing_customer_uuid !== $customer->uuid) {
            throw AdminRefused::notFound();
        }
        $rows = $this->tableRows($card, $tableKey);
        usort($rows, static fn (RateCardRow $left, RateCardRow $right): int => json_encode($left->key_values) <=> json_encode($right->key_values));
        $file = RateSheet::render($format, $table, $currency, array_map(
            static fn (RateCardRow $row): array => $row->values ?? [],
            $rows,
        ));

        return [
            'filename' => preg_replace('/[^A-Za-z0-9._-]+/', '_', "$tableKey-{$card->label}") . ".$format",
            'mime'     => $file['mime'],
            'content'  => base64_encode($file['bytes']),
        ];
    }

    /**
     * @param list<array<mixed>> $rows
     *
     * @return array{rows: int, token: string}
     */
    private function apply(
        string $customerUuid,
        string $label,
        string $tableKey,
        array $rows,
        bool $replace,
        ?string $token,
        bool $checkToken,
        bool $allowEmpty,
    ): array {
        $label = trim($label);
        if ($label === '' || mb_strlen($label) > self::MAX_LABEL) {
            throw AdminRefused::invalid('field-invalid', [], 'card');
        }
        $this->target($customerUuid, $tableKey);
        [$send, $lines] = $this->normalize($rows);
        if ($replace && $send === [] && $allowEmpty === false) {
            throw AdminRefused::invalid('replace-empty');
        }

        try {
            return $this->context->transaction(function () use ($customerUuid, $label, $tableKey, $send, $replace, $token, $checkToken): array {
                $customer = $this->customer($customerUuid);
                $card     = RateCard::firstOf(
                    RateCard::ofCompany($this->company)->where('pricing_customer_uuid', $customer->uuid)->where('label', $label),
                ) ?? $this->store->saveCard($customer, $label);
                if ($replace && $checkToken) {
                    $existing = $this->tableRows($card, $tableKey);
                    if ($existing !== [] && $token !== self::token($existing)) {
                        throw new AdminRefused(409, ['refused'  => 'rows-changed', 'problems' => [(new Problem('', 'enhanced-pricing.admin.rows-changed'))->toArray()]]);
                    }
                }
                $this->store->saveRows($card, $tableKey, $send, $replace);

                return ['rows' => count($send), 'token' => self::token($this->tableRows($card, $tableKey))];
            });
        } catch (StoreRefused $refused) {
            throw AdminRefused::fromStore(new StoreRefused(array_map(static fn (Problem $problem): Problem => new Problem(preg_replace_callback('/^(\d+)(?=\.|$)/', static fn (array $m): string => (string) ($lines[(int) $m[1]] ?? $m[1]), $problem->path) ?? $problem->path, $problem->key, $problem->params), $refused->problems)));
        }
    }

    /**
     * The rows to store: text trimmed, blank cells and blank rows dropped, and each row's line in
     * the grid or file, so a problem found at row n of what was sent names the line the person sees.
     *
     * @param list<array<mixed>> $rows
     *
     * @return array{0: list<array{values: array<mixed>}>, 1: list<int>}
     */
    private function normalize(array $rows): array
    {
        $send  = [];
        $lines = [];
        foreach ($rows as $index => $row) {
            if (is_array($row['values'] ?? null) === false) {
                throw AdminRefused::invalid('field-invalid', [], "$index.values");
            }
            $values = [];
            foreach ($row['values'] as $key => $value) {
                $value = is_string($value) ? trim($value) : $value;
                if ($value !== null && $value !== '') {
                    $values[(string) $key] = $value;
                }
            }
            if ($values !== []) {
                $send[]  = ['values' => $values];
                $lines[] = is_int($row['line'] ?? null) ? $row['line'] : $index + 1;
            }
        }

        return [$send, $lines];
    }

    private function customer(string $uuid): Customer
    {
        return Customer::firstOf(Customer::ofCompany($this->company)->whereKey($uuid)) ?? throw AdminRefused::notFound();
    }

    /**
     * @return array{0: Customer, 1: array<string, mixed>, 2: string} the customer, the rate table of its profile and the profile's currency
     */
    private function target(string $customerUuid, string $tableKey): array
    {
        $customer = $this->customer($customerUuid);
        $profile  = $customer->profile() ?? throw AdminRefused::notFound();
        $table    = RowCheck::table((new ProfileState($this->context, $profile))->normalized, $tableKey);
        if ($table === null) {
            throw AdminRefused::problems([StoreRefused::problem('row-unknown-table', ['table' => $tableKey], 'table_key')]);
        }

        return [$customer, $table, $profile->currency];
    }

    /**
     * @return list<RateCardRow>
     */
    private function tableRows(RateCard $card, string $tableKey): array
    {
        return RateCardRow::listOf(
            RateCardRow::ofCompany($this->company)->where('rate_card_uuid', $card->uuid)->where('table_key', $tableKey),
        );
    }
}
