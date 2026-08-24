<?php

declare(strict_types=1);

return [
    'plugin' => [
        'name' => 'ClickTrail',
        'description' => 'See which campaign, keyword, click ID and landing page created each form submission.',
    ],

    'settings' => [
        'label' => 'ClickTrail',
        'description' => 'Attribution site ID, API endpoint and consent options.',

        'tab_general' => 'General',
        'site_id' => 'Site ID',
        'site_id_comment' => 'Your ClickTrail site identifier.',
        'api_endpoint' => 'API endpoint',
        'api_endpoint_comment' => 'First-party ClickTrail API base URL. The loader is served from this host.',

        'tab_privacy' => 'Privacy',

        'consent_section' => 'Consent integration',
        'consent_section_comment' => 'Provider: auto-detect through the resolver-class hook below. ClickTrail never acts as a CMP - your consent platform owns the banner and the decision; this plugin obeys it (see docs/consent-compatibility-plan.md). WordPress builds read WP Consent API directly.',
        'consent_resolver_class' => 'Consent resolver class',
        'consent_resolver_class_comment' => 'Optional class implementing <code>Vizuh\\ClickTrail\\Classes\\Consent\\ConsentResolverInterface</code> that returns the current normalized ConsentSnapshot from your CMP. Leave empty to run with every signal "unknown". Real CMP adapters are deferred.',
        'require_analytics_storage' => 'Attribution persistence requires analytics_storage',
        'require_analytics_storage_comment' => 'When enabled, first-touch/last-touch attribution is only persisted while analytics_storage consent is granted.',
        'require_advertising_storage' => 'Ad click-ID storage requires advertising_storage',
        'require_advertising_storage_comment' => 'When enabled, ad click IDs (gclid, fbclid, msclkid, ...) are only stored while advertising_storage consent is granted.',
        'forward_hashed_lead_data' => 'Send hashed lead data to ad destinations (ad_user_data)',
        'forward_hashed_lead_data_comment' => 'Disabled by default. When enabled, hashed-lead forwarding still requires an explicit granted ad_user_data signal.',
        'unknown_consent_section' => 'On unknown consent: do not store or send',
        'unknown_consent_section_comment' => 'Unknown is treated as denied everywhere. Suppressed actions are recorded with a reason in diagnostics, and the consent snapshot travels with every submission.',
        'first_party_proxy' => 'First-party proxy',
        'first_party_proxy_comment' => 'Route events through your own domain instead of the API endpoint directly.',
    ],

    'components' => [
        'tracker' => [
            'name' => 'ClickTrail Tracker',
            'description' => 'Renders the first-party ClickTrail loader script with the configured site ID.',
        ],
        'attribution_hidden' => [
            'name' => 'Attribution Hidden Fields',
            'description' => 'Adds hidden form fields with visitor/session/event IDs, utm values, click IDs, landing page, referrer and consent state.',
        ],
    ],
];
