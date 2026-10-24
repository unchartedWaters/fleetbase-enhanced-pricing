# PR reviewer instructions

## Purpose

- Fleetbase extension that prices Fleet-Ops orders from pricing profiles: a
  Laravel API package (`unchartedwaters/enhanced-pricing-api`, namespace
  `Fleetbase\EnhancedPricing`) plus an Ember engine
  (`@unchartedwaters/enhanced-pricing-engine`).
- Generic by design: a profile names no business's products. Flag product or
  business-specific words, rates or rules in code, tests, fixtures or docs.

## Architecture

- `server/src/Profile`: the profile format and its checks. `ProfileValidator`
  runs `ProfileShapes` (each part's shape, through `Schema`) and, only when
  the shape is valid, `ProfileReferences` (checks between parts).
  `OverrideValidator` and `ChangeValidator` check customer overrides and dated
  changes against `OverridePaths`; `ProfileResolver` applies them.
- `server/src/Pricing`: `PricingEngine::price` checks the request, resolves
  the profile for the pickup's local date, then runs `ServiceFee`,
  `ManualCharges`, `Fees` and `SubtotalFees` in that order into a `LineBook`.
- The engine is pure: no database, HTTP, clock, config or container. The
  caller passes the timezone and distance. Flag any side effect in it.
- `EnhancedPricingServiceProvider` registers `CoreServiceProvider` and
  nothing else yet.

## Money rules

- Money is integer cents, percentages integer basis points, measurements and
  quantities exact decimals (`Exact::canonical`). No floats in arithmetic:
  use `Exact` and `BigRational`.
- Each line is rounded half up once, when it is made; lines are never rounded
  again and the total is their sum.
- A rule sees only earlier lines. Line amount is always quantity × unit.
- Caps: 100,000,000 cents per line and total, 10,000 lines; input limits in
  `docs/profile-format.md` (Limits). New input must be bounded.

## Testing

- Pest 1 in `server/tests` (`composer test` = php-cs-fixer + PHPStan level
  max + Pest). Tests build requests with `server/tests/Support`. No mocks are
  needed: the engine is plain PHP.
- Behavior changes and fixes need tests written first. Money edge cases
  (rounding, thresholds, $0, caps, dates at 00:00 and 23:59, timezones)
  need explicit cases.

## Code style & conventions

- PHP: php-cs-fixer `@Symfony` plus aligned `=>`/`=`, single quotes. Boolean
  checks are written `=== true`/`=== false`, not `!`.
- User-facing text is a key in `translations/en-us.yaml` under
  `enhanced-pricing.profile` or `enhanced-pricing.pricing`; problems and
  errors carry the key and parameters, never English.
- Codacy runs Lizard's 50-line function limit on `server/src`; split long
  methods rather than suppressing.
- JS: Prettier from the `prettier` key in `package.json`, ESLint
  `plugin:ember/recommended`.
- PHP lines stay within 120 columns (PHPCS PSR12).
- Markdown: 80-column lines (MD013).
- Format or behavior changes update `docs/profile-format.md` or
  `docs/pricing-engine.md` in the same PR.

## PR-specific rules

- Branch from `main`; PRs target `main`. One concern per PR, minimum change.
- `package.json`, `composer.json` and `extension.json` versions must match
  (`check_versions.py`).
- The npm pack must keep the manifests, the service provider,
  `translations/en-us.yaml`, `server/src/Pricing/` and `server/src/Profile/`
  (`check_registry_manifest.py`).

## Common pitfalls

- Float arithmetic or `round()` on money.
- A check that accepts unbounded input (list sizes, text length, numbers).
- A rule that reads lines made after it, or a profile check that lets a rule
  name its own or a later rule's product.
- English text in a problem or error instead of a translation key.

## Out of scope

- PHPMD rules excluded in `phpmd.xml` (`StaticAccess`, `ElseExpression`,
  `BooleanArgumentFlag`), and its raised limits: cyclomatic 15, NPath 1000,
  class complexity 100, and 13 parameters for `PricingRequest`'s named
  arguments.
- Paths excluded in `.codacy.yml`: JSHint on Ember/JS code, Lizard on
  `server/tests` and `tests`, PHPMD on `server/tests`.
- Lockfile contents (`composer.lock`, `pnpm-lock.yaml`).
