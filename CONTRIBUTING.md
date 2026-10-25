# Contributing

This package prices Fleet-Ops orders from pricing profiles. The plan it is
built from splits the work into steps; this release is the first: the repo,
the profile checks and the pricing engine.

## Setup

- PHP 8.2. This is the supported version, it matches Fleetbase, and CI runs
  it.
- Node.js 22 or newer (CI runs the current Node.js LTS).
- Composer dependencies: `composer install` in this package.
- Node dependencies: `pnpm install --frozen-lockfile` in this package.

## Layout

- `server/src/Profile`: the profile format. `Schema` holds the type checks,
  `ProfileShapes` the shape of each part, `ProfileReferences` the checks
  between parts (products, rule order, columns, dates). `ProfileValidator`
  runs them. `OverridePaths`, `OverrideValidator` and `ChangeValidator` check
  customer overrides and dated changes; `ProfileResolver` applies them for a
  pricing date.
- `server/src/Pricing`: the engine. `PricingEngine` checks the request and
  runs `ServiceFee`, `ManualCharges`, `Fees` and `SubtotalFees` in that order.
  Each adds lines to the order's `LineBook`, which merges and caps them.
- `server/src/Support/Exact.php`: exact numbers and rounding.
- `server/tests`: Pest tests. `server/tests/Fixtures/example-profile.json` is
  the example profile; `server/tests/Support` builds profiles and requests.
- `translations/en-us.yaml`: English text for every key the server returns.
- `addon/`: the Ember engine, empty until the console screens arrive.

## Behavior

- The engine reads nothing and saves nothing. The caller passes the pickup's
  timezone and the driving distance.
- Money is integer cents and percentages integer basis points. Arithmetic is
  exact (`brick/math`); each line is rounded half up once, when it is made.
- Rules run in a fixed order: the service fee, manual charges, fees in the
  profile's order, then subtotal fees in the profile's order. A rule sees only
  the lines made before it, and the profile checks refuse a rule that names a
  later rule's product.
- Lines with the same product, name and unit amount merge into one line.
  A $0 line is dropped. A line or total above 100,000,000 cents, or more than
  10,000 lines, stops pricing.
- A value comes from the customer's override, else the latest dated change on
  or before the pickup's local date, else the profile.
- Every check returns translation keys under `enhanced-pricing.profile` or
  `enhanced-pricing.pricing`, never English. A test fails when a key in the
  code has no English text.
- A change to the profile format updates
  [docs/profile-format.md](docs/profile-format.md) and the tests together. A
  change to the request or quote updates
  [docs/pricing-engine.md](docs/pricing-engine.md);
  `server/tests/DocumentationTest.php` checks its example against the engine.

## Tests

Tests come first: a new rule or fix starts with a failing test.

```bash
composer test
```

`composer test` runs php-cs-fixer, PHPStan (level max) and Pest. The parts
can run alone:

```bash
composer test:lint
composer test:types
composer test:unit
```

Front end: `pnpm lint` and `pnpm test:ember`. Ember tests need Chrome.

Codacy also checks function length (50 lines, Lizard) on `server/src`. Run it
locally with:

```bash
python3 -m lizard -L 50 -w server/src
```

PHP lines stay within 120 characters (PHPCS PSR12), and markdown lines
within 80 (markdownlint MD013).
