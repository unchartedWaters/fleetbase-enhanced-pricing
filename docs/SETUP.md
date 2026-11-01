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
- A current timezone database on production servers, and a current PHP.
  Peak hours fees and the day a dated change starts are judged in a
  timezone such as `America/Edmonton`. If the server's tzdata is out of
  date when daylight saving rules change, those times are an hour off.
  PHP uses its own copy of the database unless it was built to read the
  system's (as Debian and Ubuntu packages are) or has the `timezonedb`
  extension, so update whichever one it reads. `php -r 'echo
  timezone_version_get();'` prints the version in use.

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

When the packages are added another way, such as a Composer path repository
and a linked npm package, the console loads the engine only if its
`fleetbase.config.json` lists `@unchartedwaters/enhanced-pricing-engine` in
`EXTENSIONS`, a comma-separated list of package names. Pricing then appears in
the console header, or under its "⋯" menu when the header is full.

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

## Invoices and GST

Prices from the engine are before tax, and the engine adds no tax lines.
Fleetbase Ledger writes each quote line with a tax rate of 0, so a Ledger
invoice for a priced order starts with a total without GST.

GST is worked out in QuickBooks. When invoices sync both ways, QuickBooks is
Primary and QuickBooks works out the tax (the QuickBooks plugin's default
invoice settings), the QuickBooks sync that sends the invoice copies the GST
QuickBooks charges, each line's tax, the new total and the QuickBooks invoice
number onto the Ledger invoice. A draft invoice is sent once it leaves draft.
Whether QuickBooks charges GST on a line depends on the tax codes of the
line's QuickBooks product and customer; see the plugin's setup guide. An
invoice the plugin cannot send, for example outside the US when a line has no
tax code, keeps the total without GST.

So check that a Ledger invoice shows GST before sending a customer its public
invoice link, and check again after editing it in Ledger, until the next sync
has run. Don't set up a payment gateway in Ledger, or a customer could pay the
total without GST before the sync.

With invoices sent To QuickBooks only, no tax is copied back, and the Ledger
total stays without GST. If the plugin is set so that Fleetbase works out the
tax, it sends Ledger's tax of 0, so the invoice gets no GST or fails to sync.

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

### New Fleetbase releases

`.github/workflows/fleetbase-compat.yml` builds a full Fleetbase release with
the extension installed and checks that the extension works on it. Every six
hours it looks up the latest `fleetbase/fleetbase` release and builds it only
when it is new: published in the last 14 days and not tested by any run yet,
once its `fleetbase/fleetbase-api` image is on Docker Hub. Otherwise the run
stops after the lookup (`decide.sh`, tested by `test_decide.sh`). It also
builds the latest release when this package is released (a `v*` tag), and by
hand from the Actions tab (**Run workflow**, with an optional Fleetbase tag
such as `v0.7.74`). A failed release is not retried by the schedule; run it
again by hand after a fix.

It installs the extension from its npm tarball, as the registry would: with
Composer into the release's API image, and with pnpm into the release's
console source, built for production. It then runs the release's own
`docker-compose.yml` and `api/deploy.sh` and checks:

- every migration in `server/migrations` ran, every route is registered,
  and the permissions and policies above exist (`check_install.sh`);
- an administrator can save the example profile, an incomplete profile is
  refused, and a quote preview matches the worked example
  (`api_smoke.py`);
- in Chromium, the Pricing page lists and opens the profile, and the quote
  preview form prices the same quote (`console_smoke.mjs`).

The scripts are in `.github/fleetbase-compat/`. Screenshots and logs are
kept as the run's `fleetbase-<version>-results` artifact for 90 days; a run
with that artifact counts as the release having been tested.
GitHub turns scheduled workflows off after 60 days without commits; turn it
back on from the Actions tab.
