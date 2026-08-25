English | [Português](README.pt-BR.md) | [Deutsch](README.de.md) | [中文](README.zh-CN.md)

<div align="center">

**vizuh/october-clicktrail**

Sehen Sie, welche Kampagne, welches Keyword, welche Click-ID und welche Landingpage jede Formular-Übermittlung in October CMS erzeugt hat.

</div>

[![CI](https://github.com/vizuh/clicktrail-october/actions/workflows/ci.yml/badge.svg)](https://github.com/vizuh/clicktrail-october/actions/workflows/ci.yml)
[![Packagist Version](https://img.shields.io/packagist/v/vizuh/october-clicktrail)](https://packagist.org/packages/vizuh/october-clicktrail)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## Inhalt

- [Warum](#warum)
- [Installation](#installation)
- [Schnellstart](#schnellstart)
- [Komponenten](#komponenten)
- [Attributionserfassung](#attributionserfassung)
- [Einstellungen](#einstellungen)
- [Consent](#consent)
- [Auslieferung](#auslieferung)
- [Wie es sich unterscheidet](#wie-es-sich-unterscheidet)
- [Tests](#tests)
- [Lizenz](#lizenz)

## Warum

Wieder kein Analytics-Dashboard. ClickTrail stempelt jede October-Formular-Übermittlung mit dem Marketing-Touch, der sie erzeugt hat — deterministische First-touch-/Last-touch-Attribution, berechnet vom gemeinsamen Kern [`clicktrail/php-sdk`](https://github.com/vizuh/clicktrail-php) und gegen dieselben Golden Fixtures validiert wie jede andere ClickTrail-Integration.

Benötigt October CMS 3 (Laravel-10-Basis), PHP 8.1+ und `clicktrail/php-sdk` (^0.1@dev).

## Installation

Das Repository-Wurzelverzeichnis **ist** der Plugin-Ordner. Klonen oder kopieren Sie es in ein October-Projekt:

```bash
cd <october-project>/plugins
mkdir -p vizuh && cd vizuh
git clone https://github.com/vizuh/clicktrail-october clicktrail
php artisan october:up
```

Aktivieren Sie dann **ClickTrail** unter Einstellungen und tragen Sie Ihre Site ID ein.

## Schnellstart

Fügen Sie den Tracker in den `<head>` Ihres Layouts und die Hidden Fields in jedes October-Formular ein:

```twig
{# layouts/default.htm — innerhalb von <head> #}
{% component 'clickTrailTracker' %}

{# beliebiges October-Formular #}
<form data-request="onSubmit">
    {% component 'attributionHidden' %}
    ...
</form>
```

Ein Besucher kommt über eine bezahlte Suchanzeige, stöbert herum und sendet das Formular. Der POST trägt nun den vollständigen First-touch-Kontext:

```text
ct_utm_source=google          ← First Touch, unveränderlich
ct_utm_medium=cpc             ← auch nach späteren Direktbesuchen
ct_gclid=EAIaIQobChMI...      ← Click-ID, mit Advertising-Consent erfasst
ct_landing_page=https://example.com/promo
ct_initial_referrer=https://www.google.com/
ct_consent_state=granted
```

Jeder spätere Direktbesuch ändert nichts — der First Touch bleibt, der gespeicherte Last Touch besteht fort. Dieses Merge-Gesetz steckt im gemeinsamen SDK — getestet, nicht versprochen.

## Komponenten

### `clickTrailTracker` — First-Party-Loader

```twig
{% component 'clickTrailTracker' %}
```

Rendert genau ein Script-Tag — den ClickTrail-Loader von Ihrem konfigurierten Endpoint, markiert mit Ihrer Site ID. Keine Drittanbieter-Skripte, niemals.

### `attributionHidden` — Attributionsfelder in jedem Formular

```twig
{% component 'attributionHidden' %}
```

Rendert je gesammeltem Attribut ein Hidden Input, gespiegelt nach der GTM-Attributionsvariablen-Liste: Besucher-/Session-/Event-IDs, `utm_*`, Ad-Click-IDs (`gclid`, `fbclid`, `msclkid`, `ttclid`, ...), Landingpage, initialer Referrer und Consent-Status. Die Werte stammen aus dem in der Session gespeicherten zusammengeführten Paar; die Feldwerte entscheiden nie selbst über die Attributionslogik.

## Attributionserfassung

Seitenaufrufe und AJAX-Framework-Anfragen von October werden automatisch beobachtet (`cms.page.beforeDisplay` / `ajax.beforeRun`). Jede Anfrage wird als ein Touch gelesen und vom gemeinsamen `TouchMerger` in den Session-Zustand zusammengeführt. Kein zusätzlicher Code nötig.

## Einstellungen

Alle Optionen finden Sie unter Einstellungen → ClickTrail:

| Einstellung | Standard | Zweck |
|---|---|---|
| Site ID | leer | Identifiziert diese Site gegenüber Ihrem ClickTrail-Konto |
| API-Endpoint | leer | Wohin Payloads gesendet werden; liefert auch den Loader |
| Consent-Resolver-Klasse | leer | Eigene `ConsentResolverInterface`-Implementierung, die den normalisierten Snapshot zurückgibt; leer = alle Signale „unknown" |
| Persistenz erfordert `analytics_storage` | an | Ohne erteilten Analytics-Consent nichts speichern |
| Click-ID-Speicherung erfordert `advertising_storage` | an | gclid/fbclid/... ohne Advertising-Consent aus der Speicherung entfernen |
| Gehashte Lead-Daten weiterleiten (`ad_user_data`) | aus | Zusätzliche Schranke für das Weiterleiten gehashter Lead-Daten; erfordert weiterhin erteiltes `ad_user_data` |
| First-Party-Proxy | aus | ClickTrail-Loader von Ihrer eigenen Domain ausliefern |

## Consent

ClickTrail ersetzt Ihre Consent-Plattform nicht — es gehorcht ihr. Der normalisierte Consent-Vertrag (Capabilities, Snapshot-Form, Verhaltensmatrix) liegt in [`docs/consent-compatibility-plan.md`](../../docs/consent-compatibility-plan.md).

- Anbieter: Implementieren Sie `Vizuh\ClickTrail\Classes\Consent\ConsentResolverInterface` (liefert den aktuellen `ClickTrail\Consent\ConsentSnapshot`) und registrieren Sie ihn unter Einstellungen → Datenschutz → Consent resolver class. Echte CMP-Adapter sind zurückgestellt; das WordPress-Plugin liest direkt die WP Consent API.
- Bei unbekanntem Consent: **nichts speichern oder senden**. Unterdrückte Aktionen werden mit `suppressionReason()` in der Diagnostik protokolliert.
- Der aufgelöste Snapshot wird neben dem Attributionszustand gespeichert und reist mit jeder Übermittlung (`consent`-Schlüssel in jedem Payload).

## Auslieferung

Kanonische Payloads werden vom gemeinsamen SDK gegen das gespeicherte Attributionspaar serialisiert (mit `schema_version`, punktierten `attribution.*`-Schlüsseln). Ein geplanter Queue-Flush-Hook ist registriert und bereit; der Transport persistierter Events folgt, sobald das Parity-Gate von clicktrail-php passiert ist.

## Wie es sich unterscheidet

| Übliches Analytics-Setup | ClickTrail für October |
|---|---|
| Sessions und Seiten im Dashboard | Kampagne, Keyword, Click-ID und Landingpage **am Datensatz jeder Übermittlung** |
| Client-seitige Tags in Eigenpflege | Zwei Twig-Komponenten, ein First-Party-Skript |
| Attributionslogik pro Plattform dupliziert | Eine deterministische Engine, fixture-geprüft über WordPress, GTM und PHP-Integrationen |

## Tests

CI in GitHub Actions lintet bei jedem Push alle PHP-Dateien ([Workflow](https://github.com/vizuh/clicktrail-october/blob/main/.github/workflows/ci.yml)).

## Lizenz

MIT — Copyright (c) 2026 Vizuh OÜ. Siehe [LICENSE](LICENSE).
