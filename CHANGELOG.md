# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

The history up to upstream release 1.6.1 (version 2026100700) is in the upstream repository,
[E-learningTouch/moodle-block_download_certificates](https://github.com/E-learningTouch/moodle-block_download_certificates).

## Unreleased (MOODLE_501_STABLE)

Fleet adoption, stages 0, 1a, 1b, 2 and 3 (`docs/fleet-audit-2026-10-21.md`).

### Removed

- Bootstrap 4 support: this branch runs on Moodle 5.x only.

### Added

- Fleet scaffolding: CI workflow (on this branch, Moodle 5.1), `.moodle-plugin-ci.yml`,
  `phpcs.xml`, `.gitattributes`, `.gitignore`, `COPYING.txt`, `CLAUDE.md`, pull request template.
- Brazilian Portuguese language pack.
- Privacy provider for the download task table, with tests.
- Plugins directory guidelines: `LICENSE` (the GPL v3 text, beside `COPYING.txt`) ships in the
  package; `tests/marketplace_readiness_test.php` pins the licence files, the English-only
  package, data-only language files, a README naming every table and capability, plugin-scoped
  CSS, the boilerplate and the privacy cover of core rows stamped with the component.
- `db/uninstall.php` removes every background download directory, which hold copies of other
  users' certificates and which core's uninstall leaves in the temporary directory.
- README sections "Settings", "Scheduled and background tasks", "Data stored" and "Translations".
- Stage 1a: `block/download_certificates:downloadcourse` (course context, editing and non-editing
  teachers), version 2026100501; the namespaced data layer under `classes/local/` (certificate
  sources for tool_certificate and mod_customcert, scope, filter, repository, ZIP builder,
  request downloads, task storage) with its tests. Nothing calls it before stage 1b.
- Stage 1b: the block can stand on user profiles; tests for the request guards, the background
  task, the cleanup and the block's section choice; a data generator for block instances.
- Stage 2: four web services for the pages' JavaScript,
  `block_download_certificates_search_certificates`, `_get_groups`, `_create_task` and
  `_get_task_status` (`db/services.php`, AJAX only, login required), version 2026100502; the
  event `zip_requested`, logged for every queued background download; templates
  `certificate_rows` and `task_progress`; tests for each web service, the event and the download
  scripts' guards.
- Stage 3: the block's templates (`block_content` with the `block_manager`, `block_teacher` and
  `block_personal` sections, and `zip_form`, which the pages' header shares) and the pages' shared
  partials (`page_header`, `filter_bar`, `table_card`, `search_bar`), each with an example context;
  `classes/output/` builds their contexts; `classes/local/bootstrap.php` marks the plugin's pages on
  Moodle 4.5 for a Bootstrap 4 polyfill in `styles.css`; tests for the block's content on every page
  it stands on, for every template rendered with the context the plugin builds for it, and a scan of
  the markup, the modules and the stylesheet (Bootstrap names, data-API attributes, inline styles
  and handlers, selector qualification, colour tokens, reduced motion). New strings for the search
  field, the per-page menu, the block's course and profile sections and the certificate list.
- Stage 4: Behat smoke tests (the manager and teacher pages, the Dashboard block) with a step that
  issues tool_certificate rows without PDFs, and a test that template, course and cohort names with
  `&`, `<` and `"` are escaped exactly once in the block, the menus and the table rows.

### Changed

- One branch per Moodle version: this branch supports Moodle 5.1 only (version range 20251006XX,
  switch number 2025100599, `$plugin->version` 2025100601).
- Versions follow the per-branch ranges (`$plugin->version` 2026100500, `requires` 4.5,
  `supported` [405, 503]); README documents the switch number for sites coming from upstream.
- The upgrade steps are replaced by one schema step that brings any upstream schema (legacy table
  name, missing `batchoffset`) to the current one.
- `block/download_certificates:manage` carries `RISK_PERSONAL`.
- `lang/pt_br` and `lang/fr` leave the release package (`export-ignore`): the plugins directory
  accepts English strings only, and translations reach sites through AMOS. They stay in the
  repository.
- README: the capabilities list names `:downloadcourse` and says that `:view` is not checked;
  the privacy text no longer claims a stored archive path.
- Language packs sorted, duplicate and unused strings removed, missing strings added.
- README and CHANGELOG rewritten in English.
- Stage 1b: every page, download script, AJAX script, both tasks and the block read certificates
  through the `classes/local/` library and one scope. A single download is addressed by source type
  and issue id. The block's numbers follow the page: a course page counts that course, a profile
  that user. Background download errors are stored as string identifiers and shown in the
  reader's language. The table's `cert_type` sort key maps to the library's `type`.
- Stage 2: both JavaScript modules are ES modules on core's AJAX, string, template, dialogue and
  notification modules, without jQuery. The table's headers are buttons that state their sort
  order, the count and the table announce their changes, and a late answer to an earlier search no
  longer replaces a newer one. A background download shows its progress in a core dialogue, polls
  with one request at a time, waits longer after each failure and stops, saying so, after five
  failures in a row or an hour; dismissed banners are remembered per user. The teacher page queues
  large course downloads in the background too (F15), and the block's download buttons do the same
  from the threshold on. ZIP downloads are POST forms.
- Stage 3: the block follows the page it stands on. The dashboard and the front page show the
  site-wide summary; on a course page a manager's numbers count that course and "download all" asks
  for the course's ZIP, and a user's own certificates of that course are listed one by one; a user
  profile lists that user's certificates within the viewer's scope, with a ZIP of exactly those, and
  the block shows nothing when the scope does not reach that user (F9).
- Stage 3: the block is rendered by templates instead of 46 `html_writer` calls (F7), and the two
  pages share their header, download tabs, search bar and table instead of two copies (F30).
- Stage 3: Bootstrap 5 names throughout (`ms-*`/`me-*`, `visually-hidden`, `form-select`, `mb-3`
  rows and flex utilities instead of `form-group`/`form-inline`, badges that state their text
  colour, `data-bs-toggle` alone); on Moodle 4.5 the pages get the names its bridge lacks from a
  polyfill gated on a body class, and the tabs the attribute Bootstrap 4 listens to (F10, F11). The
  block uses no name the polyfill provides, since it cannot mark the pages it stands on.
- Stage 3: `styles.css` rewritten: theme tokens with fallback chains, a text colour with every
  background (dark mode), every selector qualified by a plugin class, the row animation only
  without reduced motion, tabular figures for numbers; the `.pagination` overrides and the rules of
  the removed hand-built dialogue and banner are gone (F16, F17).
- Stage 3: accessibility: every select and input has a label, icon-only buttons a localised name,
  decorative icons are hidden from assistive technology, tabs carry their roles, controls and
  selected state, and the progress dialogue has a plugin class for its tokens (F14).
- Stage 3: course and cohort names in the pages' menus are formatted without escaping, so the
  template escapes them once.

### Fixed

- The certificate table showed, searched and sorted by the owners' e-mail for every manager and
  teacher, whatever core's identity rules said. E-mail now follows
  `core_user\fields::get_identity_fields()`: it must be listed in `showuseridentity` and the
  viewer must hold `moodle/site:viewuseridentity` in the system context (managers) or in every
  course of theirs (teachers); otherwise the column is left out and neither the search nor the
  sort uses it.
- Stage 3: "Download all" asked its confirmation through an inline `onclick` that broke on an
  apostrophe in a translation; the question is a data attribute that the background download module
  asks in a core dialogue (F13). Inline widths and `display: none` became classes and the `hidden`
  attribute.
- Stage 3: `index.php` loaded `styles.css` a second time over the theme's copy, and so did
  `index_teacher.php` (F18).
- Stage 2: the log entry of a queued background download now says what was requested: the owner of
  a user request is its related user, and the course, group, cohort or days of the request are in
  the entry and its description.
- Stage 2: asking for the status or the archive of another user's download task answers "task not
  found", exactly as for a task that does not exist, so task ids cannot be probed.
- Stage 2: a ready archive is claimed (marked expired) before it is sent, so two requests for the
  same task can no longer both receive it; its directory is removed by the hourly cleanup.
- Stage 2: the download button stays until the server reports the archive handed over, and a
  download that did not start shows an error instead of hiding the button.
- The certificate table, its counts and the block's teacher count died on PostgreSQL: the
  certificate unions called `$DB->get_dbcollation()`, which only the MySQL driver has, and cast the
  type literal to `CHAR(50)`, which PostgreSQL pads with spaces.
- certificatebeautiful lookup called `file_storage::get_area_files_select()`, which does not exist,
  with the issue code interpolated into SQL.
- Stage 1b, from the audit:
  - A teacher's background ZIP held the whole course, or a user's certificates from every course,
    whatever the teacher's groups; the task now decides on the requester's scope as it is when it
    runs, and fails with a reason when the requester was suspended, deleted or lost access (S1,
    S4, D3, D4, D20).
  - Background ZIPs shared one directory and names built from course or user names, so one task
    could overwrite or delete another user's archive; each task now has a directory of its own id
    (S2).
  - A single download checked only that the owner was visible somewhere, or redirected to the
    certificate plugin's own page; it now checks the certificate's own course against the scope
    (S3, D15).
  - Teachers were found by role shortname and every group rule was guessed; pages, scripts and the
    block now use the course capability and core's group rules, and the teacher page opens for a
    user with a course of their own (S5, S6, D5).
  - Archives were sent with `readfile()` and raw headers, expired before the bytes went out, and
    request ZIPs stayed in the temp directory for good; downloads now go through
    `send_temp_file()`, which removes the file, and a background archive expires once sent (S8,
    D7).
  - The AJAX scripts answer JSON with its content type, and a failure shows the plugin's own
    localised reason, never another component's exception text (S9, server side).
  - A background ZIP built in several runs kept certificates of users the requester had lost
    sight of between runs (a group membership removed, an enrolment suspended); each certificate is
    now checked against the requester's current scope as it is added, and one that is out of scope
    is left out and named in `errors.txt` by type and number only.
  - The task's status changes were unconditional, so a download the cleanup had failed could be
    overwritten by a later "ready"; each change now applies only if the row still has the status
    the task expects, and a task that lost its row removes what it built.
  - A ready archive was handed over without asking again whether its owner may still download; the
    download script now repeats the access check and refuses with "Access denied".
  - The upgrade removes the ZIP directory upstream left in the temp directory, which held other
    users' certificates and was never cleaned.
  - The guest account is refused everywhere; `download_precise.php` loads `config.php` from its
    own directory and checks the session key with `require_sesskey()` (S10, S11).
  - The background request's JSON was used unchecked and its dates were read in the server's time
    zone; it is cleaned key by key for its type, and a date range covers the user's calendar days
    (S13, D21).
  - About 30 `catch` blocks turned SQL errors into "no certificates"; certificates that cannot be
    added are listed in the archive's `errors.txt`, across every batch (D6, P9).
  - A request-path ZIP held every PDF in memory with no size cap; it now stops at 30 certificates
    and points at the background download (D8).
  - A certificate issued between two background batches shifted or repeated certificates; the
    first run freezes the list (D9).
  - The table sorted without a tie-breaker and searched names the table did not show; the cohort
    menu ran seven queries per cohort without a capability check (D16, D17).
  - PDFs were sent with hand-written headers and ZIPs named in French after a course or user name;
    core's file functions send both, and ZIP names carry ids only (D25, D27).
  - Failed and stuck downloads kept their archives in the temp directory forever, and pending
    downloads were never cleaned; the cleanup fails tasks stuck for two hours, expires archives
    after a day, deletes rows after a week and removes the directory of each, touching nothing
    outside the plugin's task directories (P9).
- Stage 2, from the audit:
  - Session keys travelled in URLs (single downloads, ZIP links, the ready archive); the ZIP pages
    and the archive download now take the key from a POST body only and refuse a GET, and a single
    download, a read like `pluginfile.php`, needs none: the scope decides (S12).
  - Downloading one's own certificates required the block's `:view` capability in the system
    context, which a block capability does not decide there; own certificates need a logged-in,
    non-guest user only (S7).
  - Server text reached the page through `innerHTML`, quoted attributes were escaped without
    quotes, and names were escaped twice; rows and progress are rendered by templates, other text
    is set with `textContent`, and the web services return names formatted but not escaped (F1,
    F2, F26, S9 client side).
  - The progress dialogue was hand-built, without focus handling, Escape or a backdrop (F3); labels
    were hard-coded in English and French and one string id was built at run time (F5, F6).
  - Polling ran on a fixed interval with no guard against overlapping requests or a limit (F20),
    `localStorage` was read and written unprotected (F21), and the ready archive's URL was built in
    JavaScript with the session key (F22).
- Comment audit:
  - Expiring or failing a download row deleted its files even when the row could not be locked
    (ten seconds) and so was not written; the files now go only after the row was written.
  - The background task, on an unexpected error, failed a row after reading its status outside the
    lock; the status is now checked inside it, so a row the cleanup already failed keeps its reason.
  - A background task that could not lock its row dropped the run and deleted its files; it now
    queues the same row again and leaves the files alone.
  - The cleanup counted rows it could not lock as failed or expired; it counts only the rows it
    wrote and leaves the others for the next run.
  - Exactly 30 certificates were queued as a background task although the page builds that many at
    once; only more than 30 are queued.
  - Closing the progress dialogue while a task ran could stop the polling for good; the banner is
    attached before the dialogue is dropped and polling waits for a banner that is still rendering.
  - The task directory removal now refuses a directory that resolves outside the task base, and the
    archive path column that upstream rows hold is cleared by the upgrade, no longer written and no
    longer exported.
  - Removed the unused `downloadall` form action, the `instance_copy()` override, the unused
    `task_storage::has_refs()` and a try/catch that only rethrew the same error.

### Removed

- `db/install.php`: it added a dashboard block under a truncated name (`download_certificat`)
  that core never rendered; the upgrade step deletes those rows.
- The `course_completed` observer, which read a setting nothing could set and did nothing.
- `lib.php` placeholder functions and `ajax.php`, neither called from anywhere.
- The `cli/` developer scripts (test-data generators and a purge of every certificate on the
  site).
- `DOCS.md`, `TECHNICAL.md` (French, describing code that the next stages replace) and
  `composer.json` (upstream package name).
- Stage 1b: the five legacy classes (`controller`, `certificate_access`, `certificate_query`,
  `certificate_retriever`, `certificate_packager`), and with them every path of the three dropped
  certificate sources, the HTTP self-request fallback that sent the session cookie with TLS
  verification off, and the guessed tool_certificate file lookup (D10-D14, D18, D19).
- Language strings used only by the removed classes.
- Stage 2: the four JSON scripts `ajax/create_task.php`, `ajax/task_status.php`,
  `ajax/search_certificates.php` and `ajax/get_groups_for_course.php`, replaced by the web services;
  the string `async_close`.
