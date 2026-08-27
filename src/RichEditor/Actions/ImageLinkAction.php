<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Kisame76\FilamentAdvancedRichEditor\Forms\Components\AdvancedRichEditor;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\TipTapExtensions\ImageLink;

/**
 * Where a picture points.
 *
 * Deliberately smaller than the link dialog for text next door. That one carries `rel`,
 * `hreflang` and a referrer policy because an editorial link needs them; a picture that is
 * also a link is nearly always a teaser pointing further into the same site, and a dialog
 * asking six questions for it would be a dialog nobody finishes.
 *
 * Emptying the address is how a link is taken off - the same field, the same button, no
 * second control for undoing something. What that writes is null rather than an empty
 * string: the schema drops a blank value on the way out, and a leftover empty `data-href`
 * would sit in the document meaning nothing.
 */
class ImageLinkAction
{
    public static function make(): Action
    {
        return Action::make('imageLink')
            ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_link.label'))
            ->modalHeading(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_link.heading'))
            ->modalWidth(Width::Large)
            ->fillForm(fn (array $arguments): array => [
                'href' => $arguments['href'] ?? null,
                'newTab' => (bool) ($arguments['newTab'] ?? false),
            ])
            ->schema([
                TextInput::make('href')
                    ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_link.href'))
                    ->helperText(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_link.href_help'))
                    // Not `->url()`: that rule refuses the relative addresses a link inside a
                    // site is written with, and `/articles/7` is the commonest thing anybody
                    // will type here. What may be written is decided by `ImageLink` on both
                    // sides of the wire instead.
                    ->maxLength(2000)
                    ->autofocus(),

                Checkbox::make('newTab')
                    ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.image_link.new_tab')),
            ])
            ->action(function (array $arguments, array $data, AdvancedRichEditor $component): void {
                $href = ImageLink::normalise($data['href'] ?? null);

                // The same correction the media dialog makes at the same call site: the
                // selection can arrive described as text rather than as a node, and
                // `updateAttributes` then writes nothing while the picture sits there
                // visibly selected. Silent, because a dialog that closes looks like it
                // worked.
                if (($arguments['editorSelection']['type'] ?? null) !== 'node') {
                    $arguments['editorSelection']['type'] = 'node';
                    $arguments['editorSelection']['anchor']--;

                    unset($arguments['editorSelection']['head']);
                }

                $component->runCommands(
                    [
                        EditorCommand::make('updateAttributes', arguments: [
                            'image',
                            [
                                'href' => $href,
                                // No address, no tab setting: leaving it behind would have
                                // the box already ticked the next time somebody links the
                                // same picture, for a reason nothing on screen explains.
                                'hrefNewTab' => $href !== null && (bool) ($data['newTab'] ?? false),
                            ],
                        ]),
                    ],
                    editorSelection: $arguments['editorSelection'],
                );
            });
    }
}
