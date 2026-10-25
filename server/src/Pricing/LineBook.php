<?php

namespace Fleetbase\EnhancedPricing\Pricing;

/**
 * The lines made so far. Lines with the same product, name and unit amount are kept as one line
 * with a quantity, a $0 line is left off, and no line, total or number of lines may pass its
 * cap. Rules ask it about the lines before them; each question is answered from an index, so an
 * order with many lines still prices quickly.
 */
final class LineBook
{
    public const MAX_AMOUNT = 100000000;

    public const MAX_LINES = 10000;

    /** @var list<array{product: string, name: string, quantity: int, unit: int, sources: list<string>}> */
    private array $lines = [];

    private int $total = 0;

    /** @var array<string, int> line position by product, name and unit amount */
    private array $positions = [];

    /** @var array<string, array{sum: int, lines: int}> by product */
    private array $products = [];

    /**
     * @param list<array{product: string, name: string, quantity: int, unit: int, source: string}> $drafts
     */
    public function add(array $drafts): void
    {
        foreach ($drafts as $draft) {
            if ($draft['quantity'] <= 0 || $draft['unit'] <= 0) {
                continue;
            }
            $amount = $draft['quantity'] * $draft['unit'];
            if ($amount > self::MAX_AMOUNT || $this->total + $amount > self::MAX_AMOUNT) {
                throw PricingFailed::because('amount-too-large', ['max' => self::MAX_AMOUNT]);
            }
            $this->total += $amount;
            $this->merge($draft, $amount);
        }
    }

    /**
     * Every line is above $0, so this is whether the order has a line for the product.
     */
    public function hasPositive(string $product): bool
    {
        return isset($this->products[$product]);
    }

    /**
     * @param list<string> $products
     */
    public function hasAny(array $products): bool
    {
        foreach ($products as $product) {
            if ($this->hasPositive($product)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $products
     */
    public function sum(array $products): int
    {
        $sum = 0;
        foreach (array_unique($products) as $product) {
            $sum += $this->products[$product]['sum'] ?? 0;
        }

        return $sum;
    }

    /**
     * @param list<string> $products
     */
    public function count(array $products): int
    {
        $count = 0;
        foreach (array_unique($products) as $product) {
            $count += $this->products[$product]['lines'] ?? 0;
        }

        return $count;
    }

    /**
     * @return list<QuoteLine>
     */
    public function lines(): array
    {
        return array_map(
            static fn (array $line): QuoteLine => new QuoteLine(
                $line['product'],
                $line['name'],
                $line['quantity'],
                $line['unit'],
                $line['sources'],
            ),
            $this->lines,
        );
    }

    /**
     * @param array{product: string, name: string, quantity: int, unit: int, source: string} $draft
     */
    private function merge(array $draft, int $amount): void
    {
        $product = $draft['product'];
        $key     = $product . "\0" . $draft['name'] . "\0" . $draft['unit'];
        $index   = $this->positions[$key] ?? null;
        if ($index === null && count($this->lines) >= self::MAX_LINES) {
            throw PricingFailed::because('too-many-lines', ['max' => self::MAX_LINES]);
        }
        $this->products[$product] ??= ['sum' => 0, 'lines' => 0];
        $this->products[$product]['sum'] += $amount;
        if ($index !== null) {
            $this->lines[$index]['quantity'] += $draft['quantity'];
            if (in_array($draft['source'], $this->lines[$index]['sources'], true) === false) {
                $this->lines[$index]['sources'][] = $draft['source'];
            }

            return;
        }
        $this->positions[$key] = count($this->lines);
        $this->products[$product]['lines']++;
        $this->lines[] = [
            'product'  => $product,
            'name'     => $draft['name'],
            'quantity' => $draft['quantity'],
            'unit'     => $draft['unit'],
            'sources'  => [$draft['source']],
        ];
    }
}
