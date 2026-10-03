# Upgrade from v1 to v2

Version 2 is a breaking release. Existing `^1.1` Composer constraints do not select
v2 automatically. Keep existing release tags and production lockfiles intact until
an application's own staging checks pass. Do not run `ddd:directory --force` to upgrade.

## 1. Inventory and prepare

Create an upgrade branch and retain the current deploy artifact and lockfile.
Back up the database and validate restoration before schema changes. Record:

- Web, CLI, queue and scheduler PHP versions: all must be **8.4.1+**.
- Laravel: use 12 or 13. A Laravel 13 upgrade is separate from the DDD upgrade.
- Custom/generated `Src` models, repositories, policies, helpers and providers.
- Activitylog custom models, traits, config, schema and stored audit records.
- Passport version, user models, clients, keys and current token behavior.
- Module paths, namespaces, status registry and published configuration.
- Existing GraphQL endpoints and consumers, if any.

Use `composer why-not theaddresstechnology/ddd 2.0.0` after the release is indexed.
Resolve incompatibilities explicitly; do not use aliases or `--ignore-platform-reqs`
to force incompatible dependencies into production.

## 2. Resolve the dependency update in a development/staging copy

Update the requirement to `"theaddresstechnology/ddd": "^2.0"`, then:

```bash
composer update theaddresstechnology/ddd --with-all-dependencies --no-scripts
composer validate --strict
composer audit
```

`--no-scripts` avoids booting the old application during the initial update. It does
not disable trusted Composer plugins. Review the resulting lockfile: this upgrades
Activitylog to 5, Query Builder to 7 and Passport to 13. Do not copy an unreviewed
lockfile to production. The library's development tools are not consumer requirements.

Run the new diagnostic before Artisan boot:

```bash
vendor/bin/ddd-doctor --json
vendor/bin/ddd-doctor --strict
```

The doctor is read-only and checks default module layout/statuses and Composer
metadata. It does not certify database migrations, authorization, OAuth clients,
custom config paths, provider code or live integrations. See its documented scope.

## 3. Migrate dependency-specific application code and data

### Activitylog 4 → 5

Follow the [official Activitylog upgrade guide](https://github.com/spatie/laravel-activitylog/blob/main/UPGRADING.md).
Add the new nullable `attribute_changes` column to your existing activity table and
migrate recorded `attributes`/`old` changes out of `properties`, preserving other
custom properties. Do this with a reviewed application migration and test it on a
restored staging database. Do not publish and rerun a create-table migration over
an existing table.

Update moved namespaces, including `Models\Concerns\LogsActivity` and
`Support\LogOptions`. Replace removed batch APIs and changed activity accessors.
Preserve or archive old batch identifiers before removing their column. Define
explicit logged fields so credentials and other sensitive data are excluded.
The DDD package does not mutate consumer activity tables automatically.

### Query Builder 6 → 7

Follow the [official Query Builder guide](https://github.com/spatie/laravel-query-builder/blob/main/UPGRADING.md).
Existing generated repositories need variadic allowlists, for example:

```php
$query->allowedFields(...$this->allowedFields)
    ->allowedFilters(...$this->allowedFilters)
    ->allowedIncludes(...$this->allowedIncludes)
    ->allowedSorts(...$this->allowedSorts);
```

Replace wildcard permissions with explicit fields/relationships. Update custom
filters/sorts/includes, enum references and published config where required by the
upstream guide. `allowedAppends()` is not available; append approved attributes in
models/resources under application control. The v2 templates already use the new API.

### Passport 12 → 13

Follow the [official Passport guide](https://github.com/laravel/passport/blob/13.x/UPGRADE.md).
The authenticatable model must implement `Laravel\Passport\Contracts\OAuthenticatable`
alongside `HasApiTokens`. Review renamed middleware, client-secret hashing and
client/schema changes. Preserve existing client identifiers during a controlled
migration; changing them invalidates associated tokens. Do not recreate production
keys or clients to make tests pass. Validate existing and newly issued tokens,
revocation, authorization and every OAuth flow your application uses.

For **new applications only**, publish/run Passport migrations, generate keys, and
create a personal access client using Passport's documented setup. Generated API
login needs that client; DDD does not provision credentials or schema automatically.

## 4. Apply generated security changes to existing code

A Composer update replaces package files. It does not rewrite files in your `src/`.
Review and port the relevant changes from `stub/`; preserve business logic.

| Area | Required application work |
| --- | --- |
| CRUD | Authorize each action/record, register model policies, implement tenant/ownership scopes including lists. Deny-by-default policies need real rules. |
| Administration | Define `manage-domains` and `access-admin` gates for appropriate users; authenticate management APIs using `auth:api`. |
| Queries | Declare searchable fields, `$allowedFields`, `$allowedSorts` and `$allowedIncludes`; reject unsupported requests. |
| Passwords/login | Add the `hashed` cast, validation and throttling; inspect historical password data without blindly rehashing it. Use a shared rate-limit cache across servers. |
| Uploads | Review the 10 MiB and MIME/extension allowlist against business needs; serve public files safely and authorize replacements/deletions. |
| HTML/JS | Apply output escaping and safe JSON serialization to generated datatables and views. |
| Long-lived workers | Replace cached-user permission checks with current-user authorization; restart workers after deployment. |

GraphQL makers, templates and Lighthouse config are removed. Existing generated
GraphQL files/endpoints remain untouched. Remove them and unused dependencies only
after checking their application consumers; updating DDD alone does not disable them.

## 5. Adopt module features deliberately

Old DDD applications can retain their existing providers. A directory without a
`module.json` is not auto-discovered. To adopt registry-based activation for an old
domain, create its identity and provider mapping manually after reviewing
[the manifest format](MODULES.md#manifest-and-dependencies); do not regenerate it.

- Preserve your root `Src\` mapping. Enable Composer merging when using per-module
  autoload/dependency entries, then regenerate the autoloader.
- Review `config/modules.php` against the v2 defaults. Merge application settings;
  do not overwrite an existing unrelated module configuration with `--force`.
- Set a legacy domain provider's `$moduleName` and port `moduleIsEnabled()` guards
  from the v2 abstract provider before relying on registry disable behavior.
- Use boolean values in `modules_statuses.json`. Missing entries default enabled;
  corrupt values fail instead of silently re-enabling modules.
- Review `requires` dependencies, unique names/aliases, paths and provider classes.
  `Database/Migrations` and `Database/Seeds` remain usable for legacy DDD domains.
- `module:v6:migrate` converts a trusted legacy PHP status file, not a DDD v1 app.
  It rejects missing/ambiguous data and needs `--force` to replace an existing JSON registry.

For migration from `nwidart/laravel-modules`, see [the comparison](LARAVEL_MODULES.md).
DDD is not an automatic replacement for its namespaces, APIs, integrations or layout.

## 6. Verify and deploy

After adapting code and schema in staging:

```bash
composer dump-autoload
php artisan optimize:clear
php artisan about
php artisan route:list
php artisan module:list
vendor/bin/ddd-doctor --json
php artisan test
```

Run the application's real database, OAuth, upload, permission, tenant isolation,
module state and external integration checks. Build front-end assets. Review seeders
before running them: generated seeders are scaffolding, not production data.

Deploy an immutable artifact with the reviewed lockfile using `composer install`.
Run only reviewed application migrations, rebuild config/route caches and restart
queue/Octane/scheduler workers using your deployment process. Verify health and
critical flows before completing rollout. `module:migrate-fresh --force` drops all
tables in the selected database and is never an upgrade step.

## Rollback

Restore the previous application artifact and lockfile, clear/rebuild its caches
and restart workers. A code rollback does not undo data/schema migrations. Prefer
compatible additive migrations first, retain old audit data, and prepare an explicit
reverse migration or tested database restore. Do not promise zero downtime or a
lossless rollback without validating the application's own migration sequence.
