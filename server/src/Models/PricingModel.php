<?php

namespace Fleetbase\EnhancedPricing\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;

/**
 * A row of one of the extension's tables: a plain Eloquent model with a uuid key, on the Fleetbase
 * connection, with no soft deletes, so a removed row never blocks its unique key. Every table has
 * company_uuid, and each parent a row points at must belong to the same company.
 *
 * @property string      $uuid
 * @property string      $company_uuid
 * @property string|null $created_at
 * @property string|null $updated_at
 */
abstract class PricingModel extends Model
{
    /**
     * The columns that point at a parent, and the parent's class.
     *
     * @var array<string, class-string<PricingModel>>
     */
    protected const PARENTS = [];

    protected $primaryKey = 'uuid';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return string|null
     */
    public function getConnectionName()
    {
        $connection = $this->connection ?: Config::get('fleetbase.connection.db', 'mysql');

        return is_string($connection) ? $connection : null;
    }

    /**
     * Every read of company data starts here.
     */
    public static function ofCompany(string $company): Builder
    {
        return static::query()->where('company_uuid', $company);
    }

    /**
     * The rows a query of this model finds, typed.
     *
     * @return list<static>
     */
    public static function listOf(Builder $query): array
    {
        $models = [];
        foreach ($query->get() as $model) {
            if ($model instanceof static) {
                $models[] = $model;
            }
        }

        return $models;
    }

    /**
     * The first row a query of this model finds, typed.
     */
    public static function firstOf(Builder $query): ?static
    {
        $model = $query->first();

        return $model instanceof static ? $model : null;
    }

    /**
     * Saves a new row.
     *
     * @param array<string, mixed> $attributes
     */
    public static function createWith(array $attributes): static
    {
        $model = static::query()->create($attributes);
        if ($model instanceof static === false) {
            throw new \LogicException(static::class);
        }

        return $model;
    }

    protected static function booted(): void
    {
        static::creating(static function (PricingModel $model): void {
            if (blank($model->getAttribute('uuid'))) {
                $model->setAttribute('uuid', (string) Str::uuid());
            }
        });
        static::saving(static function (PricingModel $model): void {
            $model->checkParents();
        });
    }

    /**
     * @throws CrossCompanyReference
     */
    protected function checkParents(): void
    {
        $company = $this->getAttribute('company_uuid');
        foreach (static::PARENTS as $column => $class) {
            if ($this->exists && $this->isDirty([$column, 'company_uuid']) === false) {
                continue;
            }
            $parent = $this->getAttribute($column);
            $found  = is_string($company) && is_string($parent)
                && $class::ofCompany($company)->whereKey($parent)->exists();
            if ($found === false) {
                throw new CrossCompanyReference(static::class, $column);
            }
        }
    }
}
