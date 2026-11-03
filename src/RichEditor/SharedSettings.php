<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Illuminate\Support\Str;

/**
 * The settings every editor on a page reads the same, written out once a page rather than
 * once an editor.
 *
 * The slash menu, the strings and tab icons of both pickers and the find bar's travel to the
 * browser as JSON on the element the editor is mounted on, because a TipTap extension has no
 * other channel to the field. They are the same for every field set up the same way: 45 KB
 * of escaped JSON per editor, 900 KB of a page of twenty in a repeater - and out of reach of
 * gzip, whose window ends long before the next editor repeats them.
 *
 * So each one is written in full on the first editor of a page and named by a hash of itself
 * on every other. `resources/js/shared-settings.js` puts it back on the others before any
 * extension reads it, from the page's store or off the editor that carries it, and every
 * reader goes on reading its own element as before. When a page is and is not shared is
 * `RenderLedger`'s: an update carries everything on every editor.
 */
final class SharedSettings
{
    /**
     * The attributes that carry the settings to the editor element, written out in full only
     * where this page has not written them before.
     *
     * @param  array<string, mixed>  $settings  each setting by the key the browser reads it under, `arteSlash` for `data-arte-slash`
     */
    public static function attributes(object $component, array $settings): string
    {
        $names = [];
        $carried = [];
        $html = '';

        foreach ($settings as $name => $setting) {
            if (blank($setting)) {
                continue;
            }

            $json = (string) json_encode($setting);
            $key = RenderLedger::keyFor($json);

            $names[$name] = $key;

            if (! RenderLedger::writesOut($component, $key)) {
                continue;
            }

            $carried[] = $key;
            $html .= ' data-'.Str::kebab($name).'="'.e($json).'"';
        }

        if ($names === []) {
            return '';
        }

        $html .= ' data-arte-shared="'.e((string) json_encode($names)).'"';

        if ($carried !== []) {
            $html .= ' data-arte-carries="'.implode(' ', $carried).'"';
        }

        return $html;
    }
}
