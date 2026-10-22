# Stage 3 design: rendering, Bootstrap 5, CSS and accessibility

Contract for stage 3 of the fleet adoption (`docs/fleet-audit-2026-10-21.md`). It closes F7-F14,
F16-F18, F29 and F30, the owner's block-placement decision on the rendering side, and leaves every
gate green: mustache (example contexts) and grunt (stylelint) are the two still red after stage 2.

## The block

- No `html_writer` anywhere in the plugin. `get_content()` builds one context and renders
  `block_download_certificates/block_content` (partials per section: manager, teacher, personal) via
  `$OUTPUT->render_from_template()`; the decision of which sections show stays the stage-1 scope's.
- Placement (owner decision 2026-10-21): dashboard and front page show the site-wide summary; a
  course page shows that course only — the manager's "download all" there downloads that course's
  certificates (a course ZIP), never the site's; a user profile shows that user's certificates, to
  the user and to whoever's scope reaches them, and nothing when the viewer's scope does not.
- The block uses no Bootstrap 5 utility that Moodle 4.5's bridge lacks (it renders inside pages the
  plugin does not own, after the header, so it cannot add a body class): plugin-owned classes for
  its own layout, Bootstrap names that exist in both 4.5 and 5.x for the rest.

## The plugin's pages (`index.php`, `index_teacher.php`)

- `main_page` and `teacher_page` share partials (filter bar, search bar, table card) instead of
  duplicating ~70 % of their markup (F30); every template has an `Example context (json):` with
  non-empty loop data and no `{{...}}` inside the comment.
- Bootstrap 5 names (`ms-*`/`me-*`, `visually-hidden`, `form-select`, `bg-* text-*` badges with their
  text colour stated, `mb-3` rows instead of `form-group`, flex utilities instead of `form-inline`,
  `data-bs-toggle` only); the ones absent from 4.5's bridge are polyfilled in `styles.css` under a
  body class added only when `$CFG->branch < 500`, on the plugin's own pages
  (`classes/local/bootstrap.php`, the local_dimensions 4.5 pattern:
  `~/dev/moodle-local_dimensions-405/classes/local/bootstrap.php`), with a scanning test like
  `~/dev/moodle-local_dimensions-405/tests/local/bootstrap_compat_test.php` that fails when a template,
  the block or a JS module uses a Bootstrap-5-only name the polyfill lacks, or writes a BS4 name
  beside its BS5 one. After the branch split only the 4.5 branch keeps the polyfill.
- No inline handlers or styles: the "download all" confirmation is a `data-confirm` attribute that
  `async_download.js` (or a small listener in it) turns into `core/notification` `saveCancelPromise`
  before submitting; `min-width`/`display:none` move to classes or the `hidden` attribute.

## Accessibility (F14)

Every select and input has a `<label>` (visually hidden where the design has none); icon-only
controls carry an `aria-label` from a lang string; decorative icons `aria-hidden="true"`; tabs are
`role="tablist"`/`tab`/`tabpanel` with `aria-controls`/`aria-labelledby` and the selected state set by
Bootstrap, not hard-coded; progress elements have an accessible name; live regions as stage 2 left
them; colour is never the only signal.

## CSS (`styles.css`)

- stylelint clean: no `data:` URLs (the select chevron comes from the theme's `form-select`), no
  `!important`, short hex, transitions ≥ 100 ms, no `clamp()/min()/max()` in lengths, no
  `@container`; animations inside `@media (prefers-reduced-motion: no-preference)`.
- Colours only through theme tokens with fallback chains
  (`var(--bs-primary, var(--primary, #0f6cbf))`, `var(--bs-border-color, #dee2e6)`,
  `var(--bs-body-bg, #fff)`, `var(--bs-secondary-color, #6c757d)`); every rule that paints a
  background states its text colour (dark mode); the plugin's own custom properties declared on
  every root it paints, including the `core/modal` it appends to `body` (give that modal a plugin
  class).
- Every selector qualified by a plugin-owned class (plugin CSS loads before the theme's, so a bare
  Bootstrap class never wins); `.pagination` overrides go; numbers use
  `font-variant-numeric: tabular-nums`; dead rules from the hand-built modal and banner are removed;
  `index.php` stops loading `styles.css` a second time.

## Lang, JS, tests

- New labels in en/fr/pt_br in lockstep; strings left unused are removed.
- `amd/build` rebuilt if the JS changes (confirmation handler, class names).
- PHPUnit: the block's context per placement (dashboard, front page, course page — "download all"
  targets the course —, own profile, another user's profile with and without scope); the template
  contexts rendered with `render_from_template` in a test so a missing key fails there; the
  Bootstrap scanning test. Behat smoke is stage 4.
