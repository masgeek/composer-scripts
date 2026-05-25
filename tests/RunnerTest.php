<?php

namespace Masgeek\ComposerScripts\Tests;

use Masgeek\ComposerScripts\Support\Runner;
use RuntimeException;

class RunnerTest extends TestCase
{
    public function testRunExecutesCommandsSequentiallyAndReportsThem(): void
    {
        $project = $this->createTempDir();
        $log = $project . DIRECTORY_SEPARATOR . 'commands.log';
        $one = $project . DIRECTORY_SEPARATOR . 'one.bat';
        $two = $project . DIRECTORY_SEPARATOR . 'two.bat';

        $this->createLoggingBatch($one, $log);
        $this->createLoggingBatch($two, $log);

        $writes = [];
        $event = $this->createEvent($project . DIRECTORY_SEPARATOR . 'vendor', io: $this->createIoCollector($writes));

        Runner::run($event, ['"' . $one . '" first', '"' . $two . '" second third']);

        self::assertSame(['first', 'second third'], $this->readLogLines($log));
        self::assertCount(2, $writes);
        self::assertStringContainsString($one, $writes[0]);
        self::assertStringContainsString($two, $writes[1]);
    }

    public function testRunThrowsWhenCommandFails(): void
    {
        $project = $this->createTempDir();
        $failing = $project . DIRECTORY_SEPARATOR . 'fail.bat';

        $this->createLoggingBatch($failing, $project . DIRECTORY_SEPARATOR . 'commands.log', 7);

        $event = $this->createEvent($project . DIRECTORY_SEPARATOR . 'vendor', io: $this->createIoCollector());

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Command failed with exit code 7');

        Runner::run($event, ['"' . $failing . '"']);
    }

    public function testBinResolvesDirectBatchAndCmdVariants(): void
    {
        $project = $this->createTempDir();
        $vendorBin = $project . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        $event = $this->createEvent($project . DIRECTORY_SEPARATOR . 'vendor');

        $this->writeFile($vendorBin . DIRECTORY_SEPARATOR . 'tool', 'binary');
        self::assertSame($vendorBin . DIRECTORY_SEPARATOR . 'tool', Runner::bin($event, 'tool'));

        @unlink($vendorBin . DIRECTORY_SEPARATOR . 'tool');
        $this->writeFile($vendorBin . DIRECTORY_SEPARATOR . 'tool.bat', 'binary');
        self::assertSame($vendorBin . DIRECTORY_SEPARATOR . 'tool.bat', Runner::bin($event, 'tool'));

        @unlink($vendorBin . DIRECTORY_SEPARATOR . 'tool.bat');
        $this->writeFile($vendorBin . DIRECTORY_SEPARATOR . 'tool.cmd', 'binary');
        self::assertSame($vendorBin . DIRECTORY_SEPARATOR . 'tool.cmd', Runner::bin($event, 'tool'));
        self::assertNull(Runner::bin($event, 'missing'));
    }

    public function testArgsReturnsSpaceSeparatedArgumentString(): void
    {
        $event = $this->createEvent('vendor', ['--filter=Unit', 'tests/Unit']);

        self::assertSame(' --filter=Unit tests/Unit', Runner::args($event));
        self::assertSame('', Runner::args($this->createEvent('vendor')));
    }

    public function testRequireBinReturnsBinaryPath(): void
    {
        $project = $this->createTempDir();
        $vendorBin = $project . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        $tool = $vendorBin . DIRECTORY_SEPARATOR . 'pest.bat';

        $this->writeFile($tool, 'binary');

        $event = $this->createEvent($project . DIRECTORY_SEPARATOR . 'vendor');

        self::assertSame($tool, Runner::requireBin($event, 'pest', 'pestphp/pest'));
    }

    public function testRequireBinThrowsHelpfulMessageWhenMissing(): void
    {
        $event = $this->createEvent($this->createTempDir() . DIRECTORY_SEPARATOR . 'vendor');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Binary 'pest' not found. Install pestphp/pest to use this script.");

        Runner::requireBin($event, 'pest', 'pestphp/pest');
    }
}
