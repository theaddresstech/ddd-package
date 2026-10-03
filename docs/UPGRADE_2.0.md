# Proposed 2.0 upgrade: compatibility review

This is a release proposal, not a published version. The security and module PR
stack must not be treated as a drop-in 1.x patch. The latest published release
checked on 2026-10-03 is v1.1.15.

## Existing applications versus regenerated code

Installing a package version does not rewrite a consumer's generated controllers,
models, repositories, helpers or GraphQL endpoints. Most template hardening takes
effect only when it is applied to application code. Regenerating an existing app
with `ddd:directory --force` is not an upgrade procedure: it overwrites application
files, and its backup covers only `src/`.

Package classes, Composer dependencies, service-provider boot and CLI commands
do change immediately on update. Module discovery is new runtime behavior.
Manifests with invalid aliases, duplicate module identities or invalid status
JSON can now stop startup instead of being accepted silently. Symlinked paths
outside the application root are rejected. Validate existing manifests, paths
and status files before opting in.

The compatibility follow-up fixes one confirmed startup regression: configuration
cached before module support lacked activator defaults and caused a TypeError.
Defaults are now filled in memory even with a cache; cached application settings,
empty command lists and disabled modules are preserved. This does not replace
rebuilding deployment caches after an upgrade.

## Intentional changes requiring migration

- GraphQL makers, schema/resolver/test templates and Lighthouse configuration are
  removed. Scripts invoking those makers must change. Existing generated GraphQL
  services remain consumer-owned and are not deleted by a Composer update.
- New CRUD policies deny access until the application implements permissions,
  ownership checks and list/tenant scopes. Domain administration requires
  `manage-domains`; admin middleware requires `access-admin`.
- Updated query criteria require explicit projection, sorting and relation
  allowlists. Requests using undeclared fields or includes are rejected.
- Updated uploads require matching MIME/extension and a maximum of 10 MiB.
  Existing workflows accepting other formats or sizes need an explicit policy.
- Login throttling, password hashing and module activation controls change
  generated application behavior. Apply and test those changes deliberately.
- Scaffold rewrites require explicit force, unsupported makers return failure,
  and unknown modules no longer select every module for maintenance commands.

## Latest dependency assessment

Composer metadata checked on 2026-10-03:

| Dependency | Tested version | Latest stable checked | Latest requirements |
| --- | --- | --- | --- |
| `spatie/laravel-activitylog` | 4.12.3 | 5.1.1 | PHP ^8.4; Laravel 12 or 13 |
| `spatie/laravel-query-builder` | 6.4.4 | 7.3.5 | PHP ^8.3; Laravel 12 or 13 |
| `laravel/passport` | 13.8.0 | 13.8.0 | PHP ^8.2; Laravel ^11.35, 12 or 13 |
| `orchestra/testbench` (development) | 10.12.0 | 11.3.0 | PHP ^8.3; Laravel ^13.32 |
| `phpunit/phpunit` (development) | 11.5.56 | 13.4.0 | PHP >=8.4.1 |

All dependencies already resolve to the latest versions allowed by the current
constraints: `composer update --dry-run --no-plugins --no-scripts` makes no changes.
Moving every direct dependency to its latest major is a separate compatibility
change. In particular, requiring Activitylog 5 drops PHP 8.2/8.3 consumers.
The latest development tools would require PHP 8.4.1+ and a Laravel 13 test lane.
These major upgrades have not been applied or validated by the current suite.
Composer audit reports no known advisories in the current resolved set.

## Release proposal and verification limits

Use an explicit 2.0 release for the breaking stack, retaining existing 1.x tags.
Consumers requiring `^1.1` will not select 2.0; applications using `dev-main`, `*`
or unbounded ranges do not have that protection. Pin those consumers before
merging changes to their tracked branch. Never move an existing release tag.

Before an application opts into 2.0, test its locked dependencies and PHP runtime,
cached/uncached startup, login and real OAuth token issuance, authorized and denied
CRUD, queries, uploads, module activation and application-specific integrations.
Rebuild config/route caches and restart long-running workers during deployment.
Deploy only after staging checks and a tested rollback to the previous lockfile
and release artifact. Generated security changes need an application code review.

The package regression suite and PHP CI do not establish production compatibility
for every consumer. No production deployment, database or live OAuth integration
was tested. See [the security audit](SECURITY_AUDIT_2026-10-03.md) for measured scope.
