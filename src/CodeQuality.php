<?php

namespace Masgeek\ComposerScripts;

use Composer\Script\Event;
use Masgeek\ComposerScripts\Support\Runner;

/**
 * Composer script callbacks for code quality tools (Pint + PHPStan + SonarQube).
 *
 * All commands accept extra arguments passed after `--`:
 *
 *   composer pint:fix -- app/Http             # fix only that directory
 *   composer lint:analyse -- --level=8        # pass extra PHPStan flags
 *   composer sonar -- -Dsonar.branch.name=dev # extra sonar-scanner flags
 *
 * Usage in composer.json:
 *
 *   "pint:fix":     "Masgeek\\ComposerScripts\\CodeQuality::pintFix",
 *   "pint:fix:all": "Masgeek\\ComposerScripts\\CodeQuality::pintFixAll",
 *   "pint:check":   "Masgeek\\ComposerScripts\\CodeQuality::pintCheck",
 *   "pint:repair":  "Masgeek\\ComposerScripts\\CodeQuality::pintRepair",
 *   "lint:fix":     "Masgeek\\ComposerScripts\\CodeQuality::pintFix",
 *   "lint:fix-all": "Masgeek\\ComposerScripts\\CodeQuality::pintFixAll",
 *   "lint:analyse": "Masgeek\\ComposerScripts\\CodeQuality::analyse",
 *   "lint:check":   "Masgeek\\ComposerScripts\\CodeQuality::check",
 *   "lint:all":     "Masgeek\\ComposerScripts\\CodeQuality::all",
 *   "sonar":        "Masgeek\\ComposerScripts\\CodeQuality::sonar",
 */
class CodeQuality
{
    /** Fix only dirty (git-changed) files with Pint. Extra args are appended. */
    public static function pintFix(Event $event): void
    {
        $pint = Runner::requireBin($event, 'pint', 'laravel/pint');
        Runner::run($event, ["{$pint} --dirty" . Runner::args($event)]);
    }

    /** Fix all files with Pint. Extra args are appended (e.g. a path to limit scope). */
    public static function pintFixAll(Event $event): void
    {
        $pint = Runner::requireBin($event, 'pint', 'laravel/pint');
        Runner::run($event, ["{$pint}" . Runner::args($event)]);
    }

    /** Check formatting without making changes (non-zero exit on violations). */
    public static function pintCheck(Event $event): void
    {
        $pint = Runner::requireBin($event, 'pint', 'laravel/pint');
        Runner::run($event, ["{$pint} --test" . Runner::args($event)]);
    }

    /** Repair files that Pint could not auto-fix via --test. */
    public static function pintRepair(Event $event): void
    {
        $pint = Runner::requireBin($event, 'pint', 'laravel/pint');
        Runner::run($event, ["{$pint} --repair" . Runner::args($event)]);
    }

    /** Run PHPStan static analysis. Extra args are appended. */
    public static function analyse(Event $event): void
    {
        $phpstan = Runner::requireBin($event, 'phpstan', 'phpstan/phpstan');
        Runner::run($event, ["{$phpstan} analyse --memory-limit=256M" . Runner::args($event)]);
    }

    /** Alias for analyse — consistent with 'check' naming. */
    public static function check(Event $event): void
    {
        static::analyse($event);
    }

    /** Run both Pint check and PHPStan in sequence — useful for CI. */
    public static function all(Event $event): void
    {
        static::pintCheck($event);
        static::analyse($event);
    }

    /** Run SonarQube scanner. Extra args are appended (e.g. -Dsonar.branch.name=dev). */
    public static function sonar(Event $event): void
    {
        Runner::run($event, ['sonar-scanner' . Runner::args($event)]);
    }
}
