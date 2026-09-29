<?php

declare(strict_types=1);

/**
 * Every language the package ships has to say what English says.
 *
 * `resources/lang/en` is the reference and every folder beside it is held against it.
 * Nothing else notices a language falling behind, because Laravel answers a missing key
 * from the fallback locale: a French panel with one line untranslated shows that line in
 * English, in the middle of French, and no exception, log or test says so. A dropped
 * `:count` is quieter still - the sentence reads well and the number is gone.
 *
 * Two kinds of line are not word-for-word translations, and the comparison knows it.
 * `slash.aliases.*` are the words somebody types after the slash, comma separated; an entry
 * that is there but holds no word finds nothing, since a line that exists is never replaced
 * by the fallback. `accessibility.weak_link_phrases` is a list of what makes a poor link
 * text in that language - a corpus, so it is held to having entries and not to having as
 * many as English.
 */

/**
 * Where the language folders are, or one of them.
 */
function languageFolder(string $locale = ''): string
{
    return dirname(__DIR__, 2).'/resources/lang'.($locale === '' ? '' : "/{$locale}");
}

/**
 * The languages the package ships, which is to say the folders under `resources/lang`.
 *
 * @return array<int, string>
 */
function shippedLanguages(): array
{
    $locales = array_map(basename(...), glob(languageFolder().'/*', GLOB_ONLYDIR) ?: []);

    sort($locales);

    return $locales;
}

/**
 * The lines of a language file, flattened to `group.key` paths.
 *
 * A list is a line of its own rather than a group to walk into: comparing
 * `weak_link_phrases` entry by entry would ask German for as many as English has.
 *
 * @param  array<string, mixed>  $lines
 * @return array<string, mixed>
 */
function translationLines(array $lines, string $group = ''): array
{
    $flat = [];

    foreach ($lines as $key => $line) {
        $path = $group === '' ? (string) $key : "{$group}.{$key}";

        if (is_array($line) && ! array_is_list($line)) {
            $flat += translationLines($line, $path);

            continue;
        }

        $flat[$path] = $line;
    }

    return $flat;
}

/**
 * The placeholders in a line, by name and without regard to case.
 *
 * Laravel fills `:count`, `:Count` and `:COUNT` from one key, so a sentence that opens with
 * the number is the same sentence as one that ends with it.
 *
 * @return array<int, string>
 */
function translationPlaceholders(string $line): array
{
    preg_match_all('/:([A-Za-z_][A-Za-z0-9_]*)/', $line, $found);

    $names = array_values(array_unique(array_map(strtolower(...), $found[1])));

    sort($names);

    return $names;
}

/**
 * What a translation gets wrong next to the reference, one sentence to a fault.
 *
 * @param  array<string, mixed>  $reference
 * @param  array<string, mixed>  $translation
 * @return array<int, string>
 */
function translationFaults(array $reference, array $translation): array
{
    $expected = translationLines($reference);
    $lines = translationLines($translation);
    $faults = [];

    foreach (array_keys(array_diff_key($expected, $lines)) as $key) {
        $faults[] = "{$key} is missing";
    }

    foreach (array_keys(array_diff_key($lines, $expected)) as $key) {
        $faults[] = "{$key} is not in English";
    }

    foreach (array_intersect_key($lines, $expected) as $key => $line) {
        if (is_array($line) !== is_array($expected[$key])) {
            $faults[] = "{$key} is a ".(is_array($line) ? 'list' : 'line').' where English has a '.(is_array($line) ? 'line' : 'list');

            continue;
        }

        if (is_array($line)) {
            // A corpus of the language: it needs entries, and how many is its own business.
            if ($line === [] || array_filter($line, static fn (mixed $phrase): bool => ! is_string($phrase) || trim($phrase) === '') !== []) {
                $faults[] = "{$key} is empty or holds a blank entry";
            }

            continue;
        }

        if (! is_string($line) || trim($line) === '') {
            $faults[] = "{$key} is blank";

            continue;
        }

        $found = translationPlaceholders($line);
        $wanted = translationPlaceholders((string) $expected[$key]);

        if ($found !== $wanted) {
            $faults[] = "{$key} has [".implode(' ', $found).'] where English has ['.implode(' ', $wanted).']';
        }

        // The split `SlashMenu::aliases()` makes: what is left of the entry once the commas
        // and the blanks are gone is everything the menu can be searched by.
        if (str_starts_with($key, 'slash.aliases.') && array_filter(array_map(trim(...), explode(',', $line))) === []) {
            $faults[] = "{$key} has no word to type";
        }
    }

    return $faults;
}

it('ships English as the reference and German beside it', function (): void {
    // A guard that finds nothing to compare passes, so the list it runs over is pinned.
    expect(shippedLanguages())->toContain('en', 'de');
});

it('keeps every shipped language in step with English', function (string $locale): void {
    $files = array_map(basename(...), glob(languageFolder('en').'/*.php') ?: []);
    $shipped = array_map(basename(...), glob(languageFolder($locale).'/*.php') ?: []);

    // The same files first: a group only one language has is a group the others answer
    // from English without saying so.
    $faults = array_map(
        static fn (string $file): string => "{$file} is not there",
        array_values(array_diff($files, $shipped)),
    );

    foreach (array_intersect($files, $shipped) as $file) {
        foreach (translationFaults(require languageFolder('en')."/{$file}", require languageFolder($locale)."/{$file}") as $fault) {
            $faults[] = "{$file}: {$fault}";
        }
    }

    expect($faults)->toBe([]);
})->with(shippedLanguages());

it('finds the line a language lost and the line it made up', function (): void {
    $faults = translationFaults(
        ['tools' => ['image' => 'Image', 'table' => 'Table']],
        ['tools' => ['image' => 'Bild', 'tabel' => 'Tabelle']],
    );

    expect($faults)->toHaveCount(2)
        ->and($faults[0])->toContain('tools.table')
        ->and($faults[1])->toContain('tools.tabel');
});

it('finds a placeholder that was dropped, invented or renamed', function (string $translated): void {
    $faults = translationFaults(['used' => 'Used in :count entries'], ['used' => $translated]);

    expect($faults)->toHaveCount(1)
        ->and($faults[0])->toContain('used');
})->with([
    'dropped' => 'In Einträgen benutzt',
    'invented' => 'In :count Einträgen von :user benutzt',
    'renamed' => 'In :anzahl Einträgen benutzt',
]);

it('lets a language move a placeholder, capitalise it and order its lines its own way', function (): void {
    // Laravel fills `:count`, `:Count` and `:COUNT` from one key, and a sentence that
    // starts with the number is not a different sentence.
    expect(translationFaults(
        ['a' => 'Delete :name for :count entries', 'b' => 'Second'],
        ['b' => 'Zweite', 'a' => ':Count Einträge: :NAME löschen'],
    ))->toBe([]);
});

it('finds a line that says nothing', function (string $blank): void {
    $faults = translationFaults(['a' => 'Text'], ['a' => $blank]);

    expect($faults)->toHaveCount(1)
        ->and($faults[0])->toContain('a');
})->with([
    'empty' => '',
    'spaces' => '   ',
]);

it('finds a line that is a list on one side and a line on the other', function (): void {
    expect(translationFaults(['a' => ['x', 'y']], ['a' => 'x, y']))->toHaveCount(1)
        ->and(translationFaults(['a' => 'x, y'], ['a' => ['x', 'y']]))->toHaveCount(1);
});

it('holds a list of phrases to having entries and not to having as many as English', function (): void {
    $reference = ['phrases' => ['here', 'click here', 'more']];

    expect(translationFaults($reference, ['phrases' => ['hier', 'hier klicken', 'mehr', 'weiterlesen']]))->toBe([])
        ->and(translationFaults($reference, ['phrases' => []]))->toHaveCount(1)
        ->and(translationFaults($reference, ['phrases' => ['hier', ' ']]))->toHaveCount(1);
});

it('finds a slash alias that holds no word to type', function (string $aliases): void {
    $reference = ['slash' => ['aliases' => ['bulletList' => 'ul, bullets']]];

    $faults = translationFaults($reference, ['slash' => ['aliases' => ['bulletList' => $aliases]]]);

    expect($faults)->toHaveCount(1)
        ->and($faults[0])->toContain('slash.aliases.bulletList');
})->with([
    'a comma' => ',',
    'commas and spaces' => ' , ,',
]);
