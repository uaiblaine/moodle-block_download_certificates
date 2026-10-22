# Fleet adoption audit — block_download_certificates (2026-10-21)

Audit of the fork as cloned from `E-learningTouch/moodle-block_download_certificates` at
`aecaf11` (release 1.6.1, version 2026100700), against the fleet standard in `~/dev/CLAUDE.md`,
for Moodle 4.5, 5.1, 5.2 and 5.3. Four read-only partitions (security and entry points; SQL,
data and packaging; platform; frontend) plus a baseline run of the static gates. Findings marked
*verified* were re-read in the source by the consolidating session; the rest are reader findings
with file:line evidence, to be confirmed by the test that fixes each one.

Owner decisions taken before the audit (2026-10-21): one branch per Moodle version (`main` =
`MOODLE_503_STABLE`, plus 502, 501, 405); the fork diverges freely from upstream (later upstream
changes are ported by hand); `lang/en` + `lang/pt_br` in lockstep, `lang/fr` kept and updated;
all documentation in English with credits to the upstream authors.

## Baseline gates (4.5 leg, PHP 8.3, pgsql)

| Gate | Result |
|---|---|
| codebase | FAILED: `COPYING.txt` missing |
| phplint | OK |
| phpcs | FAILED: 28 files, roughly 600 errors and 270 warnings |
| phpdoc | FAILED: incomplete `@param` lists in `certificate_query.php` (3) |
| validate, savepoints, leftover | OK |
| mustache | FAILED: no `Example context (json)` in either template |
| grunt | FAILED: eslint 58 problems (5 errors, 53 warnings); stylelint `data:` URL, `!important`, hex length |
| phpunit, behat | nothing to run: the plugin has no `tests/` |

The 5.3 leg did not get past `npm` (proxy); rerun with `NPM_CONFIG_PREFER_OFFLINE=true
NPM_CONFIG_IGNORE_SCRIPTS=true`.

## Blockers

| ID | Where | Defect | Fix |
|---|---|---|---|
| S1 *verified* | `ajax/create_task.php:72-88`, `classes/task/generate_zip.php`, `certificate_packager.php:909,1019` | A teacher's async ZIP ignores the group scope: the check only asks whether the course or user is in scope, then the task runs the manager queries. A teacher in one group gets the whole course; `type=user` returns the user's certificates from every course. | Record the scope the request was authorised for with the task and run the scoped query in the task; re-check on run. |
| S2 *verified* | `certificate_packager.php:590-595,693-700,875-898` | Async ZIPs share one temp directory and a name built from the course/user/cohort name; a new task unlinks and rewrites the file another user's ready task points at, and two tasks for the same course write one file. A teacher can end up downloading a manager's full-course ZIP. | One directory (or file name) per task id; never touch a path another row references. |
| S3 | `download_precise.php:97-127` | simplecertificate and certificatebeautiful branches check only "the owner is visible to this teacher in some course", not the certificate's course. | Resolve the issue's course and check the per-course scope, as the other three branches do. |
| S4 | `download_user.php:61-66`, `certificate_packager.php:1688-1830` | Per-user ZIP for a teacher contains that user's certificates from every course. | Restrict to the teacher's courses and, per course, to the group scope. |
| P1 | `db/install.php:38-56` | Inserts a `block_instances` row with blockname `download_certificat` (truncated) and no block context: an orphan row on every fresh install, and an unrequested side effect. | Delete `db/install.php`; clean the orphan rows once on upgrade. |
| P2 | `version.php`, `db/upgrade.php` | Upstream 2026100700 is above every fleet branch number except 5.3's would-be counter; the three date-numbered steps are above the 4.5 and 5.1 branch versions. | local_awareness recipe: drop the date steps, a schema guard in `db/upgrade.php`, documented switch numbers (503 `2026100499`, 502 `2026041999`, 501 `2025100599`, 405 `2024100699`). |
| P8 | `classes/privacy/provider.php` | `null_provider` although `block_download_certificates_tasks` holds `userid`, filter JSON, errors and the path of a ZIP of other people's certificates. | Metadata + plugin + userlist provider with every delete method (deleting the ZIP too); `component_is_compliant()` test. |
| P11 | `cli/*.php` | Shipped developer scripts: one deletes every certificate of five plugins behind `-y`; others create and delete users and courses and hardcode course 54 and users 1019-1022. | Remove all five; anything useful becomes a test generator. |
| D1 *verified* | `certificate_query.php:723,970` | `$DB->get_dbcollation()` exists only in the mysqli driver: on PostgreSQL the search table, its counts and the block's teacher count die with "Call to undefined method" (an `\Error`, which the surrounding `catch (Exception)` does not catch). `CAST(... AS CHAR(50))` would also pad the type column to 50 characters on PostgreSQL. | Plain literals, no `COLLATE`, no `CAST`; a MariaDB-only branch behind `get_dbfamily()` only if a mixed-collation site proves it is needed; tests on both families. |
| D2 *verified* | `certificate_retriever.php:409-411` | `file_storage::get_area_files_select()` does not exist (fatal), and the same call interpolates `$code` raw into a `LIKE`. | Delete that fallback. |
| D3, D4 | `certificate_packager.php:825-829,1688-1699` | Same leaks as S1 and S4 seen from the query side: course and user fetches carry no scope. | One scope object (course id → user ids) applied identically to search, counts, single downloads and ZIPs. |
| D5 | `certificate_query.php:1092-1108,1257-1418` | Teacher counts cover three of the five sources; the simplecertificate teacher union filters by user only, so it returns rows from courses outside the teacher's scope. | Rebuild the teacher counters on the scoped union; join simplecertificate to its course. |
| D6 | about 30 `try { } catch (Exception) { debugging(); return []; }` blocks in query and packager | A SQL error (a column renamed by a newer certificate plugin, PostgreSQL's 65535-placeholder limit) silently becomes "no certificates" or a ZIP missing a whole type. | Let `dml_exception` surface, or collect per-source errors and report them in the task result. |
| F1 | `amd/src/async_download.js:195,221,245,451,606`, `certificate_table.js:243` | Server error text (exception messages, stored `task->error`) goes into `innerHTML`. | Localised error strings from the server; `textContent` or templates on the client. |
| F2 | `amd/src/certificate_table.js:326,441-447` | `escapeHtml` does not escape quotes yet is used inside attributes. | Render rows with a Mustache template (or DOM APIs). |
| F3 | `amd/src/async_download.js:484-588` | Hand-built modal: no backdrop, focus trap, Escape, `role="dialog"`; manual `modal-open` clean-up. | `core/modal`. |

## Major

Security and access
- S5 `certificate_access.php:84-90`: "teacher" is a role shortname (`editingteacher`, `teacher`) at
  course level. Ignores suspended or ended enrolments, category/system assignments and custom
  roles. → a course-level capability checked per course context (decision D1).
- S6 `certificate_access.php:135-169`: the group rule applies whenever the teacher is in any group,
  regardless of group mode and `moodle/site:accessallgroups`; without groups every active
  enrolment is in scope (other teachers included, disabled instances and dates ignored). → core's
  rules: `groups_get_course_groupmode`, `accessallgroups`, `get_enrolled_sql(..., onlyactive)`.
- S7 `db/access.php`: `:view` is a block capability checked in the system context; `:manage` has no
  `RISK_PERSONAL` and is checked in the system context only.
- S8 `ajax/download_zip.php:60-86`, `certificate_packager.php:204-226`: `readfile()` with raw
  headers, task marked expired before the bytes go out. → `send_temp_file()` / `send_file()`.
- S9 `ajax/*.php`: four hand-rolled JSON endpoints, no content type, HTTP 200 on failure,
  `$e->getMessage()` sent to the client. → external functions under `classes/external/`
  (`create_task`, `get_task_status`, `search_certificates`, `get_groups`), `db/services.php`.
- S10 every entry point: bare `require_login()`, no guest rejection.
- S11 `download_precise.php:27,46-49`: cwd-relative `config.php`, sesskey via `PARAM_RAW` +
  `confirm_sesskey()`.
- S12 sesskey in GET URLs built by `search_certificates.php:101` and `controller.php:86-89`.
- S13 `create_task.php:50-60`: `params` JSON used unvalidated; PARAM_TEXT dates fed to
  `strtotime()`.

Platform
- P3 `version.php`: `requires` 4.3, `supported = [403, 503]`. → per branch `[x, x]`.
- P4 every file header: upstream `@copyright` pair and `@author` with an e-mail. → fleet header;
  upstream credit moves to README.
- P5 `install.xml` VERSION must be one value on every branch; redundant `status_idx`.
- P6 `:manage` lacks `RISK_PERSONAL`.
- P7 `classes/observer.php`, `db/events.php`, `lib.php`: dead code — the observer reads a setting
  nothing can set and does nothing; the `lib.php` `v2_*` functions are never called.
- P9 `classes/task/cleanup_expired.php`, `generate_zip.php`: failed and stuck tasks leave their
  ZIPs in temp forever; pending tasks are never cleaned; `catch (\Exception)` not `\Throwable`;
  English literals stored as errors; only the last batch's errors reach `download_errors.txt`.
- P10 lang: unsorted, duplicate keys (`privacy:metadata` twice with different text), 35 unused
  keys, `invalidaction` / `invalidparameters` used but missing, dynamic id
  `async_zip_ready_label_` + type, no `pt_br`.
- P12 README, CHANGELOG, DOCS.md, TECHNICAL.md in French; CHANGELOG has three sections under
  one version number.
- P13 no `tests/`, no CI, none of the fleet scaffolding (`ci.yml`, `.moodle-plugin-ci.yml`,
  `phpcs.xml`, `.phpcsignore`, `.gitattributes`, `.gitignore`, `COPYING.txt`, `CLAUDE.md`).

SQL, data and packaging
- D7 `certificate_packager.php:223-225,532-559`: the request-path ZIP sends and `exit`s before its
  `unlink()`, so every course, cohort and teacher ZIP stays in `temp/` for good; one fixed name per
  course means two concurrent requests overwrite each other. → `make_request_directory()` and
  `send_temp_file()`.
- D8 every PDF is held in memory (`addFromString`) and the request path has no size cap. → stream
  stored files to temp and `addFile()`; above a threshold the request path refuses and points at the
  async task.
- D9 `generate_zip_batch()` refetches every row on every batch and slices by offset over queries
  with no `ORDER BY`: a certificate issued between two cron runs duplicates or skips rows. → freeze
  the id list in the task record, or keyset on a stable order.
- D10 two copies of every fetch (async `fetch_*` and request `download_*`, five sources each) that
  already disagree (deleted users, ordering, columns, empty-content checks). → one source class per
  certificate type, one method per filter, used by search, request and task.
- D11 `certificate_query.php:985-1032`: unused parameter arrays, and placeholder names built as
  `bc_u{$pc}` + an index collide (`1`+`11` and `11`+`1` both give `bc_u111`), so a large scope can
  bind the wrong user list.
- D12 `certificate_retriever.php:538-609`: an HTTP fallback with TLS verification off that sends the
  current session cookie to any URL starting with `wwwroot` and self-requests while holding the
  session lock. → delete; read from file storage or the plugin's API.
- D13 `certificate_retriever.php:148-206`: tool_certificate PDFs found by guessing item ids and
  matching `strpos($filename, $code)`; an empty code matches the first file of the area (another
  person's certificate).
- D14 customcert PDFs are regenerated from the current template with no `class_exists()` guard.
- D15 single downloads of three sources `redirect()` to the other plugin's page, which applies that
  plugin's permissions; certificatebeautiful is manager-only while the others allow teachers.
- D16 pagination sorts with no tie-breaker; search ignores the displayed full name.
- D17 `get_cohorts_with_certificates()` has no capability check, runs 1 + 6 queries per cohort and,
  like most counts, includes deleted users.
- D18 simplecertificate joined to its course by the stored shortname snapshot (stale after a
  rename, case rules differ between the two databases).
- D19 certificatebeautiful rows carry `'' AS code`, so their download link from the table always
  fails.

Frontend
- F4 both AMD modules are jQuery `define()` code calling the raw endpoints. → ES modules,
  `core/ajax`, `core/str`, `core/templates`, `core/modal`.
- F5, F6 hard-coded English and French strings in JS and templates; `[[loading,core]]` can show.
- F7 46 `html_writer` calls in the block class. → block templates.
- F8 `get_content()` runs 20+ queries and ~15 `table_exists()` per dashboard view. → one cached
  source list, aggregated counts, MUC with invalidation or a short TTL.
- F9 the block allows `course-view` but decides everything in the system context, so a course
  page shows a site-wide summary (decision D2).
- F10, F11 BS4-only names (`mr-*`, `form-group`, `form-inline`, `sr-only`, `badge-*`) and BS4/BS5
  names side by side; badges without a text colour.
- F12 templates without `Example context (json)`.
- F13 inline `onclick="return confirm(...)"` (breaks on an apostrophe in a translation) and inline
  styles.
- F14 accessibility: unlabelled selects, icon buttons named only by English `title`, mouse-only
  sortable headers, no live regions, decorative icons not hidden.
- F15 the teacher course form lacks `data-cert-count`, so the async path never triggers there, and
  `index_teacher.php` does not load the module.
- F16, F17, F18 stylelint failures; literal palette with no theme fallbacks and backgrounds without
  paired text colour (dark mode); `styles.css` loaded twice on `index.php`.
- F19 `amd/build` cannot be compared with `src` (no `sourcesContent`); rebuilt anyway by F4.

## Minor

S14 `ajax.php` dead; S15 `lib.php` stubs; S16 missing lang strings; S17 raw exception text on
pages; S18 a scope guard that disappears when a table is absent; S19
`certificate_retriever.php:549-559` disables TLS verification and sends the session cookie to any
URL that merely starts with `$CFG->wwwroot`; S20 legacy non-namespaced classes loaded with
`require_once`; P14, P15 task and upgrade leftovers; F20-F30 polling without an in-flight guard,
unprotected `localStorage`, sesskey in a JS-built URL, double escaping of `format_string()` output,
duplicated template markup, no `tabular-nums`; D20-D28 the task trusts the scope checked at
request time, date ranges in the server time zone, an O(n²) duplicate-name check, dead branches and
methods (`generate_zip_to_file()` has no caller), string/int id comparisons, hand-written PDF
headers, legacy class names that the autoloader cannot map, French ZIP names
(`Certificats_<course>.zip`).

## Comments

- Every PHP, JS, Mustache and CSS header carries the upstream copyright pair and `@author` with a
  personal e-mail address.
- French comments and docblocks in at least 20 files (`certificate_access.php`,
  `index_teacher.php`, `teacher_page.mustache` and `ajax/*.php` almost entirely).
- Stale or false claims: the privacy provider header describes another plugin ("Multiple
  Enrollments"); `version.php` "Moodle 4.3 minimum"; `certificate_access.php` "roleId 3 and 4"
  over a shortname query and "all active students" over a query that returns every enrolment;
  `install.xml` lists an `expired` status that nothing writes; `controller.php:70-76` says the
  table is filled by AJAX while the method still loads it; `index_teacher.php:76-94` describes
  URLs and a banner that do not exist; `instance_copy()` narrates intent.
- Narration that belongs nowhere ("parameterised query, no injection possible").
- Missing `@return` / `@throws` across `controller.php`, `certificate_packager.php`, `lib.php`,
  task `execute()` methods; camelCase `$useTempDir`; JSDoc without descriptions or `@returns`.

## Decisions for the owner

- D1 Teacher model: a course-level capability (`block/download_certificates:downloadcourse`,
  archetypes editingteacher and teacher) checked in each course context, with core's group rules,
  instead of role shortnames.
- D2 Block placement: keep `course-view` with a course-scoped summary, or limit the block to the
  dashboard and front page.
- D3 Sites upgraded from upstream 1.6.1: refuse with a message and the switch number (awareness
  recipe), or accept transparently.
- D4 Certificate sources: which of the five stay. tool_certificate and mod_customcert are
  maintained for 4.5-5.3; mod_certificate, mod_simplecertificate and mod_certificatebeautiful are
  not on any stack and could not be verified. Every kept source needs its plugin in CI
  (`extra-plugins`) and on a stack, or it ships untested.

## Stage 0 result (2026-10-21, uncommitted on `fleet-adoption`)

`mdl ci` 4.5 leg (PHP 8.3, pgsql) and 5.3 leg (PHP 8.4, pgsql): codebase, phplint, phpcs,
leftover, phpdoc, validate, savepoints and phpunit pass (9 tests, 28 assertions; on 5.3 one
PHPUnit deprecation, the docblock `@covers` that 4.5 still needs and the 5.3 branch will turn
into attributes after the split). mustache (no example contexts) and grunt (eslint 58 problems,
stylelint 3) stay red until stages 2 and 3 rewrite the templates, JavaScript and CSS, so the
branch merges as a whole once every gate is green, not stage by stage.

## Status (2026-10-22, end of the adoption; nothing pushed)

Every stage is done and committed locally. Nothing is pushed, merged into `main`, tagged or
released: those steps are the owner's.

Branches (local only):

| Branch | Worktree | Head | Moodle | version |
|---|---|---|---|---|
| `fleet-adoption` | `~/dev/moodle-block_download_certificates` | stage 0-4 + fork ci.yml | 4.5-5.3 (pre-split state) | 2026100504 |
| `MOODLE_503_STABLE` (to become `main`) | `~/dev/moodle-block_download_certificates-503` | `b7f2cbd` | 5.3 | 2026100505 |
| `MOODLE_502_STABLE` | `~/dev/moodle-block_download_certificates-502` | `aec3811` | 5.2 | 2026042000 |
| `MOODLE_501_STABLE` | `~/dev/moodle-block_download_certificates-501` | `48a51fe` | 5.1 | 2025100600 |
| `MOODLE_405_STABLE` | `~/dev/moodle-block_download_certificates-405` | `19e1e37` | 4.5 | 2024100700 |

Stage commits on `fleet-adoption`: `7c2edc9` stage 0, `ad12b22`/`23ac04f`/`9cda6d6` stage 1,
`675d82c` stage 2, `3d2645f` stage 3, `7087fe9`/`e354085`/`fd1e011` stage 4, `5ebe2fe` fork
workflow. Each MOODLE_*_STABLE branch starts at `fd1e011`; the 5.x branches add one commit dropping
the Bootstrap 4 support (`df94118` on 503, cherry-picked), one with the branch values, one with the
coverage marker and the fork workflow; the 4.5 branch adds the branch values and the fork workflow.

Verification (`mdl ci --matrix --behat`, through a scratch moodle-dev at
`~/dev/moodle-dev-dlcombo` carrying the two pending moodle-dev changes below):

- 4.5: 6 legs (PHP 8.1/8.2/8.3 x pgsql/mariadb), PHPUnit 154 tests, 934 assertions and Behat 5
  scenarios, 66 steps on every leg; every static gate green (grunt re-run through the fork path).
- 5.3, 5.2: 4 legs each (8.3/8.4 x pgsql/mariadb); 5.1: 6 legs (8.2/8.3/8.4 x pgsql/mariadb):
  PHPUnit 151 tests, 915 assertions and Behat 5 scenarios on every leg; every static gate green
  after the coverage marker and the fork workflow.
- `mdl mutate --fast` (5.3/8.4) over 87 guards on `fd1e011`: every gate reddened every test it
  names, on pgsql and on mariadb.

Open for the owner:

1. moodle-dev: two uncommitted changes the local verification depends on —
   `~/dev/moodle-dev-dlcerts` (branch `ci-deps-download-certificates`: `mdl ci` installs
   mod_customcert and tool_certificate for this plugin) and `~/dev/moodle-dev-gherkin` (branch
   `ci-gherkinlint-own-features`) with its twin in `~/dev/moodle-workflows-gherkin` (fork branch
   `gherkinlint-skip-dependencies`: gherkinlint skips the dependencies' features). Until the fork's
   change reaches its `main`, GitHub's grunt gate fails on the two plugins' shared feature names.
   `~/dev/moodle-dev-dlcombo` is a disposable worktree (detached, one scratch commit) combining both.
2. Push the four branches, make `main` = `MOODLE_503_STABLE`, tag releases (`v5.3-r1` ...).
3. Mount the plugin on stacks (`plugins.conf`, one checkout per branch) if wanted.
4. Design questions left open: the group rule reads the course's group mode, not the certificate
   activity's; the archive hand-over re-checks the request, not each certificate (accepted in
   `docs/stage1-design.md`); the fleet CI template's `cancel-in-progress` cancels push runs on
   `MOODLE_*_STABLE` (fixed here, template unchanged).
5. On 5.3, PHPUnit reports one deprecation per test class (docblock metadata); moving to
   attributes on the 5.x branches is a later modernisation.

## Stage 1 prerequisite: certificate plugins in CI

Stage 1's tests need both sources installed on every leg: `plugin-dependencies` in `ci.yml`, and
for `mdl ci` clones under `moodle-dev/deps/` with an `extra_deps_for()` entry (a moodle-dev pull
request). Upstream branches as of 2026-10-21:

| Moodle | mod_customcert (mdjnelson) | tool_certificate (moodleworkplace) |
|---|---|---|
| 4.5 | `MOODLE_404_STABLE` (requires 4.4) | tag `v4.5.7` (supported [400, 405]) |
| 5.1 | `MOODLE_500_STABLE` (requires 5.0) | `main` (supported [500, 502]) |
| 5.2 | `MOODLE_502_STABLE` | `main` |
| 5.3 | `MOODLE_503_STABLE` | `main` (5.3 not yet declared supported) |

## Stages

Each stage is one pull request against `main` (5.3) before the branch split; the tests of what a
stage changes ship in that stage.

0. Scaffolding and clean-up: fleet files, `CLAUDE.md`, README/CHANGELOG/docs in English, headers,
   dead code out (`cli/`, `ajax.php`, `lib.php` stubs, observer, `db/install.php`), version and
   upgrade guard, privacy provider (P8), `lang/pt_br`, certificate plugins in CI.
1. Data layer and access model: one source class per certificate type, one scope object built from
   a course capability and core's group rules, used by search, counts, single downloads, request
   ZIPs and the task (D1-D19, S1-S7, S13); files through `send_temp_file()` / `send_file()` and
   per-task directories (S2, S8, D7, P9).
2. External functions and the JavaScript rewrite: S9-S12, F1-F6, F15, F20-F22, F26.
3. Rendering: block templates, Bootstrap 5 names, CSS tokens, accessibility: F7-F14, F16-F18.
4. Comment pass over every file, Behat smoke, `mdl mutate` over the access guards.
5. Branch split (503/502/501/405; the Bootstrap 4 polyfill on 405 only), matrix on every branch,
   releases.
