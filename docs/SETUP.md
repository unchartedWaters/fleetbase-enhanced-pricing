# Setup

Enhanced Pricing prices Fleet-Ops orders from pricing profiles. This release
contains the profile checks and the pricing engine; it adds no tables,
routes, permissions or console screens yet.

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

This release has no migrations or permissions, so there is nothing to run
after install. Later releases that add tables and permissions will say which
commands to run.

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
