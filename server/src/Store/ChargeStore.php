<?php

namespace Fleetbase\EnhancedPricing\Store;

use Fleetbase\EnhancedPricing\Models\ManualCharge;
use Fleetbase\EnhancedPricing\Models\ProfileVersion;
use Illuminate\Support\Facades\Config;

/**
 * Manual charges, for PricingStore: only on the company's own orders, at most 20 not removed on
 * one order, and removed once. Working out a charge's amount and taking the order's pricing lock
 * arrive with the manual charge endpoints.
 */
final class ChargeStore
{
    public const MAX_PER_ORDER = 20;

    private const FIELDS = ['charge_key', 'product', 'quantity', 'amount', 'line_name', 'note'];

    public function __construct(private readonly StoreContext $context)
    {
    }

    /**
     * @param array<string, mixed> $fields
     *
     * @throws StoreRefused
     */
    public function add(string $orderUuid, ProfileVersion $version, array $fields, ?string $userUuid): ManualCharge
    {
        $this->context->own($version);
        if ($this->isOrder($orderUuid) === false) {
            throw StoreRefused::refuse('order-not-found', [], 'order_uuid');
        }
        $count = ManualCharge::ofCompany($this->context->company)
            ->where('order_uuid', $orderUuid)->whereNull('removed_at')->count();
        if ($count >= self::MAX_PER_ORDER) {
            throw StoreRefused::refuse('too-many-charges', ['max' => self::MAX_PER_ORDER]);
        }

        return ManualCharge::createWith(array_intersect_key($fields, array_flip(self::FIELDS)) + [
            'company_uuid'         => $this->context->company,
            'order_uuid'           => $orderUuid,
            'profile_version_uuid' => $version->uuid,
            'added_by_uuid'        => $userUuid,
            'added_at'             => $this->context->timestamp(),
        ]);
    }

    /**
     * @throws StoreRefused
     */
    public function remove(ManualCharge $charge, ?string $userUuid): ManualCharge
    {
        $this->context->own($charge);
        if ($charge->removed_at !== null) {
            throw StoreRefused::refuse('charge-removed', [], 'removed_at');
        }
        $charge->removed_by_uuid = $userUuid;
        $charge->removed_at      = $this->context->timestamp();
        $charge->save();

        return $charge;
    }

    private function isOrder(string $uuid): bool
    {
        $table = Config::get('enhanced-pricing.orders_table', 'orders');

        return $this->context->connection()->table(is_string($table) ? $table : 'orders')
            ->where('uuid', $uuid)
            ->where('company_uuid', $this->context->company)
            ->whereNull('deleted_at')
            ->exists();
    }
}
