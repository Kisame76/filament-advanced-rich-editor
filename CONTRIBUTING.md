# Contributing

Thanks for looking. Bug reports, ideas and pull requests are all welcome.

## Getting set up

```bash
composer install
composer test
```

The suite runs against Testbench, so nothing outside the repository is needed.

The JavaScript has a suite of its own, which needs Node:

```bash
npm install
npm test
```

## Before opening a pull request

Four gates, all of which run in CI:

```bash
composer test
composer pint
composer analyse
npm test
```

## Front-end assets

There is no bundler. `resources/css` and `resources/js` are the sources, and Filament
serves the copies under `resources/dist`. Edit the source, then publish it:

```bash
composer build-assets
```

`tests/Feature/PublishedAssetsTest.php` fails when the two ever drift apart, and it also
fails when a `fi-arte-` class is written into markup that the stylesheet has no rule for —
a component that ships without its styles looks broken and no other test can see it.

The stylesheet is two files. `filament-advanced-rich-editor.css` loads on every page of the
panel; `filament-advanced-rich-editor-overlays.css` loads with the editor, and holds only what
an action opens — a picker, a menu, a dialog. A rule for anything PHP renders with the page,
or anything JavaScript draws as the editor starts, belongs in the first:
`tests/Feature/OverlayStylesheetTest.php` fails when one is in the second, and names it.

Vitest reads those same sources as the ES modules they already are, so `npm test` needs no
build step either and never touches `resources/dist`. Behaviour that has to be tested belongs
in a file under `resources/js` rather than in an `x-data` attribute: an attribute cannot be
imported, which is exactly why the media browser moved out of one.

## Translations

English is the reference and German ships beside it. A language is a folder: copy
`resources/lang/en/advanced-rich-editor.php` to `resources/lang/<locale>/` and translate the
values. Nothing has to be registered, the package loads whatever is there.

`tests/Feature/TranslationParityTest.php` holds every folder against English, so `composer test`
tells you what is left. It fails on a line that is missing, on a line English does not have, on
a blank line, and on a placeholder (`:count`, `:name`) that was dropped, invented or renamed. A
placeholder may move within its sentence, and `:Count` is the same one as `:count`.

Two kinds of line are not word-for-word translations:

- `slash.aliases.*` are the words somebody types after `/` to find that entry, comma separated.
  Write what a speaker of the language would type rather than a rendering of the English words.
  The short ones that mean the same in every panel (`ul`, `ol`, `hr`, `img`, `mp4`) stay in.
- `accessibility.weak_link_phrases` lists the link texts that tell a reader nothing in that
  language, the way "click here" does in English. It is a list of what is true of the language,
  so it needs entries but not as many as English has.

The test is the only thing that will tell you. Laravel answers a line a language lacks from the
fallback locale, so a half-finished translation shows English in the middle of it and nothing
complains at runtime.

## Reporting a bug

Please include the Filament version, the field configuration that reproduces it, and what
you expected instead. A failing test is the fastest possible bug report.
