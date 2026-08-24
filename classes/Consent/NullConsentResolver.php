<?php

declare(strict_types=1);

namespace Vizuh\ClickTrail\Classes\Consent;

use ClickTrail\Consent\ConsentSnapshot;
use ClickTrail\Consent\ConsentValue;

/**
 * Default resolver used when no CMP resolver class is configured.
 * Every signal is "unknown" and unknown is DENIED (marketplace-safe
 * default): nothing is stored and nothing is sent.
 */
final class NullConsentResolver implements ConsentResolverInterface
{
    public function currentSnapshot(): ?ConsentSnapshot
    {
        return new ConsentSnapshot(
            source: 'custom',
            collectedAt: gmdate('Y-m-d\TH:i:s.v\Z'),
            functionalStorage: ConsentValue::Unknown,
            analyticsStorage: ConsentValue::Unknown,
            advertisingStorage: ConsentValue::Unknown,
            adUserData: ConsentValue::Unknown,
            adPersonalization: ConsentValue::Unknown,
        );
    }
}
