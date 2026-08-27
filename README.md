English | [Português](README.pt-BR.md) | [Deutsch](README.de.md) | [中文](README.zh-CN.md)

<div align="center">

**vizuh/october-clicktrail**

Carry observed acquisition context into configured October CMS form
submissions.

</div>

[![CI](https://github.com/vizuh/clicktrail-october/actions/workflows/ci.yml/badge.svg)](https://github.com/vizuh/clicktrail-october/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/vizuh/october-clicktrail)](https://packagist.org/packages/vizuh/october-clicktrail)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## Index

- [Why](#why)
- [Installation](#installation)
- [Quick start](#quick-start)
- [Components](#components)
- [Attribution capture](#attribution-capture)
- [Settings](#settings)
- [Consent](#consent)
- [Delivery](#delivery)
- [How it differs](#how-it-differs)
- [Testing](#testing)
- [License](#license)

## Why

ClickTrail attaches stored first-touch and last-touch context to configured
October form submissions. It does not determine which marketing touch caused a
submission. The shared [`clicktrail/php-sdk`](https://github.com/vizuh/clicktrail-php)
core computes the payload using merge rules validated by golden fixtures.

Requires October CMS 3 (Laravel 10 base), PHP 8.1+ and `clicktrail/php-sdk` (^0.1@dev).

## Installation

The repository root **is** the plugin folder. Clone or copy it into an October project:

```bash
cd <october-project>/plugins
mkdir -p vizuh && cd vizuh
git clone https://github.com/vizuh/clicktrail-october clicktrail
php artisan october:up
```

Then enable **ClickTrail** under Settings and enter your Site ID.

## Quick start

Add the tracker to your layout head and the hidden fields to any October form:

```twig
{# layouts/default.htm; inside <head> #}
{% component 'clickTrailTracker' %}

{# any October form #}
<form data-request="onSubmit">
    {% component 'attributionHidden' %}
    ...
</form>
```

A visitor arrives from a paid search ad, browses, then submits the form. The POST now carries the full first-touch context:

```text
ct_utm_source=google          ← first touch, immutable
ct_utm_medium=cpc             ← even after later direct visits
ct_gclid=EAIaIQobChMI...      ← click ID captured with advertising consent
ct_landing_page=https://example.com/promo
ct_initial_referrer=https://www.google.com/
ct_consent_state=granted
```

Every subsequent direct visit changes nothing; first touch stays, stored last touch persists. That merge law lives in the shared SDK, tested, not promised.

## Components

### `clickTrailTracker`; first-party loader

```twig
{% component 'clickTrailTracker' %}
```

Renders one script tag for the configured ClickTrail loader and Site ID. The
host owns the endpoint choice; this component does not inject additional tags.

### `attributionHidden`; attribution fields on any form

```twig
{% component 'attributionHidden' %}
```

Renders one hidden input per collected attribute, mirroring the GTM attribution-variable field list: visitor/session/event IDs, `utm_*`, ad click IDs (`gclid`, `fbclid`, `msclkid`, `ttclid`, ...), landing page, initial referrer and consent state. Values come from the merged pair held in the session; the field values never decide attribution logic themselves.

## Attribution capture

Page displays and October AJAX framework requests are observed automatically (`cms.page.beforeDisplay` / `ajax.beforeRun`). Each request is read as one touch and merged into session state by the shared `TouchMerger`. No glue code needed.

## Settings

All options live under Settings → ClickTrail:

| Setting | Default | Purpose |
|---|---|---|
| Site ID | empty | Identifies this site to your ClickTrail account |
| API endpoint | empty | Where payloads are sent; also serves the loader |
| Consent resolver class | empty | Custom `ConsentResolverInterface` implementation returning the normalized snapshot; empty = all signals "unknown" |
| Attribution persistence requires `analytics_storage` | on | Store nothing without granted analytics consent |
| Ad click-ID storage requires `advertising_storage` | on | Strip gclid/fbclid/... from storage without advertising consent |
| Forward hashed lead data (`ad_user_data`) | off | Extra gate for hashed-lead forwarding; still needs `ad_user_data` granted |
| First-party proxy | off | Serve the ClickTrail loader from your own domain |

## Consent

ClickTrail does not replace your consent platform; it obeys it. The normalized consent contract (capabilities, snapshot shape, behavior matrix) lives in [`docs/consent-compatibility-plan.md`](../../docs/consent-compatibility-plan.md).

- Provider: implement `Vizuh\ClickTrail\Classes\Consent\ConsentResolverInterface` (returns the current `ClickTrail\Consent\ConsentSnapshot`) and register it under Settings → Privacy → Consent resolver class. Real CMP adapters are deferred; the WordPress plugin reads WP Consent API directly.
- On unknown consent: **do not store or send**. Suppressed actions are recorded with `suppressionReason()` into diagnostics.
- The resolved snapshot is persisted alongside the attribution state and travels with every submission (`consent` key on each payload).

## Delivery

Canonical payloads are serialized against the stored attribution pair by the shared SDK (`schema_version`-stamped, dotted `attribution.*` keys). A scheduled queue-flush hook is registered and ready; the persisted-event transport ships once the clicktrail-php parity gate passes.

## How it differs

| Typical analytics setup | ClickTrail for October |
|---|---|
| Sessions and pages in a dashboard | Campaign, keyword, click ID and landing page on **each submission record** |
| Client-side tags you maintain yourself | Two Twig components, one first-party script |
| Attribution logic duplicated per platform | One deterministic engine, fixture-tested across WordPress, GTM and PHP integrations |

## Testing

GitHub Actions CI lints all PHP files on every push ([workflow](https://github.com/vizuh/clicktrail-october/blob/main/.github/workflows/ci.yml)).

## License

MIT; Copyright (c) 2026 Vizuh OÜ. See [LICENSE](LICENSE).
