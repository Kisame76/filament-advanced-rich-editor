<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Width;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Marks\Link;

/**
 * The link dialog, with the attributes a link in a published document actually carries.
 *
 * Filament's own asks for a URL and a checkbox for opening in a new tab. That covers the
 * link in a form and not the one in an article: editorial work needs `rel="nofollow"` on
 * a paid link, `hreflang` where a link leaves the current language, and a referrer policy
 * where it leaves the site.
 *
 * `rel` is a row of checkboxes and `referrerpolicy` a select rather than the free text
 * fields the obvious implementation reaches for. Both are closed vocabularies; a typo in
 * either produces an attribute that is silently inert, which is worse than one that is
 * missing, because the author believes it is doing something.
 */
class LinkAction
{
    /**
     * The `rel` values worth a checkbox. `rel` itself is an open list - `me`, `alternate`,
     * `license` and more are all valid - which is what the free text field beside them is
     * for.
     *
     * @var array<int, string>
     */
    public const REL_OPTIONS = ['nofollow', 'noopener', 'noreferrer', 'sponsored', 'ugc'];

    /**
     * What a link opening in a new tab is given whether or not anyone asked.
     *
     * `target="_blank"` hands the opened page a handle on the window that opened it,
     * which it can navigate somewhere else while the reader is looking at the new tab.
     * Nothing further down the stack prevents this, and nobody ticking "new window" is
     * thinking about it.
     *
     * @var array<int, string>
     */
    public const NEW_TAB_REL = ['noopener', 'noreferrer'];

    public static function make(): Action
    {
        return Action::make('link')
            ->label(__('filament-forms::components.rich_editor.actions.link.label'))
            ->modalHeading(__('filament-forms::components.rich_editor.actions.link.modal.heading'))
            ->modalWidth(Width::Large)
            ->fillForm(fn (array $arguments): array => [
                'href' => $arguments['href'] ?? null,
                'target' => $arguments['target'] ?? '',
                'rel' => array_values(array_intersect(
                    static::REL_OPTIONS,
                    static::tokens($arguments['rel'] ?? null),
                )),
                'relExtra' => implode(' ', array_diff(
                    static::tokens($arguments['rel'] ?? null),
                    static::REL_OPTIONS,
                )),
                'hreflang' => $arguments['hreflang'] ?? null,
                'referrerpolicy' => $arguments['referrerpolicy'] ?? null,
                'id' => $arguments['id'] ?? null,
            ])
            ->schema([
                TextInput::make('href')
                    ->label(__('filament-forms::components.rich_editor.actions.link.modal.form.url.label'))
                    ->inputMode('url')
                    ->columnSpanFull(),
                Grid::make(['md' => 2])->schema([
                    Select::make('target')
                        ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.link.target.label'))
                        ->selectablePlaceholder(false)
                        ->options([
                            '' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.link.target.self'),
                            '_blank' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.link.target.blank'),
                            '_parent' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.link.target.parent'),
                            '_top' => __('filament-advanced-rich-editor::advanced-rich-editor.tools.link.target.top'),
                        ]),
                    Select::make('referrerpolicy')
                        ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.link.referrerpolicy'))
                        ->options(array_combine(Link::REFERRER_POLICIES, Link::REFERRER_POLICIES)),
                    TextInput::make('hreflang')
                        ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.link.hreflang'))
                        ->placeholder('de'),
                    TextInput::make('id')
                        ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.link.id')),
                ]),
                CheckboxList::make('rel')
                    ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.link.rel.label'))
                    ->helperText(__('filament-advanced-rich-editor::advanced-rich-editor.tools.link.rel.new_tab_hint'))
                    ->options(array_combine(static::REL_OPTIONS, static::REL_OPTIONS))
                    ->gridDirection('row')
                    ->columns(3)
                    ->columnSpanFull(),
                TextInput::make('relExtra')
                    ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.link.rel.other'))
                    ->placeholder('me alternate')
                    ->columnSpanFull(),
            ])
            ->action(function (array $arguments, array $data, RichEditor $component): void {
                $isSingleCharacterSelection = ($arguments['editorSelection']['head'] ?? null) === ($arguments['editorSelection']['anchor'] ?? null);

                // Filament extends the mark range for a caret with nothing selected, so
                // editing a link means putting the caret in it rather than selecting it
                // exactly. The same is done here, on both branches.
                $extend = $isSingleCharacterSelection
                    ? [EditorCommand::make('extendMarkRange', arguments: ['link'])]
                    : [];

                if (blank($data['href'] ?? null)) {
                    $component->runCommands(
                        [...$extend, EditorCommand::make('unsetLink')],
                        editorSelection: $arguments['editorSelection'],
                    );

                    return;
                }

                $component->runCommands(
                    [...$extend, EditorCommand::make('setLink', arguments: [static::attributesFrom($data)])],
                    editorSelection: $arguments['editorSelection'],
                );
            });
    }

    /**
     * The attributes the form describes.
     *
     * Kept apart from the action so the rules that matter - what a new tab is given, how
     * the two `rel` fields become one attribute - can be read and tested without mounting
     * a modal.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string|null>
     */
    public static function attributesFrom(array $data): array
    {
        $target = static::filled($data['target'] ?? null);

        $rel = [
            ...(is_array($data['rel'] ?? null) ? $data['rel'] : []),
            ...static::tokens($data['relExtra'] ?? null),
            ...($target === '_blank' ? static::NEW_TAB_REL : []),
        ];

        // Order is the author's, then whatever they typed, then the protection added for
        // them - and each value appears once however often it was given.
        $rel = array_values(array_unique(array_filter($rel)));

        return [
            'href' => static::filled($data['href'] ?? null),
            'target' => $target,
            'rel' => $rel === [] ? null : implode(' ', $rel),
            'hreflang' => static::filled($data['hreflang'] ?? null),
            'referrerpolicy' => Link::normaliseReferrerPolicy($data['referrerpolicy'] ?? null),
            'id' => static::filled($data['id'] ?? null),
        ];
    }

    /**
     * A whitespace separated attribute as its values.
     *
     * @return array<int, string>
     */
    protected static function tokens(mixed $value): array
    {
        if (! is_string($value)) {
            return [];
        }

        return preg_split('/\s+/', trim($value), flags: PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * A blank field means "no attribute" rather than an empty one: both renderers drop
     * falsy attributes, so an empty string could not survive a save anyway.
     */
    protected static function filled(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? trim($value) : null;
    }
}
