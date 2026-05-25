<?php

namespace Masgeek\ComposerScripts;

use Composer\Script\Event;
use Masgeek\ComposerScripts\Support\Runner;

/**
 * Composer script callbacks for dependency analysis tools.
 *
 * Usage in composer.json:
 *
 *   "check-deps": "Masgeek\\ComposerScripts\\Deps::checkUnused",
 */
class Deps
{
    /** List packages that are required but not referenced in code (composer-unused). */
    public static function checkUnused(Event $event): void
    {
        $bin = Runner::requireBin($event, 'composer-unused', 'icanhazstring/composer-unused');
        Runner::run($event, ["{$bin}"]);
    }
}
