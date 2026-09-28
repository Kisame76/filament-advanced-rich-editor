<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Concerns\HasExtraAttributes;

/**
 * The rendered document in a document of its own.
 *
 * A frame rather than a `<div>`, and that is forced rather than chosen. The panel has already
 * loaded this package's whole stylesheet - the one asset registered without `loadedOnRequest()`
 * - and its content rules are deliberately unscoped so that they apply wherever content is
 * rendered, which is the same property that makes them apply here. Anything drawn inside the
 * panel's document therefore inherits the editor's idea of how content looks, by construction,
 * whatever it is labelled. `srcdoc` is the only boundary the browser actually enforces.
 *
 * `srcdoc` rather than a URL because this package registers no HTTP routes and should not start
 * now: a route serving an unsaved document would need the document transported to it, and with
 * it a question about who may ask for one. The markup is already in hand; it can simply be the
 * frame's content.
 *
 * A component of its own rather than a string built in the action, for the reason `StatisticsTable`
 * beside it is one: what goes in here is a project's stylesheet URLs and a project's class name,
 * both of which reach an HTML attribute, so the escaping has to live somewhere that cannot be
 * forgotten on the next edit.
 */
class PreviewFrame extends ViewComponent implements HasEmbeddedView
{
    use HasExtraAttributes;

    /**
     * What the frame is allowed to do, and the reason for each of the three.
     *
     * `allow-scripts` is absent, which is the whole point of the attribute: a preview renders
     * content, never behaviour. The document has already been through `toHtml()` and its
     * sanitiser, so this is the second lock rather than the first.
     *
     * `allow-same-origin` is present, and it is safe precisely because `allow-scripts` is not -
     * the pair together is what lets a frame lift its own sandbox, and nothing can lift anything
     * with no script to lift it. Without it the frame gets an opaque origin, which costs two
     * things a preview cannot afford: a relative `/storage/...` picture no longer resolves, and
     * a web font is offered `Origin: null` and refused by most of the places fonts are served
     * from.
     *
     * The two popup tokens are for the links in the document. Combined with `<base target>`
     * below, a click opens a tab instead of navigating the frame - a preview a single click
     * destroys is one nobody uses twice - and escaping the sandbox is what keeps the opened
     * page a working page rather than a scriptless copy of one.
     */
    public const SANDBOX = 'allow-same-origin allow-popups allow-popups-to-escape-sandbox';

    protected string $document = '';

    /**
     * @var array<int, string>
     */
    protected array $stylesheets = [];

    protected ?string $wrapperClass = null;

    protected string $title = '';

    protected string $language = 'en';

    protected string $evaluationIdentifier = 'previewFrame';

    protected string $viewIdentifier = 'previewFrame';

    public static function make(): static
    {
        $static = app(static::class);
        $static->configure();

        return $static;
    }

    /**
     * The rendered document. Sanitised markup and nothing else - whatever is handed here is
     * written into the frame as it stands, so the only acceptable source is `toHtml()`.
     */
    public function document(string $document): static
    {
        $this->document = $document;

        return $this;
    }

    /**
     * @param  array<int, string>  $stylesheets
     */
    public function stylesheets(array $stylesheets): static
    {
        $this->stylesheets = $stylesheets;

        return $this;
    }

    public function wrapperClass(?string $class): static
    {
        $this->wrapperClass = $class;

        return $this;
    }

    /**
     * What a screen reader calls the frame. A frame with no accessible name is announced as
     * "frame", which tells somebody who cannot see it nothing at all.
     */
    public function title(string $title): static
    {
        $this->title = $title;

        return $this;
    }

    public function language(string $language): static
    {
        $this->language = $language;

        return $this;
    }

    public function toEmbeddedHtml(): string
    {
        $attributes = $this->getExtraAttributeBag()->class(['fi-arte-preview']);

        ob_start(); ?>

        <div <?= $attributes->toHtml() ?>>
            <iframe
                class="fi-arte-preview-frame"
                sandbox="<?= e(static::SANDBOX) ?>"
                title="<?= e($this->title) ?>"
                srcdoc="<?= e($this->page()) ?>"
            ></iframe>
        </div>

        <?php return (string) ob_get_clean();
    }

    /**
     * The whole page the frame holds.
     *
     * A complete document rather than a fragment, because half of what a project names is only
     * meaningful at that level: a stylesheet belongs in a `<head>`, and the class that carries
     * the container width and the dark theme belongs on a `<body>` where it can be an ancestor
     * of everything.
     *
     * `e()` here is doing real work rather than being polite. This string is about to become the
     * value of an attribute, so every quote and every angle bracket in the document has to
     * survive being read twice - once as the attribute, once as the frame's markup - and Laravel
     * double-encodes by default, which is exactly the behaviour that makes a stored `&amp;`
     * arrive as `&amp;` rather than as `&`.
     */
    protected function page(): string
    {
        $body = $this->wrapperClass === null
            ? '<body>'
            : '<body class="'.e($this->wrapperClass).'">';

        $links = implode('', array_map(
            static fn (string $url): string => '<link rel="stylesheet" href="'.e($url).'">',
            $this->stylesheets,
        ));

        return '<!doctype html>'
            .'<html lang="'.e($this->language).'">'
            .'<head>'
            .'<meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            // Every link in the document opens a tab. Without this a click navigates the frame
            // itself, and the preview is gone with no way back to it but closing the dialog.
            .'<base target="_blank">'
            .$links
            .'</head>'
            .$body
            .$this->document
            .'</body></html>';
    }
}
