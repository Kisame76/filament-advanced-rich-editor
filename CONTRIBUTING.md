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

Vitest reads those same sources as the ES modules they already are, so `npm test` needs no
build step either and never touches `resources/dist`. Behaviour that has to be tested belongs
in a file under `resources/js` rather than in an `x-data` attribute: an attribute cannot be
imported, which is exactly why the media browser moved out of one.

## Reporting a bug

Please include the Filament version, the field configuration that reproduces it, and what
you expected instead. A failing test is the fastest possible bug report.
