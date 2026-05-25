<?php

namespace Masgeek\ComposerScripts\Support;

use Composer\Script\Event;
use RuntimeException;

/**
 * Locates and invokes the artisan CLI for the current project.
 *
 * Two execution modes:
 *
 *  - run()     → graceful: prints a warning and returns when artisan is absent.
 *                Use this for optional Laravel commands (IDE helpers, clears, etc.)
 *
 *  - require() → strict: throws when artisan is absent.
 *                Use this when the command has no meaningful non-Laravel equivalent.
 */
class Artisan
{
    /**
     * Return the artisan invocation string for the current project, or null.
     *
     * Resolution order:
     *   1. vendor/bin/artisan  (symlink placed by laravel/framework)
     *   2. php artisan         (artisan script in project root)
     */
    public static function find(Event $event): ?string
    {
        $bin = Runner::bin($event, 'artisan');

        if ($bin !== null) {
            return $bin;
        }

        if (file_exists(getcwd() . DIRECTORY_SEPARATOR . 'artisan')) {
            return 'php artisan';
        }

        return null;
    }

    /**
     * Run an artisan command, gracefully skipping if artisan is not present.
     *
     * Prints a <warning> to stderr and returns without throwing when the project
     * is not a Laravel application.
     *
     * Extra CLI arguments passed after `--` are automatically appended:
     *   composer meta:models -- --reset
     */
    public static function run(Event $event, string $command): void
    {
        $artisan = static::find($event);

        if ($artisan === null) {
            $event->getIO()->writeError(
                "<warning>Skipping '{$command}' — artisan not found (not a Laravel project?).</warning>"
            );
            return;
        }

        Runner::run($event, ["{$artisan} {$command}" . Runner::args($event)]);
    }

    /**
     * Run an artisan command, throwing if artisan is not present.
     *
     * Use when the command only makes sense inside a Laravel project.
     * Extra CLI arguments passed after `--` are automatically appended.
     */
    public static function require(Event $event, string $command): void
    {
        $artisan = static::find($event);

        if ($artisan === null) {
            throw new RuntimeException(
                "artisan not found — '{$command}' requires a Laravel project."
            );
        }

        Runner::run($event, ["{$artisan} {$command}" . Runner::args($event)]);
    }
}
