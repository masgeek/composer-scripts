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

    protected function createLoggingBatch(string $path, string $logFile, int $exitCode = 0): void
    {
        $this->writeFile(
            $path,
            "@echo off\r\n"
            . "if \"%~1\"==\"\" (\r\n"
            . "  echo. >> \"{$logFile}\"\r\n"
            . ") else (\r\n"
            . "  echo %* >> \"{$logFile}\"\r\n"
            . ")\r\n"
            . "exit /b {$exitCode}\r\n"
        );
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
