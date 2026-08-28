<?php

declare(strict_types=1);

/**
 * Every script a plugin asks for has to be registered, and everything registered has to be
 * asked for by somebody.
 *
 * This is the third guard of the same family as `RenderCompletenessTest` and
 * `PublishedAssetsTest`, and it exists for the same reason: a plugin that names an
 * unregistered script does not fail here, it fails in the browser of whoever installed the
 * package. `FilamentAsset::getScriptSrc()` falls back to a path that was never published,
 * so the extension simply never loads and the feature is silently absent - green suite,
 * dead button.
 */

/**
 * The script keys the package asks Filament for, from anywhere in `src`.
 *
 * @return array<int, string>
 */
function requestedScriptKeys(): array
{
    $keys = [];

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 2).'/src'));

    foreach ($iterator as $file) {
        if (! $file->isFile() || $file->getExtension() !== 'php') {
            continue;
        }

        preg_match_all(
            "/getScriptSrc\(\s*'advanced-rich-editor\/([a-z0-9-]+)'/",
            (string) file_get_contents($file->getPathname()),
            $matches,
        );

        foreach ($matches[1] as $key) {
            $keys[$key] = true;
        }
    }

    ksort($keys);

    return array_keys($keys);
}

/**
 * The script keys the service provider registers.
 *
 * @return array<int, string>
 */
function registeredScriptKeys(): array
{
    preg_match_all(
        "/Js::make\(\s*'advanced-rich-editor\/([a-z0-9-]+)'/",
        (string) file_get_contents(dirname(__DIR__, 2).'/src/FilamentAdvancedRichEditorServiceProvider.php'),
        $matches,
    );

    $keys = array_unique($matches[1]);

    sort($keys);

    return array_values($keys);
}

/**
 * The modules a sibling script pulls in at runtime.
 *
 * These are never asked for through `getScriptSrc()`, because nothing on the PHP side
 * knows about them: a module resolves them itself with `new URL('./name.js',
 * import.meta.url)` so the import carries the same version query Filament served the
 * importer with. They still have to be registered, or the URL resolves onto nothing.
 *
 * @return array<int, string>
 */
function siblingImportedScriptKeys(): array
{
    $keys = [];

    foreach (glob(dirname(__DIR__, 2).'/resources/js/*.js') ?: [] as $file) {
        preg_match_all("/'\.\/([a-z0-9-]+)\.js'/", (string) file_get_contents($file), $matches);

        foreach ($matches[1] as $key) {
            $keys[$key] = true;
        }
    }

    ksort($keys);

    return array_keys($keys);
}

it('registers every script a plugin asks for', function (): void {
    $missing = array_values(array_diff(requestedScriptKeys(), registeredScriptKeys()));

    expect($missing)->toBe([], 'Asked for through getScriptSrc() but never registered: '.implode(', ', $missing));
});

it('has an asker for every script it registers', function (): void {
    // Either a plugin names it, or a sibling module imports it. Anything else is a file
    // being published and served that nothing will ever load.
    $unclaimed = array_values(array_diff(
        registeredScriptKeys(),
        requestedScriptKeys(),
        siblingImportedScriptKeys(),
    ));

    expect($unclaimed)->toBe([], 'Registered but nothing loads it: '.implode(', ', $unclaimed));
});

it('finds the keys it is asserting on', function (): void {
    // The two assertions above are both satisfied by a regex that matches nothing, which is
    // the one way this guard could pass while seeing none of the code it guards.
    expect(requestedScriptKeys())->not->toBeEmpty()
        ->and(registeredScriptKeys())->not->toBeEmpty()
        ->and(siblingImportedScriptKeys())->not->toBeEmpty();
});
