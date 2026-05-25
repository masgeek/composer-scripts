<?php

namespace Masgeek\ComposerScripts\Tests;

use Masgeek\ComposerScripts\Support\Artisan;
use RuntimeException;

class ArtisanTest extends TestCase
{
    public function testFindPrefersVendorBinary(): void
    {
        $project = $this->createTempDir();
        $vendorBin = $project . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        $artisan = $vendorBin . DIRECTORY_SEPARATOR . 'artisan.bat';

        $this->writeFile($artisan, 'binary');
        $this->writeFile($project . DIRECTORY_SEPARATOR . 'artisan', '<?php');
        chdir($project);

        $event = $this->createEvent($project . DIRECTORY_SEPARATOR . 'vendor');

        self::assertSame($artisan, Artisan::find($event));
    }

    public function testFindFallsBackToPhpArtisanInProjectRoot(): void
    {
        $project = $this->createTempDir();
        $this->writeFile($project . DIRECTORY_SEPARATOR . 'artisan', '<?php');
        chdir($project);

        $event = $this->createEvent($project . DIRECTORY_SEPARATOR . 'vendor');

        self::assertSame('php artisan', Artisan::find($event));
    }

    public function testRunSkipsGracefullyWhenArtisanIsMissing(): void
    {
        $errors = [];
        $project = $this->createTempDir();
        chdir($project);

        $event = $this->createEvent(
            $project . DIRECTORY_SEPARATOR . 'vendor',
            io: $this->createIoCollector(errors: $errors),
        );

        Artisan::run($event, 'config:clear --ansi');

        self::assertCount(1, $errors);
        self::assertStringContainsString("Skipping 'config:clear --ansi'", $errors[0]);
    }

    public function testRunExecutesResolvedArtisanCommandWithForwardedArguments(): void
    {
        $project = $this->createTempDir();
        $vendorBin = $project . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        $log = $project . DIRECTORY_SEPARATOR . 'artisan.log';
        $artisan = $vendorBin . DIRECTORY_SEPARATOR . 'artisan.bat';

        $this->createLoggingBatch($artisan, $log);

        $event = $this->createEvent(
            $project . DIRECTORY_SEPARATOR . 'vendor',
            ['--force', '--ansi'],
            $this->createIoCollector(),
        );

        Artisan::run($event, 'migrate');

        self::assertSame(['migrate --force --ansi'], $this->readLogLines($log));
    }

    public function testRequireThrowsWhenArtisanIsMissing(): void
    {
        $project = $this->createTempDir();
        chdir($project);

        $event = $this->createEvent($project . DIRECTORY_SEPARATOR . 'vendor');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("artisan not found");

        Artisan::require($event, 'test --parallel');
    }
}
