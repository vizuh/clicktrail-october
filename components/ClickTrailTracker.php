<?php

declare(strict_types=1);

namespace Vizuh\ClickTrail\Components;

use Cms\Classes\ComponentBase;
use Vizuh\ClickTrail\Models\Settings;

/**
 * Renders the first-party ClickTrail loader script tag with the configured
 * site ID. This is the ONLY script the plugin ever injects - no third-party
 * trackers, ever.
 */
class ClickTrailTracker extends ComponentBase
{
    public string $siteId = '';
    public string $loaderUrl = '';

    public function componentDetails(): array
    {
        return [
            'name'        => 'vizuh.clicktrail::lang.components.tracker.name',
            'description' => 'vizuh.clicktrail::lang.components.tracker.description',
        ];
    }

    public function onRun(): void
    {
        $this->siteId = trim((string) Settings::get('site_id'));

        $endpoint = rtrim((string) Settings::get('api_endpoint'), '/');
        $this->loaderUrl = $endpoint === '' ? '' : $endpoint . '/loader.js';
    }
}
