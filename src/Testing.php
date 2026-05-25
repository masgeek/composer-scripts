<?php

namespace Masgeek\ComposerScripts;

use Composer\Script\Event;
use Masgeek\ComposerScripts\Support\Artisan;
use Masgeek\ComposerScripts\Support\Runner;

/**
 * Composer script callbacks for running Pest tests and artisan test.
 *
 * All commands accept extra arguments passed after `--`:
 *
 *   composer test -- --filter=SomeTest        # run a single test
 *   composer test:unit -- --stop-on-failure   # extra pest flags
 *   composer pest:coverage -- --min=80        # enforce minimum coverage
 *   composer test:parallel -- --recreate-databases
 *
 * Usage in composer.json:
 *
 *   "test":              "Masgeek\\ComposerScripts\\Testing::run",
 *   "test:unit":         "Masgeek\\ComposerScripts\\Testing::unit",
 *   "test:feature":      "Masgeek\\ComposerScripts\\Testing::feature",
 *   "test:controllers":  "Masgeek\\ComposerScripts\\Testing::controllers",
 *   "test:services":     "Masgeek\\ComposerScripts\\Testing::services",
 *   "test:ci":           "Masgeek\\ComposerScripts\\Testing::ci",
 *   "test:retry":        "Masgeek\\ComposerScripts\\Testing::retry",
 *   "test:dirty":        "Masgeek\\ComposerScripts\\Testing::dirty",
 *   "test:file":         "Masgeek\\ComposerScripts\\Testing::file",
 *   "test:parallel":     "Masgeek\\ComposerScripts\\Testing::parallel",
 *   "pest:test":         "Masgeek\\ComposerScripts\\Testing::file",
 *   "pest:coverage":     "Masgeek\\ComposerScripts\\Testing::coverage",
 *   "pest:coverage:xml": "Masgeek\\ComposerScripts\\Testing::coverageXml",
 *   "pest:coverage-xml": "Masgeek\\ComposerScripts\\Testing::coverageXml",
 */
class Testing
{
    /** Full test run — clears config cache (Laravel) then bails on first failure. */
    public static function run(Event $event): void
    {
        static::clearConfig($event);
        static::pestRun($event, '--bail');
    }

    /** Unit tests only. */
    public static function unit(Event $event): void
    {
        static::pestRun($event, 'tests/Unit');
    }

    /** Feature tests only. */
    public static function feature(Event $event): void
    {
        static::pestRun($event, 'tests/Feature');
    }

    /** Controller tests only. */
    public static function controllers(Event $event): void
    {
        static::pestRun($event, 'tests/Feature/Http/Controllers');
    }

    /** Service tests only. */
    public static function services(Event $event): void
    {
        static::pestRun($event, 'tests/Unit/Services');
    }

    /** Retry only previously-failed tests. */
    public static function retry(Event $event): void
    {
        static::pestRun($event, '--retry --bail');
    }

    /** Run only tests in files changed since the last commit. */
    public static function dirty(Event $event): void
    {
        static::pestRun($event, '--dirty');
    }

    /** CI test run — no bail so all failures are reported. */
    public static function ci(Event $event): void
    {
        static::pestRun($event);
    }

    /**
     * Bare Pest invocation — no default flags.
     *
     * Intended for IDE / file-level runners that append a path argument and as
     * a general pass-through when you want full control over the flags:
     *
     *   composer test:file -- tests/Feature/SmsTest.php --filter=it_sends
     */
    public static function file(Event $event): void
    {
        static::pestRun($event);
    }

    /**
     * Run the full test suite via artisan test --parallel (Laravel only).
     *
     * Gracefully skips when artisan is not found so this is safe in
     * non-Laravel projects. Extra args are forwarded to artisan test.
     *
     *   composer test:parallel -- --recreate-databases
     */
    public static function parallel(Event $event): void
    {
        Artisan::run($event, 'test --parallel');
    }

    /** Run with an HTML/text coverage report. */
    public static function coverage(Event $event): void
    {
        static::pestRun($event, '--coverage');
    }

    /** Run with Clover XML coverage output (for CI/SonarQube). */
    public static function coverageXml(Event $event): void
    {
        static::pestRun($event, '--coverage-clover=storage/coverage/coverage.xml');
    }

    // -------------------------------------------------------------------------

    /**
     * Resolve the pest binary and run it with optional flags + any extra
     * arguments the user passed after `--`.
     */
    private static function pestRun(Event $event, string $flags = ''): void
    {
        $pest = Runner::requireBin($event, 'pest', 'pestphp/pest');
        Runner::run($event, [trim("{$pest} {$flags}") . Runner::args($event)]);
    }

    /** Clear the Laravel config cache before running tests (no-op on non-Laravel projects). */
    private static function clearConfig(Event $event): void
    {
        // Artisan::run() gracefully skips when artisan is not present
        Artisan::run($event, 'config:clear --ansi');
    }
}
