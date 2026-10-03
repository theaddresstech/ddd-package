# Command reference

Run `php artisan help COMMAND` for Artisan options. Commands below are extracted
from the implementation. “Every module” means enabled modules by default, unless
`module:use` selects an active module. An explicit module is checked before work.

## DDD and diagnostics

- `ddd:make {type}`: legacy DDD makers. Common examples: `Domain --name=Sales` and
  `Crud --name=Order --domain=Sales`. Run help for dynamically registered options.
- `ddd:directory --force`: destructive fresh scaffold; never an upgrade command.
  `--withoutBackup` skips the src backup; `--removeBackup` removes backup/ after success.
- `vendor/bin/ddd-doctor [application-directory] [--modules=src/Domain] [--json] [--strict]`:
  read-only standalone upgrade checks, usable without Laravel boot.

## Module commands

| Signature | Purpose |
| --- | --- |
| `module:composer-update {module?}` | Rewrite a module composer.json autoload |
| `module:delete {module} {--force}` | Delete a module directory |
| `module:disable {module}` | Disable a module without dropping its tables |
| `module:dump {module?}` | Run Composer dump-autoload |
| `module:enable {module}` | Enable a module |
| `module:migrate-fresh {module?} {--database=} {--force}` | Drop all tables and re-run module migrations |
| `module:install {package}` | Install a module from a Composer package name |
| `module:lang {module?}` | Report translation keys missing from a module |
| `module:list` | List modules and whether each one is enabled |
| `module:list-commands {module?}` | List Artisan commands defined by modules |
| `module:make-action {name} {module?}` | Create an action class |
| `module:make-cast {name} {module?}` | Create an Eloquent cast |
| `module:make-channel {name} {module?}` | Create a broadcast channel |
| `module:make-class {name} {module?}` | Create a plain PHP class |
| `module:make {name*} {--plain} {--api} {--disabled} {--force}` | Create one or more modules |
| `module:make-enum {name} {module?}` | Create a PHP enum |
| `module:make-event-provider {name} {module?}` | Create an additional event service provider |
| `module:make-exception {name} {module?}` | Create an exception |
| `module:make-helper {name} {module?}` | Create a helper class |
| `module:make-inertia-component {name} {module?} {--frontend=vue}` | Create an Inertia component |
| `module:make-inertia-page {name} {module?} {--frontend=vue}` | Create an Inertia page |
| `module:make-interface {name} {module?}` | Create an interface |
| `module:make-job {name} {module?} {--sync}` | Create a job |
| `module:make-listener {name} {module?} {--queued}` | Create a listener |
| `module:make-provider {name} {module?}` | Create an additional service provider |
| `module:make-replacement {name} {module?}` | Create a stub replacement class |
| `module:make-trait {name} {module?}` | Create a trait |
| `module:make-view {name} {module?}` | Create a Blade view |
| `module:migrate {module?} {--force} {--database=} {--subpath=}` | Run migrations for one module or every module |
| `module:v6:migrate {--force : Replace an existing status registry}` | Convert legacy module status data to the status file |
| `module:model-show {model} {module?}` | Show a module model attributes and relations |
| `module:prune {module?}` | Prune obsolete Eloquent models for modules |
| `module:publish {module?}` | Copy module public assets into public/modules |
| `module:publish-config {module?} {--force : Replace existing published configuration}` | Copy module config into the application |
| `module:publish-inertia {--frontend=vue}` | Publish an Inertia page resolver for every module |
| `module:publish-migration {module?}` | Copy module migrations into the application |
| `module:publish-translation {module?}` | Copy module translations so the app can override them |
| `module:migrate-refresh {module?} {--force} {--database=} {--subpath=}` | Roll module migrations back and run them again |
| `module:migrate-reset {module?} {--force} {--database=}` | Reset module migrations |
| `module:migrate-rollback {module?} {--force} {--database=} {--subpath=}` | Roll back module migrations |
| `module:route-provider {name} {module?}` | Create an additional route service provider |
| `module:seed {module?} {--force} {--database=}` | Run module seeders in priority order |
| `module:setup` | Create the module directories the package expects |
| `module:migrate-status {module?} {--database=}` | Show migration status for modules |
| `module:unuse` | Forget the remembered module |
| `module:update {package?}` | Update Composer dependencies for modules |
| `module:update-phpunit-coverage` | Add enabled modules to phpunit.xml |
| `module:use {module}` | Remember a module for later generators |

## Operational notes

- `module:make --force` does not authorize overwriting an existing module; existing
  directories are rejected. Artifact commands also preserve existing files.
- Use a correctly bootstrapped DDD application for `ddd:make` CRUD generation.
  `module:make` creates a minimal module; it does not install the legacy DDD scaffold.
- `module:setup` edits root Composer merge includes and creates status/directories;
  install and trust the merge plugin separately if using merged module manifests.
- `module:composer-update` rewrites one module's autoload mapping; it is not a
  dependency update. `module:install`, `module:update` and `module:dump` run Composer
  with normal application plugin/script behavior. Review installed code first.
- Maintenance commands without an explicit module honor the active selection, then
  enabled modules. Explicit disabled modules may be targeted for operator maintenance.
- Migrations pass selected paths together as one Laravel command, preserving a shared
  batch. Laravel determines migration ordering from filenames. Seeder ordering follows
  module dependencies/priority. Laravel database command failures return nonzero.
- `module:migrate-fresh --force` drops **all tables in the selected database**, not
  just tables owned by the named module. It is never a production upgrade procedure.
- `module:delete --force` deletes code, not database tables. Dependencies prevent
  removing prerequisites of enabled modules. Back up reviewed code before deletion.
- Migration publishing preserves filenames and refuses conflicting files. Do not
  run both published and module migrations as separate copies of the same migration.
- Config publishing preserves existing configuration unless `--force` is specified.
  Static asset/translation publishing can replace existing destination files.
- `module:v6:migrate` executes a trusted legacy PHP status file, rejects missing or
  ambiguous states, and requires `--force` to replace an existing registry. It does
  not migrate a DDD v1 application to v2.
- Inertia helpers generate frontend placeholders/resolver glue, not a complete
  frontend installation. Install/configure the chosen framework in the application.
