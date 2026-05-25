# masgeek/composer-scripts

Reusable Composer script callbacks for PHP projects — testing, linting, IDE helpers, model generation, dev server, and queue management. Works with Laravel, Symfony, or plain PHP. No framework dependency.

---

## Table of Contents

- [Installation](#installation)
- [How it works](#how-it-works)
- [All available scripts](#all-available-scripts)
- [Passing arguments](#passing-arguments)
- [Overriding a script](#overriding-a-script)
- [Manual wiring](#manual-wiring-opt-out-of-auto-discovery)
- [Scripts reference](#scripts-reference)
  - [Testing](#testing-masgeekcomposerscriptstesting)
  - [Code Quality](#code-quality-masgeekcomposerscriptscodequality)
  - [IDE Helper](#ide-helper-masgeekcomposerscriptsidehelper)
  - [Dev Server & Queue](#dev-server--queue-masgeekcomposerscriptsdevserver)
  - [Dependency Analysis](#dependency-analysis-masgeekcomposerscriptsdeps)
- [Non-Laravel projects](#non-laravel-projects)
- [Extending the package](#extending-the-package)
- [Requirements](#requirements)

---

## Installation

```bash
composer require --dev masgeek/composer-scripts
```

Allow the plugin once in your `composer.json`:

```json
{
    "config": {
        "allow-plugins": {
            "masgeek/composer-scripts": true
        }
    }
}
```

Run `composer install` — every script listed below is immediately available with no further configuration.

---

## How it works

This package is a **Composer plugin**. `Plugin::activate()` is called before any script runs and injects all default callbacks into the root package at runtime. Nothing is written to disk.

**Your definitions always win.** If a script name already exists in your `composer.json`, the plugin skips it entirely. You can override any default or add project-specific steps without touching the package.

---

## All available scripts

Quick-reference of every script injected by the plugin.

| Script | Class::method | Notes |
|--------|--------------|-------|
| `test` | `Testing::run` | config:clear + pest --bail |
| `test:unit` | `Testing::unit` | pest tests/Unit |
| `test:feature` | `Testing::feature` | pest tests/Feature |
| `test:controllers` | `Testing::controllers` | pest tests/Feature/Http/Controllers |
| `test:services` | `Testing::services` | pest tests/Unit/Services |
| `test:retry` | `Testing::retry` | pest --retry --bail |
| `test:dirty` | `Testing::dirty` | pest --dirty |
| `test:ci` | `Testing::ci` | pest (no bail, all failures reported) |
| `test:file` | `Testing::file` | bare pest, no flags — IDE runner friendly |
| `test:parallel` | `Testing::parallel` | artisan test --parallel ¹ |
| `pest:test` | `Testing::file` | alias for test:file |
| `pest:coverage` | `Testing::coverage` | pest --coverage |
| `pest:coverage:xml` | `Testing::coverageXml` | Clover XML → storage/coverage/coverage.xml |
| `pest:coverage-xml` | `Testing::coverageXml` | alias (dash form) |
| `pint:fix` | `CodeQuality::pintFix` | pint --dirty |
| `pint:fix:all` | `CodeQuality::pintFixAll` | pint (all files) |
| `pint:check` | `CodeQuality::pintCheck` | pint --test |
| `pint:repair` | `CodeQuality::pintRepair` | pint --repair |
| `lint:fix` | `CodeQuality::pintFix` | alias for pint:fix |
| `lint:fix-all` | `CodeQuality::pintFixAll` | alias for pint:fix:all (dash form) |
| `lint:analyse` | `CodeQuality::analyse` | phpstan analyse --memory-limit=256M |
| `lint:check` | `CodeQuality::check` | alias for lint:analyse |
| `lint:all` | `CodeQuality::all` | pint:check then lint:analyse |
| `sonar` | `CodeQuality::sonar` | sonar-scanner |
| `meta:helper` | `IdeHelper::generate` | artisan ide-helper:generate ¹ |
| `meta:ide` | `IdeHelper::meta` | artisan ide-helper:meta ¹ |
| `meta:models` | `IdeHelper::models` | artisan ide-helper:models --write ¹ |
| `meta:all` | `IdeHelper::all` | all three ide-helper commands ¹ |
| `model:gen` | `IdeHelper::modelGen` | code:models → ide-helper:models → pint --dirty ¹ |
| `dev` | `DevServer::serve` | artisan serve or php -S (DEV_PORT env var) |
| `dev:all` | `DevServer::serveAll` | server + queue + Vite via npx concurrently |
| `queue:listen` | `DevServer::queueListen` | artisan queue:listen --timeout=0 ¹ |
| `check-deps` | `Deps::checkUnused` | composer-unused |
| `scripts` | *(alias)* | `composer run-script --list` |

> ¹ Gracefully skips with a warning when `artisan` is not found — safe on non-Laravel projects.

---

## Discovering available scripts

After installing the package, run either of these to see every script available in your project — both those you defined and those injected by the plugin:

```bash
composer run-script --list

# or the short alias injected by this package
composer scripts
```

Scripts defined in your own `composer.json` show a description; plugin-injected ones appear without one:

```
Available scripts:
  setup                     Runs the setup script as defined in composer.json
  dev                       Runs the dev script as defined in composer.json
  queue:all                 Runs the queue:all script as defined in composer.json
  test                                           ← injected by plugin
  test:unit                                      ← injected by plugin
  test:feature                                   ← injected by plugin
  pint:fix                                       ← injected by plugin
  lint:analyse                                   ← injected by plugin
  model:gen                                      ← injected by plugin
  ...
```

To add descriptions to plugin scripts (useful for team documentation), define the script
name in your `composer.json` with a `scripts-descriptions` entry:

```json
{
    "scripts-descriptions": {
        "test":         "Run the full test suite",
        "pint:fix":     "Fix formatting on changed files",
        "lint:analyse": "Run PHPStan static analysis",
        "model:gen":    "Regenerate Eloquent models and IDE annotations",
        "queue:listen": "Start a queue worker — pass --queue=name1,name2 after --"
    }
}
```

---

## Passing arguments

Every command forwards extra arguments passed after `--` to the underlying tool.

```bash
# Run a single test file
composer test -- tests/Feature/SmsControllerTest.php

# Filter by test name
composer test -- --filter=it_sends_an_sms

# Run feature tests and stop on first failure
composer test:feature -- --stop-on-failure

# Re-run failures with verbose output
composer test:retry -- --verbose

# CI run producing a JUnit report
composer test:ci -- --log-junit=build/test-results/junit.xml

# Parallel tests with a fresh database
composer test:parallel -- --recreate-databases

# Coverage with a minimum threshold enforced
composer pest:coverage -- --min=80

# Fix only a specific directory
composer pint:fix -- app/Http/Controllers

# Fix a single file
composer lint:fix-all -- app/Services/AuthService.php

# Check formatting for a single path
composer pint:check -- app/Models

# PHPStan at a stricter level
composer lint:analyse -- --level=8

# PHPStan on a specific path
composer lint:analyse -- app/Services

# PHPStan with a custom config
composer lint:analyse -- -c phpstan-strict.neon

# Listen on specific queues
composer queue:listen -- --queue=high,default,low

# Queue worker with extra tuning
composer queue:listen -- --queue=sms,email --sleep=3 --tries=3

# Annotate models in dry-run mode
composer meta:models -- --no-write

# Reset existing model docblocks
composer meta:models -- --reset

# SonarQube on a specific branch
composer sonar -- -Dsonar.branch.name=develop
```

---

## Overriding a script

Define the script name in your `composer.json` and the plugin leaves it alone. Use this to:

**Add steps before or after the default:**
```json
{
    "scripts": {
        "test": [
            "@php artisan db:seed --class=TestSeeder",
            "Masgeek\\ComposerScripts\\Testing::run"
        ]
    }
}
```

**Change the port for the dev server:**
```json
{
    "scripts": {
        "dev": [
            "Composer\\Config::disableProcessTimeout",
            "@php artisan serve --port=8700"
        ]
    }
}
```

**Hardcode a project-specific queue list** so you never have to type it:
```json
{
    "scripts": {
        "queue:all": [
            "Composer\\Config::disableProcessTimeout",
            "@php artisan queue:listen --timeout=0 --queue=broadcast,sms,campaign,archive,zoho"
        ]
    }
}
```

> The `queue:listen` script injected by the plugin is the ad-hoc tool (pass queues via `--`).
> A named override like `queue:all` above is the convenience alias for your fixed production stack.

**Replace entirely with a plain shell command:**
```json
{
    "scripts": {
        "sonar": "sonar-scanner -Dsonar.projectKey=my-project"
    }
}
```

---

## Manual wiring (opt-out of auto-discovery)

If you prefer explicit control, copy the entries you need from [`scripts.json`](scripts.json) into your `composer.json`. The class callbacks work identically whether discovered by the plugin or wired manually.

```json
{
    "scripts": {
        "test":          "Masgeek\\ComposerScripts\\Testing::run",
        "pint:fix":      "Masgeek\\ComposerScripts\\CodeQuality::pintFix",
        "lint:analyse":  "Masgeek\\ComposerScripts\\CodeQuality::analyse",
        "model:gen":     "Masgeek\\ComposerScripts\\IdeHelper::modelGen",
        "dev":           "Masgeek\\ComposerScripts\\DevServer::serve",
        "queue:listen":  "Masgeek\\ComposerScripts\\DevServer::queueListen"
    }
}
```

---

## Scripts reference

### Testing (`Masgeek\ComposerScripts\Testing`)

Requires [`pestphp/pest`](https://pestphp.com).

`test` clears the Laravel config cache before running (no-op on non-Laravel projects).  
`test:parallel` uses `artisan test --parallel` and gracefully skips if artisan is absent.

```bash
# Full suite — bails on first failure
composer test

# Filter to a single test
composer test -- --filter=SmsServiceTest

# Run a specific file (bare pest, no default flags)
composer test:file -- tests/Feature/Http/Controllers/SmsControllerTest.php

# Unit suite only
composer test:unit

# Feature suite, stop on first failure
composer test:feature -- --stop-on-failure

# Re-run only what failed last time
composer test:retry

# Tests for git-changed files only
composer test:dirty

# Full CI run — no bail, all failures reported
composer test:ci

# CI run with JUnit XML output
composer test:ci -- --log-junit=build/test-results/junit.xml

# Laravel parallel runner
composer test:parallel

# Parallel with fresh databases
composer test:parallel -- --recreate-databases

# HTML coverage report
composer pest:coverage

# Coverage with enforced minimum
composer pest:coverage -- --min=80

# Clover XML for SonarQube
composer pest:coverage:xml
```

---

### Code Quality (`Masgeek\ComposerScripts\CodeQuality`)

Requires [`laravel/pint`](https://github.com/laravel/pint) for formatting and
[`phpstan/phpstan`](https://phpstan.org) for static analysis. Each tool is only
required when its specific command runs.

```bash
# Fix git-changed files only (fast, safe for pre-commit)
composer pint:fix

# Fix a specific directory
composer pint:fix -- app/Http

# Fix everything
composer pint:fix:all
# or
composer lint:fix-all

# Check formatting without changing anything (CI-friendly)
composer pint:check

# Check only a path
composer pint:check -- app/Models

# Repair files that couldn't be auto-fixed
composer pint:repair

# PHPStan at default level (256 MB memory limit)
composer lint:analyse

# Stricter level
composer lint:analyse -- --level=8

# Analyse a single directory
composer lint:analyse -- app/Services

# Custom PHPStan config
composer lint:analyse -- -c phpstan-strict.neon

# Pint check + PHPStan in one shot (full CI quality gate)
composer lint:all

# SonarQube scan
composer sonar

# SonarQube on a feature branch
composer sonar -- -Dsonar.branch.name=feat/my-feature
```

---

### IDE Helper (`Masgeek\ComposerScripts\IdeHelper`)

Requires [`barryvdh/laravel-ide-helper`](https://github.com/barryvdh/laravel-ide-helper).
All commands **gracefully skip** with a warning when `artisan` is not found.

```bash
# Generate _ide_helper.php (facades + container bindings)
composer meta:helper

# Generate .phpstorm.meta.php
composer meta:ide

# Annotate Eloquent models with @property docblocks
composer meta:models

# Annotate without writing (dry-run / preview)
composer meta:models -- --no-write

# Reset and rewrite all existing docblocks
composer meta:models -- --reset

# All three ide-helper commands in one go
composer meta:all

# Full model regeneration workflow (code:models → ide-helper → pint)
composer model:gen
```

#### `model:gen` — three-step workflow

Replaces the common inline chain:

```bash
php artisan code:models                 # 1. regenerate Base model classes
php artisan ide-helper:models --write   # 2. add @property docblocks
vendor/bin/pint --dirty                 # 3. format generated files
```

Step 1 requires a package that registers `code:models`
(e.g. [`masgeek/reliese-laravel-model-gen`](https://github.com/masgeek/reliese-laravel-model-gen)).
Steps 2 and 3 gracefully skip if the binaries are absent.

---

### Dev Server & Queue (`Masgeek\ComposerScripts\DevServer`)

All three commands automatically disable the Composer process timeout so long-running
processes are never killed after the default 300-second limit.

```bash
# Start artisan serve on port 8000 (or php -S for non-Laravel)
composer dev

# Start on a custom port
DEV_PORT=8700 composer dev          # Linux / macOS
$env:DEV_PORT=8700; composer dev    # Windows PowerShell

# Start server + queue worker + Vite all at once (requires npx concurrently)
composer dev:all

# Listen on the default queue
composer queue:listen

# Listen on specific queues (priority order, left = highest)
composer queue:listen -- --queue=high,default,low

# Listen with extra tuning flags
composer queue:listen -- --queue=sms,email --sleep=3 --tries=3
```

**Port configuration**

`DEV_PORT` controls the port for both `dev` and `dev:all`. Set it in `.env` for a permanent project default:

```
DEV_PORT=8700
```

**Project-specific queue shortcut**

`queue:listen` is the generic tool — pass any queues at runtime. For a fixed production
stack, define a named override in your `composer.json` so you never have to type the list:

```json
{
    "scripts": {
        "queue:all": [
            "Composer\\Config::disableProcessTimeout",
            "@php artisan queue:listen --timeout=0 --queue=broadcast,sms,campaign,archive,zoho"
        ]
    }
}
```

Then just run `composer queue:all`.

---

### Dependency Analysis (`Masgeek\ComposerScripts\Deps`)

Requires [`icanhazstring/composer-unused`](https://github.com/icanhazstring/composer-unused).

```bash
# List packages that are required but not referenced in code
composer check-deps
```

---

## Non-Laravel projects

Commands that call artisan go through `Support\Artisan`, which resolves the binary:

1. `vendor/bin/artisan` — symlink placed by `laravel/framework`
2. `php artisan` — script in the project root

If neither exists the command **prints a warning and exits cleanly** (code 0),
so the Composer script chain is never broken:

```
$ composer model:gen
[warning] Skipping 'code:models' — artisan not found (not a Laravel project?).
[warning] Skipping 'ide-helper:models --write' — artisan not found (not a Laravel project?).
> vendor/bin/pint --dirty
```

Commands that gracefully skip: `meta:helper`, `meta:ide`, `meta:models`, `meta:all`,
`model:gen`, `test:parallel`, `queue:listen`.

Commands that work without artisan: all `pint:*`, `lint:*`, `test:*` (pest), `sonar`, `check-deps`.

**Forcing a hard failure** — call `Artisan::require()` in your own script class when
you want an error instead of a silent skip:

```php
use Masgeek\ComposerScripts\Support\Artisan;
use Composer\Script\Event;

class MyScripts
{
    public static function deploy(Event $event): void
    {
        Artisan::require($event, 'config:cache');   // throws if artisan absent
        Artisan::require($event, 'route:cache');
        Artisan::require($event, 'view:cache');
    }
}
```

---

## Extending the package

Compose package callbacks with project-specific steps in any `scripts` array:

```json
{
    "scripts": {
        "ci": [
            "Masgeek\\ComposerScripts\\CodeQuality::pintCheck",
            "Masgeek\\ComposerScripts\\CodeQuality::analyse",
            "Masgeek\\ComposerScripts\\Testing::ci",
            "Masgeek\\ComposerScripts\\CodeQuality::sonar"
        ],
        "fresh": [
            "@php artisan migrate:fresh --seed",
            "Masgeek\\ComposerScripts\\IdeHelper::modelGen"
        ],
        "queue:all": [
            "Composer\\Config::disableProcessTimeout",
            "@php artisan queue:listen --timeout=0 --queue=broadcast,sms,campaign,archive,zoho,map-network,call-back"
        ]
    }
}
```

---

## Requirements

- PHP **8.2+**
- Composer **2.x**

No framework dependency — works with Laravel, Symfony, or plain PHP projects.
Each script only requires its own tool (`pest`, `pint`, `phpstan`, `sonar-scanner`, etc.)
and only when that specific script is run.
