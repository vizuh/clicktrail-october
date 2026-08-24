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
        'consent_required' => 'Require consent',
        'consent_required_comment' => 'Only track and send events when the host consent state permits. The plugin never acts as a CMP.',
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
