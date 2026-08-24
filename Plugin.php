<?php

declare(strict_types=1);

namespace Vizuh\ClickTrail;

use System\Classes\PluginBase;
use Vizuh\ClickTrail\Classes\AttributionObserver;
use Vizuh\ClickTrail\Components\AttributionHidden;
use Vizuh\ClickTrail\Components\ClickTrailTracker;
use Vizuh\ClickTrail\Models\Settings;

/**
 * ClickTrail - campaign, keyword, click-ID and landing-page attribution
 * for October CMS form submissions.
 *
 * Positioning: NOT analytics. This plugin answers one question - which
 * marketing touch created this form submission?
 *
 * Installation: clone or copy this repository to plugins/vizuh/clicktrail
 * (the repository root IS the plugin folder).
 *
 * Deterministic attribution logic is delegated entirely to the shared core
 * package clicktrail/php-sdk (namespace ClickTrail\); nothing is
 * reimplemented here.
 */
class Plugin extends PluginBase
{
    public function pluginDetails(): array
    {
        return [
            'name'        => 'vizuh.clicktrail::lang.plugin.name',
            'description' => 'vizuh.clicktrail::lang.plugin.description',
            'author'      => 'Vizuh OÜ',
            'homepage'    => 'https://github.com/vizuh/october-clicktrail',
            'icon'        => 'icon-crosshairs',
        ];
    }

    public function registerComponents(): array
    {
        return [
            ClickTrailTracker::class => 'clickTrailTracker',
            AttributionHidden::class => 'attributionHidden',
        ];
    }

    public function registerSettings(): array
    {
        return [
            'settings' => [
                'label'       => 'vizuh.clicktrail::lang.settings.label',
                'description' => 'vizuh.clicktrail::lang.settings.description',
                'category'    => 'vizuh.clicktrail::lang.plugin.name',
                'icon'        => 'icon-crosshairs',
                'class'       => Settings::class,
                'order'       => 500,
                'keywords'    => 'attribution clicktrail utm gclid campaign',
            ],
        ];
    }

    public function boot(): void
    {
        AttributionObserver::subscribe();
    }

    /**
     * Scheduled queue flush stub. The persisted-event delivery transport
     * ships after the clicktrail-php parity gate passes.
     */
    public function registerSchedule($schedule): void
    {
        $schedule->call(static function () {
            // DEFERRED — Phase P2 (reason: ingestion transport in
            // clicktrail/php-sdk is not shipped yet; see PLAN.md parity gate).
        })->everyThirtyMinutes();
    }
}
