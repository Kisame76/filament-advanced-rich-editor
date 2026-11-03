<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions;

use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Links\LinkPreviews;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\LinkPreview;

/**
 * The dialog behind a link card: paste an address, and the page fills in the rest.
 *
 * The page is read as the address field is left, and what it says lands in the fields below it,
 * where it can be corrected before anything is inserted - a page's own title is often the site's
 * name twice and a slogan. A page that cannot be read leaves the fields as they are and says so;
 * the card is then whatever somebody types.
 */
class LinkPreviewAction
{
    public static function make(): Action
    {
        $key = 'filament-advanced-rich-editor::advanced-rich-editor.tools.link_preview.';

        return Action::make('linkPreview')
            ->label(__($key.'label'))
            ->modalHeading(__($key.'heading'))
            ->modalWidth(Width::Large)
            ->fillForm(fn (array $arguments): array => [
                'href' => $arguments['href'] ?? null,
                'title' => $arguments['title'] ?? null,
                'description' => $arguments['description'] ?? null,
                'site' => $arguments['site'] ?? null,
                'icon' => $arguments['icon'] ?? null,
            ])
            ->schema([
                TextInput::make('href')
                    ->label(__($key.'url'))
                    ->helperText(__($key.'url_hint'))
                    ->inputMode('url')
                    ->required()
                    ->live(onBlur: true)
                    ->rule(static fn (): Closure => static function (string $attribute, mixed $value, Closure $fail) use ($key): void {
                        if (LinkPreview::href($value) === null) {
                            $fail(__($key.'not_a_page'));
                        }
                    })
                    ->afterStateUpdated(static fn (mixed $state, Set $set) => static::fill($state, $set))
                    // The answer is kept beside the form rather than worked out by the hint,
                    // which is drawn on every round trip - see `LinkAction`.
                    ->hint(static fn (Get $get): ?string => $get('hrefProblem'))
                    ->hintIcon(static fn (Get $get): ?Heroicon => filled($get('hrefProblem')) ? Heroicon::ExclamationTriangle : null)
                    ->hintColor('warning'),
                TextInput::make('title')
                    ->label(__($key.'title'))
                    ->maxLength(LinkPreview::TITLE_LENGTH),
                Textarea::make('description')
                    ->label(__($key.'description'))
                    ->rows(2)
                    ->maxLength(LinkPreview::DESCRIPTION_LENGTH),
                TextInput::make('site')
                    ->label(__($key.'site'))
                    ->maxLength(LinkPreview::SITE_LENGTH),
                // Carried, not shown: a picture in a text field is a wall of base64.
                Hidden::make('icon'),
            ])
            ->action(function (array $arguments, array $data, RichEditor $component): void {
                $attributes = static::attributesFrom($data);

                if ($attributes === null) {
                    return;
                }

                $component->runCommands(
                    [EditorCommand::make('setLinkPreview', arguments: [$attributes])],
                    editorSelection: $arguments['editorSelection'],
                );
            });
    }

    /**
     * What the page says, written into the form - or, where it could not be read, a word
     * saying so and the fields left for somebody to fill in.
     */
    public static function fill(mixed $href, Set $set): void
    {
        $preview = is_string($href) ? LinkPreviews::make()->read($href) : null;

        // The icon was the old page's, whatever happens next.
        $set('icon', $preview['icon'] ?? null);

        if ($preview === null) {
            $set('hrefProblem', (LinkPreview::href($href) === null)
                ? null
                : __('filament-advanced-rich-editor::advanced-rich-editor.tools.link_preview.unreadable'));

            return;
        }

        $set('hrefProblem', null);
        $set('title', $preview['title']);
        $set('description', $preview['description']);
        $set('site', $preview['site']);
    }

    /**
     * The card's attributes out of a filled-in form, or null where the address is not a page.
     *
     * @param  array<string, mixed>  $data
     * @return array{href: string, title: string|null, description: string|null, site: string|null, icon: string|null}|null
     */
    public static function attributesFrom(array $data): ?array
    {
        $href = LinkPreview::href($data['href'] ?? null);

        if ($href === null) {
            return null;
        }

        return [
            'href' => $href,
            'title' => LinkPreview::text($data['title'] ?? null, LinkPreview::TITLE_LENGTH),
            'description' => LinkPreview::text($data['description'] ?? null, LinkPreview::DESCRIPTION_LENGTH),
            'site' => LinkPreview::text($data['site'] ?? null, LinkPreview::SITE_LENGTH),
            'icon' => LinkPreview::icon($data['icon'] ?? null),
        ];
    }
}
