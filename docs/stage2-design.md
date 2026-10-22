# Stage 2 design: web services and the JavaScript rewrite

Contract for stage 2 of the fleet adoption (`docs/fleet-audit-2026-10-21.md`). It replaces the four
JSON scripts under `ajax/` with external functions and rewrites both AMD modules as ES modules on
core's APIs. It closes S7, S9 (client side), S10-S12 where they remain, F1-F6, F15, F20-F22 and F26.
The page templates (`main_page`, `teacher_page`) and the block's HTML are stage 3: stage 2 touches
them only where the JavaScript contract changes (data attributes, form methods, the async marker of
the teacher's course form).

## External functions (`classes/external/`, one class per file, `core_external\*`)

Every function: `validate_parameters()`, `validate_context(context_system)` (the scope is site-wide
and narrows itself), `require_login()` semantics through the WS layer plus an explicit guest
rejection, the scope built from `$USER` with `scope::for_user()`, and the same checks the 1b scripts
apply through `request` and `zip_tasks` — the external function is a thin adapter, not a second copy
of the rules. Return structures are allowlists; names and texts go out through
`format_string(..., ['escape' => false])` and are declared `PARAM_TEXT` (stage-1 F26: the client
escapes once).

| Function | Replaces | Type | Notes |
|---|---|---|---|
| `block_download_certificates_search_certificates` | `ajax/search_certificates.php` | read | filter fields, `sort`, `order`, `page`, `perpage` (bounded); returns rows + total; each row carries a download URL to `download_precise.php` without sesskey |
| `block_download_certificates_get_groups` | `ajax/get_groups_for_course.php` | read | `request::course_groups()` |
| `block_download_certificates_create_task` | `ajax/create_task.php` | write | `zip_tasks::create()`; triggers `\block_download_certificates\event\zip_requested` (objectid = task id, objecttable = the tasks table, context = the requester's user context) |
| `block_download_certificates_get_task_status` | `ajax/task_status.php` | read | owner only; returns status, progress, total, a localised error and, when ready, the download URL |

`db/services.php` declares the four with `'ajax' => true`, `'loginrequired' => true`; version bump.
The four scripts are deleted. `ajax/download_zip.php` stays (it serves a file) and becomes POST-only
with the sesskey in the body; the JavaScript submits a form to a hidden iframe.

## Downloads and sesskeys (S7, S12)

- `download_precise.php` (one certificate) is a read, like `pluginfile.php`: GET, no sesskey, the
  scope check of `download::send_single()` as today. No sesskey travels in any URL any more.
  A GET on it may make `tool_certificate` render and store a missing or empty issue PDF through the
  plugin's own API (`create_issue_file`), the same lazy creation `tool_certificate`'s own pages do,
  bounded to certificates the viewer may download; accepted.
- The ZIP pages (`download*.php`) build a ZIP: POST only, `require_sesskey()` from the body; the
  forms that call them use `method="post"` with a hidden `sesskey` input.
- Downloading one's own certificates needs a logged-in, non-guest user and nothing else: own
  certificates are always in scope, and `:view` is a block capability that the system context does
  not decide (S7).

## JavaScript (`amd/src/`, ES modules, no jQuery)

- `certificate_table.js`: `core/ajax` calls to `search_certificates` and `get_groups`; rows rendered
  by a new template `block_download_certificates/certificate_rows` through `core/templates`
  (example context with non-empty rows); every label through `core/str` `getStrings`; sortable
  headers are `<button>`s with `aria-sort` on the `<th>`; the result count and the table container
  are `aria-live="polite"`; a monotonically increasing request id drops stale responses; no
  `innerHTML` with server data.
- `async_download.js`: intercepts the ZIP forms marked `data-async-threshold` (the teacher's course
  form included, F15) when the count is above `download::SYNC_LIMIT` (exactly that many are built at once); `create_task` and
  polling `get_task_status` with a recursive `setTimeout`, one request in flight, backoff on errors
  and a cap after which it stops and says so; progress shown in a `core/modal` (`Modal.create`,
  available on 4.5) whose body is a template (`block_download_certificates/task_progress`); the
  ready download through the POST form; dismissed banners remembered in `localStorage` under a key
  that includes the user id, every access inside `try`; errors through `core/notification` or
  `textContent`, never `innerHTML`.
- Strings: every key the modules use exists in en/fr/pt_br; no dynamic ids (the
  `async_zip_ready_label_<type>` family becomes a literal map in the module).
- `amd/build` rebuilt and committed with the sources; eslint clean at `--max-warnings 0`.

## Tests

- PHPUnit per external function (`tests/external/`): `call_external_function()` with
  `$_POST['sesskey']`, guest refused, a learner refused where the scope does not reach, a teacher
  limited to their scope, return structure validation; `create_task` triggers `zip_requested` with
  the right object and context (and a refused call triggers none); `get_task_status` refuses
  another user's task.
- Script guards that stage 1 left untested (security read, items 12-16): the ZIP pages refuse GET
  without a body sesskey; `download_precise.php` serves without a sesskey to a user in scope and
  refuses one outside it — through the functions the scripts delegate to, or Behat in stage 4.
- Behat stays for stage 4; the JS is verified there.
