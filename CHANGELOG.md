# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

The history up to upstream release 1.6.1 (version 2026100700) is in the upstream repository,
[E-learningTouch/moodle-block_download_certificates](https://github.com/E-learningTouch/moodle-block_download_certificates).

## Unreleased

Fleet adoption, stages 0, 1a and 1b (`docs/fleet-audit-2026-10-21.md`).

### Added

- Fleet scaffolding: CI workflow for Moodle 4.5, 5.1, 5.2 and 5.3, `.moodle-plugin-ci.yml`,
  `phpcs.xml`, `.gitattributes`, `.gitignore`, `COPYING.txt`, `CLAUDE.md`, pull request template.
- Brazilian Portuguese language pack.
- Privacy provider for the download task table, with tests.
- Stage 1a: `block/download_certificates:downloadcourse` (course context, editing and non-editing
  teachers), version 2026100501; the namespaced data layer under `classes/local/` (certificate
  sources for tool_certificate and mod_customcert, scope, filter, repository, ZIP builder,
  request downloads, task storage) with its tests. Nothing calls it before stage 1b.
- Stage 1b: the block can stand on user profiles; tests for the request guards, the background
  task, the cleanup and the block's section choice; a data generator for block instances.

### Changed

- Versions follow the per-branch ranges (`$plugin->version` 2026100500, `requires` 4.5,
  `supported` [405, 503]); README documents the switch number for sites coming from upstream.
- The upgrade steps are replaced by one schema step that brings any upstream schema (legacy table
  name, missing `batchoffset`) to the current one.
- `block/download_certificates:manage` carries `RISK_PERSONAL`.
- Language packs sorted, duplicate and unused strings removed, missing strings added.
- README and CHANGELOG rewritten in English.
- Stage 1b: every page, download script, AJAX script, both tasks and the block read certificates
  through the `classes/local/` library and one scope. A single download is addressed by source type
  and issue id. The block's numbers follow the page: a course page counts that course, a profile
  that user. Background download errors are stored as string identifiers and shown in the
  reader's language. The table's `cert_type` sort key maps to the library's `type`.

### Fixed

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
