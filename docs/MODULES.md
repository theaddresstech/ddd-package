# Modules in DDD v2

## Setup and layout

Keep `"Src\\": "src/"` in root `autoload.psr-4`. Run `composer dump-autoload`,
then `php artisan module:setup`. Setup creates the module root/status file and adds
`src/Domain/*/composer.json` to `extra.merge-plugin.include` in root `composer.json`.
It does not download dependencies or grant Composer plugin permissions.

For per-module dependencies, autoload files and extra namespace mappings:

```bash
composer config allow-plugins.wikimedia/composer-merge-plugin true
composer require wikimedia/composer-merge-plugin:^2.1
composer dump-autoload
```

Trust and review module Composer manifests before merging them. Disabled module
Composer `files` can still execute: activation is not a sandbox.

```bash
php artisan module:make Blog
php artisan module:make Billing --api --disabled
php artisan module:make Shared --plain
```

A full module contains `Providers/`, `config/`, `database/`, `lang/`, `routes/`,
`resources/`, `tests/`, `module.json`, `composer.json`, `package.json` and Vite config.
API modules omit web/frontend assets; plain modules contain identity and a provider.
Classes use `Src\Domain\Blog\...`. Additional per-module mappings cover lower-case
seeder/factory directories. Generated skeletons need application behavior added.
Existing module directories and artifact files are not overwritten by generators.
Full/API modules include Pest unit and feature todos. Use `module:make-test` to
add named tests and `ddd:setup-tests` for discovery; see [testing](TESTING.md).

The `ddd:make Domain` / `ddd:make Crud` workflow uses the legacy DDD layout with
capitalized directories and richer REST scaffolding. Use it in an application that
already has DDD infrastructure; it is distinct from `module:make`'s minimal module.
Do not mix their layouts by regenerating the same directory.

## Manifest and dependencies

```json
{
  "name": "Billing",
  "alias": "billing",
  "description": "Billing services",
  "keywords": [],
  "priority": 10,
  "providers": ["Src\\Domain\\Billing\\Providers\\BillingServiceProvider"],
  "files": [],
  "requires": ["Accounts"]
}
```

Names preserve CamelCase. Aliases allow letters, digits, `_` and `-`, beginning
with a letter; names and aliases must be unique. Lists contain strings and priority
is an integer. PHP manifest includes must stay within the module root.

Lower priorities are considered first; dependency order takes precedence, with
stable name ordering for equal priorities. `requires` names other modules, not
Composer package/version constraints. Missing/disabled dependencies and cycles
are rejected before enabled modules boot. Enable prerequisites first. Disable
or delete dependents before their prerequisites. State changes use locked atomic
writes; they do not remove code or tables.

Default module maintenance selection uses enabled modules, or the active module
chosen with `module:use`. An explicit name is validated before work begins.

## Runtime

Enabled modules load manifest providers/includes, config, views, JSON/PHP language
files, migrations and `routes/web.php` / `routes/api.php`. Web routes receive the
`web` middleware; API routes receive `api` middleware and the `/api` prefix. Route
files start empty: add endpoints with your own authentication and authorization.
Do not load these same files again from a custom provider. Cached routes are not
reloaded from source.

Use `blog::view-name` for module views; published overrides under
`resources/views/modules/blog` take precedence. `config/config.php` is loaded as
`config('blog...')`; other config files are nested under that alias. JSON language
files are available through normal Laravel translation calls.
Translations use `lang/`, falling back to `resources/lang/` when absent.
`module:publish-translation` copies them to Laravel's `lang/vendor/{alias}`
directory; partial PHP overrides preserve untranslated module defaults.

Extend `Modules\ModuleServiceProvider` from this package for command registration
and `configureSchedules($schedule)`. New command classes in `Console/` can be
registered there. Class-generating helpers are starting points; implement their
behavior. Casts implement Laravel's cast contract, jobs support dispatching, and
broadcast channel stubs deny by default.

Module disable does not unexecute PHP already loaded by Composer or other providers,
revoke user permissions, remove cached routes, or stop active workers. Rebuild caches
and restart long-lived processes after changing activation state.

## Assets

With the default `src/Domain` layout:

```bash
npm install --prefix src/Domain/Blog
npm run build --prefix src/Domain/Blog
```

Commit the consuming module's npm lockfile. Vite 8 requires Node ^20.19 or >=22.12;
CI tests Node 24. Output goes to `public/modules/blog` with `manifest.json`.
Render JS and CSS (including imported chunk CSS) using:

```blade
{!! module_vite('Blog') !!}
```

The helper escapes output and rejects traversal. It supports the default
`/modules/{alias}` URL layout. If you customize module/public locations, update the
Vite output and serving URL integration; the default relative output is not portable
to arbitrary directory layouts. `module:publish` copies static `public/` files;
it does not run npm or replace Vite compilation.

## Upgrade diagnostics

```bash
vendor/bin/ddd-doctor /path/to/application --json
vendor/bin/ddd-doctor --modules=src/Domain --strict
```

This standalone Composer binary does not boot Laravel, read `.env`, run SQL,
execute module providers, or write application files. It can help when Artisan
cannot boot after the package update. Composer's autoloader still loads its normal
registered files; use only trusted installed dependencies.

Checks cover PHP, root Composer metadata, conflicting module runtimes, basic PSR-4
provider paths, duplicate identities/aliases, manifest syntax/fields, missing PHP
includes, dependency ordering, default JSON statuses, old dependency versions,
legacy GraphQL files and cached configuration. Exit codes: 0 means no detected
errors; 1 means errors (or warnings with `--strict`); 2 means invalid CLI arguments.
JSON includes `ok`, `errors`, `warnings`, and a `checks` array with severity/code/message.

The doctor reads the default `modules_statuses.json` and a single module root. It
does not execute `config/modules.php` or inspect custom activators/vendor scan roots.
PSR-4 file existence does not prove a provider compiles or registers correctly.
No database migration, application permission, secret, endpoint or live-integration
validation is implied by a successful result.
