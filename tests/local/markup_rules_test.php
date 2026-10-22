<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace block_download_certificates\local;

/**
 * Guards the plugin's markup and stylesheet rules.
 *
 * phpcs, the Mustache lint and stylelint never read a class name or an attribute out of a template,
 * a class or a JS module: this test scans them for Bootstrap 4 names and data-API attributes, badge
 * text colours, inline styles, and the stylesheet's selector, colour and motion rules.
 *
 * Metadata stays in docblock form so the test files stay identical across the plugin's branches.
 *
 * @package   block_download_certificates
 * @category  test
 * @copyright 2026 Anderson Blaine
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @coversNothing
 */
final class markup_rules_test extends \basic_testcase {
    /**
     * Bootstrap 4 names that 5.x resolves only through its deprecated compatibility layer, or not at
     * all, each with its Bootstrap 5 spelling.
     *
     * @return array Regex => the name to use instead.
     */
    private function bootstrap4_names(): array {
        return [
            '/(?<![\w-])sr-only(-focusable)?(?![\w-])/' => 'visually-hidden',
            '/(?<![\w-])m[lr](-(sm|md|lg|xl))?-([0-5]|auto)(?![\w-])/' => 'ms-* / me-*',
            '/(?<![\w-])p[lr](-(sm|md|lg|xl))?-[0-5](?![\w-])/' => 'ps-* / pe-*',
            '/(?<![\w-])text(-(sm|md|lg|xl))?-(left|right)(?![\w-])/' => 'text-start / text-end',
            '/(?<![\w-])float(-(sm|md|lg|xl))?-(left|right)(?![\w-])/' => 'float-start / float-end',
            '/(?<![\w-])(border|rounded)-(left|right)(?![\w-])/' => '*-start / *-end',
            '/(?<![\w-])no-gutters(?![\w-])/' => 'g-0',
            '/(?<![\w-])font-weight-[a-z]+(?![\w-])/' => 'fw-*',
            '/(?<![\w-])form-(group|inline|row)(?![\w-])/' => 'mb-3 / flex utilities',
            '/(?<![\w-])custom-(select|control|checkbox|radio|switch|file|range)(?![\w-])/' => 'form-select / form-check',
            '/(?<![\w-])badge-(primary|secondary|success|danger|warning|info|light|dark|pill)(?![\w-])/' => 'bg-* text-*',
            '/(?<![\w-])btn-block(?![\w-])/' => 'w-100',
            '/(?<![\w-])input-group-(append|prepend)(?![\w-])/' => 'input-group',
        ];
    }

    /**
     * Saturated backgrounds that need an explicit text colour, so that the badge reads whatever the
     * theme sets for `.badge`.
     *
     * @return array Background utility => the text utility it requires.
     */
    private function badge_text_colours(): array {
        return [
            'bg-success' => 'text-white',
            'bg-primary' => 'text-white',
            'bg-danger' => 'text-white',
            'bg-info' => 'text-white',
            'bg-dark' => 'text-white',
            'bg-secondary' => 'text-dark',
            'bg-warning' => 'text-dark',
            'bg-light' => 'text-dark',
        ];
    }

    /**
     * Absolute path to the plugin root.
     *
     * @return string
     */
    private function plugin_root(): string {
        return dirname(__DIR__, 2);
    }

    /**
     * Every file whose contents can put a class name or an attribute in front of a user.
     *
     * amd/build is generated from amd/src; tests and docs are not shipped markup.
     *
     * @return array List of absolute paths.
     */
    private function markup_files(): array {
        $root = $this->plugin_root();
        $files = glob($root . '/*.php') ?: [];
        foreach ([$root . '/templates', $root . '/amd/src', $root . '/classes'] as $dir) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS));
            foreach ($iterator as $file) {
                if (in_array($file->getExtension(), ['mustache', 'js', 'php'], true)) {
                    $files[] = $file->getPathname();
                }
            }
        }
        sort($files);
        return $files;
    }

    /**
     * A file's lines with its comments blanked, so that prose naming a class is not read as markup.
     *
     * Line numbers are kept: every removed comment leaves its line breaks.
     *
     * @param string $path The file.
     * @return string[] The lines, numbered from 0.
     */
    private function code_lines(string $path): array {
        $contents = file_get_contents($path);
        $keepbreaks = static fn(array $m): string => str_repeat("\n", substr_count($m[0], "\n"));
        switch (pathinfo($path, PATHINFO_EXTENSION)) {
            case 'mustache':
                $contents = preg_replace_callback('/\{\{!.*?\}\}/s', $keepbreaks, $contents);
                break;
            case 'js':
                $contents = preg_replace_callback('~/\*.*?\*/~s', $keepbreaks, $contents);
                $contents = preg_replace('~^\s*//.*$~m', '', $contents);
                break;
            case 'php':
                $code = '';
                foreach (token_get_all($contents) as $token) {
                    if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
                        $code .= str_repeat("\n", substr_count($token[1], "\n"));
                        continue;
                    }
                    $code .= is_array($token) ? $token[1] : $token;
                }
                $contents = $code;
                break;
        }
        return explode("\n", $contents);
    }

    /**
     * styles.css without comments.
     *
     * @return string
     */
    private function stylesheet(): string {
        return preg_replace('~/\*.*?\*/~s', '', file_get_contents($this->plugin_root() . '/styles.css'));
    }

    /**
     * The rules of styles.css outside @keyframes, each with the at-rules around it.
     *
     * @return array List of [string $selector, string $declarations, string[] $atrules].
     */
    private function css_rules(): array {
        $css = $this->stylesheet();
        $rules = [];
        $stack = [];
        $buffer = '';
        $length = strlen($css);
        for ($i = 0; $i < $length; $i++) {
            $char = $css[$i];
            if ($char === '{') {
                $prelude = trim($buffer);
                $buffer = '';
                if (str_starts_with($prelude, '@')) {
                    $stack[] = $prelude;
                    continue;
                }
                $close = strpos($css, '}', $i);
                $inkeyframes = (bool) array_filter($stack, static fn(string $at): bool => str_starts_with($at, '@keyframes'));
                if (!$inkeyframes) {
                    $rules[] = [$prelude, substr($css, $i + 1, $close - $i - 1), $stack];
                }
                $i = $close;
            } else if ($char === '}') {
                array_pop($stack);
                $buffer = '';
            } else {
                $buffer .= $char;
            }
        }
        return $rules;
    }

    /**
     * No Bootstrap 4 name: 5.x keeps them only behind a deprecation layer that Moodle 6.0 removes.
     *
     * Change that must make it fail: write `mr-1` beside an icon in a template.
     */
    public function test_no_bootstrap4_names(): void {
        $offenders = [];
        $scanned = 0;
        foreach ($this->markup_files() as $path) {
            $scanned++;
            foreach ($this->code_lines($path) as $number => $line) {
                foreach ($this->bootstrap4_names() as $pattern => $replacement) {
                    if (preg_match($pattern, $line, $match)) {
                        $offenders[] = basename($path) . ':' . ($number + 1) . ' ' . $match[0] . ' => ' . $replacement;
                    }
                }
            }
        }
        $this->assertGreaterThan(20, $scanned);
        $this->assertSame([], $offenders, 'Bootstrap 4 names: ' . implode('; ', $offenders));
    }

    /**
     * Every saturated background states its text colour, in the same class string.
     *
     * Change that must make it fail: drop `text-dark` from the type badge of certificate_rows.
     */
    public function test_backgrounds_state_their_text_colour(): void {
        // The progress bar's colour is Bootstrap's own .progress-bar white.
        $exceptions = ['async_download.js'];
        $offenders = [];
        $checked = 0;
        foreach ($this->markup_files() as $path) {
            if (in_array(basename($path), $exceptions, true)) {
                continue;
            }
            foreach ($this->code_lines($path) as $number => $line) {
                foreach ($this->badge_text_colours() as $background => $text) {
                    $pattern = '/(?<![\w-])' . preg_quote($background, '/') . '(?![\w-])/';
                    if (!preg_match_all($pattern, $line, $matches, PREG_OFFSET_CAPTURE)) {
                        continue;
                    }
                    foreach ($matches[0] as [$token, $offset]) {
                        $checked++;
                        $classes = $this->quoted_text_around($line, $offset, strlen($token));
                        if (!preg_match('/(?<![\w-])' . preg_quote($text, '/') . '(?![\w-])/', $classes)) {
                            $offenders[] = basename($path) . ':' . ($number + 1) . ' needs ' . $text . ' beside ' . $background;
                        }
                    }
                }
            }
        }
        $this->assertGreaterThan(0, $checked, 'No background utility found: the check would pass over nothing.');
        $this->assertSame([], $offenders, implode('; ', $offenders));
    }

    /**
     * The text between the nearest quotes around a token.
     *
     * @param string $line The line.
     * @param int $offset The token's byte offset.
     * @param int $length The token's byte length.
     * @return string
     */
    private function quoted_text_around(string $line, int $offset, int $length): string {
        $start = 0;
        $end = strlen($line);
        foreach (['"', "'", "\x60"] as $quote) {
            $before = strrpos(substr($line, 0, $offset), $quote);
            if ($before !== false && $before + 1 > $start) {
                $start = $before + 1;
            }
            $after = strpos($line, $quote, $offset + $length);
            if ($after !== false && $after < $end) {
                $end = $after;
            }
        }
        return substr($line, $start, $end - $start);
    }

    /**
     * Every Bootstrap data-API attribute is a `data-bs-*` one; the Bootstrap 4 spelling does nothing
     * on 5.x, and nothing in the JavaScript may write it either.
     *
     * Changes that must make it fail: write `data-toggle="tab"` on a tab of filter_bar; add JS that
     * writes `dataset.toggle`.
     */
    public function test_data_api_attributes_are_bootstrap5_only(): void {
        $offenders = [];
        $found = 0;
        $names = '(toggle|target|dismiss|parent|ride|slide)=';
        foreach ($this->markup_files() as $path) {
            foreach ($this->code_lines($path) as $number => $line) {
                $found += preg_match_all('/(?<![\w-])data-bs-' . $names . '/', $line);
                if (preg_match('/(?<![\w-])data-' . $names . '/', $line, $match)) {
                    $offenders[] = basename($path) . ':' . ($number + 1) . ' ' . $match[0];
                }
            }
        }
        $this->assertGreaterThan(0, $found, 'No data-bs-* attribute: the tabs are gone or renamed.');
        $this->assertSame([], $offenders, 'Bootstrap 4 data-API attributes: ' . implode('; ', $offenders));

        foreach (glob($this->plugin_root() . '/amd/src/*.js') ?: [] as $module) {
            $this->assertStringNotContainsString('dataset.toggle', file_get_contents($module), basename($module));
        }
    }

    /**
     * No inline style or event handler in the markup: styles live in styles.css, behaviour in the
     * modules, and a translated string never lands inside JavaScript code.
     *
     * Change that must make it fail: put `style="min-width: 250px;"` on a menu of filter_bar.
     */
    public function test_no_inline_styles_or_handlers(): void {
        $offenders = [];
        foreach ($this->markup_files() as $path) {
            if (!str_ends_with($path, '.mustache') && !str_ends_with($path, '.js')) {
                continue;
            }
            foreach ($this->code_lines($path) as $number => $line) {
                if (preg_match('/(?<![\w-])(style|on[a-z]+)\s*=\s*["\'{]/i', $line, $match)) {
                    $offenders[] = basename($path) . ':' . ($number + 1) . ' ' . $match[0];
                }
                if (str_contains($line, 'html_writer')) {
                    $offenders[] = basename($path) . ':' . ($number + 1) . ' html_writer';
                }
            }
        }
        foreach ($this->markup_files() as $path) {
            if (str_ends_with($path, '.php') && preg_match('/\bhtml_writer\b/', implode("\n", $this->code_lines($path)))) {
                $offenders[] = basename($path) . ' html_writer';
            }
        }
        $this->assertSame([], $offenders, implode('; ', $offenders));
    }

    /**
     * Every rule of styles.css is qualified by a plugin class.
     *
     * Plugin CSS is compiled before the theme's: a bare Bootstrap selector never wins, and an
     * unqualified one would restyle core pages.
     *
     * Change that must make it fail: write a `.pagination .page-link` rule.
     */
    public function test_every_selector_is_qualified_by_a_plugin_class(): void {
        $offenders = [];
        $rules = $this->css_rules();
        $this->assertGreaterThan(10, count($rules));
        foreach ($rules as [$selector]) {
            foreach (explode(',', $selector) as $part) {
                if (!preg_match('/\.block-download-certificates-[a-z0-9-]+/', $part)) {
                    $offenders[] = trim($part);
                }
            }
        }
        $this->assertSame([], $offenders, 'Unqualified selectors: ' . implode('; ', $offenders));
    }

    /**
     * Every colour literal is the last fallback of a theme token, and the stylesheet declares no
     * custom property in core's --mds- namespace.
     *
     * Changes that must make it fail: `color: #6c757d;` in any rule; a `--mds-accent` declaration.
     */
    public function test_colours_come_from_theme_tokens(): void {
        $offenders = [];
        $literals = 0;
        foreach ($this->css_rules() as [$selector, $declarations]) {
            foreach (explode(';', $declarations) as $declaration) {
                if (preg_match('/--mds-[a-z0-9-]*\s*:/i', $declaration)) {
                    $offenders[] = $selector . ' declares ' . trim($declaration);
                }
                if (!preg_match_all('/#[0-9a-f]{3,8}\b|\brgba?\(|\bhsla?\(/i', $declaration, $matches, PREG_OFFSET_CAPTURE)) {
                    continue;
                }
                foreach ($matches[0] as [$literal, $offset]) {
                    $literals++;
                    // A fallback is the last argument of a var() still open where the literal stands.
                    $before = substr($declaration, 0, $offset);
                    $open = substr_count($before, 'var(') - substr_count($before, ')');
                    if ($open < 1 || !preg_match('/,\s*$/', $before)) {
                        $offenders[] = $selector . ': ' . trim($declaration);
                    }
                }
            }
        }
        $this->assertGreaterThan(0, $literals, 'No fallback literal found: the token block is gone.');
        $this->assertSame([], $offenders, 'Colours outside a token fallback: ' . implode('; ', $offenders));
    }

    /**
     * Every animation runs only for users who did not ask for reduced motion.
     *
     * Change that must make it fail: move the row animation out of its media query.
     */
    public function test_animations_respect_reduced_motion(): void {
        $animated = 0;
        $offenders = [];
        foreach ($this->css_rules() as [$selector, $declarations, $atrules]) {
            if (!preg_match('/(^|;)\s*(animation|transition)(-[a-z-]+)?\s*:/', $declarations)) {
                continue;
            }
            $animated++;
            $guarded = (bool) array_filter(
                $atrules,
                static fn(string $at): bool => (bool) preg_match('/prefers-reduced-motion:\s*no-preference/', $at)
            );
            if (!$guarded) {
                $offenders[] = $selector;
            }
        }
        $this->assertGreaterThan(0, $animated);
        $this->assertSame([], $offenders, 'Animations outside prefers-reduced-motion: ' . implode('; ', $offenders));
    }
}
