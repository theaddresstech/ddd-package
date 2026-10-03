# V2 release audit — 2026-10-03

This review covers the DDD package, generated templates, module runtime/commands,
dependency integrations, build workflow and documentation. It combines the
[15 original security findings](SECURITY_AUDIT_2026-10-03.md) with the v2 review
below. It is source review and executable regression testing, not a penetration
test or certification of consuming applications.

The existing PR stack was reviewed before adding fixes: [#4](https://github.com/theaddresstech/ddd-package/pull/4)
contains the original scaffold safeguards; [#5](https://github.com/theaddresstech/ddd-package/pull/5)
introduces modules; [#6](https://github.com/theaddresstech/ddd-package/pull/6)
contains the follow-up security fixes, GraphQL removal and complete v2 work.
Integrating the stack from its tip keeps the complete tested change together
before it reaches `main`.

## Additional findings and changes

| Area | Finding | V2 resolution |
| --- | --- | --- |
| Cached configuration | A pre-module Laravel config cache omitted required defaults and could prevent startup. | Fill missing defaults in memory while preserving existing settings, paths and intentionally empty command lists. |
| Dependency upgrades | Old generated query methods and User contracts did not match current Query Builder/Passport APIs. | Variadic explicit query allowlists, working sorts, non-accumulating filter arrays, removed unsupported appends, correct model queries and Passport's authenticatable contract. |
| Module boot | Global static boot state could leak between application instances; missing providers were silently ignored. | Application-scoped weak references, explicit provider failures, route-cache awareness and tested conventional routes. |
| Identity/dependencies | Invalid manifests and alias collisions were ambiguous; activation could leave enabled modules without prerequisites. | Strict manifest types, unique identities/aliases, `requires` dependency ordering, cycle/missing checks and protected prerequisite disable/delete. |
| Migration commands | Separate calls split batches; old DDD directories were missed; publication renamed existing migrations. | One call with all selected paths, legacy directory support, original filenames, identical-file skipping and collision failures. |
| Seed/prune commands | Missing seed classes or failed child commands could appear successful. | Load trusted confined seeder files when needed, support both layouts, propagate database selection and failure codes. Default selection excludes disabled modules. |
| Publishing | Published views/config did not consistently match runtime precedence or keys; PHP translation overrides replaced the whole module namespace. | Published views override module views, root config publishes under its alias, existing config is preserved unless explicitly forced. Translation publication uses Laravel's standard vendor path so partial overrides retain defaults. |
| Legacy status conversion | Missing files and ambiguous values could replace activation state unexpectedly. | Reject missing/invalid input and require `--force` to replace an existing registry. The legacy PHP file remains trusted executable input. |
| Composer operations | Sequential subprocess pipe reads could stall; manifest rewrites were unchecked/non-atomic. | Argument-array Symfony processes stream both outputs; checked atomic manifest replacement refuses linked files. Composer plugins/scripts remain trusted code. |
| Generated modules | CamelCase names were flattened, artifacts overwrote files, several skeletons lacked their Laravel contracts and assets referenced a missing import. | Preserve names, reject overwrite, functional casts/jobs and deny-default channels, valid JS/Sass entrypoints and a real Vite build. |
| Asset rendering | Vite manifest location and CSS rendering did not match generated output. | Stable manifest location, safe JS/CSS tags, imported CSS traversal with cycle/deduplication handling and hyphenated aliases. |
| Runtime coexistence | Nwidart and DDD register overlapping helpers, config, aliases and commands. | Explicit Composer conflict and a documented comparison/migration boundary; no drop-in compatibility claim. |
| Upgrade diagnostics | Broken Laravel startup made module readiness difficult to inspect. | Standalone read-only `ddd-doctor` with human/JSON output, actionable errors and optional strict warnings. |

GraphQL makers, templates, configuration and generation hooks are removed. Legacy
GraphQL application files are not deleted by a Composer update. The initial audit
documents authorization, query disclosure, XSS, password/login, upload, path,
activation-state and destructive-scaffold fixes in detail.

## Dependency review

Runtime requirements are PHP `^8.4.1`, Activitylog `^5.1.1`, Query Builder
`^7.3.5` and Passport `^13.8`, supporting Laravel 12 and 13. DOM and Fileinfo
extensions are declared. Generated frontend modules use Vite `^8.3.2` and Sass
`^1.105.1`. These were the latest stable runtime/frontend releases checked for
this release; consumers resolve their own reviewed lockfiles.

Testbench 10.12/11.3 supports the two Laravel majors. PHPUnit 13.4.0 cannot be
installed with the current Testbench constraints: core 10.15 excludes PHPUnit
13.2+, and core 11.5 excludes 13.4+. The tested latest compatible versions are
13.1.14 and 13.3.6 respectively. Do not bypass those conflicts to claim an
all-latest development environment.

## Validation evidence

- Final local run: **75 tests, 425 assertions**, PHP 8.5.11, Laravel 13.34.0,
  Testbench 11.3.0 and PHPUnit 13.3.6. Warnings, risky tests, deprecations and
  PHPUnit notices are configured to fail the run.
- The preceding 74-test/420-assertion checkpoint also passed PHP 8.5.11 with
  Laravel 12.69.3/Testbench 10.12.0/PHPUnit 13.1.14, and PHP 8.4.25 with Laravel 13.
  Final regressions additionally cover Composer namespace/linked-manifest handling
  and partial PHP translation overrides with retained defaults.
- CI runs the final suite on Linux with PHP 8.4/8.5 crossed with Laravel 12/13,
  plus a Node 24 generated-module build. Exact commit results are recorded in
  [GitHub Actions](https://github.com/theaddresstech/ddd-package/actions/workflows/security.yml)
  and PR checks; the workflow definition alone is not a passing result.
- Generated applications boot and list routes in separate PHP processes.
  Regression tests execute generated authorization, query, upload and model code.
  Real Passport keys/client/token issuance, protected-route authentication and
  revocation are exercised against an isolated SQLite database. Activitylog 5
  records changes using its new schema and excludes a tested sensitive field.
- Module tests cover dependencies, registry corruption/concurrent writes,
  provider failures, per-application boot state, routes/translations/views,
  legacy migration/seeder paths, maintenance failures, publishing collisions,
  generator preservation and standalone diagnostic behavior.
- `composer validate --strict`, `composer audit`, PHP syntax checks and
  `git diff --check` pass. Composer reports no known advisories for the resolved
  local set. Generated module `npm audit` reports no known vulnerabilities;
  Vite builds real JS/CSS and the output manifest is checked.
- The initial audit's limited high-confidence credential-pattern scan found no
  matches in the reviewed tracked inventory and 45 reachable commits. This is
  not a comprehensive secret-scanner attestation.

## Application work and remaining coverage

Follow [UPGRADE_2.0.md](UPGRADE_2.0.md) before changing a v1 application. Existing
generated code, policies, schema, OAuth clients and keys do not migrate themselves.
Activitylog 5 needs application-owned schema/data changes; Query Builder 7 and
Passport 13 need application code review. PHP/Laravel minimums are intentional
breaking changes. Existing `^1` constraints remain on the v1 release line.

The [Laravel Modules review](LARAVEL_MODULES.md) separates shipped features from
future additions: broader native generators, tested custom namespaces/layouts,
optional framework integrations and persistent discovery caching remain gaps.
The doctor checks default paths and metadata, not arbitrary PHP configuration,
provider behavior, databases, permissions or live integrations.

No production consumer was deployed or modified, and no production database
migration was run. Tests do not cover every OAuth grant, a real existing-token
upgrade, MySQL/PostgreSQL behavior, browser rendering, external integrations,
Windows paths, minimum-version dependency resolution or every generated command
combination. Fresh scaffold boot and focused security tests are not full branch
coverage or a promise of production compatibility.

Module code, PHP config/status sources, Composer plugins/scripts and authorized
local operators remain trusted. Module disable is not a sandbox and requires
cache rebuilding/worker restarts. Filesystem checks do not defeat a hostile local
process racing a path replacement. Scaffold backups cover only `src/`, not the
whole application; destructive commands retain the explicitly documented risks.
Upload serving headers, malware scanning, quotas, tenant isolation and actual
policy decisions belong to each consuming application.

Repository security settings were inspected during the initial audit but not
changed. CI is an added control; it does not enable repository secret scanning,
push protection or Dependabot security updates automatically.
