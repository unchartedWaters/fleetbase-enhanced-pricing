# Setup

Enhanced Pricing prices Fleet-Ops orders from pricing profiles. This release
contains the profile checks, the pricing engine, the tables that store
profiles, customers, rate cards and dated changes, and the console's
**Pricing** page: profiles and their versions, the profile editor, subtotal
fees and their dated changes, each customer's overrides and rate cards
(read-only), and a quote preview. The order form fields and the order details
panel come in a later release.

The package lives in
[watersAbove/fleetbase-enhanced-pricing](https://github.com/watersAbove/fleetbase-enhanced-pricing),
checked out on its own, not inside the Fleetbase repository.

| | Name |
| --- | --- |
| Composer | `unchartedwaters/enhanced-pricing-api` |
| npm / Ember engine | `@unchartedwaters/enhanced-pricing-engine` |
| PHP namespace | `Fleetbase\EnhancedPricing` |
| Translation keys | `enhanced-pricing.*` |

## Requirements

- PHP `^8.2` with `fleetbase/core-api` `^1.6`.
- `brick/math` `^0.12`, for exact arithmetic.
- Node `>= 22` for the Ember engine.

## Install

From the Fleetbase directory, install the extension from the Fleetbase
registry:

```bash
flb install @unchartedwaters/enhanced-pricing-engine
```

Pass `--path` with the Fleetbase directory when that directory is not the
current one. `flb install` installs `@unchartedwaters/enhanced-pricing-engine`
and `unchartedwaters/enhanced-pricing-api`. Then rebuild or restart the
console so it includes the engine.

Then, in the Fleetbase API, run the migrations and create the extension's
permissions:

```bash
php artisan migrate
php artisan fleetbase:create-permissions
```

`fleetbase:create-permissions` reads `server/src/Auth/Schemas/EnhancedPricing.php`
and makes these permissions, which an administrator gives to roles or users
in the console's IAM settings:

| Permission | Allows |
| --- | --- |
| `enhanced-pricing see extension` | the Pricing item in the console header |
| `enhanced-pricing manage pricing-profile` | reading and saving profiles, their versions and dated changes; reading customers and previewing quotes |
| `enhanced-pricing manage rate-card` | reading profiles, customers' overrides and rate cards; previewing quotes |
| `enhanced-pricing manage manual-charge` | manual charges on orders (a later release) |

Any of the three `manage` permissions also counts as `see extension`.

It also makes two policies: `EnhancedPricingManager`, with all of them, and
`EnhancedPricingRateCards`, with the Pricing page and rate cards.
Administrators have every permission. Drivers, customers and contacts are
refused on every Pricing endpoint, whatever they hold.

## Publishing

`.github/workflows/deploy.yml` publishes the package to the Fleetbase
registry with `flb publish`, and the engine to npm, when a `v*` tag is
pushed. It publishes only when the tagged commit is on `main` and the PHP and
Ember test workflows it calls (`server.yml`, `ember.yml`) pass.

The workflows need these repository secrets:

| Secret | Used by |
| --- | --- |
| `NPM_AUTH_TOKEN` | `deploy.yml`, to publish the engine to npm |
| `FLEETBASE_REGISTRY_USERNAME` | `deploy.yml`, for the Fleetbase registry |
| `FLEETBASE_REGISTRY_PASSWORD` | `deploy.yml`, for the Fleetbase registry |
| `FLEETBASE_REGISTRY_EMAIL` | `deploy.yml`, for the Fleetbase registry |
| `CODACY_PROJECT_TOKEN` | `codacy.yml`, to upload Codacy results |

The Fleetbase registry can attach a bundle only to an extension it already
lists, so the first listing is created in the Fleetbase console. Later tags
upload the new bundle.

`package.json`, `composer.json` and `extension.json` must have the same
version; `.github/workflows/check_versions.py` checks this in CI.

`.github/dependabot.yml` has Dependabot check the Composer, npm and GitHub
Actions dependencies each week and open a pull request for each update.

## Tests

PHP 8.2 is the supported version. It matches Fleetbase and is the version CI
runs (`.github/workflows/server.yml`).

```bash
composer install
composer test
```

`composer test` runs php-cs-fixer, PHPStan and Pest. `composer test:unit`
runs Pest alone. Ember tests need Chrome:

```bash
pnpm install --frozen-lockfile
pnpm test:ember
```
