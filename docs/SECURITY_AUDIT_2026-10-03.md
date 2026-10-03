# Package security audit — 2026-10-03

## Scope and repository state

Repository: `theaddresstech/ddd-package` (`theaddresstechnology/ddd`). This is a
package/source audit with executable regression tests against generated PHP,
not a penetration test of a deployed consumer application.

Reviewed `main` at `dfa959bf8662fd80954c4f531f41ea03384fbde5` and both open PRs:

| PR | Reviewed head | Scope and conclusion |
| --- | --- | --- |
| [#4 — Fix destructive scaffolding and unsafe generated paths](https://github.com/theaddresstech/ddd-package/pull/4) | `8af2861d6fb4d2578a3aa2353b0cc82057d226c0` | Draft against main. Adds the force gate, non-destructive domain disable, lexical confinement, authentication, error redaction, and query operator restrictions. Useful baseline, but authentication is insufficient for administrative actions, symlinks bypass lexical checks, and the changed path joining breaks template lookup. |
| [#5 — Add module identity, boot, and generators](https://github.com/theaddresstech/ddd-package/pull/5) | `ba55a607c6ed1c43358abe01a7424b931140621e` | Draft against #4. Adds module discovery, state, publishing and database commands. Additional gaps include traversal-capable aliases, recursive symlink deletion/copy, corrupt state enabling modules, legacy providers ignoring disabled state, and unknown module names selecting every module. |

Neither existing PR reported CI checks or a review decision at audit time; both
were mergeable. This fix branch is based on #5, preserving the existing changes
and showing only additional fixes/removals. Merge order is #4, #5, then this PR.
No existing PR was merged, closed, or rewritten.

Security-sensitive surfaces inspected: package/provider registration; CLI maker
dispatch and file writes; scaffold rewrite and backup; module discovery,
activation, PHP includes, asset publishing, deletion, subprocess and migration
commands; generated HTTP auth/middleware/routes/controllers/policies; password
storage; uploads; query criteria and repositories; HTML/JavaScript generation;
GraphQL generators/templates; Composer constraints and repository automation.
All 349 tracked files at the reviewed head were inventoried. Static sink and
credential-pattern searches covered tracked files and all 45 reachable commits.

## Findings and resolution

Severity reflects the required access and likely impact in a consuming app.
Filesystem/manifest findings usually require a local module, writable path, or
operator-controlled configuration; they are not claimed as unauthenticated
remote exploits of the package itself.

| ID | Severity | Finding and impact | Resolution / evidence |
| --- | --- | --- | --- |
| SEC-01 | High | Any authenticated user could enable/disable domains and trigger privileged application changes. The admin middleware checked only login. | API guard plus explicit `manage-domains` gate, form-request and listing checks; `access-admin` gate for admin middleware. HTTP denial tests cover guests and ordinary users. |
| SEC-02 | High | Generated CRUD controllers lacked action/record authorization; route-bound IDs could be read, updated, or deleted by another user. | Authorize each action before repository/transaction work. Generate/register deny-by-default policies. Tests verify all five actions deny, another record stays intact, and an allowed owner deletion works. Apps must implement their own tenant scoping for list queries. |
| SEC-03 | High | GraphQL templates exposed user/entity reads and mutations without authorization, with unrestricted query complexity/pagination. | Entire feature removed at the user's request: makers, schemas, resolver/directive/scalar stubs, config, test templates, prompts, CORS path and first-domain generation. No GraphQL runtime/dependency references remain in `src`, `stub`, `config`, or `composer.json`. Fresh scaffold and removed-command tests verify removal. |
| SEC-04 | High | `searchFields=password:like` could add a column not declared in `fieldSearchable`. Projection, sorting and eager-loading accepted identifiers without application allowlists. | Require declared search fields and repository field/sort/include allowlists. Test unknown columns/relations, valid filters, and bound SQL values. This is an authorization/data-disclosure flaw, not a proven raw SQL injection. |
| SEC-05 | High | Datatable values from database records were interpolated into HTML and executable JavaScript. A value containing a closing script tag or quotes could execute in a consuming page. | HTML-escape option text/attributes; JSON-encode JavaScript strings, validate column indices, encode navigation parameters and constrain navigation to the same origin. Test adversarial values round-trip as literal option text. Vue scaffold values use Laravel's JavaScript serializer. |
| SEC-06 | High | Generated User models stored directly assigned plaintext passwords unless each caller remembered to hash them. | Add the `hashed` cast. Database tests verify password hashing, preservation of existing valid hashes, and hidden serialization. Existing plaintext data requires a consumer-specific migration/reset. |
| SEC-07 | Medium | Login accepted arbitrary input shapes, lacked a credential-attempt limit, and used the default/session guard on stateless requests. | Validate credentials, throttle by email/IP, retain route-level throttling, explicitly use the web credential guard, use `once()` without a session, and regenerate sessions on success. Bad-input/rate-limit HTTP tests plus guard/session tests. OAuth signing is mocked in these tests. |
| SEC-08 | High, conditional | `userCan()` cached the first user's permission array in a static variable. Long-lived processes or user switching could reuse another user's permissions. | Resolve the current user's gate every call; guests deny. Same-process admin/member/guest regression. |
| SEC-09 | Medium | Upload checks trusted extensions, allowed oversized content, followed linked paths, and removed an old file before knowing its replacement was accepted. | Detected MIME match, 10 MiB ceiling, physical path confinement, random names, and delete old file only after successful replacement. Real forged-content and filesystem tests. MIME checks are not malware scanning or proof of harmless PDF/image content. |
| SEC-10 | Medium | Lexical path checks permitted symlink escapes; recursive module deletion followed linked directories, and publishing could copy secrets or overwrite linked destinations. | Resolve existing ancestors, reject broken/out-of-root links, unlink nested links during deletion, reject publish links and module-root deletion, and confine scaffold writes. Temporary-directory tests preserve external target files. |
| SEC-11 | Medium | Module aliases and generator paths could escape output roots; duplicate manifest identities could replace a discovered module. | Validate aliases and generator namespace paths; reject duplicate names. Manifest/path regression tests. Manifests remain trusted executable-code configuration. |
| SEC-12 | High, conditional | Invalid/truncated status JSON returned an empty map, causing disabled modules to become enabled. Concurrent writes could lose disabled entries. Legacy providers registered from bootstrap ignored registry status. | Strict boolean/JSON validation, locked atomic replacement, registry-aware legacy provider registration/boot, and unified enable/disable commands. Corrupt-state, concurrent-writer and disabled-provider tests. |
| SEC-13 | High, operator-triggered | An unknown module silently selected all modules for maintenance commands; `migrate-fresh` dropped tables before checking the selection. Database failures could be reported as success. | Reject unknown names before action, validate before fresh, forward `--database`, use real migration paths, and return failed migration exit codes. Command tests assert no destructive call for a missing module. Fresh remains explicitly global to a database. |
| SEC-14 | Medium, local | Relation test generation wrote executable PHP to a predictable shared temporary filename. A precreated symlink could overwrite another file. | Use a unique temporary file with cleanup and checked writes. Module cache writes also use atomic replacement instead of following the destination link. Regressions preserve both linked targets. |
| SEC-15 | Medium, operator-triggered | Scaffold rewrite ignored a failed backup and could proceed to delete source. Root-level symlinks could redirect destructive/write operations. | Fail before rewrite if copying fails; randomize backup directory names, preflight output paths, and restore the force configuration after use. Backup-failure and forced-scaffold tests. Backup still covers only `src/`; this is not a transaction or full application backup. |

Integration fixes required for exercising the security changes: join stub paths
correctly after #4's path-helper change; use a fixed package root; generate valid
User policies without colliding class aliases/parameter names; return non-zero
for unsupported/failed makers; remove a stray character before the CORS PHP tag;
make two nullable probe-test parameters explicit for PHP 8.4+.

## Validation

- Existing PR suite before edits: **15 tests, 59 assertions**, passing.
- Twelve targeted regression tests were run with the original affected
  implementations restored temporarily: **12 failures**. These covered physical
  path confinement, recursive deletion, scaffold output, aliases, module state,
  unknown selection, datatable injection, password hashing, search-field
  expansion, and upload validation. Fixed files were restored immediately.
- Final local suite: **53 tests, 331 assertions** on PHP **8.5.11** and
  **8.4.25**, Laravel **12.69.3**, Passport **13.8.0**, Testbench **10.12.0**.
  Tests use isolated temporary directories and an in-memory SQLite database.
- `composer validate --strict` passes. `composer audit --format=json` reports
  **no advisories or abandoned packages** for the resolved dependency set.
  A library's unconstrained consumer lockfiles and other supported dependency
  combinations are not covered by that result.
- PHP syntax checks pass for all 141 source/config/test PHP files. Generated controller,
  policy, upload, repository and provider code is loaded and exercised in tests;
  a fresh `ddd:directory --force` run is tested in a temporary application.
- High-confidence private-key/GitHub-token/AWS-key/Slack-token pattern searches
  found **no matches** in tracked files or reachable history. This limited
  pattern scan is not a comprehensive secret-scanner attestation. The known
  Laravel factory password is test data; #4 already removed it from the seeder.
- Added a read-only GitHub Actions workflow with pinned actions for PHP 8.2,
  8.4 and 8.5: dependency audit, PHPUnit and PHP syntax checks. CI results are
  reported on the PR; local checks alone do not establish Linux/8.2 coverage.

## Consumer work and remaining limits

### Compatibility follow-up

A release review reproduced a package-startup TypeError with configuration cached
before module support existed: Laravel skipped merging defaults, so the status
activator received a null file path. The service provider now fills missing module
defaults in memory while preserving application overrides. Three regressions cover
an old cache without module settings, unrelated application module settings, and
custom cached paths/statuses with a disabled module and an empty command list.
The expanded local suite passes **56 tests, 346 assertions** on PHP **8.5.11**
and **8.4.25**.

The [proposed 2.0 upgrade guide](UPGRADE_2.0.md) records intentional breaks,
dependency-major requirements and consumer validation steps. The current resolved
dependencies have no pending updates within their constraints. Latest-major
upgrades, consuming application updates, merges and a new release have not been
performed as part of this compatibility review.

### Remaining consumer work

1. Updating this generator does not patch existing applications or remove their
   GraphQL endpoints. Apply the template changes to existing generated code and
   remove old GraphQL routes/imports/dependencies separately. Implement actual
   model policies, management gates and tenant/query scopes before deployment.
2. Module disable is a startup/discovery control. It is not a sandbox for PHP,
   Composer autoload files, already-loaded providers, cached routes, or workers
   that have not restarted. Clear relevant caches and restart those processes.
   Missing status files preserve the prior default that new modules are enabled.
3. Manifests, generator configuration, PHP translation/config/status files and
   Composer operations remain trusted inputs. Filesystem checks reject existing
   escapes; they do not protect against a hostile local process swapping links
   between a check and a write, or against a compromised application account.
4. Public upload storage, per-record deletion authorization, antivirus scanning,
   image re-encoding, response MIME/nosniff/disposition headers, storage quotas
   and reverse-proxy upload limits belong to the host application/deployment.
5. No production database, OAuth keys/provider integrations, browser rendering,
   external module packages, full legacy framework matrix, or live application
   traffic was tested. Tests are focused security evidence, not full branch
   coverage or a production-readiness claim.
6. GitHub reported Dependabot security updates, secret scanning and push
   protection disabled. This PR adds CI but does not change repository/account
   settings. Enable those controls through repository administration if desired.

Implementation references: [Laravel authorization](https://laravel.com/docs/12.x/authorization),
[Laravel authentication/session handling](https://laravel.com/docs/12.x/authentication),
and [OWASP file upload guidance](https://cheatsheetseries.owasp.org/cheatsheets/File_Upload_Cheat_Sheet.html).
