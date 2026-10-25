# Fleetbase Enhanced Pricing

Enhanced Pricing is a [Fleetbase](https://www.fleetbase.io) extension that
prices Fleet-Ops orders from pricing profiles. A profile describes a
business's products, service rates, fees, subtotal fees and manual charges as
configuration. Each customer can have their own rate cards and overrides, and
any global value can change on a date set ahead of time.

The extension names no business's products. Every product, rate and fee is
configuration, so the same code prices a courier, a moving company or a
rental business.

## What this release contains

- The profile format and its checks: every problem is found before a profile
  is saved, with the path of the value and a translated message.
- The pricing engine: a plain PHP class that prices one order from a profile,
  a customer's overrides, the dated changes in effect and the chosen rate card
  row, in exact arithmetic, into invoice lines with quantities and unit
  amounts.
- Tests for every rule, and the worked examples priced end to end.

Saving profiles, rate cards and overrides, pricing orders as they are made,
and the console screens arrive in later releases. Until then the package
installs, loads and changes nothing in Fleetbase.

## Documentation

- [Profile format](docs/profile-format.md): the profile's JSON, how each rule
  prices an order, overrides, dated changes, limits and errors.
- [Pricing engine](docs/pricing-engine.md): how to call the engine and read
  its quote, with an example.
- [Setup](docs/SETUP.md): requirements, install and publishing.
- [Contributing](CONTRIBUTING.md): development setup, tests and conventions.

## License

AGPL-3.0-or-later. See [LICENSE.md](LICENSE.md).
