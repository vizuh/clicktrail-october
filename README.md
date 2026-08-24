# ClickTrail for October CMS

**See which campaign, keyword, click ID and landing page created each form
submission.**

Not another analytics dashboard. ClickTrail stamps every October form
submission with the marketing touch that produced it, then hands the
canonical payload to your ClickTrail account.

Built on the shared deterministic core [`clicktrail/php-sdk`](https://github.com/vizuh/clicktrail-php)
(schema_version / classifier_version parity with the JS engine).

## Features

- First-touch / last-touch attribution with the same merge laws as the
  ClickTrail engine (first-touch immutability, last-non-direct,
  click-ID-aware guard).
- `clickTrailTracker` Twig component: renders the **first-party**
  ClickTrail loader with your Site ID. No third-party scripts, ever.
- `attributionHidden` Twig component: hidden form fields mirroring the
  GTM attribution-variable field list (visitor/session/event IDs, utm_*,
  ad click IDs, landing page, initial referrer, consent state).
- Page-display and October AJAX observation wired to the shared core.
- Backend settings: Site ID, API endpoint, consent-required toggle,
  first-party proxy toggle.
- Scheduled queue-flush hook ready for the delivery lane.

## Requirements

- October CMS 3 (Laravel 10 base)
- PHP 8.1+
- `clicktrail/php-sdk` (^0.1@dev)

## Installation

This repository root **is** the plugin folder. Clone or copy it into an
October project:

```
cd <october-project>/plugins
mkdir -p vizuh && cd vizuh
git clone https://github.com/vizuh/october-clicktrail clicktrail
php artisan october:up
```

Then enable **ClickTrail** in Settings and configure the Site ID.

## Usage

Add the tracker to your layout head:

```twig
{% component 'clickTrailTracker' %}
```

Add hidden fields to any October form:

```twig
{% component 'attributionHidden' %}
```

Every submitted form now carries the full attribution context.

## Events

Canonical ClickTrail events (`lead.submitted`, `appointment.booked`,
`sale.completed`, ...) flow through the shared SDK contracts; platform-native
form events map onto them in the delivery lane (deferred until the parity
gate passes).

## Consent

Adapters obey host consent state; this plugin never acts as a CMP. When
*Require consent* is enabled, tracking waits for a permitted consent state.

## License

MIT - Copyright (c) 2026 Vizuh OÜ. See [LICENSE](LICENSE).
