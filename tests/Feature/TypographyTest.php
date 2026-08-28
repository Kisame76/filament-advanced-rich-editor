<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Typography;

/**
 * Straight quotes turning into the ones the language uses while they are typed.
 *
 * The table lives here rather than in the browser because it is configuration: a project
 * whose language is not shipped adds it where it adds everything else. The browser half
 * keeps a copy as its fallback, and the last test in this file holds the two together.
 */
it('knows the two languages the package itself is translated into', function (): void {
    expect(Typography::for('de'))->toMatchArray(['open' => '„', 'close' => '“', 'dash' => '–'])
        ->and(Typography::for('en'))->toMatchArray(['open' => '“', 'close' => '”', 'dash' => '—']);
});

it('sets the dash the language sets', function (): void {
    // The half the English convention gets wrong in German, and the reason a hard-coded
    // table would have been a bug rather than a shortcut.
    expect(Typography::for('de')['dash'])->toBe('–')
        ->and(Typography::for('en')['dash'])->toBe('—');
});

it('reads a region off a locale rather than failing on it', function (): void {
    // `app()->getLocale()` answers `de_DE` as readily as `de`.
    expect(Typography::for('de_DE'))->toBe(Typography::for('de'))
        ->and(Typography::for('de-AT'))->toBe(Typography::for('de'));
});

it('falls back rather than guessing at a language nobody described', function (): void {
    expect(Typography::for('pl'))->toBe(Typography::for('en'))
        ->and(Typography::for(null))->toBe(Typography::for('en'));
});

it('lets a project describe a language the package does not ship', function (): void {
    config()->set('filament-advanced-rich-editor.typography.languages.fr', [
        'open' => '«', 'close' => '»', 'openSingle' => '‹', 'closeSingle' => '›', 'dash' => '—',
    ]);

    expect(Typography::for('fr')['open'])->toBe('«');
});

it('lets a project correct one that it does', function (): void {
    config()->set('filament-advanced-rich-editor.typography.languages.de', [
        'open' => '»', 'close' => '«', 'openSingle' => '›', 'closeSingle' => '‹', 'dash' => '–',
    ]);

    // Guillemets pointing inwards are as German as the low-nine quotes, and which of the two
    // a house uses is not this package's decision.
    expect(Typography::for('de')['open'])->toBe('»');
});

it('ships off, because what it writes ends up in the database', function (): void {
    // The one feature here that changes what somebody typed rather than giving a field
    // something it can do. Switching it on costs a line; switching it off after the fact
    // does not un-write the quotation marks already stored.
    expect(editor()->hasTypography())->toBeFalse()
        ->and(editor()->getTypographySettingsForJs())->toBeNull();
});

it('takes the locale from the application and lets the field overrule it', function (): void {
    app()->setLocale('de');

    expect(editor()->typography()->getTypographySettingsForJs())->toBe(Typography::for('de'))
        ->and(editor()->typography()->typographyLanguage('en')->getTypographySettingsForJs())
        ->toBe(Typography::for('en'));
});

it('tells the browser nothing while it is switched off', function (): void {
    expect(editor()->typography()->typography(false)->getTypographySettingsForJs())->toBeNull();
});

it('reads whether it is offered from the config file', function (): void {
    config()->set('filament-advanced-rich-editor.typography.enabled', true);

    expect(editor()->hasTypography())->toBeTrue()
        ->and(editor()->typography(false)->hasTypography())->toBeFalse();
});

it('keeps the shipped tables the same on both sides', function (): void {
    // The browser half carries the same two languages as its fallback, for the case where no
    // settings reach it at all. Two copies of one table are otherwise a quote that is right
    // in the editor and wrong in the file, or the reverse.
    $js = (string) file_get_contents(dirname(__DIR__, 2).'/resources/js/typography.js');

    foreach (['de', 'en'] as $language) {
        foreach (Typography::for($language) as $key => $character) {
            expect($js)->toContain("{$key}: '{$character}'");
        }
    }
});
