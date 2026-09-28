<?php

declare(strict_types=1);

use Filament\Forms\Components\RichEditor\MentionProvider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Actions\PreviewAction;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\PreviewFrame;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\ToolbarLayout;

/**
 * The document as the site draws it, rather than as the editor does.
 *
 * Two things carry the whole feature and both are easy to get almost right. The frame is
 * isolated, because the panel has already loaded this package's stylesheet and its content
 * rules are deliberately unscoped - anything drawn inside that document is the editor's idea
 * of how content looks, whatever it is labelled. And the CSS is the project's, because a front
 * end's stylesheet is not this package's to invent; so the tool is a seam that ships closed.
 */
$sheets = ['https://example.test/site.css'];

it('is not offered where nothing was named to draw the document with', function () use ($sheets): void {
    // The centre of the design. A frame with no stylesheet shows unstyled markup, and a
    // button labelled "preview" opening onto that lies about what it did - so a field nobody
    // gave a stylesheet gets no button, the same answer the media browser gives a field with
    // nothing browsable and the styles trigger gives a field with no styles.
    expect(editor()->hasPreviewFrontEnd())->toBeFalse()
        ->and(editor()->getTools())->not->toHaveKey('preview')
        ->and(editor()->previewStylesheets($sheets)->hasPreviewFrontEnd())->toBeTrue()
        ->and(editor()->previewStylesheets($sheets)->getTools())->toHaveKey('preview');
});

it('keeps its place in the tools menu whether or not it is drawn', function (): void {
    // The name stays in the list and the token resolves it away, which is what lets a project
    // switch the preview on with a stylesheet and nothing else. A raw name in a group with no
    // token behind it raises while the view renders and costs the whole field.
    expect(editor()->getToolsMenu())->toContain('preview')
        ->and(array_keys(ToolbarLayout::tokens()))->toContain('preview');
});

it('takes the tool away with the switch, stylesheet or no stylesheet', function () use ($sheets): void {
    expect(editor()->previewStylesheets($sheets)->preview(false)->hasPreviewFrontEnd())->toBeFalse()
        ->and(editor()->previewStylesheets($sheets)->preview(false)->getTools())->not->toHaveKey('preview');
});

it('reads both halves from the config file, and lets a field overrule either', function () use ($sheets): void {
    config()->set('filament-advanced-rich-editor.preview.stylesheets', $sheets);

    expect(editor()->hasPreviewFrontEnd())->toBeTrue()
        ->and(editor()->getPreviewStylesheets())->toBe($sheets)
        ->and(editor()->previewStylesheets([])->hasPreviewFrontEnd())->toBeFalse();

    config()->set('filament-advanced-rich-editor.preview.enabled', false);

    expect(editor()->hasPreview())->toBeFalse()
        ->and(editor()->preview()->hasPreview())->toBeTrue();
});

it('reads the wrapper class from the config file, and lets a field overrule it', function (): void {
    config()->set('filament-advanced-rich-editor.preview.wrapper_class', 'prose');

    expect(editor()->getPreviewWrapperClass())->toBe('prose')
        ->and(editor()->previewWrapperClass('article')->getPreviewWrapperClass())->toBe('article');
});

it('drops a blank stylesheet rather than linking to nothing', function (): void {
    // An empty href is resolved against the frame's own address and fetched. A preview has no
    // business making a request nobody asked for.
    expect(editor()->previewStylesheets(['  ', '', 'https://example.test/a.css '])->getPreviewStylesheets())
        ->toBe(['https://example.test/a.css'])
        ->and(editor()->previewStylesheets(['   '])->hasPreviewFrontEnd())->toBeFalse();
});

/**
 * What ends up inside the frame.
 */
it('draws the document the browser sent, not the state the server holds', function () use ($sheets): void {
    // The crux, and the source view's channel for the same reason: the last keystrokes may not
    // have been synced yet, and they are usually the ones somebody opened a preview to look at.
    $page = previewFramePage(editor()->previewStylesheets($sheets), '<p>gerade getippt</p>');

    expect($page)->toContain('gerade getippt');
});

it('falls back to the stored document where the browser sent none', function () use ($sheets): void {
    // The argument is an expression evaluated in the browser, so it can simply fail to
    // arrive - an editor not yet ready, a stale bundle after an upgrade where the assets
    // were not republished, the dialog opened from anywhere but that button. Without a
    // fallback the frame opens blank under the word "preview", which an author reads as
    // "this document is empty" rather than as "the document did not reach me".
    $editor = editor()->previewStylesheets($sheets);
    $editor->getContainer()->components([$editor]);
    $editor->state('<p>was gespeichert ist</p>');

    expect(previewFramePage($editor, null))->toContain('was gespeichert ist')
        // And blank counts as absent: an empty string is what a failed expression leaves.
        ->and(previewFramePage($editor, ''))->toContain('was gespeichert ist');
});

it('prefers the browser copy over the stored one, because that is the point', function () use ($sheets): void {
    $editor = editor()->previewStylesheets($sheets);
    $editor->getContainer()->components([$editor]);
    $editor->state('<p>was gespeichert ist</p>');

    expect(previewFramePage($editor, '<p>was gerade getippt wurde</p>'))
        ->toContain('was gerade getippt wurde')
        ->not->toContain('was gespeichert ist');
});

it('renders through the same call the front end renders through', function () use ($sheets): void {
    // `toHtml()` and its sanitiser, which is the only sanitised exit and the one
    // `<x-arte-content>`, the entry and the column all take. A second render path here would
    // give an author a preview that agrees with nothing - and `toUnsafeHtml()` into the panel
    // would be a live XSS surface.
    $page = previewFramePage(editor()->previewStylesheets($sheets), '<p>hallo<script>alert(1)</script></p>');

    expect($page)->toContain('hallo')
        ->and($page)->not->toContain('alert(1)');
});

it('links every stylesheet the field was given, in the head', function (): void {
    $page = previewFramePage(
        editor()->previewStylesheets(['https://example.test/a.css', '/build/site.css']),
        '<p>x</p>',
    );

    expect($page)->toContain('<link rel="stylesheet" href="https://example.test/a.css">')
        ->and($page)->toContain('<link rel="stylesheet" href="/build/site.css">')
        // In the head, because that is the only place a stylesheet means anything.
        ->and(strpos($page, 'site.css'))->toBeLessThan((int) strpos($page, '<body'));
});

it('puts the wrapper class on the body, where a dark theme can reach the whole page', function () use ($sheets): void {
    // On the body rather than on an inner wrapper, and that is the reason the key exists: a
    // `dark` class has to be an ancestor of the content to do anything, and so does the
    // container that sets the measure.
    $page = previewFramePage(
        editor()->previewStylesheets($sheets)->previewWrapperClass('prose dark:prose-invert mx-auto'),
        '<p>x</p>',
    );

    expect($page)->toContain('<body class="prose dark:prose-invert mx-auto">');
});

it('leaves the body bare where no class was named', function () use ($sheets): void {
    expect(previewFramePage(editor()->previewStylesheets($sheets), '<p>x</p>'))->toContain('<body>');
});

it('never lets the frame run a script', function () use ($sheets): void {
    // A preview renders content, never behaviour. `allow-same-origin` is present and safe
    // exactly because `allow-scripts` is not - the pair together is what lets a frame lift its
    // own sandbox, and nothing lifts anything with no script to lift it.
    $html = previewFrameHtml(editor()->previewStylesheets($sheets), '<p>x</p>');

    expect($html)->toContain('sandbox="')
        ->and(PreviewFrame::SANDBOX)->not->toContain('allow-scripts')
        ->and(PreviewFrame::SANDBOX)->toContain('allow-same-origin');
});

it('opens a link in a tab rather than navigating the frame away', function () use ($sheets): void {
    // A preview a single click destroys is one nobody uses twice.
    expect(previewFramePage(editor()->previewStylesheets($sheets), '<p>x</p>'))
        ->toContain('<base target="_blank">');
});

it('escapes the document so it survives being an attribute', function () use ($sheets): void {
    // The page travels in `srcdoc`, so every quote and bracket is read twice - once as the
    // attribute, once as the frame's markup. Laravel double-encodes by default, which is what
    // makes a stored `&amp;` arrive as `&amp;` rather than as a bare ampersand.
    $html = previewFrameHtml(editor()->previewStylesheets($sheets), '<p>Fish &amp; chips</p>');

    expect($html)->not->toContain('<p>Fish')
        ->and(previewFramePage(editor()->previewStylesheets($sheets), '<p>Fish &amp; chips</p>'))
        ->toContain('Fish &amp; chips');
});

it('says which language the page is in, from the application rather than the browser', function () use ($sheets): void {
    app()->setLocale('de');

    expect(previewFramePage(editor()->previewStylesheets($sheets), '<p>x</p>'))->toContain('<html lang="de">');
});

/**
 * The seam underneath it.
 */
it('hands the preview a renderer that knows everything the field knows', function () use ($sheets): void {
    // Until this existed the field could not build a complete renderer: it assembled four
    // setters inline for parsing a save and threw the renderer away. Anything built on those
    // four renders an uploaded picture as a broken image and a mention with whatever name was
    // stored the day it was typed - quietly, because both are valid markup.
    //
    // Asked through the dialog rather than of the method, and that is the point of the test.
    // A `getRichContentRenderer()` that is correct and an action that does not call it look
    // identical from the method's side; this went green through a deliberate break until it
    // was asked the way a reader asks.
    $stale = '<p>Ping <span data-type="mention" data-id="2" data-label="Ada L." data-char="@"></span></p>';

    $editor = editor()
        ->previewStylesheets($sheets)
        ->mentions([MentionProvider::make('@')->items(['2' => 'Ada Lovelace'])]);

    expect(previewFramePage($editor, $stale))->toContain('Ada Lovelace')
        // The stored name is the one a renderer without the providers would have shown.
        ->not->toContain('Ada L.');
});

it('renders with what the page was told, not only with what the field knows', function () use ($sheets): void {
    // Found by comparing the frame against the lab's own rendered page rather than by
    // reasoning: they differed at the third character. A field knows its plugins, its
    // mentions and its disk; it knows nothing about the decisions a page makes on top of
    // them - anchors, a table of contents, syntax colours are all named where the document
    // is rendered, which is somewhere the field has never seen. Without this hook a preview
    // is truthful about the field and wrong about the page, which is the one thing it may
    // not be.
    $editor = editor()
        ->previewStylesheets($sheets)
        ->configureRenderer(fn (AdvancedRichContentRenderer $renderer) => $renderer->anchorHeadings());

    expect(previewFramePage($editor, '<h2>Eine Überschrift</h2>'))->toContain('id="eine-uberschrift"')
        // And a field that was told nothing still renders what it knows, unchanged - which
        // is exactly the difference the comparison found.
        ->and(previewFramePage(editor()->previewStylesheets($sheets), '<h2>Eine Überschrift</h2>'))
        ->toContain('<h2>Eine Überschrift</h2>');
});

it('parses a save through the same assembly it renders with', function (): void {
    // One place where a field says how its content renders. Two would drift, and the direction
    // they would drift in is the one nothing reports: markup that renders but does not save.
    expect(editor()->getTipTapEditor()->setContent('<p><strong>fett</strong></p>')->getHtml())
        ->toContain('<strong>fett</strong>');
});

it('is a dialog to read rather than one to answer', function (): void {
    // The statistics dialog's shape, for the statistics dialog's reason: a footer holding one
    // button called Close is furniture beside the cross that closes it already.
    $action = PreviewAction::make();

    expect($action->getModalSubmitAction())->toBeNull()
        ->and($action->getModalCancelAction())->toBeNull()
        ->and($action->hasModalCloseButton())->toBeTrue()
        ->and($action->isModalClosedByClickingAway())->toBeTrue()
        // The way out that does not need a mouse.
        ->and($action->isModalClosedByEscaping())->toBeTrue();
});
