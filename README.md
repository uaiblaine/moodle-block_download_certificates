moodle-block_download_certificates
==================================

[![Moodle Plugin CI](https://github.com/uaiblaine/moodle-block_download_certificates/actions/workflows/ci.yml/badge.svg?branch=main)](https://github.com/uaiblaine/moodle-block_download_certificates/actions/workflows/ci.yml?query=branch%3Amain)

Download issued certificates one by one or in bulk, as a ZIP archive.

The block lists the certificates issued by Moodle Workplace's certificate tool
(`tool_certificate`) and by the Custom certificate activity (`mod_customcert`) and lets the
people allowed to see them download them:

- managers download every certificate of the site, or those of one course, one user, one cohort
  or one date range;
- teachers download the certificates of the courses where they hold
  `block/download_certificates:downloadcourse`, limited to the groups they can see;
- every user downloads their own certificates.

Large downloads are built in the background by a task and offered for download when ready.

This fork is being brought to the fleet standard; `docs/fleet-audit-2026-10-21.md` lists what
changes and in which stage.


Requirements
------------

- Moodle 4.5, 5.1, 5.2 or 5.3, each on its own branch (see below).
- At least one of `tool_certificate` and `mod_customcert`. Neither is declared in `version.php`,
  since either one is enough: the block reads the sources that are installed and lists nothing
  without them.
- Nothing else: no Composer packages, no external service, no step after installation.


Branches
--------

| Moodle | Branch | Version range | Switch number |
|---|---|---|---|
| 5.3 | `main` (= `MOODLE_503_STABLE`) | `20261005XX` | `2026100499` |
| 5.2 | `MOODLE_502_STABLE` | `20260420XX` | `2026041999` |
| 5.1 | `MOODLE_501_STABLE` | `20251006XX` | `2025100599` |
| 4.5 | `MOODLE_405_STABLE` | `20241007XX` | `2024100699` |

This is the 5.1 branch (`MOODLE_501_STABLE`); the other versions live on `main` (5.3) and the
other `MOODLE_*_STABLE` branches.


Installation
------------

Install the plugin like any other plugin to folder `/blocks/download_certificates`.

See http://docs.moodle.org/en/Installing_plugins for details on installing Moodle plugins.

### Upgrading from the upstream plugin

Upstream releases (E-learning Touch, up to 1.6.1, version `2026100700`) are numbered above this
fork's ranges, so Moodle would refuse the upgrade as a downgrade. Set the installed version to
the switch number of your branch first, then upgrade; the upgrade step brings any upstream
schema to the current one:

```bash
php admin/cli/cfg.php --component=block_download_certificates --name=version --set=<switch number>
php admin/cli/upgrade.php
```


Settings
--------

The plugin has no settings: the block has no global configuration and no instance configuration.
What a user sees is decided by the capabilities below and by core's group and identity rules.


Capabilities
------------

- `block/download_certificates:manage` — download every user's certificates from the site-wide
  page (`index.php`), by course, user, cohort or date range (manager). Carries `RISK_PERSONAL`: it
  exposes names, certificates and, where core's identity rules allow, e-mail addresses.
- `block/download_certificates:downloadcourse` — download the certificates of a course's actively
  enrolled participants from the teacher page (`index_teacher.php`), within the groups the holder
  can see under the course's group mode (editing and non-editing teacher, course context). Carries
  `RISK_PERSONAL`.
- `block/download_certificates:addinstance` — add the block to a page.
- `block/download_certificates:myaddinstance` — add the block to the dashboard.

The certificate table shows the owners' e-mail only where core would show it: e-mail must be one
of the identity fields (`showuseridentity`) and the viewer must hold `moodle/site:viewuseridentity`
in the system context (managers) or in every course of theirs (teachers). Otherwise the column is
left out, and the search and the sort do not use it.


Scheduled and background tasks
------------------------------

- `\block_download_certificates\task\generate_zip` (adhoc) builds a background ZIP, in batches,
  re-checking the requester's access to every certificate as it adds it.
- `\block_download_certificates\task\cleanup_expired` (scheduled, hourly) fails downloads stuck
  for two hours, expires ready archives after a day and deletes rows after a week, removing each
  row's directory.


Data stored
-----------

The plugin owns one table, `block_download_certificates_tasks`: one row per background ZIP
download, with the requesting user, the request type and its filter (course, group, user, cohort
or dates, as JSON), the status, the progress counters, the times and the reason of a failure. The
`filepath` column is an upstream column the plugin no longer writes; the upgrade clears it.

- The archive itself lives in a temporary directory of its own task id (under
  `$CFG->tempdir/block_download_certificates/tasks`). It holds copies of other users'
  certificates and is deleted once it is downloaded, when its task fails, or by the hourly
  clean-up a day after it was built.
- The row is deleted by the hourly clean-up a week after it was created.
- Uninstalling the plugin drops the table and removes every task directory.
- Each queued download logs the event `\block_download_certificates\event\zip_requested`, kept
  by the site's log stores under their own retention.

The privacy provider places these rows in the requesting user's context. A data request exports
each row (type, filter, status, times and error), but not the archive, a temporary copy of
certificates that the certificate plugins export to their own owners. A deletion request removes
the user's rows and the task directory of each, archive included. The plugin writes no core row
under its own component (no group memberships, favourites or stored files) and stores no other
personal data; the certificates themselves belong to `tool_certificate` and `mod_customcert`.


Translations
------------

The English strings ship in the package. The Brazilian Portuguese (`lang/pt_br`) and French
(`lang/fr`) translations are maintained in this repository and reach sites through
[AMOS](https://lang.moodle.org/), as the plugins directory requires; they are left out of the
release package.


Credits
-------

This plugin is a fork of
[E-learningTouch/moodle-block_download_certificates](https://github.com/E-learningTouch/moodle-block_download_certificates),
maintained by E-learning Touch and written by Thomas Clément, itself based on the original
block by Manieer Chhettri (2015, Moodle 2.x and 3.x).


License
-------

This plugin is licensed under the [GNU GPL v3 or later](http://www.gnu.org/copyleft/gpl.html).

Copyright: 2015 Manieer Chhettri; 2025 E-learning Touch; 2026 Anderson Blaine
