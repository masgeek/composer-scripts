<?php

namespace Masgeek\ComposerScripts\Tests;

use Masgeek\ComposerScripts\CodeQuality;
use Masgeek\ComposerScripts\Deps;
use Masgeek\ComposerScripts\DevServer;
use Masgeek\ComposerScripts\IdeHelper;
use Masgeek\ComposerScripts\Testing;

class ScriptCallbacksTest extends TestCase
{
    public function testCodeQualityCommandsBuildExpectedInvocations(): void
    {
        $project = $this->createTempDir();
        $vendorBin = $project . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        $log = $project . DIRECTORY_SEPARATOR . 'quality.log';

        $this->createLoggingBatch($vendorBin . DIRECTORY_SEPARATOR . 'pint.bat', $log);
        $this->createLoggingBatch($vendorBin . DIRECTORY_SEPARATOR . 'phpstan.bat', $log);

        $event = $this->createEvent(
            $project . DIRECTORY_SEPARATOR . 'vendor',
            ['app/Http', '--preset=psr12'],
            $this->createIoCollector(),
        );

        CodeQuality::pintFix($event);
        CodeQuality::pintFixAll($event);
        CodeQuality::pintCheck($event);
        CodeQuality::pintRepair($event);
        CodeQuality::analyse($event);
        CodeQuality::check($event);

        self::assertSame([
            '--dirty app/Http --preset=psr12',
            'app/Http --preset=psr12',
            '--test app/Http --preset=psr12',
            '--repair app/Http --preset=psr12',
            'analyse --memory-limit=256M app/Http --preset=psr12',
            'analyse --memory-limit=256M app/Http --preset=psr12',
        ], $this->readLogLines($log));
    }

    public function testCodeQualityAllRunsPintCheckThenPhpstan(): void
    {
        $project = $this->createTempDir();
        $vendorBin = $project . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        $log = $project . DIRECTORY_SEPARATOR . 'quality-all.log';

        $this->createLoggingBatch($vendorBin . DIRECTORY_SEPARATOR . 'pint.bat', $log);
        $this->createLoggingBatch($vendorBin . DIRECTORY_SEPARATOR . 'phpstan.bat', $log);

        $event = $this->createEvent($project . DIRECTORY_SEPARATOR . 'vendor', io: $this->createIoCollector());

        CodeQuality::all($event);

        self::assertSame([
            '--test',
            'analyse --memory-limit=256M',
        ], $this->readLogLines($log));
    }

    public function testDepsChecksUnusedDependencies(): void
    {
        $project = $this->createTempDir();
        $vendorBin = $project . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        $log = $project . DIRECTORY_SEPARATOR . 'deps.log';

        $this->createLoggingBatch($vendorBin . DIRECTORY_SEPARATOR . 'composer-unused.bat', $log);

        Deps::checkUnused($this->createEvent($project . DIRECTORY_SEPARATOR . 'vendor', io: $this->createIoCollector()));

        self::assertSame([''], $this->readLogLines($log));
    }

    public function testTestingCommandsBuildExpectedPestInvocations(): void
    {
        $project = $this->createTempDir();
        $vendorBin = $project . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        $log = $project . DIRECTORY_SEPARATOR . 'testing.log';
        $errors = [];

        $this->createLoggingBatch($vendorBin . DIRECTORY_SEPARATOR . 'pest.bat', $log);
        chdir($project);

        $event = $this->createEvent(
            $project . DIRECTORY_SEPARATOR . 'vendor',
            ['--filter=CheckoutTest'],
            $this->createIoCollector(errors: $errors),
        );

        Testing::run($event);
        Testing::unit($event);
        Testing::feature($event);
        Testing::controllers($event);
        Testing::services($event);
        Testing::retry($event);
        Testing::dirty($event);
        Testing::ci($event);
        Testing::file($event);
        Testing::coverage($event);
        Testing::coverageXml($event);

        self::assertCount(1, $errors);
        self::assertStringContainsString("Skipping 'config:clear --ansi'", $errors[0]);
        self::assertSame([
            '--bail --filter=CheckoutTest',
            'tests/Unit --filter=CheckoutTest',
            'tests/Feature --filter=CheckoutTest',
            'tests/Feature/Http/Controllers --filter=CheckoutTest',
            'tests/Unit/Services --filter=CheckoutTest',
            '--retry --bail --filter=CheckoutTest',
            '--dirty --filter=CheckoutTest',
            '--filter=CheckoutTest',
            '--filter=CheckoutTest',
            '--coverage --filter=CheckoutTest',
            '--coverage-clover=storage/coverage/coverage.xml --filter=CheckoutTest',
        ], $this->readLogLines($log));
    }

    public function testTestingParallelUsesArtisan(): void
    {
        $project = $this->createTempDir();
        $vendorBin = $project . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        $log = $project . DIRECTORY_SEPARATOR . 'parallel.log';

        $this->createLoggingBatch($vendorBin . DIRECTORY_SEPARATOR . 'artisan.bat', $log);

        $event = $this->createEvent(
            $project . DIRECTORY_SEPARATOR . 'vendor',
            ['--recreate-databases'],
            $this->createIoCollector(),
        );

        Testing::parallel($event);

        self::assertSame(['test --parallel --recreate-databases'], $this->readLogLines($log));
    }

    public function testIdeHelperRunsFullWorkflowAndWarnsWhenPintIsMissing(): void
    {
        $project = $this->createTempDir();
        $vendorBin = $project . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        $log = $project . DIRECTORY_SEPARATOR . 'ide-helper.log';
        $writes = [];

        $this->createLoggingBatch($vendorBin . DIRECTORY_SEPARATOR . 'artisan.bat', $log);

        $event = $this->createEvent(
            $project . DIRECTORY_SEPARATOR . 'vendor',
            io: $this->createIoCollector($writes),
        );

        IdeHelper::generate($event);
        IdeHelper::meta($event);
        IdeHelper::models($event);
        IdeHelper::all($event);
        IdeHelper::modelGen($event);

        self::assertSame([
            'ide-helper:generate',
            'ide-helper:meta',
            'ide-helper:models --write',
            'ide-helper:generate',
            'ide-helper:meta',
            'ide-helper:models --write',
            'code:models',
            'ide-helper:models --write',
        ], $this->readLogLines($log));
        self::assertNotEmpty($writes);
        self::assertStringContainsString('Skipping pint format', implode("\n", $writes));
    }

    public function testIdeHelperModelGenFormatsDirtyFilesWhenPintExists(): void
    {
        $project = $this->createTempDir();
        $vendorBin = $project . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        $log = $project . DIRECTORY_SEPARATOR . 'model-gen.log';

        $this->createLoggingBatch($vendorBin . DIRECTORY_SEPARATOR . 'artisan.bat', $log);
        $this->createLoggingBatch($vendorBin . DIRECTORY_SEPARATOR . 'pint.bat', $log);

        IdeHelper::modelGen($this->createEvent($project . DIRECTORY_SEPARATOR . 'vendor', io: $this->createIoCollector()));

        self::assertSame([
            'code:models',
            'ide-helper:models --write',
            '--dirty',
        ], $this->readLogLines($log));
    }

    public function testDevServerServeUsesArtisanWhenPresentAndFallbackPhpServerOtherwise(): void
    {
        $project = $this->createTempDir();
        $binDir = $project . DIRECTORY_SEPARATOR . 'bin';
        $log = $project . DIRECTORY_SEPARATOR . 'serve.log';

        $this->createLoggingBatch($binDir . DIRECTORY_SEPARATOR . 'php.bat', $log);
        $this->prependPath($binDir);
        putenv('DEV_PORT=9010');
        chdir($project);

        $withArtisan = $this->createEvent(
            $project . DIRECTORY_SEPARATOR . 'vendor',
            ['--host=0.0.0.0'],
            $this->createIoCollector(),
        );

        $this->writeFile($project . DIRECTORY_SEPARATOR . 'artisan', '<?php');
        DevServer::serve($withArtisan);

        @unlink($project . DIRECTORY_SEPARATOR . 'artisan');

        $withoutArtisan = $this->createEvent($project . DIRECTORY_SEPARATOR . 'vendor', io: $this->createIoCollector());
        DevServer::serve($withoutArtisan);

        self::assertSame([
            'artisan serve --port=9010 --host=0.0.0.0',
            '-S localhost:9010 -t public',
        ], $this->readLogLines($log));
    }

    public function testDevServerServeAllAndQueueListenBuildExpectedCommands(): void
    {
        $project = $this->createTempDir();
        $binDir = $project . DIRECTORY_SEPARATOR . 'bin';
        $vendorBin = $project . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'bin';
        $log = $project . DIRECTORY_SEPARATOR . 'devserver.log';

        $this->createLoggingBatch($binDir . DIRECTORY_SEPARATOR . 'npx.bat', $log);
        $this->createLoggingBatch($vendorBin . DIRECTORY_SEPARATOR . 'artisan.bat', $log);
        $this->prependPath($binDir);
        putenv('DEV_PORT=8123');

        DevServer::serveAll($this->createEvent($project . DIRECTORY_SEPARATOR . 'vendor', io: $this->createIoCollector()));
        DevServer::queueListen($this->createEvent($project . DIRECTORY_SEPARATOR . 'vendor', io: $this->createIoCollector()));
        DevServer::queueListen($this->createEvent(
            $project . DIRECTORY_SEPARATOR . 'vendor',
            ['--queue=high,default', '--sleep=3'],
            $this->createIoCollector(),
        ));

        self::assertSame([
            'concurrently -c "#93c5fd,#c4b5fd,#fdba74" "php artisan serve --port=8123" "php artisan queue:listen --tries=1" "npm run dev" --names=\'server,queue,vite\'',
            'queue:listen --timeout=0 --queue=default',
            'queue:listen --timeout=0 --queue=high,default --sleep=3',
        ], $this->readLogLines($log));
    }
}
