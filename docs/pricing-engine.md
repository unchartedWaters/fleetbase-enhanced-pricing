# Pricing engine

The engine prices one order from a pricing profile. It is a plain PHP class:
it reads nothing from the database, calls no service and saves nothing. The
caller gathers everything the order is priced from, and the same request
always gives the same lines. The profile's JSON is described in
[Profile format](profile-format.md).

## Example

The example profile in
[`server/tests/Fixtures/example-profile.json`](../server/tests/Fixtures/example-profile.json)
has a per-meter service rate, `distance`. This prices a 7 km order with one
42 kg entity, picked up in Toronto on a Wednesday:

```php
use Fleetbase\EnhancedPricing\Pricing\EntityInput;
use Fleetbase\EnhancedPricing\Pricing\PricingEngine;
use Fleetbase\EnhancedPricing\Pricing\PricingFailed;
use Fleetbase\EnhancedPricing\Pricing\PricingRequest;

$request = new PricingRequest(
    profile: $profile,
    serviceRate: 'distance',
    pickupAt: new DateTimeImmutable('2027-03-03T15:00:00Z'),
    timezone: 'America/Toronto',
    entities: [new EntityInput(weight: 42, weightUnit: 'kg')],
    distanceMeters: 7000,
);

try {
    $quote = (new PricingEngine())->price($request);
} catch (PricingFailed $failed) {
    // $failed->key and $failed->params say why the order has no price.
}
```

`$quote->toArray()` gives:

<!-- example-quote -->

```json
{
  "currency": "USD",
  "total": 4033,
  "lines": [
    {
      "product": "DISTANCE",
      "name": "Distance (10 km)",
      "quantity": 1,
      "unit_amount": 3500,
      "amount": 3500,
      "sources": ["service_rate:distance"]
    },
    {
      "product": "PARCEL",
      "name": "Parcel fee (12 kg)",
      "quantity": 1,
      "unit_amount": 480,
      "amount": 480,
      "sources": ["fee:heavy"]
    },
    {
      "product": "PERCENT",
      "name": "Percentage subtotal fee",
      "quantity": 1,
      "unit_amount": 53,
      "amount": 53,
      "sources": ["subtotal_fee:percent"]
    }
  ],
  "notes": [],
  "meta": {
    "timezone": "America/Toronto",
    "pricing_date": "2027-03-03",
    "service_rate": "distance",
    "overrides": [],
    "changes": []
  }
}
```

The 7 km is raised to the rate's 10 km minimum: 10 × $2.10 plus the $14.00
base fee is $35.00. The parcel fee charges $0.40 for each of the 12 kg over
30 kg. The percentage subtotal fee is 11% of the lines for the products it
names, which leave out distance, so it is 11% of the parcel fee's $4.80:
$0.528, rounded half up to $0.53. The flat subtotal fees don't apply: one is
skipped for distance orders, and the other requires the additional fee.

## The request

`PricingRequest` takes named arguments:

| Argument | Meaning |
| --- | --- |
| `profile` | the profile as saved, decoded from JSON |
| `serviceRate` | the key of the service rate the order uses |
| `pickupAt` | the pickup time, in any timezone |
| `timezone` | the pickup place's IANA timezone, such as `America/Toronto` |
| `entities` | a list of `EntityInput`, in the order's order |
| `attributes` | the order's attribute values, by attribute key |
| `row` | the chosen rate card row, by column key, or `null` |
| `distanceMeters` | the driving distance in meters, or `null` |
| `manualCharges` | a list of `ManualChargeInput` |
| `overrides` | the customer's overrides, by path |
| `datedChanges` | dated changes: `path`, `value`, `effective_from` |
| `labels` | `customer`, `card` and `row` names, all three used in messages |

An `EntityInput` has a `type` (an entity type key, or `null` for the
profile's default), `weight` with `weightUnit`, `length`, `width` and
`height` with `dimensionsUnit`, and its entity `attributes`. Measurements may
be integers, floats or numeric strings, with up to 7 digits before the point
and 6 after it. A string is read exactly as written, so `"0.1"` is one tenth.
A float is read as the shortest decimal that is the same float, so `0.1` is
also one tenth, and a float that needs more than 6 decimals, such as
`30.000000000000004`, stops pricing rather than being cut. Weight units are
`g`, `oz`, `lb` and `kg`; length units are `mm`, `cm`, `m`, `in`, `ft` and
`yd`.

A `ManualChargeInput` comes in two shapes:

- `ManualChargeInput::new($key, quantity: ..., amount: ...)` is a charge being
  added: a `quantity` for a charge with quantity limits, an `amount` in minor
  units for an entered charge. It is priced and checked against the profile's limits.
- `ManualChargeInput::added($key, $product, $name, $amount)` is a charge
  already on the order, with the product, line name and amount stored when it
  was added. It bills exactly those, so a later profile change does not
  reprice or rename it. The profile must still have the charge's key and the
  product.

The caller looks up the timezone and the distance. Both depend on the
pickup place and the routing provider, and keeping them out of the engine
keeps the engine free of side effects. The pickup's local date in that
timezone is the *pricing date*: it decides rule dates, dated changes, the
weekday and holidays.

## Checks before pricing

In this order, the engine:

1. checks the profile and fills in its defaults;
2. checks the overrides against the profile, and each dated change on its
   own;
3. checks the timezone;
4. applies the overrides and the dated changes in effect on the pricing date,
   and checks the result again;
5. finds the service rate, which must be enabled and, when the customer has
   `allowed_service_rates`, one of them;
6. checks the row against the service rate's rate table;
7. checks the order's attributes and entities.

The first problem stops pricing with a `PricingFailed`. When the overrides or
a dated change are refused, its `path` parameter names the first one at fault:
the override's path, or the path the dated change sets.
When the profile is refused only after they are applied, `path` names the
value by rule key, for example `fees.heavy.minimum`.

## The quote

`Quote` has the profile's `currency`, the `lines` in the order the rules made
them, `notes`, and `meta`. `total()` is the sum of the line amounts.

Each `QuoteLine` has a `product` code, a `name`, a `quantity`, a
`unitAmount` in minor units, and `sources`: the rules that made it, such as
`fee:heavy`. Its `amount()` is always quantity × unit amount, so an invoice
can show the line as it is.

`notes` lists what the engine could not check without stopping, such as an
entity with no weight for a weight fee. Each note has a translation `key`
and its `params`.

`meta` records what the order was priced with: the `timezone`, the
`pricing_date`, the `service_rate`, the `overrides` given, and `changes`,
the `effective_from` date of each dated change used, by path. Saved with the
quote, it lets a price be explained later.

## Errors

`PricingFailed` has a `key` under `enhanced-pricing.pricing` and `params`
that name what was wrong. A rule, charge, service rate or attribute is named
by its `name`, with `rule_key` beside it where the key is needed. Errors
raised while reading the order also carry the `customer`, `card` and `row`
labels, and the messages use them. The English text for each key is in
[`translations/en-us.yaml`](../translations/en-us.yaml), with `{name}`
standing for each parameter.

The profile checks can also be run alone, to show every problem before a
profile is saved:

```php
use Fleetbase\EnhancedPricing\Profile\ChangeValidator;
use Fleetbase\EnhancedPricing\Profile\OverrideValidator;
use Fleetbase\EnhancedPricing\Profile\ProfileValidator;

$problems = (new ProfileValidator())->check($profile);
$problems = (new OverrideValidator())->check($profile, $overrides);
$problems = (new ChangeValidator())->check($profile, $changes);
```

Each `Problem` has the `path` of the value, a translation `key` under
`enhanced-pricing.profile` and its `params`. An `expected` parameter is a type
code with its text under `enhanced-pricing.types`, and a `kind` parameter a
code with its text under `enhanced-pricing.kinds`: show that text, not the
code.

## Money

Amounts are integers in the minor unit of the profile's currency (cents for
USD, whole yen for JPY, thousandths for KWD; see the profile format's
Conventions) and percentages are integer basis points. The engine never
assumes two decimals: it works in exact fractions and rounds each line half
up to one minor unit, once, when the line is made. Lines are never rounded
again, and the total is their sum, so the invoice adds up.
