<?php

declare(strict_types=1);

namespace Vizuh\ClickTrail\Classes;

use ClickTrail\Consent\ConsentSnapshot;
use ClickTrail\Core\AttributionInput;
use ClickTrail\Core\PayloadSerializer;
use ClickTrail\Core\StoredState;
use ClickTrail\Core\TouchMerger;
use Illuminate\Http\Request;
use Vizuh\ClickTrail\Classes\Consent\ConsentGate;
use Vizuh\ClickTrail\Models\Settings;

/**
 * Adapter-side glue between October CMS and the deterministic core.
 *
 * Effects owned here (per the core law): clock, storage (session),
 * request reading. The merge/classify/serialize logic always comes from
 * ClickTrail\Core via the clicktrail/php-sdk autoload.
 *
 * Consent contract: attribution persistence requires analytics_storage and
 * ad click-ID storage requires advertising_storage (per settings); on
 * denied/unknown nothing is stored or sent and the reason lands in
 * diagnostics. The resolved ConsentSnapshot is persisted alongside the
 * attribution state and attached to every serialized payload.
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

        if (!ConsentGate::allows(ConsentSnapshot::CAP_ANALYTICS)) {
            // Unknown/denied analytics consent: do not store or send.
            return StoredState::empty();
        }

        $query = $request->query();

        if (!ConsentGate::allows(ConsentSnapshot::CAP_ADVERTISING_STORAGE)) {
            // Ad click-ID keys are stripped before they can be persisted.
            $query = array_diff_key($query, array_fill_keys(\ClickTrail\Conventions\Stable::CLICK_ID_KEYS, true));
        }

        $stored = StoredState::fromJson(session()->get(self::SESSION_STATE_KEY));

        $input = new AttributionInput(
            query: $query,
            host: (string) $request->getHost(),
            landingPage: $request->fullUrl(),
            referrer: $request->headers->get('referer'),
            touchTimestamp: now()->format('Y-m-d\\TH:i:s.v\\Z'),
        );

        $merged = TouchMerger::observe($stored, $input);
        session()->put(self::SESSION_STATE_KEY, $merged->toJson());
        ConsentGate::resolve();

        return $merged;
    }

    /**
     * Serialize a canonical schema_version-stamped event payload against the
     * currently stored attribution pair. The current consent snapshot rides
     * along under the top-level "consent" key.
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
            $extra + ['consent' => json_decode(ConsentGate::resolve()->toJson(), true)],
        );
    }
}
