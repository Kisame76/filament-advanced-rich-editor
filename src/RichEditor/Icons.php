<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use BackedEnum;
use Filament\Support\Icons\Heroicon;
use InvalidArgumentException;

/**
 * Every icon this package draws, in one place.
 *
 * Values are strings so the config file survives `config:cache`, and a bare Heroicon name
 * such as `trash` is handed back as the enum case. That distinction is not cosmetic:
 * Filament resolves its enum per size - the mini, filled variant at the size a toolbar
 * button uses - while a written-out `heroicon-o-trash` is always the 24px outline. Passing
 * the enum is therefore what keeps these buttons looking like the ones Filament draws
 * beside them.
 *
 * Anything with a set prefix is passed through untouched: `fi-o-*` (Filament's own),
 * `arte-*` (this package), `lucide-*` once that package is installed, and so on.
 */
class Icons
{
    /**
     * @return array<string, string>
     */
    public static function defaults(): array
    {
        return [
            // Toolbar. Outline throughout, so the package's own buttons sit together with
            // Filament's `fi-o-*` ones and with the bundled Lucide drawings, all of which
            // are stroke drawings. A bare `photo` would give Filament's filled variant.
            'headings' => 'fi-o-heading',
            'lists' => 'heroicon-o-list-bullet',
            // The spacing dropdown's trigger keeps this icon: its options are numbers, so
            // there is nothing to swap it for and it is the only thing saying what they
            // measure - lines, and the room between them.
            'line_height' => 'arte-line-spacing',
            'task_list' => 'arte-task-list',
            'blockquote' => 'arte-message-square-quote',
            'image' => 'heroicon-o-photo',
            'embed' => 'heroicon-o-film',
            // The colour tools carry the thing they paint, not the instrument: a bare `A`
            // for the letters - the trigger already draws the current colour as a bar
            // under it, so Lucide's baseline would only repeat that stroke - a highlighter
            // for the paint behind them, and a palette for the free choice.
            'text_color' => 'arte-letter-a',
            'text_background' => 'arte-highlighter',
            'color_custom' => 'arte-palette',
            // The overflow menu. Three dots is what a toolbar has always used for "the rest
            // of it", so the trigger says what it is without a label.
            'more' => 'heroicon-o-ellipsis-horizontal',
            'source_code' => 'heroicon-o-code-bracket',
            // Searching. The magnifier is the button, and the three inside the bar are the
            // chrome around it: two chevrons for the way through the hits and a cross for
            // the way out. The arrow curving back is the replacement, which is one thing
            // becoming another rather than a direction.
            'find' => 'heroicon-o-magnifying-glass',
            'find_previous' => 'heroicon-o-chevron-up',
            'find_next' => 'heroicon-o-chevron-down',
            'find_close' => 'heroicon-o-x-mark',
            // Lucide's own, like the rotations: Heroicons has nothing for replacing, and
            // its circular arrow reads as reload. The grip says the window can be moved.
            'find_replace' => 'arte-replace',
            'find_grip' => 'arte-grip-vertical',

            // The grip in the margin, and the plus that starts a block under it. The same
            // drawing as the find window's grip on purpose: both of them say the same thing
            // about the thing they sit on, which is that it can be taken hold of.
            'drag_handle' => 'arte-grip-vertical',
            'drag_handle_insert' => 'heroicon-o-plus',
            'help' => 'heroicon-o-question-mark-circle',
            'emoji' => 'heroicon-o-face-smile',
            // The emoji picker's tabs. Drawn icons, not emoji: nine coloured faces in a row
            // read as things to pick rather than as the chrome around them.
            'emoji_recent' => 'heroicon-o-clock',
            'emoji_smileys' => 'heroicon-o-face-smile',
            'emoji_nature' => 'heroicon-o-bug-ant',
            'emoji_food' => 'heroicon-o-cake',
            'emoji_activities' => 'heroicon-o-trophy',
            'emoji_travel' => 'heroicon-o-globe-americas',
            'emoji_objects' => 'heroicon-o-light-bulb',
            'emoji_symbols' => 'heroicon-o-hashtag',
            'emoji_flags' => 'heroicon-o-flag',
            'emoji_close' => 'heroicon-o-x-mark',
            // A pilcrow with the direction the text runs in. The alignment icons say where
            // a line sits in its box, which is a different question and already has a
            // dropdown of its own.
            'direction_ltr' => 'arte-pilcrow-right',
            'direction_rtl' => 'arte-pilcrow-left',
            'fullscreen_enter' => 'heroicon-o-arrows-pointing-out',
            'fullscreen_exit' => 'heroicon-o-arrows-pointing-in',

            // Image toolbar. The two rotations are Lucide's as well - Heroicons has no
            // rotation icon, and its arrows read as undo and redo.
            'image_rotate_left' => 'arte-rotate-ccw',
            'image_rotate_right' => 'arte-rotate-cw',
            'image_alt' => 'heroicon-o-chat-bubble-bottom-center-text',
            'image_size' => 'heroicon-o-arrows-pointing-out',
            'image_download' => 'heroicon-o-arrow-down-tray',
            'image_delete' => 'heroicon-o-trash',
            'image_locked' => 'heroicon-o-lock-closed',
            'image_unlocked' => 'heroicon-o-lock-open',
        ];
    }

    public static function get(string $key): string|BackedEnum
    {
        $name = config('filament-advanced-rich-editor.icons.'.$key);

        if (blank($name)) {
            $name = static::defaults()[$key] ?? throw new InvalidArgumentException(
                "There is no icon named [{$key}] in the advanced rich editor.",
            );
        }

        // A bare Heroicon name becomes the enum, so Filament picks the variant that matches
        // the size it is drawn at. Everything else is a name from some other set.
        return Heroicon::tryFrom((string) $name) ?? (string) $name;
    }
}
