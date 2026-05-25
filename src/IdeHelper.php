<?php

namespace Masgeek\ComposerScripts;

use Composer\Script\Event;
use Masgeek\ComposerScripts\Support\Artisan;
use Masgeek\ComposerScripts\Support\Runner;

/**
 * Composer script callbacks for barryvdh/laravel-ide-helper.
 *
 * All methods silently skip if the binary is not installed so this class
 * is safe to wire up even when ide-helper is only installed in some
 * environments.
 *
 * Usage in composer.json:
 *
 *   "meta:helper": "Masgeek\\ComposerScripts\\IdeHelper::generate",
 *   "meta:ide":    "Masgeek\\ComposerScripts\\IdeHelper::meta",
 *   "meta:models": "Masgeek\\ComposerScripts\\IdeHelper::models",
 *   "meta:all":    "Masgeek\\ComposerScripts\\IdeHelper::all",
 *   "model:gen":   "Masgeek\\ComposerScripts\\IdeHelper::modelGen",
 */
class IdeHelper
{
    /** Generate _ide_helper.php (facades + service container). */
    public static function generate(Event $event): void
    {
        static::artisan($event, 'ide-helper:generate');
    }

    /** Generate .phpstorm.meta.php. */
    public static function meta(Event $event): void
    {
        static::artisan($event, 'ide-helper:meta');
    }

    /** Annotate Eloquent models with @property docblocks. */
    public static function models(Event $event): void
    {
        static::artisan($event, 'ide-helper:models --write');
    }

    /** Run all three ide-helper commands in sequence. */
    public static function all(Event $event): void
    {
        static::generate($event);
        static::meta($event);
        static::models($event);
    }

    /**
     * Full model-generation workflow:
     *   1. artisan code:models          — regenerate Base model classes
     *   2. artisan ide-helper:models    — annotate with @property docblocks
     *   3. pint app/Models/Base         — auto-format the generated files
     *
     * Step 1 requires masgeek/reliese-laravel-model-gen (or any package that
     * registers the `code:models` artisan command). Steps 2 and 3 gracefully
     * skip when ide-helper / pint are not installed.
     *
     * Replaces the common inline chain:
     *   @php artisan code:models && @php artisan ide-helper:models --write && pint app/Models/Base
     */
    public static function modelGen(Event $event): void
    {
        // 1. regenerate model classes (graceful — warns if artisan absent)
        Artisan::run($event, 'code:models');

        // 2. annotate with IDE-helper docblocks
        Artisan::run($event, 'ide-helper:models --write');

        // 3. format only the files that changed (optional — skip if pint absent)
        $pint = Runner::bin($event, 'pint');
        if ($pint !== null) {
            Runner::run($event, ["{$pint} --dirty"]);
        } else {
            $event->getIO()->write(
                '<warning>Skipping pint format — laravel/pint not installed.</warning>'
            );
        }
    }

    // -------------------------------------------------------------------------

    private static function artisan(Event $event, string $command): void
    {
        Artisan::run($event, $command);
    }
}
