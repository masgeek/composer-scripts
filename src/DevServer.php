<?php

namespace Masgeek\ComposerScripts;

use Composer\Config;
use Composer\Script\Event;
use Masgeek\ComposerScripts\Support\Artisan;
use Masgeek\ComposerScripts\Support\Runner;

/**
 * Composer script callbacks for local development processes.
 *
 * All long-running commands call Config::disableProcessTimeout() so Composer
 * never kills them after the default 300-second limit.
 *
 * Usage in composer.json:
 *
 *   "dev":         "Masgeek\\ComposerScripts\\DevServer::serve",
 *   "dev:all":     "Masgeek\\ComposerScripts\\DevServer::serveAll",
 *   "queue:listen":"Masgeek\\ComposerScripts\\DevServer::queueListen",
 */
class DevServer
{
    /** Start the built-in PHP dev server (or artisan serve if available). */
    public static function serve(Event $event): void
    {
        Config::disableProcessTimeout();

        $port = (int) (getenv('DEV_PORT') ?: 8000);

        if (file_exists(getcwd() . '/artisan')) {
            Runner::run($event, ["php artisan serve --port={$port}" . Runner::args($event)]);
        } else {
            Runner::run($event, ["php -S localhost:{$port} -t public" . Runner::args($event)]);
        }
    }

    /**
     * Start server + queue worker + Vite concurrently (Laravel projects).
     * Requires `npx concurrently` to be available.
     */
    public static function serveAll(Event $event): void
    {
        Config::disableProcessTimeout();

        $port = (int) (getenv('DEV_PORT') ?: 8000);

        Runner::run($event, [
            'npx concurrently -c "#93c5fd,#c4b5fd,#fdba74"'
            . " \"php artisan serve --port={$port}\""
            . ' "php artisan queue:listen --tries=1"'
            . ' "npm run dev"'
            . " --names='server,queue,vite'",
        ]);
    }

    /**
     * Start a persistent queue worker via artisan queue:listen.
     *
     * Disables the Composer process timeout so the worker can run indefinitely.
     * Defaults to the `default` queue when no --queue argument is provided.
     *
     * Pass specific queues after `--`:
     *
     *   composer queue:listen -- --queue=high,default,low
     *
     * Or set a custom timeout:
     *
     *   composer queue:listen -- --queue=sms,email --sleep=3
     */
    public static function queueListen(Event $event): void
    {
        Config::disableProcessTimeout();

        // Default to --queue=default when the caller passes no arguments.
        $flags = $event->getArguments() ? '' : ' --queue=default';

        Artisan::run($event, 'queue:listen --timeout=0' . $flags);
    }
}
