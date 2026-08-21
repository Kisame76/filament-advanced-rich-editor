<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions;

use Filament\Actions\Action;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\RichEditor\EditorCommand;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Support\Enums\Width;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\EmbedUrl;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\Embed;

/**
 * The dialog behind the embed button: paste a link, get a video.
 *
 * One field does the work. The link is taken apart here rather than being handed to an
 * iframe as it was found - a watch URL cannot be framed at all, and the timestamp somebody
 * shared "from 1:30" is in it and would otherwise be thrown away. A link this package
 * cannot place is refused while the dialog is open, which is the only moment anyone can
 * still do something about it.
 */
class EmbedAction
{
    /**
     * The shapes offered. Anything else can be typed, since the value is a CSS ratio.
     *
     * @var array<string, string>
     */
    public const RATIOS = [
        '16 / 9' => '16:9',
        '4 / 3' => '4:3',
        '1 / 1' => '1:1',
        '21 / 9' => '21:9',
        '9 / 16' => '9:16',
    ];

    public static function make(): Action
    {
        return Action::make('embed')
            ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.embed.label'))
            ->modalHeading(__('filament-advanced-rich-editor::advanced-rich-editor.tools.embed.heading'))
            ->modalWidth(Width::Large)
            ->fillForm(fn (array $arguments): array => [
                // Reopening an embed shows the link it stands for rather than an empty
                // field, so a timestamp can be corrected without rebuilding the block.
                'url' => isset($arguments['provider'], $arguments['id'])
                    ? EmbedUrl::src($arguments['provider'], $arguments['id'], $arguments['start'] ?? null)
                    : null,
                'title' => $arguments['title'] ?? null,
                'ratio' => $arguments['ratio'] ?? Embed::DEFAULT_RATIO,
            ])
            ->schema([
                TextInput::make('url')
                    ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.embed.url'))
                    ->helperText(__('filament-advanced-rich-editor::advanced-rich-editor.tools.embed.url_hint'))
                    ->required()
                    ->live(onBlur: true)
                    // Refused here rather than silently rendered as nothing later. The
                    // dialog is the last moment anyone can still paste a different link.
                    ->rule(static fn (): \Closure => static function (string $attribute, mixed $value, \Closure $fail): void {
                        if (EmbedUrl::parse(is_string($value) ? $value : null) === null) {
                            $fail(__('filament-advanced-rich-editor::advanced-rich-editor.tools.embed.unsupported'));
                        }
                    }),
                TextInput::make('title')
                    ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.embed.title'))
                    ->helperText(__('filament-advanced-rich-editor::advanced-rich-editor.tools.embed.title_hint')),
                Select::make('ratio')
                    ->label(__('filament-advanced-rich-editor::advanced-rich-editor.tools.embed.ratio'))
                    ->options(static::RATIOS)
                    ->selectablePlaceholder(false)
                    ->default(Embed::DEFAULT_RATIO),
            ])
            ->action(function (array $arguments, array $data, RichEditor $component): void {
                $embed = EmbedUrl::parse($data['url'] ?? null);

                if ($embed === null) {
                    return;
                }

                $component->runCommands(
                    [
                        EditorCommand::make('setEmbed', arguments: [[
                            ...$embed,
                            'title' => filled($data['title'] ?? null) ? trim((string) $data['title']) : null,
                            'ratio' => $data['ratio'] ?? Embed::DEFAULT_RATIO,
                        ]]),
                    ],
                    editorSelection: $arguments['editorSelection'],
                );
            });
    }
}
