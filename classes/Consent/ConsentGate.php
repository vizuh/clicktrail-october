<?php

declare(strict_types=1);

namespace Vizuh\ClickTrail\Classes\Consent;

use ClickTrail\Consent\ConsentBehavior;
use ClickTrail\Consent\ConsentSnapshot;
use Illuminate\Support\Facades\Log;
use Vizuh\ClickTrail\Models\Settings;

/**
 * Adapter-side gate around the shared ConsentBehavior matrix.
 *
 * Settings decide which capability requires which signal:
 *   - attribution persistence requires analytics_storage;
 *   - ad click-ID storage requires advertising_storage;
 *   - hashed-lead forwarding additionally requires the ad_user_data flag.
 *
 * On any denied/unknown signal the caller must not store or send; the
 * suppressionReason() lands in diagnostics for the audit trail. The resolved
 * snapshot is persisted alongside the attribution state so every submission
 * carries the consent decision it was captured under.
 */
final class ConsentGate
{
    public const SESSION_SNAPSHOT_KEY = 'vizuh.clicktrail.consent_snapshot';
    public const SESSION_SUPPRESSION_KEY = 'vizuh.clicktrail.consent_suppression';

    /** Resolve the current snapshot (custom resolver class or NullResolver)
     * and persist it next to the attribution state. */
    public static function resolve(): ConsentSnapshot
    {
        $resolver = self::resolver();
        $snapshot = $resolver->currentSnapshot()
            ?? (new NullConsentResolver())->currentSnapshot();

        session()->put(self::SESSION_SNAPSHOT_KEY, $snapshot->toJson());

        return $snapshot;
    }

    public static function storedSnapshot(): ?ConsentSnapshot
    {
        $json = session()->get(self::SESSION_SNAPSHOT_KEY);

        return $json === null ? null : ConsentSnapshot::fromJson((string) $json);
    }

    /**
     * Whether the given capability is permitted under the current settings
     * and snapshot. Records suppressionReason() into diagnostics when blocked.
     */
    public static function allows(string $capability): bool
    {
        $setting = match ($capability) {
            ConsentSnapshot::CAP_ANALYTICS => 'require_analytics_storage',
            ConsentSnapshot::CAP_ADVERTISING_STORAGE => 'require_advertising_storage',
            default => null,
        };

        // Toggle off = this use does not require CMP consent (site's own basis).
        if ($setting !== null && !Settings::get($setting)) {
            return true;
        }

        $snapshot = self::resolve();

        if (ConsentBehavior::can($snapshot, $capability)) {
            return true;
        }

        self::recordSuppression(
            (string) ConsentBehavior::suppressionReason($snapshot, $capability)
        );

        return false;
    }

    /**
     * Hashed-lead forwarding gate: disabled by default, and when enabled it
     * still needs an explicit granted ad_user_data signal.
     */
    public static function hashedLeadForwardingAllowed(): bool
    {
        if (!Settings::get('forward_hashed_lead_data')) {
            self::recordSuppression('Hashed-lead forwarding is disabled in settings');

            return false;
        }

        return self::allows(ConsentSnapshot::CAP_AD_USER_DATA);
    }

    /** Diagnostics: audit-trail reason for a suppressed action. */
    public static function recordSuppression(string $reason): void
    {
        session()->put(self::SESSION_SUPPRESSION_KEY, $reason);
        Log::notice('ClickTrail suppressed: ' . $reason);
    }

    private static function resolver(): ConsentResolverInterface
    {
        $class = (string) Settings::get('consent_resolver_class');

        if ($class !== '' && class_exists($class) && is_subclass_of($class, ConsentResolverInterface::class)) {
            try {
                /** @var ConsentResolverInterface $instance */
                $instance = app($class);

                return $instance;
            } catch (\Throwable $e) {
                Log::warning('ClickTrail consent resolver failed to instantiate: ' . $e->getMessage());
            }
        } elseif ($class !== '') {
            Log::warning('ClickTrail consent_resolver_class is set but missing or invalid; falling back to unknown-consent behavior');
        }

        return new NullConsentResolver();
    }
}
