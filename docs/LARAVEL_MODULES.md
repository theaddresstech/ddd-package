# DDD v2 and Laravel Modules

Reviewed against `nwidart/laravel-modules` v13.0.0 and its
[official introduction](https://laravelmodules.com/docs/13/getting-started/introduction),
[setup](https://laravelmodules.com/docs/13/getting-started/installation-and-setup),
and [upgrade guide](https://laravelmodules.com/docs/13/getting-started/upgrade).
DDD provides its own module runtime; it is not a complete reimplementation of that package.

| Area | DDD v2 status |
| --- | --- |
| Identity, activation, priority, providers | Supported using `module.json` and a boolean JSON status registry. |
| Dependency ordering | Added: `requires`, cycle/missing dependency detection, protected disable/delete. |
| Config, views, translations, migrations | Supported; published view overrides and JSON translations tested. |
| Routing | Added automatic conventional web/API route loading with cache awareness. |
| Generators | Minimal module/artifact generators plus the legacy DDD REST/CRUD workflow. Command options and generated layouts differ. |
| Composer integration | Optional root merge plugin; configuration alone does not install or trust it. |
| Asset tooling | Vite 8/Sass module build and manifest JS/CSS rendering for the default layout. |
| Diagnostics | Added standalone read-only v2 upgrade/module doctor. |
| Pest tests | Unit/feature generators, automatic starter tests, application setup and both module layouts; see [testing](TESTING.md). |
| Models, repository methods and facade API | DDD-specific APIs; no complete Nwidart API compatibility promise. |
| Livewire and third-party module plugins | Not integrated or tested as replacements. |
| Module cache | Metadata snapshot export; not a complete persistent discovery-cache implementation. |
| Custom namespaces/layouts | Some paths configurable; generators, namespace conventions and asset URLs still assume DDD defaults. |
| Package publishing/distribution | Trusted Composer commands available; no marketplace, signing or dependency isolation system. |

## What to add to your application

1. The root `Src\` PSR-4 mapping and, when needed, the trusted merge plugin plus
   `extra.merge-plugin.include` for per-module manifests.
2. A reviewed `module.json`, correct provider classes and explicit `requires` for
   dependencies; a versioned/deployed status registry with appropriate booleans.
3. Real policies, tenant scopes, validation and admin gates. Module enablement is
   application composition, not authorization or tenant isolation.
4. Passport setup/migration and Activitylog schema/data migration where relevant.
5. Application-owned tests, migrations, lockfiles, cache/worker deployment steps,
   and an asset build for each frontend module.

## Migrating an existing Nwidart application

Do not install both runtimes. Their `module:*` commands, `Module` alias, helpers and
`modules` configuration collide; v2 declares a Composer conflict explicitly.

In a separate application branch, inventory every Nwidart facade/helper/provider,
namespace, plugin and manifest field. Port those references to DDD APIs, map modules
to `Src\Domain`, and adapt provider registration, configuration, routes and asset
builds. Preserve module state and data. Review Composer mappings and remove the old
runtime only as part of the tested transition. Existing Nwidart application code
is not made compatible merely by copying its module manifests.

Neither runtime creates a security boundary between arbitrary PHP modules. Keep
module code and manifests trusted and implement application-level permissions.

## Recommended future additions

These are remaining work, not shipped claims:

- Broader native generator coverage: model/controller/migration/observer/service
  commands with explicit options and tested custom namespace/layout support.
- A validated adapter/migration tool for selected Nwidart API conventions, if actual
  consumers need that compatibility; do not advertise drop-in support today.
- Persistent discovery caching with invalidation and measured large-module startup
  benchmarks, plus dependency-aware migration planning.
- Optional Livewire/Inertia framework integrations and tested asset URL customization.
- PostgreSQL/MySQL consumer integration suites and more browser-level scaffold coverage.

V2 prioritizes safe activation/dependencies, useful diagnostics and working generated
code over adding commands that only appear to support a feature.
