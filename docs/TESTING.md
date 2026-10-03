# Generate unit and feature tests with Pest

DDD generates Pest tests for both module layouts and the legacy DDD/CRUD workflow.
Pest is an application **development dependency**, not a production requirement of
the generator. The new Pest generators do not convert or overwrite application tests.

## Install once in the consuming application

Commit or review your current Composer changes first. If `phpunit/phpunit` is a
direct development requirement, remove that constraint before installing Pest;
Pest selects a compatible PHPUnit version itself:

```bash
composer remove phpunit/phpunit --dev --no-update
composer config allow-plugins.pestphp/pest-plugin true
```

Use the dependency set matching your Laravel major:

```bash
# Laravel 12
composer require --dev 'pestphp/pest:^4.4.1' 'pestphp/pest-plugin-laravel:^4.1' --with-all-dependencies

# Laravel 13 (current releases)
composer require --dev 'pestphp/pest:^5.3' 'pestphp/pest-plugin-laravel:^5.0' --with-all-dependencies
```

Choose one command, not both. Review the resulting lockfile; the Laravel plugin
can require a more recent framework patch than your current install. The plugin
adds convenient Laravel testing functions; generated tests also work with Pest
core because they use the application's `Tests\TestCase` HTTP methods directly.

Keep `"Tests\\": "tests/"` in root `autoload-dev.psr-4`, then run:

```bash
composer dump-autoload
php artisan ddd:setup-tests
```

Setup creates `tests/Pest.php` and `tests/TestCase.php` only when missing. It uses
`phpunit.xml`, or an existing `phpunit.xml.dist`, preserving existing bootstrap,
environment settings, suites, comments and source coverage configuration. For a
new configuration it supplies SQLite in memory and array cache/session settings.
It does not install dependencies, run tests or execute database migrations.

Module discovery includes `src/Domain/*/[Tt]ests/Unit` and
`src/Domain/*/[Tt]ests/Feature`. Both `tests/` and legacy `Tests/` work. These
patterns also discover modules created later, regardless of activation state.
If custom module discovery is already configured, setup preserves it and asks
you to verify its coverage instead of adding overlapping suites. Re-running
setup does not duplicate its entries. For a custom module root:

```bash
php artisan ddd:setup-tests --modules-path=src/Modules
```

The default comes from `modules.paths.modules`. Roots must stay inside the app.
`module:update-phpunit-coverage` remains a separate source-coverage operation;
it does not configure test discovery.

## Create tests

```bash
# Module feature test (default)
php artisan module:make-test Http/CreateOrderTest Sales

# Isolated unit test
php artisan module:make-test Pricing/TotalTest Sales --unit

# Both types in one command
php artisan module:make-test OrderTest Sales --both

# Legacy DDD domain, capitalized Tests/ layout
php artisan ddd:make Test --domain=Sales --name=Http/CreateOrderTest --feature
php artisan ddd:make Test --domain=Sales --name=Pricing/TotalTest --unit
php artisan ddd:make Test --domain=Sales --name=OrderTest --both
```

Module commands also accept the active module selected by `module:use`. Unknown
modules fail; no other module is selected automatically. Omit `--name` from the
DDD command to use the domain's name. `--unit`, `--feature` and `--both` are mutually
exclusive. Nested names are supported and the `Test` suffix is added only once.
Invalid paths, symbolic-link escapes and existing output files are rejected.

Full/API `module:make` and `ddd:make Domain` create `ExampleTest.php` in both test
directories automatically. Plain modules stay minimal; add tests with the command.
`ddd:make Crud` adds unit and feature files named after the entity, preserving
existing entity tests. Starter tests are **todos**, not completed coverage:

```php
<?php

uses(\Tests\TestCase::class);

it('implements OrderTest feature behavior')->todo();
```

Replace each todo with real assertions for business behavior, authorization,
validation, tenant boundaries and persistence. No route, permission rule, schema
or response contract is guessed. Feature files bind the Laravel application base
class explicitly; unit files do not bootstrap Laravel. Keep unit directories out
of global Laravel base-class bindings in an existing `tests/Pest.php`. If your app
uses another test base class, adapt the generated feature binding accordingly.

Opt into `RefreshDatabase` only for tests needing the database, using your
application's isolated test database and fixtures. No database trait is enabled
automatically by the templates.

## Run and maintain tests

```bash
vendor/bin/pest
vendor/bin/pest --testsuite='DDD Unit'
vendor/bin/pest --testsuite='DDD Feature'
vendor/bin/pest src/Domain/Sales/tests
```

The suite names above apply when setup added the default module discovery.
Existing custom suite names remain unchanged. Todos remain visibly unfinished;
they are not passing assertions. Do not treat generating files as testing your app.

Existing PHPUnit class tests can remain alongside Pest tests. Review old PHPUnit
annotations and version-specific APIs when upgrading their runner. The previous
reflection-based DDD generator is still explicitly available with
`ddd:make Test --domain=Sales --legacy-phpunit`; it retains its old PHPUnit output
and write behavior. The normal `ddd:make Test` command now generates Pest.
An old PHPUnit XML schema may also need `vendor/bin/pest --migrate-configuration`;
review that migration separately. Setup does not remove old configuration options.

If you published an earlier `config/modules.php` with an explicit command list,
add `theaddresstechnology\DDD\Modules\Console\Commands\MakeTestCommand::class` to
that list. Do not overwrite your configuration just to add this command. Clear
the configuration cache after adapting it.

Package CI runs the existing security regressions and new generator tests through
Pest 4/Laravel 12 and Pest 5/Laravel 13 on PHP 8.4/8.5. Generated fixtures are run by
the real Pest executable: unit cases stay isolated and feature cases boot Laravel
and perform HTTP assertions.

References: [Pest installation](https://pestphp.com/docs/installation),
[test configuration](https://pestphp.com/docs/configuring-tests), and
[todos](https://pestphp.com/docs/skipping-tests#creating-todos).
