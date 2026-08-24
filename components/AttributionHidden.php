<?php

declare(strict_types=1);

namespace Vizuh\ClickTrail\Components;

use Cms\Classes\ComponentBase;
use ClickTrail\Conventions\Stable;
use ClickTrail\Core\StoredState;
use Vizuh\ClickTrail\Classes\AttributionManager;
use Vizuh\ClickTrail\Classes\Consent\ConsentGate;
use Vizuh\ClickTrail\Models\Settings;

/**
 * Renders hidden form fields carrying the full attribution context -
 * mirroring the ClickTrail GTM attribution-variable field list: visitor /
 * session / event IDs, utm_* values, ad click IDs, landing page, initial
 * referrer and consent state.
 *
 * Values come from the merged first-touch pair held in the session; the
 * field VALUES never decide attribution logic themselves.
 */
class AttributionHidden extends ComponentBase
{
    /** @var array<string, string> name => value for the hidden inputs */
    public array $fields = [];

    public function componentDetails(): array
    {
        return [
            'name'        => 'vizuh.clicktrail::lang.components.attribution_hidden.name',
            'description' => 'vizuh.clicktrail::lang.components.attribution_hidden.description',
        ];
    }

    public function onRun(): void
    {
        $this->fields = $this->buildFields();
    }

    /** @return array<string, string> */
    protected function buildFields(): array
    {
        $stored = StoredState::fromJson(
            session()->get(AttributionManager::SESSION_STATE_KEY)
        );

        $first = $stored->first;
        $fields = [];

        // Identity / session identifiers (adapter-owned effects).
        $fields['ct_visitor_id'] = (string) session()->getId();
        $fields['ct_session_id'] = (string) session()->getId();
        $fields['ct_event_id'] = $this->newEventId();
        $fields['ct_site_id'] = (string) Settings::get('site_id');

        // First-touch UTM values (canonical conventions).
        foreach (
            [
                'utm_source' => $first?->source,
                'utm_medium' => $first?->medium,
                'utm_campaign' => $first?->campaign,
                'utm_content' => $first?->content,
                'utm_term' => $first?->term,
                'utm_id' => $first?->utmId,
            ] as $key => $value
        ) {
            $fields['ct_' . $key] = (string) $value;
        }

        // Ad click IDs captured on the first touch.
        foreach (Stable::CLICK_ID_KEYS as $key) {
            $fields['ct_' . $key] = (string) ($first?->clickIds[$key] ?? '');
        }

        // Landing page, initial referrer, consent state.
        $fields['ct_landing_page'] = (string) $first?->landingPage;
        $fields['ct_initial_referrer'] = (string) $first?->referrer;
        $snapshot = ConsentGate::storedSnapshot();
        $fields['ct_consent_state'] = $snapshot === null
            ? 'unknown'
            : $snapshot->analyticsStorage->value;

        return $fields;
    }

    private function newEventId(): string
    {
        try {
            return bin2hex(random_bytes(16));
        } catch (\Throwable) {
            return uniqid('ctevt', true);
        }
    }
}
