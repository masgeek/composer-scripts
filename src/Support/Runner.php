<?php

namespace Masgeek\ComposerScripts\Support;

use Composer\Script\Event;
use RuntimeException;

/**
 * Thin wrapper around Composer's ProcessExecutor so all script classes share
 * a single execution path and consistent error handling.
 */
class Runner
{
    /**
     * Run one or more shell commands in sequence.
     * Throws on non-zero exit so the Composer script chain aborts cleanly.
     *
     * @param  string[]  $commands
     */
    public static function run(Event $event, array $commands): void
    {
        $io = $event->getIO();

        foreach ($commands as $command) {
            $io->write("<info>></info> <comment>{$command}</comment>");

            passthru($command, $exitCode);

            if ($exitCode !== 0) {
                throw new RuntimeException(
                    "Command failed with exit code {$exitCode}: {$command}"
                );
            }
        }
    }

    /**
     * Return the path to a vendor binary, or null if it does not exist.
     */
    public static function bin(Event $event, string $name): ?string
    {
        $vendorDir = $event->getComposer()->getConfig()->get('vendor-dir');
        $path = $vendorDir . DIRECTORY_SEPARATOR . 'bin' . DIRECTORY_SEPARATOR . $name;

        // On Windows the binary may have a .bat wrapper
        foreach ([$path, $path . '.bat', $path . '.cmd'] as $candidate) {
            if (file_exists($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Return any extra CLI arguments passed after `--` as a space-separated string.
     *
     * Usage:
     *   composer pint:fix -- app/Http Controllers
     *   composer test -- --filter=SomeTest
     *
     * Returns an empty string when no arguments were given, so it is safe to
     * append to any command unconditionally: "{$bin} --dirty" . Runner::args($event)
     */
    public static function args(Event $event): string
    {
        $args = $event->getArguments();

        return $args ? ' ' . implode(' ', $args) : '';
    }

    /**
     * Assert a vendor binary exists, printing a friendly error if not.
     */
    public static function requireBin(Event $event, string $name, string $package): string
    {
        $bin = static::bin($event, $name);

        if ($bin === null) {
            throw new RuntimeException(
                "Binary '{$name}' not found. Install {$package} to use this script."
            );
        }

        return $bin;
    }
}
