<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;

/**
 * Everything this package can write into a document has to survive a render that was told
 * nothing.
 *
 * This is one bug rather than a list of them, and it was found six times over: an
 * extension arrived only with the plugin that puts its button on the toolbar, so
 * `AdvancedRichContentRenderer::make($article->content)->toHtml()` - the call every
 * front end makes - silently dropped it. A task list came back as an ordinary bullet list
 * with every tick gone, and a size, a typeface, a line height, a highlight and a writing
 * direction were simply not there. Nothing said so; the page just looked plainer than the
 * editor did.
 *
 * A field's own plugins still win where they are handed over - they carry that field's
 * configuration - and this only decides what a render with no plugins at all knows about.
 * The dataset is the guard: a feature added without a line in the renderer fails here.
 */
it('keeps what was written when nobody named a plugin', function (string $stored, string $expected): void {
    expect(AdvancedRichContentRenderer::make($stored)->toHtml())->toContain($expected);
})->with([
    'a task list' => [
        '<ul data-type="taskList" class="fi-arte-task-list"><li data-type="taskItem" data-checked="true"><p>Ding</p></li></ul>',
        'fi-arte-task-list',
    ],
    'a ticked item' => [
        '<ul data-type="taskList" class="fi-arte-task-list"><li data-type="taskItem" data-checked="true"><p>Ding</p></li></ul>',
        'fi-arte-task-item-checked',
    ],
    'a font size' => ['<p><span style="font-size: 24px">Gross</span></p>', 'font-size: 24px'],
    'a typeface' => ['<p><span style="font-family: Georgia">Serif</span></p>', 'font-family: Georgia'],
    'a line height' => ['<p style="line-height: 2">Locker</p>', 'line-height: 2'],
    'a highlight' => ['<p><mark data-color="#fef08a">Markiert</mark></p>', 'background-color: #fef08a'],
    'a writing direction' => ['<p dir="rtl">Rechts</p>', 'dir="rtl"'],
    'a callout' => [
        '<div data-type="callout" class="fi-arte-callout fi-arte-callout-warning"><p>Achtung</p></div>',
        'fi-arte-callout-warning',
    ],
    'an anchor' => ['<h2 id="eigene-id">Titel</h2>', 'id="eigene-id"'],
    'a marked passage' => ['<p><span lang="fr">bonjour</span></p>', 'lang="fr"'],
    'a list marker' => ['<ol type="a" start="3"><li><p>Punkt</p></li></ol>', 'start="3"'],
    'a turned picture' => [
        '<p><img src="/a.png" width="20" height="10" style="transform: rotate(90deg)" /></p>',
        'rotate(90deg)',
    ],
    'a floated picture' => ['<p><img src="/a.png" style="float: left" /></p>', 'float: left'],
    'a picture marked decorative' => ['<p><img src="/a.png" alt="" role="presentation" /></p>', 'role="presentation"'],
    'a linked picture' => ['<p><img src="/a.png" data-href="/somewhere" /></p>', '<a href="/somewhere">'],
    'a caption' => ['<p><img src="/a.png" data-caption="Untertitel" /></p>', '<figcaption>Untertitel</figcaption>'],
    'a video' => [
        '<div data-type="embed" style="aspect-ratio: 16 / 9"><iframe src="https://www.youtube-nocookie.com/embed/dQw4w9WgXcQ"></iframe></div>',
        'youtube-nocookie.com/embed/dQw4w9WgXcQ',
    ],
]);

it('keeps a named style on a block the picker was narrowed away from', function (): void {
    // Narrowing a style says where the picker may offer it, not that the same words stop
    // being a lead the moment they become a heading. The browser half declares the
    // attribute over all five block types; this half used to declare it only over the ones
    // the configured styles named, so styling a paragraph and then turning it into a
    // heading lost the style on save - the editor kept it, the save threw it away.
    withStyles([
        ['key' => 'lead', 'label' => 'Lead', 'class' => 'lead', 'scope' => 'block', 'types' => ['paragraph']],
    ]);

    expect(AdvancedRichContentRenderer::make('<h2 class="lead">Titel</h2>')->toHtml())
        ->toBe('<h2 class="lead">Titel</h2>')
        ->and(AdvancedRichContentRenderer::make('<p class="lead">Text</p>')->toHtml())
        ->toBe('<p class="lead">Text</p>');
});

it('still keeps out a class that is not a style the project declared', function (): void {
    // The widening is about which blocks may carry a style, not about which classes count.
    withStyles([
        ['key' => 'lead', 'label' => 'Lead', 'class' => 'lead', 'scope' => 'block', 'types' => ['paragraph']],
    ]);

    expect(AdvancedRichContentRenderer::make('<h2 class="erfunden">Titel</h2>')->toHtml())
        ->toBe('<h2>Titel</h2>');
});
