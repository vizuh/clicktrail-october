<?php

declare(strict_types=1);

namespace Vizuh\ClickTrail\Classes\Consent;

use ClickTrail\Consent\ConsentSnapshot;

/**
 * Platform consent hook (normalized contract: docs/consent-compatibility-plan.md).
 *
 * Returns the CURRENT normalized ConsentSnapshot, or null when no CMP state
 * is available - callers then treat every signal as "unknown" (= denied).
 *
 * On WordPress, ClickTrail builds read WP Consent API directly; on October
 * this interface is the custom-resolver class hook configured in the plugin
 * settings. Real CMP adapters are DEFERRED and are NOT part of this plugin -
 * ship your own implementation of this interface for CookieYes/Cookiebot/
 * iubenda/... server-side state.
 */
interface ConsentResolverInterface
{
    public function currentSnapshot(): ?ConsentSnapshot;
}
