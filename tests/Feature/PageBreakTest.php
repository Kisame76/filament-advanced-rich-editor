<?php

declare(strict_types=1);

use Filament\Support\Facades\FilamentAsset;
use Kisame76\FilamentAdvancedRichEditor\Pdf\RichEditorPdf;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Icons;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Nodes\PageBreak;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\PageBreakPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\SlashMenu;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\StateCasts\RichEditorStateCast;
use Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire\EditorFormComponent;
use Livewire\Livewire;
use Smalot\PdfParser\Parser;

/**
 * The page break: where a printed document starts a new page.
 *
 * How it is drawn and inserted is the editor's - `tests/js/page-break.test.js`, and a
 * running editor. What is tested here is the wiring and every place a stored break has to
 * survive or mean something: the renderer, the Markdown export, a document another editor
 * wrote, and the PDF.
 */
const PAGE_BREAK = '<div class="fi-arte-page-break" data-type="pageBreak" style="break-after: page; page-break-after: always;"></div>';

describe('the field', function (): void {
    it('ships off, because most fields never reach paper', function (): void {
        expect(editor()->hasPageBreak())->toBeFalse()
            ->and(editor()->getTools())->not->toHaveKey('pageBreak')
            ->and(editor()->getPageBreakSettingsForJs())->toBeNull()
            // The name stays in the overflow and the slash menu, and resolves away.
            ->and(config('filament-advanced-rich-editor.more'))->toContain('pageBreak')
            ->and(editor()->getTipTapJsExtensions())->not->toContain(FilamentAsset::getScriptSrc('advanced-rich-editor/page-break', 'kisame76/filament-advanced-rich-editor'));
    });

    it('offers the break in the overflow and the slash menu once switched on', function (): void {
        $field = editor()->pageBreak();

        $items = collect(SlashMenu::for($field)['groups'])->flatMap(fn (array $group): array => $group['items']);
        $entry = $items->firstWhere('name', 'pageBreak');

        expect($field->getTools())->toHaveKey('pageBreak')
            ->and($field->getMoreTools())->toContain('pageBreak')
            ->and($field->getTipTapJsExtensions())->toContain(FilamentAsset::getScriptSrc('advanced-rich-editor/page-break', 'kisame76/filament-advanced-rich-editor'))
            ->and(SlashMenu::GROUPS['insert'])->toContain('pageBreak')
            ->and($entry['label'])->toBe('Page break')
            ->and($entry['aliases'])->toContain('new page');
    });

    it('reads the switch from the config file, and lets a field overrule it', function (): void {
        config()->set('filament-advanced-rich-editor.page_break', true);

        expect(editor()->hasPageBreak())->toBeTrue()
            ->and(editor()->pageBreak(false)->hasPageBreak())->toBeFalse();
    });

    it('inserts through its own command, under its own drawing', function (): void {
        $tool = PageBreakPlugin::make()->getEditorTools()[0];

        expect($tool->getName())->toBe('pageBreak')
            ->and($tool->getJsHandler())->toBe('$getEditor()?.chain().focus().setPageBreak().run()')
            ->and($tool->getIcon())->toBe(Icons::get('page_break'))
            ->and(Icons::get('page_break'))->toBe('arte-page-break')
            ->and(file_exists(__DIR__.'/../../resources/svg/page-break.svg'))->toBeTrue();
    });

    it('hands the editor the line\'s label, in the language of whoever looks at it', function (): void {
        app()->setLocale('de');

        expect(editor()->pageBreak()->getPageBreakSettingsForJs())->toBe(['label' => 'Seitenumbruch']);

        app()->setLocale('en');
        config()->set('filament-advanced-rich-editor.page_break', true);

        $document = new DOMDocument;
        $document->loadHTML('<?xml encoding="utf-8"?>'.Livewire::test(EditorFormComponent::class)->html(), LIBXML_NOERROR | LIBXML_NOWARNING);
        $element = (new DOMXPath($document))->query('//*[@data-arte-page-break]')->item(0);

        assert($element instanceof DOMElement);

        expect($element->getAttribute('x-ref'))->toBe('editor')
            ->and(json_decode($element->getAttribute('data-arte-page-break'), true))->toBe(['label' => 'Page break']);
    });
});

describe('a stored break', function (): void {
    it('renders as an empty block that asks for the break itself, whatever the field says', function (): void {
        $html = '<p>One</p>'.PAGE_BREAK.'<p>Two</p>';

        expect(AdvancedRichContentRenderer::make($html)->toHtml())->toBe($html)
            ->and(AdvancedRichContentRenderer::make($html)->toArray()['content'][1])->toBe(['type' => 'pageBreak'])
            ->and(PageBreak::STYLE)->toBe('break-after: page; page-break-after: always;');
    });

    it('comes through a save from the editor untouched', function (): void {
        $state = ['type' => 'doc', 'content' => [
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'One']]],
            ['type' => 'pageBreak'],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Two']]],
        ]];

        $saved = app(RichEditorStateCast::class, ['richEditor' => editor()->pageBreak()])->set($state);

        expect(json_encode($saved))->toContain('{"type":"pageBreak"}');
    });

    it('reads a break another editor wrote, and leaves an ordinary div alone', function (): void {
        // CKEditor's, with the hidden space it puts inside so the element is never empty -
        // which the PHP parser would otherwise have read as text in the break.
        $html = '<p>One</p><div class="page-break" style="page-break-after:always;"><span style="display:none;">&nbsp;</span></div><p>Two</p>'
            .'<div style="page-break-after: always;"><p>Not empty, so not a break</p></div>';

        $document = AdvancedRichContentRenderer::make($html)->toArray();

        expect($document['content'][1])->toBe(['type' => 'pageBreak'])
            ->and(json_encode($document['content'][3]))->toContain('Not empty, so not a break')
            ->and(AdvancedRichContentRenderer::make($html)->toHtml())->toContain('<p>One</p>'.PAGE_BREAK.'<p>Two</p>');
    });

    it('leaves nothing in Markdown, which has no pages', function (): void {
        expect(AdvancedRichContentRenderer::make('<p>One</p>'.PAGE_BREAK.'<p>Two</p>')->toMarkdown())->toBe("One\n\nTwo");
    });
});

describe('in a PDF', function (): void {
    beforeEach(function (): void {
        requires('dompdf/dompdf');
        requires('smalot/pdfparser');

        config()->set('filament-advanced-rich-editor.pdf.dompdf.font_directory', sys_get_temp_dir().'/arte-pdf-fonts');
    });

    /**
     * The text of each page, whitespace folded.
     *
     * @return array<int, string>
     */
    function brokenPages(string $pdf): array
    {
        return array_map(
            static fn ($page): string => trim((string) preg_replace('/\s+/', ' ', $page->getText())),
            (new Parser)->parseContent($pdf)->getPages(),
        );
    }

    it('starts a new page where the break stands', function (): void {
        expect(brokenPages(RichEditorPdf::make('<p>Alpha</p>'.PAGE_BREAK.'<p>Beta</p>')->toString()))->toBe(['Alpha', 'Beta']);
    });

    it('prints a blank page where two breaks follow each other, as a word processor would', function (): void {
        expect(brokenPages(RichEditorPdf::make('<p>Alpha</p>'.PAGE_BREAK.PAGE_BREAK.'<p>Beta</p>')->toString()))->toBe(['Alpha', '', 'Beta']);
    });

    it('drops a break with nothing after it, which would only print an empty sheet', function (): void {
        expect(brokenPages(RichEditorPdf::make('<p>Alpha</p>'.PAGE_BREAK.PAGE_BREAK)->toString()))->toBe(['Alpha'])
            ->and(brokenPages(RichEditorPdf::combine([
                RichEditorPdf::make('<p>Alpha</p>'.PAGE_BREAK)->title('One'),
                RichEditorPdf::make('<p>Beta</p>')->title('Two'),
            ])->toString()))->toBe(['One Alpha', 'Two Beta']);
    });
});
