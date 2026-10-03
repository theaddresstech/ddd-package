# DDD for Laravel — v2

`theaddresstechnology/ddd` generates REST/web application code and manages modules
under `src/Domain`. Version 2 adds security hardening, module diagnostics and
modern dependencies. GraphQL support and generation have been removed.

**Existing v1 application? Start with [the v1 → v2 upgrade guide](docs/UPGRADE_2.0.md).**
Updating Composer does not update previously generated application code.

## Requirements

| Component | Supported version |
| --- | --- |
| PHP | 8.4.1+ within PHP 8.x |
| Required PHP extensions | DOM and Fileinfo, plus Laravel's requirements |
| Laravel | 12 or 13 |
| Passport | ^13.8 |
| Spatie Activitylog | ^5.1.1 |
| Spatie Query Builder | ^7.3.5 |
| Node, for generated module assets | ^20.19 or >=22.12; CI uses Node 24 |
| Vite / Sass, for generated module assets | ^8.3.2 / ^1.105.1 |

The library does not ship a consumer lockfile. Commit your application's
`composer.lock` and use `composer install` during deployment.

## Install in an existing Laravel application

```bash
composer require theaddresstechnology/ddd:^2.0
```

Add `"Src\\": "src/"` to your application's existing `autoload.psr-4` map, keeping
its other entries, then run:

```bash
composer dump-autoload
php artisan vendor:publish --tag=ddd-config
php artisan module:setup
php artisan module:make Blog --api
php artisan module:list
vendor/bin/ddd-doctor --json
```

Review an existing `config/modules.php` before publishing. This package and
`nwidart/laravel-modules` use overlapping helpers, config and commands and cannot
be installed together. Read the [comparison and migration notes](docs/LARAVEL_MODULES.md).

The root `Src\\` mapping loads normal DDD classes. To merge per-module dependencies,
autoload files and additional PSR-4 mappings, install and explicitly trust
`wikimedia/composer-merge-plugin` as described in [module setup](docs/MODULES.md).
`module:setup` writes the include pattern; it does not install or trust plugins.

## Generate application code

For an application already using the DDD scaffold:

```bash
php artisan ddd:make Domain --name=Sales
php artisan ddd:make Crud --name=Order --domain=Sales
```

CRUD generation creates a model, migration, factory, seeder, requests, repository,
resource, policies, controllers/routes and Pest unit/feature test todos. It does **not** generate a completed
business module, tenant isolation, views or a datatable automatically. Implement
validation, policy decisions, ownership/tenant scopes, and repository allowlists.
Generated CRUD policies and broadcast channels deny access by default.

For a **new disposable Laravel application only**, `php artisan ddd:directory --force`
creates the legacy DDD application structure. It rewrites bootstrap, routes, auth
configuration, migrations and `src/`. Its automatic backup covers **only `src/`**;
never use this command to upgrade a production application.

## Modules and upgrade diagnostics

- `module.json` describes identity, providers, included PHP files, priority and
  `requires` dependencies. Dependencies boot first; cycles and missing/disabled
  dependencies fail clearly. Active dependents prevent disabling/deleting prerequisites.
- Enabled modules load routes, config, views, PHP/JSON translations and migrations.
  Published views take precedence. Boot bookkeeping is scoped to the application.
- `module:disable Blog` preserves its files and data. Clear deployment caches and
  restart workers; module state is not a PHP execution sandbox or authorization rule.
- `vendor/bin/ddd-doctor [app-directory] --json --strict` checks migration readiness
  without booting Laravel or modifying application files. Errors return exit 1;
  `--strict` also fails on warnings. Read [diagnostic scope](docs/MODULES.md#upgrade-diagnostics).
- Artifact generators refuse to overwrite existing files. Module asset builds use
  Vite 8 and produce JS/CSS tags through `module_vite()`.

## Pest unit and feature tests

New domains, full/API modules and CRUD scaffolds include Pest unit and feature
test files. Install Pest in your application's development dependencies, then:

```bash
php artisan ddd:setup-tests
php artisan module:make-test OrderTest Sales --both
php artisan ddd:make Test --domain=Sales --name=PricingTest --unit
vendor/bin/pest
```

Feature tests use your Laravel `Tests\TestCase`; unit tests stay isolated.
Generated cases are todos for you to implement, and existing tests are preserved.
See [Pest setup, dependency versions and examples](docs/TESTING.md).

## Security behavior

Generated domain administration requires `auth:api` and a `manage-domains` gate;
admin middleware requires `access-admin`. Generated login validates and throttles
credentials, rotates sessions, and hashes User passwords. Repository criteria
require declared searchable fields and explicit allowed fields, sorts and includes.
Uploads accept matching JPEG/PNG/GIF/WebP/PDF content up to 10 MiB.

Filesystem operations reject paths escaping their configured roots. Status writes
are locked and atomic. Unknown module selections fail before maintenance runs;
migrations, seeders and pruning propagate command failures.

These safeguards require application policies, scopes, upload serving controls
and deployment procedures. They do not replace reviewing generated code.

## Documentation

- [v1 → v2 upgrade and rollback](docs/UPGRADE_2.0.md)
- [Module setup, dependencies, assets and diagnostics](docs/MODULES.md)
- [Command reference](docs/COMMANDS.md)
- [Pest unit and feature testing](docs/TESTING.md)
- [Laravel Modules comparison and remaining additions](docs/LARAVEL_MODULES.md)
- [V2 audit, validation and limits](docs/V2_AUDIT.md)
- [Original security findings](docs/SECURITY_AUDIT_2026-10-03.md)
- [Changelog](CHANGELOG.md)

## Development

```bash
composer update --no-scripts
composer validate --strict
composer audit
vendor/bin/pest --fail-on-warning --fail-on-risky --fail-on-deprecation --fail-on-phpunit-deprecation --fail-on-phpunit-notice
```

CI covers PHP 8.4/8.5 with Laravel 12/13 plus a real generated module asset build.
CI uses Pest 4 with Testbench 10/Laravel 12 and Pest 5 with Testbench 11/Laravel 13.
Pest selects its compatible PHPUnit version; the package retains its existing
class-based regression tests and runs them through Pest alongside new Pest cases.
