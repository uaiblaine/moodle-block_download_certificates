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
- At least one of `tool_certificate` and `mod_customcert`.


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


Capabilities
------------

- `block/download_certificates:view` — see the block and download one's own certificates
  (authenticated user, student, teacher, editing teacher, manager).
- `block/download_certificates:manage` — download every user's certificates from the site-wide
  page (manager). Carries `RISK_PERSONAL`: it exposes names, e-mail addresses and certificates.
- `block/download_certificates:addinstance` — add the block to a page.
- `block/download_certificates:myaddinstance` — add the block to the dashboard.


Privacy
-------

The plugin stores one row per background download in `block_download_certificates_tasks`: the
requesting user, the filter, the status, the path of the generated ZIP and any error. The ZIP
holds copies of other users' certificates; it is deleted once downloaded, when its task fails, or
by the hourly clean-up a day after it was built. The privacy provider exports these rows and
deletes them, ZIP included.


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
