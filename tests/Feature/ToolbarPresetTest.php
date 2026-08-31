<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarPresets;

it('ships five presets, from the shortest bar to the longest', function (): void {
    expect(array_keys(ToolbarPresets::all()))->toBe(['minimal', 'comment', 'blog', 'default', 'full']);
});

it('hands back one preset by name', function (): void {
    $comment = ToolbarPresets::get('comment');

    expect($comment['toolbar'])->toBeArray()
        ->and($comment['file_attachments'])->toBeFalse();
});

it('names the presets it knows when asked for one it does not', function (): void {
    expect(fn (): array => ToolbarPresets::get('bloggg'))
        ->toThrow(LogicException::class, 'minimal, comment, blog, default, full');
});

it('draws the bar the preset names', function (): void {
    expect(toolbarShape(editor()->preset('comment')))->toBe([
        ['bold', 'italic'],
        ['divider'],
        ['link', 'dropdown:bulletList,orderedList,taskList', 'blockquote'],
        ['divider'],
        ['emoji'],
    ]);
});

it('empties the overflow and the tools menu with the bar', function (): void {
    // The fallout the roadmap warns about: a preset that only shrinks the main bar leaves
    // both menus behind it at full length, and the corner still opens onto everything.
    $editor = editor()->preset('comment');

    expect($editor->getMoreTools())->toBe([])
        ->and($editor->getToolsMenu())->toBe([]);
});

it('cuts the selection bubble to the same size', function (): void {
    expect(toolbarGroupsShape([editor()->preset('comment')->getTextToolbarButtons()])[0])
        ->toBe(['bold', 'italic', 'link']);
});

it('says for itself whether the field takes attachments', function (): void {
    // Not left to `hasToolbarButton(['attachFiles', 'image'])`, which is how a shrinking
    // bar used to take the upload, the drop and the paste-upload away without saying so.
    expect(editor()->preset('comment')->hasFileAttachments())->toBeFalse()
        ->and(editor()->preset('blog')->hasFileAttachments())->toBeTrue();
});

it('still lets a bar without a picture button answer for itself', function (): void {
    // Nothing about presets changes what an unpreset field does.
    expect(editor()->toolbarButtons([['bold']])->hasFileAttachments())->toBeFalse()
        ->and(editor()->toolbarButtons([['bold', 'image']])->hasFileAttachments())->toBeTrue();
});

it('loses to what the field says itself', function (): void {
    $editor = editor()
        ->preset('comment')
        ->toolbarButtons([['bold', 'italic', 'underline']])
        ->moreTools(['code'])
        ->toolsMenu(['help'])
        ->textToolbarButtons(['bold'])
        ->fileAttachments(true);

    expect(toolbarShape($editor))->toBe([['bold', 'italic', 'underline']])
        ->and($editor->getMoreTools())->toBe(['code'])
        ->and($editor->getToolsMenu())->toBe(['help'])
        ->and(toolbarGroupsShape([$editor->getTextToolbarButtons()])[0])->toBe(['bold'])
        ->and($editor->hasFileAttachments())->toBeTrue();
});

it('beats the configured bar', function (): void {
    config()->set('filament-advanced-rich-editor.toolbar', [['undo']]);
    config()->set('filament-advanced-rich-editor.more', ['code']);

    expect(toolbarShape(editor()->preset('minimal')))->toBe([
        ['bold', 'italic', 'underline'],
        ['divider'],
        ['link', 'dropdown:bulletList,orderedList,taskList'],
    ])->and(editor()->preset('minimal')->getMoreTools())->toBe([]);
});

it('is a fixed list rather than a copy of the configuration', function (): void {
    // The reason presets exist: a starting point that moves with the project is not one.
    config()->set('filament-advanced-rich-editor.toolbar', [['undo']]);

    expect(toolbarShape(editor()->preset('default'))[0])->toBe(['undo', 'redo']);
});

it('leaves the bars a preset says nothing about to the configuration', function (): void {
    config()->set('filament-advanced-rich-editor.toolbar_presets', [
        'bar-only' => ['toolbar' => [['bold']]],
    ]);
    config()->set('filament-advanced-rich-editor.more', ['code']);

    $editor = editor()->preset('bar-only');

    expect(toolbarShape($editor))->toBe([['bold']])
        ->and($editor->getMoreTools())->toBe(['code']);
});

it('lets a project replace a shipped preset under its own name', function (): void {
    config()->set('filament-advanced-rich-editor.toolbar_presets', [
        'comment' => ['toolbar' => [['bold']]],
    ]);

    expect(toolbarShape(editor()->preset('comment')))->toBe([['bold']]);
});

it('resolves a preset named by a closure', function (): void {
    expect(toolbarShape(editor()->preset(fn (): string => 'minimal'))[0])
        ->toBe(['bold', 'italic', 'underline']);
});

it('offers every shipped preset a bar that resolves', function (): void {
    // A token that no longer exists, or a button switched off by default, would raise here
    // rather than in the browser of whoever asked for that preset.
    foreach (array_keys(ToolbarPresets::all()) as $name) {
        expect(toolbarShape(editor()->preset($name)))->not->toBeEmpty($name);
    }
});

it('places in full the four tools no shipped bar carries', function (): void {
    // The documentation names these as registered but deliberately unplaced. `full` is
    // where they are placed, because that is the whole of what `full` means.
    $editor = editor()->preset('full');

    $bar = array_merge(...toolbarShape($editor));

    expect($bar)->toContain('fontFamily')
        ->and($bar)->toContain(toolbarDropdownName($editor, 'textCaseUpper'))
        ->and($bar)->toContain(languagesShape($editor))
        ->and($editor->getMoreTools())->toContain('strike');
});

it('carries in full everything default carries', function (): void {
    // `full` is default plus, never default rearranged: a preset that quietly dropped a
    // tool while calling itself full would be the worst of the five. Asked of the named
    // tools rather than of the resolved bar, because the overflow dropdown is named after
    // its own contents and full's are longer by exactly the tool under test.
    $names = static fn (string $preset): array => array_merge(
        ...array_map(
            static fn (mixed $group): array => is_array($group) ? $group : [$group],
            ToolbarPresets::get($preset)['toolbar'],
        ),
    );

    expect($names('full'))->toContain(...$names('default'))
        ->and(ToolbarPresets::get('full')['more'])
        ->toEqualCanonicalizing([...ToolbarPresets::get('default')['more'], 'strike']);
});

it('keeps the default preset and the shipped config file saying the same thing', function (): void {
    // Both are the shipped bar written down, and a published config file is what actually
    // answers on a real installation - so an edit to one and not the other would make
    // `->preset('default')` mean something the field never draws without it.
    $default = ToolbarPresets::shipped()['default'];

    expect($default['toolbar'])->toBe(config('filament-advanced-rich-editor.toolbar'))
        ->and($default['more'])->toBe(config('filament-advanced-rich-editor.more'))
        ->and($default['tools_menu'])->toBe(config('filament-advanced-rich-editor.tools_menu'))
        ->and($default['text_toolbar_buttons'])->toBe(config('filament-advanced-rich-editor.text_toolbar_buttons'));
});

it('drops from a preset the button a switched-off feature took away', function (): void {
    // `comment` names the emoji picker on the bar. Switching the picker off unregisters the
    // tool, and a bar naming a tool that does not exist raises while the view renders - so
    // the name has to go with the tool rather than stay behind as a dead entry.
    config()->set('filament-advanced-rich-editor.emoji', false);

    $editor = editor()->preset('comment');

    expect(array_merge(...toolbarShape($editor)))->not->toContain('emoji');
});

it('names on its bars only what the field can actually resolve', function (): void {
    // The general form of the case above, over every preset and every switch this package
    // has. A name that survives its feature being switched off is a 500 on the page rather
    // than a missing button, so the guard is over all of them rather than over the one that
    // was found by hand.
    foreach ([
        'emoji' => 'emoji',
        'find' => 'find',
        'accessibility' => 'accessibility',
        'source_code' => 'sourceCode',
        'fullscreen' => 'fullscreen',
        'characters' => 'characters',
        'text_case' => 'textCase',
        'task_list' => 'taskList',
        'text_direction' => 'textDirection',
        'list_properties' => 'listProperties',
    ] as $key => $label) {
        config()->set('filament-advanced-rich-editor.'.$key, false);
    }

    config()->set('filament-advanced-rich-editor.callouts.variants', []);
    config()->set('filament-advanced-rich-editor.languages.enabled', false);
    config()->set('filament-advanced-rich-editor.fonts.enabled', false);
    config()->set('filament-advanced-rich-editor.embed.enabled', false);

    $dead = [];

    foreach (array_keys(ToolbarPresets::shipped()) as $preset) {
        $editor = editor()->preset($preset)->embeds(false)->fontPicker(false);

        $tools = $editor->getTools();

        // The resolved items rather than `toolbarShape()`, which names a divider and a
        // colour picker after what they are: only an item that is still a bare string is
        // one the view has to look up in `getTools()`, and only that one can be dead.
        foreach (array_merge(...$editor->getToolbarButtons()) as $item) {
            if ((! is_string($item)) || array_key_exists($item, $tools)) {
                continue;
            }

            $dead[] = "{$preset}: {$item}";
        }
    }

    expect($dead)->toBe([], 'These names survived their tool being switched off: '.implode(', ', $dead));
});

it('lets a replaced bar answer the attachment question again', function (): void {
    // A preset's attachment answer belongs to the bar the preset draws. Once the field has
    // replaced that bar outright, the preset is no longer describing what is on screen, and
    // the picture button is the honest answer again - the same one an unpreset field gives.
    expect(editor()->preset('blog')->toolbarButtons([['bold']])->hasFileAttachments())->toBeFalse()
        ->and(editor()->preset('blog')->toolbarButtons([['bold', 'image']])->hasFileAttachments())->toBeTrue()
        // Untouched, the preset still answers - including where it says no on a bar that
        // carries no picture button anyway.
        ->and(editor()->preset('blog')->hasFileAttachments())->toBeTrue()
        ->and(editor()->preset('comment')->hasFileAttachments())->toBeFalse();
});

it('refuses a preset name that is empty rather than ignoring it', function (): void {
    // `->preset(null)` is "no preset". An empty string is a name that resolved to nothing -
    // a missing config key, a closure that found no record - and silently drawing the
    // configured bar hides that.
    expect(fn (): array => editor()->preset('')->getPresetLayout())
        ->toThrow(LogicException::class)
        ->and(editor()->preset(null)->getPresetLayout())->toBe([]);
});

it('refuses a registered preset that is not shaped like one', function (): void {
    config()->set('filament-advanced-rich-editor.toolbar_presets', ['house' => 'minimal']);

    expect(fn (): array => ToolbarPresets::all())
        ->toThrow(LogicException::class, 'house');
});

it('refuses a preset key the readers would never look at', function (): void {
    // A typo in a key is silent otherwise: the preset is found, and the bar it meant to
    // describe falls through to the config file as though the preset had said nothing.
    config()->set('filament-advanced-rich-editor.toolbar_presets', [
        'house' => ['toolbar' => [['bold']], 'more_tools' => []],
    ]);

    expect(fn (): array => ToolbarPresets::all())
        ->toThrow(LogicException::class, 'more_tools');
});
