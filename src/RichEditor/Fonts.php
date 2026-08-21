<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;

/**
 * Which typefaces a field can offer, and the `@font-face` rules that make them real.
 *
 * A font picker is only worth having if every entry in it works, so nothing here is
 * declared - it is found. The configured directory is read, one family is built per folder
 * or file-name prefix, and a face is written for every file, so a project adds a typeface
 * by putting it where its other assets live and nothing else.
 *
 * The generic stacks come free: `system-ui`, `serif` and `monospace` resolve on every
 * machine without a byte being downloaded. A project can also name families it loads
 * elsewhere - a theme, a self-hosted kit - and those are the only entries the browser is
 * asked about before they are shown, because nothing on this side can prove they arrived.
 *
 * Nothing is fetched from anywhere: no CDN, no Google Fonts, no network at all.
 */
class Fonts
{
    /**
     * @var array<int, string>
     */
    protected const EXTENSIONS = ['woff2', 'woff', 'ttf', 'otf'];

    /**
     * The formats CSS wants to be told about, keyed by extension.
     *
     * @var array<string, string>
     */
    protected const FORMATS = [
        'woff2' => 'woff2',
        'woff' => 'woff',
        'ttf' => 'truetype',
        'otf' => 'opentype',
    ];

    /**
     * Weight names as they turn up in font file names.
     *
     * @var array<string, int>
     */
    protected const WEIGHTS = [
        'thin' => 100,
        'extralight' => 200,
        'ultralight' => 200,
        'light' => 300,
        'regular' => 400,
        'normal' => 400,
        'book' => 400,
        'medium' => 500,
        'semibold' => 600,
        'demibold' => 600,
        'bold' => 700,
        'extrabold' => 800,
        'ultrabold' => 800,
        'black' => 900,
        'heavy' => 900,
    ];

    /**
     * Everything the picker may offer, in the order it is drawn.
     *
     * @return array<int, array{label: string, stack: string, verify: bool}>
     */
    public static function for(AdvancedRichEditor $editor): array
    {
        $own = $editor->getFonts();

        if ($own !== null) {
            $fonts = [];

            foreach ($own as $label => $stack) {
                $fonts[] = ['label' => (string) $label, 'stack' => $stack, 'verify' => false];
            }

            return $fonts;
        }

        $fonts = [];

        if (config('filament-advanced-rich-editor.fonts.generic') ?? true) {
            foreach (static::generic() as $label => $stack) {
                $fonts[] = ['label' => $label, 'stack' => $stack, 'verify' => false];
            }
        }

        // Found on disk, so there is nothing to verify: the face this package writes points
        // at a file it has just seen.
        foreach (array_keys(static::files()) as $family) {
            $fonts[] = ['label' => $family, 'stack' => static::stack($family), 'verify' => false];
        }

        foreach (config('filament-advanced-rich-editor.fonts.families') ?? [] as $label => $stack) {
            $fonts[] = ['label' => (string) $label, 'stack' => (string) $stack, 'verify' => true];
        }

        // One list, in the order a list of names is looked through. Where a typeface came
        // from - a file, a config entry, the operating system - says nothing about where
        // someone expects to find it.
        usort($fonts, static fn (array $a, array $b): int => strnatcasecmp($a['label'], $b['label']));

        return $fonts;
    }

    /**
     * @return array<string, string>
     */
    public static function generic(): array
    {
        return [
            __('filament-advanced-rich-editor::advanced-rich-editor.fonts.system') => 'system-ui, -apple-system, "Segoe UI", sans-serif',
            __('filament-advanced-rich-editor::advanced-rich-editor.fonts.serif') => 'Georgia, "Times New Roman", serif',
            __('filament-advanced-rich-editor::advanced-rich-editor.fonts.monospace') => 'ui-monospace, "SF Mono", "Cascadia Mono", monospace',
        ];
    }

    /**
     * The `@font-face` rules for everything found on disk. Without them the picker would
     * offer a typeface the page has never been told how to load.
     */
    public static function styleSheet(AdvancedRichEditor $editor): string
    {
        if ($editor->getFonts() !== null) {
            return '';
        }

        $rules = [];

        foreach (static::files() as $family => $files) {
            // Both halves come off the disk, and the result is written into a `<style>`
            // element. A file named `x"); } body { display: none } @font-face { a:"` would
            // otherwise be a stylesheet of somebody else's choosing, and one containing
            // `</style>` would be markup - so a name that is not a name is skipped rather
            // than escaped: there is no legitimate typeface behind it.
            $name = ToolbarFontPicker::sanitise($family);

            if ($name === null) {
                continue;
            }

            foreach ($files as $file) {
                $url = static::url($file['path']);

                if (! static::isSafeUrl($url)) {
                    continue;
                }

                $rules[] = sprintf(
                    "@font-face { font-family: \"%s\"; src: url('%s') format('%s'); font-weight: %d; font-style: %s; font-display: swap; }",
                    $name,
                    $url,
                    $file['format'],
                    $file['weight'],
                    $file['style'],
                );
            }
        }

        return implode("\n", $rules);
    }

    /**
     * What the last scan found, keyed by the directory it scanned.
     *
     * Resolving a toolbar is not a once-per-render affair: Filament asks the field what
     * buttons it has several times while rendering one editor, and every one of those walks
     * the token list again. Without this the picker would read the font directory off the
     * disk a handful of times per field, and a page with three editors would do it a dozen.
     *
     * Keyed by directory rather than reset per request, because font files arrive with a
     * deploy and a deploy restarts the worker.
     *
     * @var array<string, array<string, array<int, array{path: string, format: string, weight: int, style: string}>>>
     */
    protected static array $scanned = [];

    /**
     * The font files in the configured directory, grouped by family.
     *
     * A file in a folder belongs to the folder's family, which is how a project that keeps
     * `fonts/Inter/Inter-Regular.woff2` means it. A loose file is read up to its first
     * separator, which is how `fonts/Fraunces-Light.woff` means the same thing.
     *
     * Only the directory itself and one level of folders below it are read: a family is a
     * folder, and a folder inside a family is not a second family.
     *
     * @return array<string, array<int, array{path: string, format: string, weight: int, style: string}>>
     */
    public static function files(): array
    {
        $directory = config('filament-advanced-rich-editor.fonts.directory');

        if (blank($directory)) {
            return [];
        }

        $root = static::path($directory);

        if (array_key_exists($root, static::$scanned)) {
            return static::$scanned[$root];
        }

        if (! is_dir($root)) {
            return static::$scanned[$root] = [];
        }

        $families = [];

        foreach (static::scan($root) as $file) {
            $extension = strtolower(pathinfo($file, PATHINFO_EXTENSION));

            if (! in_array($extension, static::EXTENSIONS, strict: true)) {
                continue;
            }

            $name = pathinfo($file, PATHINFO_FILENAME);
            $folder = basename(dirname($file));

            $family = $folder === basename($root)
                ? static::familyOf($name)
                : static::humanise($folder);

            $families[$family][] = [
                'path' => $file,
                'format' => static::FORMATS[$extension],
                'weight' => static::weightOf($name),
                'style' => str_contains(strtolower($name), 'italic') ? 'italic' : 'normal',
            ];
        }

        ksort($families);

        return static::$scanned[$root] = $families;
    }

    /**
     * Forgets what the last scan found. Only a test - or a project that writes font files at
     * runtime - has any reason to call this.
     */
    public static function forget(): void
    {
        static::$scanned = [];
    }

    /**
     * Whether a URL can be put inside `url('…')` without ending the declaration, the rule or
     * the `<style>` element around it. A plain space is allowed - it is legal inside a quoted
     * url, and a family called `My Font` has one in every path it owns - so what is left are
     * the characters a file name would need in order to write CSS of its own.
     */
    protected static function isSafeUrl(string $url): bool
    {
        return preg_match("/[\"'()<>\\\\\r\n\t;{}]/", $url) !== 1;
    }

    /**
     * @return array<int, string>
     */
    protected static function scan(string $root): array
    {
        $files = glob($root.'/*') ?: [];

        $found = [];

        foreach ($files as $file) {
            if (is_dir($file)) {
                $found = [...$found, ...(glob($file.'/*') ?: [])];

                continue;
            }

            $found[] = $file;
        }

        sort($found);

        return $found;
    }

    protected static function stack(string $family): string
    {
        return '"'.$family.'", system-ui, sans-serif';
    }

    /**
     * The family a loose file name names: everything before the first separator, so
     * `Fraunces-Light` is Fraunces and `PlayfairDisplay_Bold` is Playfair Display.
     */
    protected static function familyOf(string $name): string
    {
        return static::humanise(preg_split('/[-_.]/', $name)[0] ?? $name);
    }

    /**
     * `PlayfairDisplay` reads as two words to everyone but a computer.
     */
    protected static function humanise(string $name): string
    {
        return trim(preg_replace('/(?<=[a-z0-9])(?=[A-Z])/', ' ', str_replace(['-', '_'], ' ', $name)) ?? $name);
    }

    protected static function weightOf(string $name): int
    {
        $lower = strtolower(str_replace(['-', '_', ' '], '', $name));

        // Longest first, or "extrabold" is found as "bold" and "semibold" as "bold" too.
        $weights = static::WEIGHTS;
        uksort($weights, static fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($weights as $word => $weight) {
            if (str_contains($lower, $word)) {
                return $weight;
            }
        }

        return preg_match('/(100|200|300|400|500|600|700|800|900)/', $name, $matches) === 1
            ? (int) $matches[1]
            : 400;
    }

    protected static function path(string $directory): string
    {
        if (str_starts_with($directory, '/')) {
            return rtrim($directory, '/');
        }

        return rtrim(function_exists('public_path') ? public_path($directory) : $directory, '/');
    }

    /**
     * The URL a browser can fetch the file from. A file under `public/` is served from
     * there; anything else is left as the path it was given, which is the honest answer
     * when a project keeps its fonts somewhere only the server can see.
     */
    protected static function url(string $path): string
    {
        $public = function_exists('public_path') ? public_path() : '';

        if ($public !== '' && str_starts_with($path, $public)) {
            $relative = ltrim(substr($path, strlen($public)), '/');

            return function_exists('asset') ? asset($relative) : '/'.$relative;
        }

        return $path;
    }
}
