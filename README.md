# Fleetbase Enhanced Pricing

Enhanced Pricing is a [Fleetbase](https://www.fleetbase.io) extension that
prices Fleet-Ops orders from pricing profiles. A profile describes a
business's products, service rates, fees, subtotal fees and manual charges as
configuration. Each customer can have their own rate cards and overrides, and
any global value can change on a date set ahead of time.

The extension names no business's products. Every product, rate and fee is
configuration, so the same code prices a courier, a moving company or a
rental business.

## Requirements

**Fleetbase 0.8.1 or later.** The extension requires `fleetbase/core-api`
`^2.0`, and its compatibility workflow last passed on Fleetbase v0.8.1.
Fleetbase v0.7 and earlier use core-api 1.x, so Composer refuses to install
the extension there. PHP `^8.2` and Node `>= 22` are also required; see
[docs/SETUP.md](docs/SETUP.md#requirements).

## Quick start

From the Fleetbase directory, install the extension from the Fleetbase
registry:

```bash
flb install @unchartedwaters/enhanced-pricing-engine
```

`flb install` only adds the packages. From the Fleetbase API directory, run
`php artisan migrate --force` and `php artisan fleetbase:create-permissions`
(both are part of Fleetbase's `api/deploy.sh`), then rebuild or restart the
console so it includes the engine.

An administrator then gives the `enhanced-pricing` permissions to roles or
users in the console's IAM settings. Pricing appears in the console header,
or under its "⋯" menu when the header is full.

The rest of setup, including the permissions, is in
[docs/SETUP.md](docs/SETUP.md).

## What this release contains

- The profile format and its checks: every problem is found before a profile
  is saved, with the path of the value and a translated message.
- The pricing engine: a plain PHP class that prices one order from a profile,
  a customer's overrides, the dated changes in effect and the chosen rate card
  row, in exact arithmetic, into invoice lines with quantities and unit
  amounts.
- The tables that store profiles and their versions, customers, rate cards and
  dated changes, and the console's **Pricing** page: profiles and their
  versions, the profile editor, subtotal fees and their dated changes, each
  customer's overrides and rate cards (read-only), and a quote preview.
- Tests for every rule, and the worked examples priced end to end.

Pricing orders as they are made, and the order form and order details
screens, arrive in later releases.

## Documentation

- [Profile format](docs/profile-format.md): the profile's JSON, how each rule
  prices an order, overrides, dated changes, limits and errors.
- [Pricing engine](docs/pricing-engine.md): how to call the engine and read
  its quote, with an example.
- [Holiday calendars](docs/holidays.md): filling holiday lists by country and
  state or province, and adding a country.
- [Setup](docs/SETUP.md): requirements, install and publishing.
- [Contributing](CONTRIBUTING.md): development setup, tests and conventions.

## License

AGPL-3.0-or-later. See [LICENSE.md](LICENSE.md).
