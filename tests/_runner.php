<?php

declare(strict_types=1);

/**
 * Standalone assert runner - no composer, no October runtime needed.
 * Pattern: clicktrail-php/tests/_runner.php. The SDK must be at /sdk
 * (or set CLICKTRAIL_SDK_SRC to its src/ directory):
 *   podman run --rm -v "$PWD":/app -v ../clicktrail-php:/sdk:ro -w /app \
 *     php:8.3-cli php tests/_runner.php
 *
 * October/Laravel pieces ConsentGate touches (Settings, Log, session(), app())
 * are stubbed below so the real gate code runs unmodified.
 */

namespace Vizuh\ClickTrail\Models {
    /** Mirrors SettingModel::get($key, $default): unsaved keys return $default. */
    class Settings
    {
        /** @var array<string, mixed> */
        public static array $data = [];

        public static function get(string $key, mixed $default = null): mixed
        {
            return array_key_exists($key, self::$data) ? self::$data[$key] : $default;
        }
    }
}

namespace Illuminate\Support\Facades {
    class Log
    {
        public static function notice(string $message): void
        {
        }

        public static function warning(string $message): void
        {
        }
    }
}

namespace {
    final class StubSession
    {
        /** @var array<string, mixed> */
        public array $data = [];

        public function get(string $key, mixed $default = null): mixed
        {
            return $this->data[$key] ?? $default;
        }

        public function put(string $key, mixed $value): void
        {
            $this->data[$key] = $value;
        }
    }

    function session(): StubSession
    {
        static $session = null;

        return $session ??= new StubSession();
    }

    function app(string $class): object
    {
        return new $class();
    }

    $sdkSrc = rtrim((string) (getenv('CLICKTRAIL_SDK_SRC') ?: '/sdk/src'), '/');

    spl_autoload_register(function (string $class) use ($sdkSrc): void {
        if (str_starts_with($class, 'ClickTrail\\')) {
            $path = $sdkSrc . '/' . str_replace('\\', '/', substr($class, strlen('ClickTrail\\'))) . '.php';
        } elseif (str_starts_with($class, 'Vizuh\\ClickTrail\\')) {
            // Repo layout: lower-case top-level dir (classes/, models/), rest as namespaced.
            $parts = explode('\\', substr($class, strlen('Vizuh\\ClickTrail\\')));
            $parts[0] = strtolower($parts[0]);
            $path = __DIR__ . '/../' . implode('/', $parts) . '.php';
        } else {
            return;
        }
        if (is_file($path)) {
            require $path;
        }
    });

    use ClickTrail\Consent\ConsentSnapshot;
    use ClickTrail\Consent\ConsentValue;
    use Vizuh\ClickTrail\Classes\Consent\ConsentGate;
    use Vizuh\ClickTrail\Models\Settings;

    $failures = [];

    function check(bool $cond, string $msg): void
    {
        global $failures;
        if (!$cond) {
            $failures[] = $msg;
            fwrite(STDERR, "FAIL: $msg\n");
        }
    }

    /** Run one case; an Error/Exception is a failure, not a runner crash. */
    function run_case(string $name, callable $case): void
    {
        try {
            $case();
        } catch (\Throwable $e) {
            check(false, $name . ' threw ' . get_class($e) . ': ' . $e->getMessage());
        }
    }

    function reset_state(): void
    {
        Settings::$data = [];
        session()->data = [];
    }

    // --- T1: unsaved settings record fails closed ---------------------------------
    run_case('T1', function (): void {
        reset_state();
        check(ConsentGate::allows(ConsentSnapshot::CAP_ANALYTICS) === false, 'T1 empty settings deny analytics');
        check(ConsentGate::allows(ConsentSnapshot::CAP_ADVERTISING_STORAGE) === false, 'T1 empty settings deny advertising_storage');
        check(ConsentGate::hashedLeadForwardingAllowed() === false, 'T1 empty settings deny hashed-lead forwarding');
    });

    // --- T2: explicitly saved "off" toggle still allows (site's own basis) --------
    run_case('T2', function (): void {
        reset_state();
        Settings::$data = ['require_analytics_storage' => false, 'require_advertising_storage' => '0'];
        check(ConsentGate::allows(ConsentSnapshot::CAP_ANALYTICS) === true, 'T2 saved false toggle allows analytics');
        check(ConsentGate::allows(ConsentSnapshot::CAP_ADVERTISING_STORAGE) === true, 'T2 saved "0" toggle allows advertising_storage');
        Settings::$data = ['require_analytics_storage' => true];
        check(ConsentGate::allows(ConsentSnapshot::CAP_ANALYTICS) === false, 'T2 saved true toggle + unknown consent denies');
    });

    // --- T3: resolve() -> storedSnapshot() round-trips without fatal --------------
    run_case('T3', function (): void {
        reset_state();
        $resolved = ConsentGate::resolve();
        $stored = ConsentGate::storedSnapshot();
        check($stored instanceof ConsentSnapshot, 'T3 stored snapshot decodes');
        check($stored?->toArray() === $resolved->toArray(), 'T3 stored snapshot equals resolved');

        $granted = new ConsentSnapshot(
            source: 'custom',
            collectedAt: '2026-11-10T00:00:00.000Z',
            functionalStorage: ConsentValue::Granted,
            analyticsStorage: ConsentValue::Granted,
            advertisingStorage: ConsentValue::Denied,
            adUserData: ConsentValue::Unknown,
            adPersonalization: ConsentValue::Denied,
            policyVersion: 'v2',
            receiptId: 'r-1',
            rawCategoryIds: ['c1', 'c2'],
        );
        session()->put(ConsentGate::SESSION_SNAPSHOT_KEY, $granted->toJson());
        check(ConsentGate::storedSnapshot()?->toArray() === $granted->toArray(), 'T3 granted snapshot round-trips');
    });

    // --- T4: missing / malformed session value -> null, never fatal ---------------
    run_case('T4', function (): void {
        reset_state();
        check(ConsentGate::storedSnapshot() === null, 'T4 no stored snapshot -> null');
        foreach (['not json', '"str"', '123', 'null', '', '[', 42, ['a' => 1]] as $bad) {
            session()->put(ConsentGate::SESSION_SNAPSHOT_KEY, $bad);
            check(ConsentGate::storedSnapshot() === null, 'T4 malformed ' . var_export($bad, true) . ' -> null');
        }
        session()->put(ConsentGate::SESSION_SNAPSHOT_KEY, '{}');
        $empty = ConsentGate::storedSnapshot();
        check($empty?->analyticsStorage === ConsentValue::Unknown, 'T4 "{}" -> all-unknown snapshot (fail closed)');
    });

    if ($failures !== []) {
        fwrite(STDERR, count($failures) . " OCTOBER ASSERTION(S) FAILED\n");
        exit(1);
    }

    echo "OCTOBER CONSENT GATE ASSERTIONS PASSED\n";
}
