# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

The history up to upstream release 1.6.1 (version 2026100700) is in the upstream repository,
[E-learningTouch/moodle-block_download_certificates](https://github.com/E-learningTouch/moodle-block_download_certificates).

## Unreleased

Fleet adoption, stage 0 (`docs/fleet-audit-2026-10-21.md`).

### Added

- Fleet scaffolding: CI workflow for Moodle 4.5, 5.1, 5.2 and 5.3, `.moodle-plugin-ci.yml`,
  `phpcs.xml`, `.gitattributes`, `.gitignore`, `COPYING.txt`, `CLAUDE.md`, pull request template.
- Brazilian Portuguese language pack.
- Privacy provider for the download task table, with tests.

### Changed

- Versions follow the per-branch ranges (`$plugin->version` 2026100500, `requires` 4.5,
  `supported` [405, 503]); README documents the switch number for sites coming from upstream.
- The upgrade steps are replaced by one schema step that brings any upstream schema (legacy table
  name, missing `batchoffset`) to the current one.
- `block/download_certificates:manage` carries `RISK_PERSONAL`.
- Language packs sorted, duplicate and unused strings removed, missing strings added.
- README and CHANGELOG rewritten in English.

### Fixed

- The certificate table, its counts and the block's teacher count died on PostgreSQL: the
  certificate unions called `$DB->get_dbcollation()`, which only the MySQL driver has, and cast the
  type literal to `CHAR(50)`, which PostgreSQL pads with spaces.
- certificatebeautiful lookup called `file_storage::get_area_files_select()`, which does not exist,
  with the issue code interpolated into SQL.

### Removed

- `db/install.php`: it added a dashboard block under a truncated name (`download_certificat`)
  that core never rendered; the upgrade step deletes those rows.
- The `course_completed` observer, which read a setting nothing could set and did nothing.
- `lib.php` placeholder functions and `ajax.php`, neither called from anywhere.
- The `cli/` developer scripts (test-data generators and a purge of every certificate on the
  site).
- `DOCS.md`, `TECHNICAL.md` (French, describing code that the next stages replace) and
  `composer.json` (upstream package name).
