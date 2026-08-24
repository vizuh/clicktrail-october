<?php

declare(strict_types=1);

namespace Vizuh\ClickTrail\Classes;

use ClickTrail\Core\AttributionInput;
use ClickTrail\Core\PayloadSerializer;
use ClickTrail\Core\StoredState;
use ClickTrail\Core\TouchMerger;
use Illuminate\Http\Request;
use Vizuh\ClickTrail\Models\Settings;

/**
 * Adapter-side glue between October CMS and the deterministic core.
 *
 * Effects owned here (per the core law): clock, storage (session),
 * request reading. The merge/classify/serialize logic always comes from
 * ClickTrail\Core via the clicktrail/php-sdk autoload.
 */
final class AttributionManager
{
    public const SESSION_STATE_KEY = 'vizuh.clicktrail.state';

    /**
     * Read the current HTTP request as one attribution touch, merge it into
     * the stored first/last-touch pair, and persist the pair in the session.
     */
    public function observeCurrentRequest(?Request $request = null): StoredState
    {
        $request = $request ?: request();
        $stored = StoredState::fromJson(session()->get(self::SESSION_STATE_KEY));

        $input = new AttributionInput(
            query: $request->query(),
            host: (string) $request->getHost(),
            landingPage: $request->fullUrl(),
            referrer: $request->headers->get('referer'),
            touchTimestamp: now()->format('Y-m-d\\TH:i:s.v\\Z'),
        );

        $merged = TouchMerger::observe($stored, $input);
        session()->put(self::SESSION_STATE_KEY, $merged->toJson());

        return $merged;
    }

    /**
     * Serialize a canonical schema_version-stamped event payload against the
     * currently stored attribution pair.
     *
     * @param array<string, mixed> $extra
     * @return array<string, mixed>
     */
    public function serializeEvent(string $eventName, array $extra = [], ?StoredState $state = null): array
    {
        $state = $state ?: StoredState::fromJson(
            session()->get(self::SESSION_STATE_KEY)
        );

        $serializer = new PayloadSerializer();

        return $serializer->serialize(
            (string) Settings::get('site_id'),
            ['name' => $eventName],
            $state,
            $extra,
        );
    }
}
