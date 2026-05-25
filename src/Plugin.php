<?php

namespace Masgeek\ComposerScripts;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;

/**
 * Composer plugin that auto-injects script callbacks into the root package.
 *
 * When this package is installed, all default scripts are available immediately
 * without any manual entries in the consumer's composer.json.
 *
 * Scripts already defined in the consumer's composer.json are never overwritten,
 * so individual overrides always win.
 *
 * Consumer only needs one line in their config:
 *
 *   "allow-plugins": { "masgeek/composer-scripts": true }
 */
class Plugin implements PluginInterface
{
    /**
     * Default scripts injected into any project that requires this package.
     * Keys map to composer script names; values are the class-method callbacks.
     */
    private const DEFAULT_SCRIPTS = [
        // ---- Testing (Pest) -------------------------------------------------
        'test'               => ['Masgeek\\ComposerScripts\\Testing::run'],
        'test:unit'          => ['Masgeek\\ComposerScripts\\Testing::unit'],
        'test:feature'       => ['Masgeek\\ComposerScripts\\Testing::feature'],
        'test:controllers'   => ['Masgeek\\ComposerScripts\\Testing::controllers'],
        'test:services'      => ['Masgeek\\ComposerScripts\\Testing::services'],
        'test:retry'         => ['Masgeek\\ComposerScripts\\Testing::retry'],
        'test:dirty'         => ['Masgeek\\ComposerScripts\\Testing::dirty'],
        'test:ci'            => ['Masgeek\\ComposerScripts\\Testing::ci'],
        // bare pest — for IDE file-level runners; also aliased as pest:test
        'test:file'          => ['Masgeek\\ComposerScripts\\Testing::file'],
        'pest:test'          => ['Masgeek\\ComposerScripts\\Testing::file'],
        // artisan test --parallel (graceful on non-Laravel)
        'test:parallel'      => ['Masgeek\\ComposerScripts\\Testing::parallel'],
        // coverage
        'pest:coverage'      => ['Masgeek\\ComposerScripts\\Testing::coverage'],
        'pest:coverage:xml'  => ['Masgeek\\ComposerScripts\\Testing::coverageXml'],
        'pest:coverage-xml'  => ['Masgeek\\ComposerScripts\\Testing::coverageXml'],

        // ---- Code quality ---------------------------------------------------
        // pint: prefix (explicit)
        'pint:fix'           => ['Masgeek\\ComposerScripts\\CodeQuality::pintFix'],
        'pint:fix:all'       => ['Masgeek\\ComposerScripts\\CodeQuality::pintFixAll'],
        'pint:check'         => ['Masgeek\\ComposerScripts\\CodeQuality::pintCheck'],
        'pint:repair'        => ['Masgeek\\ComposerScripts\\CodeQuality::pintRepair'],
        // lint: prefix (alternative naming convention)
        'lint:fix'           => ['Masgeek\\ComposerScripts\\CodeQuality::pintFix'],
        'lint:fix-all'       => ['Masgeek\\ComposerScripts\\CodeQuality::pintFixAll'],
        // static analysis
        'lint:analyse'       => ['Masgeek\\ComposerScripts\\CodeQuality::analyse'],
        'lint:check'         => ['Masgeek\\ComposerScripts\\CodeQuality::check'],
        'lint:all'           => ['Masgeek\\ComposerScripts\\CodeQuality::all'],
        // sonar
        'sonar'              => ['Masgeek\\ComposerScripts\\CodeQuality::sonar'],

        // ---- IDE helpers ----------------------------------------------------
        'meta:helper'        => ['Masgeek\\ComposerScripts\\IdeHelper::generate'],
        'meta:ide'           => ['Masgeek\\ComposerScripts\\IdeHelper::meta'],
        'meta:models'        => ['Masgeek\\ComposerScripts\\IdeHelper::models'],
        'meta:all'           => ['Masgeek\\ComposerScripts\\IdeHelper::all'],

        // ---- Dev server -----------------------------------------------------
        'dev'                => ['Masgeek\\ComposerScripts\\DevServer::serve'],
        'dev:all'            => ['Masgeek\\ComposerScripts\\DevServer::serveAll'],
        'queue:listen'       => ['Masgeek\\ComposerScripts\\DevServer::queueListen'],

        // ---- Dependencies ---------------------------------------------------
        'check-deps'         => ['Masgeek\\ComposerScripts\\Deps::checkUnused'],

        // ---- Meta -----------------------------------------------------------
        // Shorthand for `composer run-script --list`
        'scripts'            => ['@composer run-script --list'],

        // ---- Laravel chained workflows --------------------------------------
        'model:gen'          => ['Masgeek\\ComposerScripts\\IdeHelper::modelGen'],
    ];

    public function activate(Composer $composer, IOInterface $io): void
    {
        $package  = $composer->getPackage();
        $existing = $package->getScripts();

        $injected = 0;

        foreach (self::DEFAULT_SCRIPTS as $name => $callbacks) {
            if (!array_key_exists($name, $existing)) {
                $existing[$name] = $callbacks;
                $injected++;
            }
        }

        if ($injected > 0) {
            $package->setScripts($existing);
        }
    }

    public function deactivate(Composer $composer, IOInterface $io): void {}

    public function uninstall(Composer $composer, IOInterface $io): void {}
}
