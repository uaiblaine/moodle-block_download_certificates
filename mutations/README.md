# Mutation spec

Every guard that decides who may see, request or download a certificate is paired with the test
that must go red when the guard is removed or inverted. `gates.conf` holds 100 gates, one
guard each, grouped by file: `scope.php`, the two sources, `repository.php`, `request.php`,
`filter.php`, `zip_tasks.php`, `zip_builder.php`, `task_storage.php`, the two tasks, the privacy
provider, the external functions, `block_content.php`, and the plugins directory guidelines
(identity fields, the uninstall, the package). Under each gate the `#   -> ` lines name
the tests written for that guard. A gate with no such line is expected to expose a missing test
and its comment says why.

## How to run

`mdl-mutate` takes the repository directory and the spec path relative to `~/dev`. Until
moodle-dev merges the certificate-plugin dependencies (`tool_certificate`, `mod_customcert`), run
the copy in the worktree:

```sh
cd ~/dev
D=~/dev/moodle-dev-dlcerts/bin/mdl-mutate
R=moodle-block_download_certificates

$D $R $R/mutations/gates.conf --fast --dry-run                      # patterns only, seconds
$D $R $R/mutations/gates.conf --fast --db pgsql --jobs 4            # the sweep, PostgreSQL
$D $R $R/mutations/gates.conf --fast --db mariadb --jobs 4          # the sweep, MariaDB
$D $R $R/mutations/gates.conf --fast --db pgsql --only SC_group_course_binding
```

Sweep both database families: a guard around a comparison can redden on one only. A fast sweep
reads the working tree as it is on disk, uncommitted changes included, and never writes it. Run it
from the main session in a background command, never inside a subagent: it takes tens of minutes.

## Reading the result

Exit 0: every gate reddened a test and every test it names. Exit 2: the tags listed reddened
nothing, or left a named test green; each is a finding, not noise. Exit 1: the sweep itself cannot
be trusted (red baseline, no verdict).

JavaScript guards (`async_download.js`) have no gate: the spec rewrites PHP, and the plugin has no
JavaScript test runner.

## Rules for editing the spec

- **Anchor patterns on code, never on comments.** The `.pl` scripts are `s###` with `#` as the
  delimiter; none of the patterns contains a `#`.
- **Perl interpolates `$variables` and `@arrays` on both sides of `s///`.** Write `\$` for a PHP
  variable in the pattern and in the replacement; `$1` in a replacement is the capture on purpose.
- **Keep a placeholder used.** A guard written as a SQL condition is neutralised with
  `(1 = 1 OR condition)`, not deleted, because Moodle refuses a named parameter the statement does
  not use.
- **Each pattern must match exactly once.** Run `--dry-run` after any refactor of the file it
  names; a pattern that matches nothing is a hard error.
- **Add the gate in the same change as the guard**, and name the test that holds it. If you cannot
  name one, the guard is unheld and that is the finding.
- `mutations/` is not shipped: `.gitattributes` marks it `export-ignore`.
