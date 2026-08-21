<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\TaskListPlugin;

it('writes the document as markdown', function (): void {
    expect(AdvancedRichContentRenderer::make('<h2>Intro</h2><p>Some <strong>bold</strong> text.</p>')->toMarkdown())
        ->toBe("## Intro\n\nSome **bold** text.");
});

it('keeps the boxes of a task list ticked', function (): void {
    // A task list is a list of decisions, and the decisions are the checkboxes. The
    // converter has never heard of `data-checked`, so left alone it writes three plain
    // bullets and throws away the only part anybody was tracking.
    $content = '<ul data-type="taskList">'
        .'<li data-type="taskItem" data-checked="true">Shipped</li>'
        .'<li data-type="taskItem" data-checked="false">Open</li>'
        .'</ul>';

    expect(AdvancedRichContentRenderer::make($content)->plugins([TaskListPlugin::make()])->toMarkdown())
        ->toBe("- [x] Shipped\n- [ ] Open");
});

it('leaves an ordinary list item alone', function (): void {
    expect(AdvancedRichContentRenderer::make('<ul><li>One</li><li>Two</li></ul>')->toMarkdown())
        ->toBe("- One\n- Two");
});

it('writes links and emphasis the way markdown spells them', function (): void {
    expect(AdvancedRichContentRenderer::make('<p><a href="https://example.com">Docs</a> and <em>stress</em>.</p>')->toMarkdown())
        ->toBe('[Docs](https://example.com) and *stress*.');
});

it('lets the caller overrule the options it defaults to', function (): void {
    expect(AdvancedRichContentRenderer::make('<h2>Intro</h2>')->toMarkdown(['header_style' => 'setext']))
        ->toBe("Intro\n-----");
});

it('has nothing to write for an empty record', function (): void {
    expect(AdvancedRichContentRenderer::make(null)->toMarkdown())->toBe('');
});
