<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\AccessibilityFindings;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\AccessibilityPlugin;

/**
 * The accessibility check asked of a stored document, for `arte:diagnose`. The rules are the
 * script's said again in PHP, so the decisions are held to the list of cases the script's
 * suite reads too; what is tested on its own here is the walk over a stored tree.
 */
it('decides every case the way the script does', function (array $case): void {
    $findings = AccessibilityFindings::judge($case['subjects'], $case['rules'] ?? AccessibilityFindings::RULES, $case['weakPhrases'] ?? []);

    expect($findings)->toBe($case['findings']);
})->with(fn (): array => array_column(
    array_map(static fn (array $case): array => [$case['case'], [$case]], json_decode((string) file_get_contents(__DIR__.'/../js/fixtures/accessibility-findings.json'), associative: true)),
    1,
    0,
));

it('asks every rule but contrast, less what the configuration leaves out', function (): void {
    expect(AccessibilityFindings::RULES)->toBe(array_values(array_diff(AccessibilityPlugin::RULES, ['weak_contrast'])))
        ->and(AccessibilityFindings::rules())->toBe(AccessibilityFindings::RULES);

    config()->set('filament-advanced-rich-editor.accessibility.rules', ['weak_contrast', 'skipped_heading']);

    expect(AccessibilityFindings::rules())->toBe(['skipped_heading']);
});

it('reads a stored document the way the editor reads the one on the screen', function (): void {
    $document = AdvancedRichContentRenderer::make(
        '<h2>Prices</h2>'
        .'<p><a href="/report">click <strong>here</strong></a> and <a href="/report">here</a></p>'
        .'<p><img src="/storage/cat.png"><img src="/storage/rule.png" role="presentation" data-href="/home"></p>'
        .'<table><tbody><tr><td><p>Q1</p></td><td><p>Q2</p></td></tr></tbody></table>'
        .'<h4>Small print</h4>',
    )->toArray();

    expect(AccessibilityFindings::subjectsOf($document))->toBe([
        ['kind' => 'heading', 'level' => 2, 'text' => 'Prices'],
        // Text under one link is one link, and the same address after a word is another.
        ['kind' => 'link', 'href' => '/report', 'text' => 'click here'],
        ['kind' => 'link', 'href' => '/report', 'text' => 'here'],
        ['kind' => 'image', 'alt' => null, 'src' => '/storage/cat.png', 'decorative' => false, 'href' => null],
        ['kind' => 'image', 'alt' => null, 'src' => '/storage/rule.png', 'decorative' => true, 'href' => '/home'],
        ['kind' => 'table', 'headerCells' => 0, 'text' => 'Q1Q2'],
        ['kind' => 'heading', 'level' => 4, 'text' => 'Small print'],
    ])
        ->and(array_column(AccessibilityFindings::of($document, AccessibilityFindings::RULES, ['here', 'click here']), 'rule'))
        ->toBe(['weak_link_text', 'weak_link_text', 'missing_alt', 'decorative_link', 'table_without_header', 'skipped_heading']);
});
