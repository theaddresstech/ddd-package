# Changelog

## 2.0.0 — 2026-10-03

### Breaking changes

- Require PHP ^8.4.1, Activitylog ^5.1.1, Query Builder ^7.3.5 and Passport ^13.8.
  Support Laravel 12/13. See [upgrade instructions](docs/UPGRADE_2.0.md) for app code/data migrations.
- Remove GraphQL support and all generation/config/test templates.
- Generated CRUD policies deny by default; management gates, repository allowlists,
  upload limits and safer maintenance behavior require application adoption.
- Prevent co-installation with `nwidart/laravel-modules`, which shares runtime names.

### Added

- Standalone `ddd-doctor` with JSON output and strict warning handling.
- Module `requires` dependencies, deterministic boot ordering, cycle checks and
  prerequisite protection during enable/disable/delete.
- Module web/API route loading, JSON translations, published view precedence,
  Vite 8/Sass builds and CSS manifest rendering.
- Functional cast/channel/job skeletons and generator overwrite protection.
- Laravel 12/13 and PHP 8.4/8.5 CI, generated asset builds, real OAuth regression tests,
  revised documentation and Laravel Modules gap analysis.

### Fixed

- Authorization, query-field disclosure, output escaping, password handling,
  login throttling, upload validation and current-user permission checks in stubs.
- Filesystem confinement, symlink operations, atomic activation state and
  destructive-command validation; see the security audit for conditions and limits.
- Cached-config startup, application-scoped module boot state, missing provider
  detection, duplicate aliases, malformed manifests and CamelCase names.
- Query Builder 7 integration and Passport 13 generated User contract.
- Legacy migration/seeder paths, grouped migration batches, seed/prune failure codes,
  migration filename preservation, safe legacy-state conversion and Composer I/O.

## 1.1.15

Allow Passport 13 alongside Passport 12. Existing 1.x tags are retained.
