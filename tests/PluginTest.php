<?php

namespace Masgeek\ComposerScripts\Tests;

use Composer\IO\IOInterface;
use Composer\Package\RootPackageInterface;
use Masgeek\ComposerScripts\Plugin;

class PluginTest extends TestCase
{
    public function testActivateInjectsMissingDefaultScriptsWithoutOverwritingExistingOnes(): void
    {
        $package = $this->createMock(RootPackageInterface::class);
        $package->expects($this->once())
            ->method('setScripts')
            ->with($this->callback(function (array $scripts): bool {
                self::assertSame(['App\\Testing::customRun'], $scripts['test']);
                self::assertSame(['Masgeek\\ComposerScripts\\Testing::unit'], $scripts['test:unit']);
                self::assertSame(['Masgeek\\ComposerScripts\\CodeQuality::pintFix'], $scripts['pint:fix']);
                self::assertSame(['@composer run-script --list'], $scripts['scripts']);
                self::assertSame(['Masgeek\\ComposerScripts\\IdeHelper::modelGen'], $scripts['model:gen']);

                return true;
            }));

        $composer = $this->createPluginComposer([
            'test' => ['App\\Testing::customRun'],
        ], $package);

        (new Plugin())->activate($composer, $this->createStub(IOInterface::class));
    }

    public function testActivateDoesNothingWhenAllScriptsAlreadyExist(): void
    {
        $allScripts = [
            'test' => ['Masgeek\\ComposerScripts\\Testing::run'],
            'test:unit' => ['Masgeek\\ComposerScripts\\Testing::unit'],
            'test:feature' => ['Masgeek\\ComposerScripts\\Testing::feature'],
            'test:controllers' => ['Masgeek\\ComposerScripts\\Testing::controllers'],
            'test:services' => ['Masgeek\\ComposerScripts\\Testing::services'],
            'test:retry' => ['Masgeek\\ComposerScripts\\Testing::retry'],
            'test:dirty' => ['Masgeek\\ComposerScripts\\Testing::dirty'],
            'test:ci' => ['Masgeek\\ComposerScripts\\Testing::ci'],
            'test:file' => ['Masgeek\\ComposerScripts\\Testing::file'],
            'pest:test' => ['Masgeek\\ComposerScripts\\Testing::file'],
            'test:parallel' => ['Masgeek\\ComposerScripts\\Testing::parallel'],
            'pest:coverage' => ['Masgeek\\ComposerScripts\\Testing::coverage'],
            'pest:coverage:xml' => ['Masgeek\\ComposerScripts\\Testing::coverageXml'],
            'pest:coverage-xml' => ['Masgeek\\ComposerScripts\\Testing::coverageXml'],
            'pint:fix' => ['Masgeek\\ComposerScripts\\CodeQuality::pintFix'],
            'pint:fix:all' => ['Masgeek\\ComposerScripts\\CodeQuality::pintFixAll'],
            'pint:check' => ['Masgeek\\ComposerScripts\\CodeQuality::pintCheck'],
            'pint:repair' => ['Masgeek\\ComposerScripts\\CodeQuality::pintRepair'],
            'lint:fix' => ['Masgeek\\ComposerScripts\\CodeQuality::pintFix'],
            'lint:fix-all' => ['Masgeek\\ComposerScripts\\CodeQuality::pintFixAll'],
            'lint:analyse' => ['Masgeek\\ComposerScripts\\CodeQuality::analyse'],
            'lint:check' => ['Masgeek\\ComposerScripts\\CodeQuality::check'],
            'lint:all' => ['Masgeek\\ComposerScripts\\CodeQuality::all'],
            'sonar' => ['Masgeek\\ComposerScripts\\CodeQuality::sonar'],
            'meta:helper' => ['Masgeek\\ComposerScripts\\IdeHelper::generate'],
            'meta:ide' => ['Masgeek\\ComposerScripts\\IdeHelper::meta'],
            'meta:models' => ['Masgeek\\ComposerScripts\\IdeHelper::models'],
            'meta:all' => ['Masgeek\\ComposerScripts\\IdeHelper::all'],
            'dev' => ['Masgeek\\ComposerScripts\\DevServer::serve'],
            'dev:all' => ['Masgeek\\ComposerScripts\\DevServer::serveAll'],
            'queue:listen' => ['Masgeek\\ComposerScripts\\DevServer::queueListen'],
            'check-deps' => ['Masgeek\\ComposerScripts\\Deps::checkUnused'],
            'scripts' => ['@composer run-script --list'],
            'model:gen' => ['Masgeek\\ComposerScripts\\IdeHelper::modelGen'],
        ];

        $package = $this->createMock(RootPackageInterface::class);
        $package->expects($this->never())
            ->method('setScripts');

        $composer = $this->createPluginComposer($allScripts, $package);

        (new Plugin())->activate($composer, $this->createStub(IOInterface::class));
    }
}
