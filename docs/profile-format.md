# Pricing profile format

A pricing profile describes everything an organization charges for: its
products, the kinds of shipment it offers, the fees it adds and the charges
staff can add by hand. Its values are the global defaults for every customer
of the organization. A customer can override some of those values, and its
rate cards hold its own rates.

The extension uses Fleet-Ops' own pricing words: service rate, tiers, per
meter, fixed rate, base fee, entity and entity type, parcel fee, peak hours
fee, additional fee and subtotal fee. A profile names no business's products:
every product, rate and fee is configuration.

This document is the reference for the profile's JSON. The engine that prices
an order from it is described in [Pricing engine](pricing-engine.md), and a
complete profile is in
[`server/tests/Fixtures/example-profile.json`](../server/tests/Fixtures/example-profile.json).

## How an order is priced

The price of an order is a list of invoice lines, made in this order:

1. The service fee, from the order's service rate, and a line for each entity
   type with its own price.
2. Manual charges added to the order.
3. Fees, in the order the profile lists them.
4. Subtotal fees, in the order the profile lists them.

Each rule sees only the lines made before it. So a fee can depend on the
service fee and on manual charges, a subtotal fee can depend on fees and on
earlier subtotal fees, and nothing depends on a later rule. Every line is billed
as one of the profile's products.

## Conventions

| Kind of value | Written as | Example |
| --- | --- | --- |
| Money | integer minor units of the profile's currency | `2700` is $27.00, ¥2,700 or 2.700 KWD |
| Percent | integer basis points | `1250` is 12.5% |
| Number | JSON number or decimal string | `0.25`, `"0.25"` |
| Integer | JSON integer | `5` |
| Date | `YYYY-MM-DD` | `"2027-04-01"` |
| Time of day | `HH:MM`, 24-hour | `"21:00"` |
| Key | lowercase letters, digits, `_` and `-` | `"parcel_fee"` |
| Product code | letters, digits, `_`, `-` and `.` | `"PARCEL"` |

- Money and percents are never negative.
- A currency's minor unit is its ISO 4217 one: cents for USD and CAD, whole
  yen for JPY (no decimals), and thousandths for KWD, BHD and the other
  three-decimal currencies. Fleetbase's server (moneyphp) reads amounts the
  same way. Fleetbase's own `format-currency` console helper doesn't for some
  codes: it divides BHD, IQD, JOD, KWD, LYD, OMR and TND by 100, not 1,000;
  shows CDF, HUF, LAK, LBP, MNT and MUR undivided, not divided by 100; and
  divides DJF, KMF, PYG, RWF, UGX and XOF by 100 although they have no
  decimals. Check how Fleetbase shows those before billing in them.
- The money limits below are in minor units, so they are much lower in some
  currencies: 100,000,000 is $1,000,000 or ¥100,000,000, but only about
  Rp 1,000,000 in IDR or ₫100,000,000 in VND.
- A number has up to 7 digits before the point and 6 after it, with no
  exponent: `1e3` is refused.
- Keys and product codes are 1 to 64 characters and start with a letter or
  digit. Names and other text are 1 to 255 characters.
- Every object is strict: a field that this document does not list for it is
  refused, so a misspelt field never goes unnoticed.
- Fields marked *optional* below may be left out, and take the default shown.
- Each rule (service rate, fee, subtotal fee, manual charge) has a key that is
  unique in its section of the profile. Overrides and dated changes name a
  value by that key, so they stay with their rule across profile versions.

Arithmetic is exact. Unit conversions, distances, percentages and fractional
quantities are worked out as exact fractions, and compared exactly with
thresholds. Each line's amount is then rounded half up to the minor unit. Units in a
line name are shown with up to 3 decimals, for example `Parcel fee (1.751 kg)`.

## Top level

```json
{
  "currency": "CAD",
  "scheduling_timezone": "America/Edmonton",
  "distance_unit": "km",
  "products": [],
  "attributes": [],
  "entity_types": [],
  "default_entity_type": "standard",
  "rate_tables": [],
  "service_rates": [],
  "fees": [],
  "subtotal_fees": [],
  "manual_charges": [],
  "holiday_lists": {}
}
```

| Field | Meaning |
| --- | --- |
| `currency` | ISO 4217 code, three capital letters |
| `scheduling_timezone` | IANA name; see below |
| `distance_unit` | `km` or `mi`, for per-meter rates |
| `products` | at least one product |
| `attributes` | optional, default `[]` |
| `entity_types` | at least one entity type |
| `default_entity_type` | key of the type for entities with none |
| `rate_tables` | optional, default `[]` |
| `service_rates` | at least one service rate |
| `fees` | optional, default `[]` |
| `subtotal_fees` | optional, default `[]` |
| `manual_charges` | optional, default `[]` |
| `holiday_lists` | optional, default `{}` |

`scheduling_timezone` only decides which day is "tomorrow" when a dated
change is scheduled. It doesn't time orders: each order is timed in the
timezone of its pickup place's location, which the caller looks up and passes
to the engine. Peak hours and rule dates follow that timezone too.

## Products

```json
{ "code": "PARCEL", "name": "Parcel fee" }
```

Every invoice line carries one product, and the line's billing code is that
product's code, unless a service rate takes it from the rate card row (see
[Billing codes from the rate card row](#billing-codes-from-the-rate-card-row)).
Codes are unique. Each service rate, each entity type with its own price,
each fee, subtotal fee and manual charge names its product. Several rules may
share a product.

## Attributes

```json
{
  "key": "signature",
  "name": "Signature on delivery",
  "scope": "order",
  "type": "boolean",
  "required": false
}
```

| Field | Meaning |
| --- | --- |
| `key`, `name` | unique key, and the label shown in forms |
| `scope` | `order` (one value per order) or `entity` (one per entity) |
| `type` | `boolean`, `choice` or `number` |
| `choices` | `choice` only: 1 to 50 `{ "key", "name" }` |
| `unit` | `number` only, optional: the unit shown, for example `h` |
| `maximum` | `number` only, optional, default 1,000,000 |
| `required` | optional, default `false`: `true` needs a value on every order |
| `required_for` | optional, default `[]`: service rate keys that need a value |

A required attribute must have a value on every order (or every entity) when
`required` is `true`, or on every order using one of the service rates in
`required_for`. Set one or the other, not both.
Pricing stops with a message when one is missing. A missing boolean that is not
required counts as `false`; a missing choice or number matches nothing. A value
of the wrong kind, an unknown choice, a negative number or one above `maximum`
stops pricing.

## Entity types

```json
{ "key": "standard", "name": "Standard" }
```

```json
{
  "key": "oversize",
  "name": "Oversize",
  "own_price": { "column": "oversize_price", "product": "OVERSIZE" }
}
```

An entity is one package in the order's payload. Its type decides how the
service fee prices it.

| Field | Value |
| --- | --- |
| `key` | key |
| `name` | text; names the type's own line when it has `own_price` |
| `own_price` | optional, default `null`; `{ "column": key, "product": code }` |

- Without `own_price`, the entity counts in the service rate's tiers and is
  billed under the service rate's product.
- With `own_price`, on a service rate priced in tiers, the entity is not
  counted in the tiers. It is billed on its own line, at the rate card row's
  value in `column`, under `product`. On a per-meter or fixed-rate service
  rate, `own_price` is not used: the entity is billed under the service rate's
  product.

When any entity type has `own_price`, every service rate priced in tiers must
have a rate table, and each `own_price.column` must be a money column of that
table. `default_entity_type`
is used for entities that have no type. An entity with a type the profile does
not know stops pricing.

Each entity is billed under one product: its *billing product*. Fees that count
entities count those whose billing product is in their `applies_to`, and only
when the order has a line of more than $0 for that product.

## Rate tables

A rate table is the layout of a customer's rate card rows: typed columns and
the columns that form a row's key.

```json
{
  "key": "routes",
  "columns": [
    { "key": "code", "type": "text", "required": true },
    { "key": "included", "type": "integer", "required": true },
    { "key": "first", "type": "money", "required": true },
    { "key": "extra", "type": "money", "required": false }
  ],
  "row_key": ["code"]
}
```

Column types are `text`, `money`, `integer` and `number`; `required` is
optional, default `false`. A column may also have a `name`, optional, default
`null`: the label shown for it, for example `"First package"`; messages use it
in place of the key. `row_key` lists 1 to 5 columns that are required. A
row must have every required column, and every value must match its column's
type. A blank value in a column that is not required is a missing amount:
pricing stops when a rule needs it, with a message naming the customer, card,
row and rule.

## Service rates

Every order has one service rate. It decides the service fee.

```json
{
  "key": "route",
  "name": "Route delivery",
  "product": "ROUTE",
  "rate_table": "routes",
  "line_name": { "column": "code" },
  "billing_code_column": null,
  "method": "tiers",
  "tiers": [
    { "up_to": { "column": "included" }, "price": { "column": "first" } },
    { "up_to": null, "price": { "column": "extra" } }
  ],
  "minimum": null,
  "enabled": true
}
```

| Field | Meaning |
| --- | --- |
| `key`, `product` | as for every rule |
| `name` | label in the order form's rate picker and in messages |
| `rate_table` | optional, default `null`: table whose row the order picks |
| `line_name` | `{ "text": name }`, `{ "column": key }` of a text column, or both |
| `billing_code_column` | optional, default `null`: key of a text column of the `rate_table` holding each row's billing code; see below |
| `method` | `tiers`, `per_meter` or `fixed_rate` |
| `minimum` | optional, default `null`: money, the lowest service fee |
| `enabled` | optional, default `true`; `false` refuses orders using it |

A service rate with a `rate_table` needs a row from one of the customer's rate
cards; one without needs none. A `{ "column": ... }` reference needs a
`rate_table` that has that column. Only the field for the `method` is allowed:
`tiers`, `per_meter` or `fixed_rate`.

### Billing codes from the rate card row

Each invoice line has a billing code: the code it is billed under, which
Ledger and accounting can map. A line's billing code is its product's code,
except when a service rate names a `billing_code_column`. Then its service
fee lines, including a service fee raised to its minimum, are billed under the
code in that column of the order's rate card row. Each route on a customer's
rate card can then bill under its own code, such as `4010-NORTH`, without a
new product in the profile for each route. The column may be the row's own
key column, such as `code`, or a column of its own.

The line keeps the service rate's `product`. Fees and subtotal fees still find
lines by product (`applies_to`, `requires`, `skipped_for`), so a fuel
surcharge on `ROUTE` covers every route whatever its billing code. Lines of
entity types with their own price, fees, subtotal fees and manual charges are
billed under their product's code.

A billing code is written like a product code (letters, digits, `_`, `-` and
`.`, 1 to 64 characters, starting with a letter or digit), and can't be
another product's code in any case: a route billed as `fuel` would land on the
fuel surcharge's code. It may be the service rate's own product code. A row is
checked when it is saved, against every enabled service rate that reads a
billing code from it: without a usable billing code it is refused. A new
profile version that starts reading billing codes lists the rows without one
as stale, as for any row that no longer fits. If such a row is still used,
pricing stops with a message naming the customer, card, row and rule, so no
line is billed under the wrong code.

Anyone who may manage rate cards can set a row's billing code.

### Tiers

`tiers` is an ordered list of 1 to 10 tiers. Each has an `up_to` and a `price`
per entity:

- `up_to`: `{ "value": n }`, an integer column `{ "column": key }`, or `null`.
  It counts entities from the start of the order, so tiers of 2, 5 and `null`
  price entities 1 to 2, 3 to 5, and 6 onward. Each `up_to` must be at least
  1 and more than the one before. Only the last tier may be `null`. Limits from
  columns are checked when the order is priced.
- `price`: `{ "value": amount }` or a money column.

Entities whose type has no `own_price` are counted. When the last tier has a
limit and the order has more entities, pricing stops. A tiered service rate
needs at least one entity. Each tier that covers any entities makes a line:
that number of entities × the tier's price, named with `line_name`. A tier may
have a `name`, optional, default `null`, which is added to its line's name:
with `"name": "first package"`, the line is `North - Zone 1 (first package)`.

A `line_name` with both a text and a column shows them as `text: value`, for
example `{ "text": "Route delivery", "column": "code" }` gives
`Route delivery: North - Zone 1`.

Each entity type with its own price makes a line: the number of those entities
× the row's value in its `own_price.column`, under its `own_price.product`,
named with the type's name.

### Per meter

Fleet-Ops calls a distance rate "per meter" whatever its unit. Here the rate is
per `distance_unit`, a kilometre or a mile.

```json
{
  "method": "per_meter",
  "per_meter": {
    "rate": 210,
    "base_fee": 1400,
    "minimum_distance": 10,
    "maximum_distance": 1000,
    "rounding": "up"
  }
}
```

| Field | Meaning |
| --- | --- |
| `rate` | money per `distance_unit` |
| `base_fee` | optional, default 0: money added once |
| `minimum_distance` | optional, default 0: number |
| `maximum_distance` | number, at most 20,000 |
| `rounding` | `up` or `nearest` whole unit (half up), or `none` |

The driving distance is converted to the unit. A distance above
`maximum_distance`, before rounding, stops pricing, so a bad distance lookup
can't produce an absurd charge, and so does a distance of 0 or none at all.
The distance is then rounded and raised to `minimum_distance`. `nearest` can
round a short distance down to 0, leaving only `base_fee`; set a
`minimum_distance` to charge at least that far. The line is
`base_fee + rate × distance`, quantity 1, named with `line_name` and the
distance, for example `Distance (10 km)`.

### Fixed rate

```json
{ "method": "fixed_rate", "fixed_rate": { "amount": { "value": 4500 } } }
```

`amount` is `{ "value": amount }` or a money column. The line has quantity 1.

### Minimum

When the service fee lines (not the entity-type lines) total less than
`minimum`, they are replaced by one line of quantity 1 at the minimum, named
with `line_name`. A service fee of $0, such as a row whose prices are all 0,
stays $0 and makes no line.

## Fees

Fees come after the service fee and manual charges. There are three kinds:
`peak_hours`, `parcel` and `additional`.

Fields every fee has:

| Field | Meaning |
| --- | --- |
| `key`, `kind` | unique key among fees, and the kind |
| `name`, `product` | the line name and the product it is billed as |
| `applies_to` | 1 or more product codes the fee applies to |
| `charge` | how it is charged; the choices depend on the kind |
| `minimum`, `maximum` | optional, default `null`: money, on the fee's total |
| `effective_from` | optional, default `null`: first date, inclusive |
| `effective_until` | optional, default `null`: last date, inclusive |
| `enabled` | optional, default `true`; `false` turns the fee off |

Peak hours and additional fees also have:

| Field | Meaning |
| --- | --- |
| `amount` | money, or `null` when it must be set per customer |
| `if_missing` | optional, default `stop`; `skip` leaves the fee off |

A fee's amount is looked up only when the fee matches the order. When it is
`null`, pricing stops with a message, or, with `if_missing: "skip"`, the fee is
left off.

`applies_to` limits the fee to some products. A fee charged per entity counts
only entities billed under one of them (see Entity types). A fee charged per
order applies only when the order already has a line of more than $0 for one of
them.

When the fee's lines total less than `minimum`, or more than `maximum`, they
are replaced by one line of quantity 1 at that limit. A fee whose lines total
$0 makes no line, whatever its `minimum`. `minimum` must not be more than
`maximum`.

### Peak hours fee

```json
{
  "key": "weekend",
  "kind": "peak_hours",
  "name": "Weekend pickup",
  "product": "PEAK",
  "applies_to": ["ROUTE", "DISTANCE"],
  "charge": "per_order",
  "amount": 2200,
  "window": null,
  "weekdays": [6, 7],
  "holidays": null
}
```

| Field | Meaning |
| --- | --- |
| `window` | optional: `{ "from": "21:00", "to": "06:00" }` or `null` |
| `weekdays` | optional: 1 to 7 ISO weekdays, 1 Monday to 7 Sunday, or `null` |
| `holidays` | optional: the name of a holiday list, or `null` |
| `charge` | `per_entity` or `per_order` |

The pickup time must be at or after `from` and before `to`. A window may cross
00:00; `from` and `to` must differ. At least one of the three must be set.

The fee applies when the pickup is inside the `window` (or there is no window)
and, when weekdays or a holiday list is set, the pickup's day is one of the
weekdays or a date in the list. The day is the pickup's own local date: with a
21:00 to 06:00 window and `weekdays: [5]`, a pickup on Friday at 23:00 matches
and one on Saturday at 02:00 does not. Everything is judged in the pickup
place's timezone, so daylight saving is handled.

### Parcel fee

```json
{
  "key": "heavy",
  "kind": "parcel",
  "name": "Heavy parcel",
  "product": "PARCEL",
  "applies_to": ["ROUTE", "DISTANCE"],
  "charge": "per_unit",
  "basis": "weight",
  "measure": "actual",
  "divisor": null,
  "unit": "kg",
  "scope": "entity",
  "thresholds": [{ "over": 30, "amount": 40 }]
}
```

| Field | Meaning |
| --- | --- |
| `basis` | `weight` or `volume` |
| `measure` | weight: `actual`, `dimensional` or `greater`; volume: `null` |
| `divisor` | cm³ per kg, more than 0; `dimensional` and `greater` only |
| `unit` | weight `g`, `oz`, `lb`, `kg`; volume `cm3`, `m3`, `in3`, `ft3` |
| `scope` | `entity` (each entity) or `order` (counted entities together) |
| `thresholds` | 1 to 10 `{ "over", "amount" }`, `over` increasing |
| `charge` | entity: `per_entity`, `per_unit`; order: `per_order`, `per_unit` |

A parcel fee has no `amount` or `if_missing`: its amounts are its thresholds'.

Dimensional weight is length × width × height ÷ `divisor`, with the volume in
cm³ and the result in kg. A divisor written in in³ per lb converts with
1 in³/lb ≈ 36.13 cm³/kg (139 in³/lb ≈ 5022 cm³/kg). `greater` uses the larger
of the actual and the dimensional weight.

A measurement must be strictly over a threshold's `over` to match it.

- `per_entity` (entity scope) and `per_order` (order scope): the highest
  threshold matched applies, and its amount is charged for each matching
  entity, or once.
- `per_unit`: each unit over the first threshold is charged in bands, like
  tiers. With thresholds over 30 at $0.40 and over 50 at $0.60, 55 kg is
  20 kg × $0.40 + 5 kg × $0.60 = $11.00. Each matching entity (entity scope),
  or the order (order scope), makes a line of quantity 1 named with the units
  over, for example `Heavy parcel (25 kg)`.

Weights and dimensions use Fleet-Ops' units (`g`, `oz`, `lb`, `kg`; `mm`, `cm`,
`m`, `in`, `ft`, `yd`). An entity with no weight, or no dimensions when they
are needed, is not checked, and the quote notes it.

### Additional fee

```json
{
  "key": "signature",
  "kind": "additional",
  "name": "Signature",
  "product": "ADDITIONAL",
  "applies_to": ["ROUTE"],
  "charge": "per_order",
  "amount": 1600,
  "attribute": "signature",
  "equals": null
}
```

| Field | Meaning |
| --- | --- |
| `attribute` | the key of an attribute |
| `equals` | a choice key for a `choice` attribute; otherwise `null` |
| `charge` | `per_entity`, `per_order` or `per_unit` |

A `boolean` attribute matches when `true`, a `choice` when it is `equals`, and
a `number` when it is more than 0. `per_unit` needs a `number` attribute and
charges the amount for each unit of its value, on a line of quantity 1 named
with the units, for example `Waiting (2.5 h)`.

For an entity attribute, `per_entity` charges each matching entity billed under
one of `applies_to`, `per_order` charges once when any of them matches, and
`per_unit` makes a line for each of them. For an order attribute, `per_order`
and `per_unit` charge once, and `per_entity` charges every entity billed under
one of `applies_to`.

## Subtotal fees

Subtotal fees come last. Each is a percentage of the lines for its chosen
products, or a flat amount.

```json
{
  "key": "fuel",
  "name": "Fuel",
  "product": "FUEL",
  "applies_to": ["ROUTE", "PARCEL"],
  "requires": [],
  "skipped_for": ["DISTANCE"],
  "mode": "percentage",
  "percentage": 1100,
  "charge": "per_order"
}
```

| Field | Meaning |
| --- | --- |
| `key`, `name`, `product` | as for every rule |
| `applies_to` | 1 or more chosen product codes |
| `requires` | optional, default `[]` |
| `skipped_for` | optional, default `[]` |
| `mode` | `percentage` or `flat` |
| `percentage` | percentage mode only: percent, or `null` |
| `amount` | flat mode only: money, or `null` |
| `charge` | percentage: `per_order`; flat: also `per_line`, `per_entity` |

`if_missing`, `minimum`, `maximum`, `effective_from`, `effective_until` and
`enabled` are optional and work as for fees; `if_missing` looks at `percentage`
or `amount`, by mode.

A subtotal fee applies when all of these hold, judged on the lines before it:

- the order has a line of more than $0 for one of `applies_to`;
- it has a line of more than $0 for every product in `requires`;
- it has no line of more than $0 for any product in `skipped_for`.

Manual charge lines count for all three lists.

- `percentage`: the total of the lines for `applies_to` × the percentage. The
  line's name shows the percentage and the total it is of, for example
  `Fuel (11% of 330.00)`, unless the `minimum` or `maximum` replaced it.
- `flat`, `per_order`: the amount, once.
- `flat`, `per_line`: the amount for each line of more than $0 for
  `applies_to`, as one line with that quantity. Lines are counted after they
  are combined (see Invoice lines).
- `flat`, `per_entity`: the amount for each entity billed under one of
  `applies_to`. Manual charge lines have no entities.

## Product lists and rule order

A fee or subtotal fee only sees lines made before it. So, for every fee and
subtotal fee:

- each product in `applies_to`, `requires` and `skipped_for` must be a known
  product;
- none of them may be the rule's own product, or a product that a later fee or
  subtotal fee is billed as;
- a product may appear in only one of the rule's lists.

A profile, or a customer's override, that breaks one of these is refused.

## Manual charges

A manual charge is added to an order by a person with the "manage manual
charges" permission, or by another system through the API.

```json
{
  "key": "waiting",
  "name": "Waiting time",
  "product": "WAITING",
  "amount": 4200,
  "quantity": { "unit": "h", "step": "0.25", "maximum": "24" }
}
```

| Field | Meaning |
| --- | --- |
| `amount` | money: the charge, or the charge per unit of quantity |
| `entered` | `{ "minimum", "maximum" }`: the amount is typed when added |
| `quantity` | optional, default `null`: `{ "unit", "step", "maximum" }` |
| `offered` | optional, default `true`; `false` hides it unless overridden |
| `effective_from`, `effective_until` | optional dates, as for fees |

Exactly one of `amount` and `entered` is set (the other is left out or `null`).
An entered amount must be within its minimum and maximum, the minimum not more
than the maximum, and takes no quantity. A quantity is a positive multiple of
`step`, up to `maximum`. A charge with a quantity is one line of quantity 1
named with it, for example `Waiting time (1.75 h)`.

The amount is worked out when the charge is added, from the values and dates in
effect on the order's pricing date, and stored with the charge. Once added, a
charge bills its stored amount, whatever a later profile version, override or
dated change says. If a later version removes the charge's key, pricing stops
until someone removes the charge. An order can have up to 20 manual charges.

## Holiday lists

```json
{ "main": ["2027-01-01", "2027-07-01", "2027-12-25"] }
```

A name (a key) and up to 400 dates. Peak hours fees name a list.

## Invoice lines

Lines that share a product, a billing code, a name and a unit amount are
combined into one line with a quantity: three entities that each get a $12 fee are one line, 3 × $12,
not three lines. A line that differs in any of them, such as a second tier's
price, stays apart. Because a line's amount is always its quantity × its unit
amount, combining never changes the total.

A line whose amount is $0 is left off. A line or an order total above
$1,000,000 stops pricing, and so does a quote of more than 10,000 lines.

A line name longer than 191 characters, which is as much as a Ledger invoice
line holds, is cut to 190 characters and ends in `…`.

## Customer overrides

Each customer uses one profile, and the profile's values are its defaults. A
customer's overrides change some of those values for that customer only.
Overrides have no dates: a reloaded override takes effect when it is loaded.

Overrides are a JSON object from a *path* to a value:

```json
{
  "service_rates.distance.per_meter.rate": 160,
  "fees.heavy.thresholds": [{ "over": 30, "amount": 50 }],
  "subtotal_fees.fuel.percentage": 900,
  "manual_charges.visit.offered": true,
  "allowed_service_rates": ["route"]
}
```

A path is the section of the profile (`service_rates`, `fees`, `subtotal_fees`
or `manual_charges`), the rule's key and a field, `K` below. A list is replaced
as a whole. `null` is allowed wherever the profile allows it.

| Path | Value |
| --- | --- |
| `service_rates.K.enabled` | boolean |
| `service_rates.K.minimum` | money or `null` |
| `service_rates.K.tiers` | the whole list of tiers |
| `service_rates.K.per_meter.rate` | money |
| `service_rates.K.per_meter.base_fee` | money |
| `service_rates.K.per_meter.minimum_distance` | number |
| `service_rates.K.per_meter.rounding` | `up`, `nearest` or `none` |
| `service_rates.K.fixed_rate.amount` | `{ "value": amount }` or a column |
| `fees.K.F` | any field in the fee lists below |
| `subtotal_fees.K.F` | any field in the subtotal fee list below |
| `manual_charges.K.F` | `amount`, `entered`, `quantity`, `offered` |
| `holiday_lists.NAME` | a list of dates, for a list the profile has |
| `allowed_service_rates` | rate keys the customer may order; `[]` allows none |

Every fee's `applies_to`, `minimum`, `maximum` and `enabled` can be
overridden. So can a peak hours fee's `amount`, `if_missing`, `window`,
`weekdays` and `holidays`, a parcel fee's `thresholds` and `divisor`, and an
additional fee's `amount`, `if_missing` and `equals`. A subtotal fee's are
`applies_to`, `requires`, `skipped_for`, `percentage`, `amount`, `if_missing`,
`minimum`, `maximum` and `enabled`.

A manual charge's `amount`, `entered` and `quantity` can be replaced, but an
override can't set one the profile leaves `null` or clear one it sets: that
would turn a flat charge into an entered one, or an hourly one into a flat one.

The structure can't be overridden: products, service rates, entity types,
attributes, rate tables, the kind of each fee, each charge's mode and how it is
charged (including whether a manual charge is flat, entered or by quantity),
the distance unit and maximum distance, and rule dates. An override is
checked when it is loaded: its path must name a rule the profile has and a
value of it that can be overridden, its value must have the right type, and
the profile with every override applied must still pass every check in this
document.

Money is in minor units and percentages in basis points, so `31` meant as
31% is 0.31%. When an override or a dated change is saved, a money or percent value
(an `amount`, `percentage`, `minimum`, `maximum`, `rate` or `base_fee`, or
the amount of a `{ "value": amount }` or an entered charge's limits) ten or more
times above or below the value it replaces is refused as a likely unit slip,
with the key `enhanced-pricing.store.large-change`, until it is sent again
marked as intended. An override replaces the customer's own earlier override
of that value, else the profile's; a dated change replaces the live change in
effect before its date, else the profile's value. Values of 0, lists such as
`thresholds` and `tiers`, and values already stored are not checked. The
override checks run first, so a save that has both an invalid override and a
large change is refused for the invalid override.

A customer's rate cards are not overrides: they are the customer's own rates,
rows of the profile's rate tables.

## Dated changes

A dated change gives one of the profile's global values a new value from a
date, so a rate can be raised ahead of time and earlier orders keep the old
one.

```json
{
  "path": "subtotal_fees.fuel.percentage",
  "value": 1300,
  "effective_from": "2027-04-01"
}
```

Only these values can change this way, with the override paths above:
amounts, rates, percentages, base fees, minimum distances, minimums,
maximums, `thresholds` and `tiers` (each a whole list).

The value used on an order is, in this order: the customer's override, if it
has one; else the latest dated change whose `effective_from` is on or before
the order's pricing date; else the profile's value. So a scheduled change only
reaches customers without an override for that value; the store lists the
customers with an override at a path, so a screen can show who a change won't
reach. A value can have only one change on a date.

The pricing date is the pickup's local date in the pickup place's timezone.
Rule dates (`effective_from` and `effective_until`) are judged on the same
date, so a rule until 2027-03-31 still applies at 23:59 that day.

The profile with a customer's overrides and the dated changes in effect must
still pass every check, for example a minimum not more than a maximum. When it
doesn't, pricing stops with a message rather than guessing.

Dated changes are stored apart from the profile, so a loader rerun does not
undo one. Storing, scheduling and cancelling them, and checking overrides and
changes again when a new profile version is saved, arrive with the extension's
tables; the engine already applies them.

## Limits

| What | Limit |
| --- | --- |
| Products, fees and subtotal fees | 50 each |
| Service rates, entity types, attributes, manual charges | 20 each |
| Rate tables, holiday lists | 10 each |
| Columns in a rate table | 30 |
| Tiers or thresholds in one rule | 10 |
| Choices in one attribute | 50 |
| Dates in one holiday list | 400 |
| Overrides for one customer | 200 paths |
| Dated changes for one profile | 1,000 |
| Entities in one order | 1,000 |
| Manual charges on one order | 20 |
| An entity's weight or a dimension | 1,000,000 in its unit |
| A per-meter rate's maximum distance | 20,000 in the distance unit |
| Text | 255 characters |
| Profile | 64 KB as saved, in UTF-8 JSON |
| Money in the profile | up to 100,000,000 minor units |
| Percent | up to 100,000 basis points (1,000%) |
| A line or an order total | up to 100,000,000 minor units |
| Lines in one quote | 10,000 |
| A line name | 191 characters |

## Errors

The profile checks return every problem they find, each with the path of the
value in the JSON being checked (for example `fees.2.thresholds.0.over`), a
translation key and its parameters. The English text for each key is in
[`translations/en-us.yaml`](../translations/en-us.yaml), under
`enhanced-pricing.profile`. An `expected` parameter is a type, with its text
under `enhanced-pricing.types`, and a `kind` parameter is what was looked up
(a service rate, a column), with its text under `enhanced-pricing.kinds`.

Pricing errors and notes use keys under `enhanced-pricing.pricing`. They name
rules, charges and service rates by their names, columns by their `name` (with
the key as `column_key`), entities by the label the caller gives or else their
position, and the customer, card and row by the labels the caller gives. Money
is given as an amount in the currency's own decimals, such as `150.00` in USD
or `15000` in JPY, with a `currency` parameter, not in minor units. A path in a pricing error uses rule keys, as overrides do (for example
`fees.heavy.minimum`).
