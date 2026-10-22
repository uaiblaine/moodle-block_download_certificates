# Claude instructions for `block_download_certificates`

This file is auto-loaded as context whenever Claude works in this plugin's directory tree.
**Fleet-wide standards live in `~/dev/CLAUDE.md`** — do not repeat them here. This file keeps
only what is true for this plugin, as rules with a one-line reason each.

Plugin context: a Moodle **block** ("Download certificates") that lists certificates issued by
`tool_certificate` and `mod_customcert` and downloads them one by one or as a ZIP, for managers
(site-wide), teachers (their courses, within their groups) and users (their own). It owns one
table, `block_download_certificates_tasks` (background ZIP downloads, built by the adhoc task
`generate_zip`, cleaned by the scheduled task `cleanup_expired`). Fork of
E-learningTouch/moodle-block_download_certificates (upstream 1.6.1), diverging freely: upstream
changes are ported by hand. This checkout is the **4.5 branch** (`MOODLE_405_STABLE`, `$plugin->requires = 2024100700`,
`$plugin->supported = [405, 405]`); the other Moodle versions live on `main` (5.3) and their own
`MOODLE_*_STABLE` branches. The Bootstrap 4 polyfill stays here.
The plugin is mounted on no stack yet: tests run through `mdl ci` runners.

The adoption is staged; the audit and the stage plan are in `docs/fleet-audit-2026-10-21.md`.
Read the stage you are working on there before editing.

## Agent orchestration budget (fleet rule, repeated here on purpose)

Section 6 of `~/dev/CLAUDE.md` (`moodle-dev/CLAUDE.fleet.md`) is the authority and says why.
This short copy reaches sessions that do not load that file: cloud sessions and checkouts
outside `~/dev`. Every subagent gets the model and effort of its role from its agent
definition, and none runs on the session model.

| Role | model | effort | agent |
|---|---|---|---|
| Mechanical sweeps, greps, renames, counts, log reading | `haiku` | `medium` | `fleet-sweeper` |
| Checklists against evidence (handoff counts, spec lines against a sweep log, lang lockstep) | `haiku` | `high` | `fleet-checker` |
| Readers, measurers, graders | `sonnet` | `medium` | `fleet-reader` |
| Refuters and verifiers of a blocking finding | `sonnet` | `high` | `fleet-verifier` |
| Well-scoped implementation (established cause, settled design, written recipe) | `sonnet` | `medium` | `fleet-fixer` |
| Non-trivial implementation (open design, several files, long tasks) | `opus` | `high` | `fleet-implementer` |
| Consolidators, critics, estimators, ADR and documentation drafters | `opus` | `high` | `fleet-synthesist` |

- Launch the `Agent` tool with `subagent_type: "fleet-*"`; it has no `effort` parameter, so
  the role's effort comes from that definition (`mdl claude-setup` installs them). Where they
  are not installed, pass `model`. Aliases only; never `fable`; `xhigh` only for a long-horizon
  implementer whose prompt says why; never `xhigh`/`max` on Sonnet or Haiku.
- No long command inside a subagent: `mdl ci --matrix`, `mdl mutate` and Behat run from the
  main session in a background Bash command; a subagent runs the fast gate its prompt names and
  reports the command with its counts.
- Workflows only on the user's opt-in, every `agent()` with `agentType: 'fleet-*'`, under 10
  agents. Advisor off by default.

## Commands

```sh
mdl ci moodle-block_download_certificates --branch MOODLE_405_STABLE   # the 4.5 leg (moodle-cs)
NPM_CONFIG_PREFER_OFFLINE=true NPM_CONFIG_IGNORE_SCRIPTS=true \
  mdl ci moodle-block_download_certificates --branch MOODLE_503_STABLE # 5.x legs behind the proxy
mdl ci moodle-block_download_certificates --matrix --behat             # before any push
```

In a Claude Code cloud session none of `mdl`, the stacks or `~/dev/CLAUDE.md` exist. The cloud
environment's setup script (`moodle-dev/cloud/setup.sh`) installs `moodle-plugin-ci` and the `mpci`
runner instead: `mpci` runs every gate on the highest branch in `ci.yml`, `mpci --branch <branch>`
another one, `mpci --reuse --only phpunit --filter <test>` iterates. It cannot run Behat, PHP 8.4 or
MariaDB, so the owner runs `mdl ci moodle-block_download_certificates --matrix --behat` before merging: push a branch, open
the pull request, leave the merge to them. If `which mpci` prints nothing, the session was resumed on
a container that skipped the setup script: attach `moodle-dev` and run `bash cloud/setup.sh` from it.
See `moodle-dev/cloud/README.md`.

## Owner decisions (2026-10-21)

- One branch per Moodle version (`main` = 5.3, then 502/501/405); the Bootstrap 4 polyfill
  lives on the 405 branch only.
- Teachers are decided by a course capability checked in each course context plus core's group
  rules (group mode, `moodle/site:accessallgroups`, active enrolments), never by role shortnames.
- The block lives on the dashboard, the site front page, course pages and user profiles, each
  scoped to its context: a course page shows that course, a profile that user.
- Certificate sources: `tool_certificate` and `mod_customcert` only.
- `lang/en`, `lang/pt_br` and `lang/fr` share one key set.

## Rules

- File headers keep the upstream `@copyright` lines (GPL notices) above
  `@copyright 2026 Anderson Blaine`; no `@author`. New files carry only the 2026 line.
- `$DB->get_dbcollation()` exists only in the mysqli driver: calling it fatals on PostgreSQL.
  No `COLLATE` or `CAST(... AS CHAR(n))` in the certificate unions.
- One scope (`classes/local/scope.php`: course id => null or the viewer's group ids, evaluated in
  SQL) decides search, counts, single downloads, request ZIPs, the background task and the ZIP
  hand-over; the task re-derives it on every run and re-checks each issue, because the request that
  queued it was authorised by a scope that may have changed.
- A background ZIP lives in the directory of its own task id (`task_storage`); names built from
  course or user names collided between users upstream.
- Every status write of a task row runs under the core lock named after the row
  (`zip_tasks::with_row_lock()`): a conditional UPDATE cannot tell its caller whether it or a
  concurrent writer moved the row, because the DML layer reports no affected rows.
- Blocks render through `$this->page->get_renderer('core')`, never `$OUTPUT`: in a test `$OUTPUT`
  is the bootstrap renderer until a page prints its header.
- `db/upgrade.php` first step reads the schema, not `$oldversion`: sites from upstream arrive by a
  hand-set switch number (README), which says nothing about which upstream steps ran.

## When in doubt

Follow the patterns in existing files, and the fleet file.
