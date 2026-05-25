<?php

namespace Masgeek\ComposerScripts\Tests;

use Composer\Composer;
use Composer\Config;
use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Composer\Script\Event;
use PHPUnit\Framework\TestCase as PhpUnitTestCase;

abstract class TestCase extends PhpUnitTestCase
{
    private string $originalCwd;

    private string $originalPath;

    private string|false $originalDevPort;

    /** @var list<string> */
    private array $tempDirs = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->originalCwd = getcwd();
        $this->originalPath = getenv('PATH') ?: '';
        $this->originalDevPort = getenv('DEV_PORT');
    }

    protected function tearDown(): void
    {
        chdir($this->originalCwd);
        putenv("PATH={$this->originalPath}");

        if ($this->originalDevPort === false) {
            putenv('DEV_PORT');
        } else {
            putenv("DEV_PORT={$this->originalDevPort}");
        }

        foreach (array_reverse($this->tempDirs) as $dir) {
            $this->deleteDirectory($dir);
        }

        parent::tearDown();
    }

    protected function createTempDir(): string
    {
        $dir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'composer-scripts-tests-' . bin2hex(random_bytes(8));

        if (!mkdir($dir, 0777, true) && !is_dir($dir)) {
            self::fail("Failed to create temp directory: {$dir}");
        }

        $this->tempDirs[] = $dir;

        return $dir;
    }

    protected function createEvent(
        string $vendorDir,
        array $args = [],
        ?IOInterface $io = null,
    ): Event {
        $io ??= $this->createStub(IOInterface::class);

        $config = $this->createStub(Config::class);
        $config->method('get')
            ->willReturnCallback(static fn (string $key): mixed => $key === 'vendor-dir' ? $vendorDir : null);

        $composer = $this->createStub(Composer::class);
        $composer->method('getConfig')
            ->willReturn($config);

        $event = $this->createStub(Event::class);
        $event->method('getComposer')
            ->willReturn($composer);
        $event->method('getIO')
            ->willReturn($io);
        $event->method('getArguments')
            ->willReturn($args);

        return $event;
    }

    protected function createPluginComposer(array $scripts, RootPackageInterface $package): Composer
    {
        $package->method('getScripts')
            ->willReturn($scripts);

        $composer = $this->createStub(Composer::class);
        $composer->method('getPackage')
            ->willReturn($package);

        return $composer;
    }

    /**
     * Return the platform-appropriate script filename for a given base name.
     *
     * On Windows the OS resolves bare names via PATHEXT, so we use a .bat wrapper.
     * On POSIX systems we use a plain executable (no extension).
     */
    protected function scriptName(string $name): string
    {
        return PHP_OS_FAMILY === 'Windows' ? $name . '.bat' : $name;
    }

    protected function createLoggingBatch(string $path, string $logFile, int $exitCode = 0): void
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // `echo %*` echoes the verbatim command-line string, including any
            // double-quotes the caller passed. This preserves `=` signs that
            // CMD would otherwise treat as token delimiters when using %~N.
            // str_replace converts LF → CRLF for proper Windows batch line endings.
            $bat = <<<BAT
                @echo off
                if "%~1"=="" (
                  echo. >> "{$logFile}"
                ) else (
                  echo %* >> "{$logFile}"
                )
                exit /b {$exitCode}
                BAT;

            $this->writeFile($path, str_replace("\n", "\r\n", $bat));
        } else {
            // POSIX: each positional param is an already-resolved argument value
            // (shell has stripped surrounding quotes). Re-wrap any multi-word
            // argument in double-quotes so the output is unambiguous and matches
            // the platform-normalised form used in test assertions.
            //
            // Nowdoc (<<<'SHELL') prevents PHP from interpolating shell ${var}
            // references, avoiding the PHP 8.2 deprecated-interpolation warning.
            // __LOG__ and __EXIT__ are substituted after the fact.
            $escaped = str_replace("'", "'\\''", $logFile);

            $sh = <<<'SHELL'
                #!/bin/sh
                _sep=''
                _out=''
                for _arg; do
                    case "$_arg" in
                        *' '*) _out="${_out}${_sep}\"${_arg}\"" ;;
                        *)     _out="${_out}${_sep}${_arg}" ;;
                    esac
                    _sep=' '
                done
                if [ -z "$_out" ]; then
                    printf '\n' >> '__LOG__'
                else
                    printf '%s\n' "$_out" >> '__LOG__'
                fi
                exit __EXIT__
                SHELL;

            $this->writeFile($path, str_replace(['__LOG__', '__EXIT__'], [$escaped, $exitCode], $sh));
            chmod($path, 0755);
        }
    }

    protected function writeFile(string $path, string $contents): void
    {
        $dir = dirname($path);

        if (!is_dir($dir) && !mkdir($dir, 0777, true) && !is_dir($dir)) {
            self::fail("Failed to create directory: {$dir}");
        }

        file_put_contents($path, $contents);
    }

    protected function prependPath(string $dir): void
    {
        putenv('PATH=' . $dir . PATH_SEPARATOR . $this->originalPath);
    }

    /**
     * @return list<string>
     */
    protected function readLogLines(string $path): array
    {
        $lines = file($path, FILE_IGNORE_NEW_LINES);

        if ($lines === false) {
            return [];
        }

        return array_map(static fn (string $line): string => rtrim($line), $lines);
    }

    protected function createIoCollector(array &$writes = [], array &$errors = []): IOInterface
    {
        $io = $this->createStub(IOInterface::class);
        $io->method('write')
            ->willReturnCallback(function (mixed $messages) use (&$writes): void {
                foreach ((array) $messages as $message) {
                    $writes[] = (string) $message;
                }
            });
        $io->method('writeError')
            ->willReturnCallback(function (mixed $messages) use (&$errors): void {
                foreach ((array) $messages as $message) {
                    $errors[] = (string) $message;
                }
            });

        return $io;
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = scandir($dir);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
                continue;
            }

            @unlink($path);
        }

        @rmdir($dir);
    }
}
