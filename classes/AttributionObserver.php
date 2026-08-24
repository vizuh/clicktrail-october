<?php

declare(strict_types=1);

namespace Vizuh\ClickTrail\Classes;

use Illuminate\Support\Facades\Event;

/**
 * Wires October CMS lifecycle events to the shared attribution core.
 *
 * Every page display and every AJAX-framework request is observed as one
 * potential touch. Form-submission payloads are then stamped with the
 * merged pair by the attributionHidden component and the queued delivery
 * lane (see Plugin.php registerSchedule stub).
 *
 * No attribution logic lives here - TouchMerger/PayloadSerializer are the
 * single source of truth (clicktrail/php-sdk).
 */
final class AttributionObserver
{
    public static function subscribe(): void
    {
        Event::listen('cms.page.beforeDisplay', static function () {
            app(AttributionManager::class)->observeCurrentRequest();
        });

        // October AJAX framework: handlers may carry form data; observing the
        // request keeps the session state current before any handler reads it.
        Event::listen('ajax.beforeRun', static function () {
            app(AttributionManager::class)->observeCurrentRequest();
        });
    }
}
