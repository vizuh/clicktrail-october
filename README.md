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
- Backend settings: Site ID, API endpoint, consent integration
  (capability gates + resolver hook), first-party proxy toggle.
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

ClickTrail does not replace your consent platform - it obeys it. The full
normalized consent contract (capabilities, snapshot shape, behavior matrix)
lives in [`docs/consent-compatibility-plan.md`](../../docs/consent-compatibility-plan.md).

- Provider: auto-detect through a custom resolver class. WordPress ClickTrail
  builds read WP Consent API directly; on October, implement
  `Vizuh\ClickTrail\Classes\Consent\ConsentResolverInterface` (returns the
  current `ClickTrail\Consent\ConsentSnapshot`) and register it under
  Settings -> Privacy -> Consent resolver class. Real CMP adapters are deferred.
- Attribution persistence requires `analytics_storage`; ad click-ID storage
  requires `advertising_storage`; hashed-lead forwarding additionally needs an
  explicit granted `ad_user_data` signal (disabled by default).
- On unknown consent: **do not store or send**. Suppressed actions are recorded
  with `suppressionReason()` into diagnostics.
- The resolved consent snapshot is persisted alongside the attribution state and
  travels with every submission (`consent` key on each payload).

## License

MIT - Copyright (c) 2026 Vizuh OÜ. See [LICENSE](LICENSE).
