<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\AccessibilityPlugin;

/**
 * The accessibility check, asked of a stored document rather than of the one on the screen.
 *
 * The rules are `accessibility.js`'s, said a second time in PHP, because the question is put
 * where there is no browser: `arte:diagnose` reads every document a project keeps. Two copies
 * of a rule are two chances to disagree, so the decisions - `judge()` here, `findingsFor()`
 * there - are held to one list of cases, `tests/js/fixtures/accessibility-findings.json`,
 * which both suites read.
 *
 * Every rule but contrast. A colour is stored by its name, and what the name means, and what
 * it is read against, belongs to the field that offered it.
 */
final class AccessibilityFindings
{
    /**
     * @var array<int, string>
     */
    public const RULES = [
        'missing_alt',
        'decorative_link',
        'empty_link',
        'weak_link_text',
        'skipped_heading',
        'table_without_header',
    ];

    /**
     * What is wrong with a document, in document order.
     *
     * @param  array<string, mixed>  $document
     * @param  array<int, string>  $rules
     * @param  array<int, string>  $weakPhrases
     * @return array<int, array{rule: string, text: string}>
     */
    public static function of(array $document, array $rules, array $weakPhrases): array
    {
        return self::judge(self::subjectsOf($document), $rules, $weakPhrases);
    }

    /**
     * The rules the configuration asks for, less the one a stored document cannot answer.
     *
     * @return array<int, string>
     */
    public static function rules(): array
    {
        $configured = (array) (config('filament-advanced-rich-editor.accessibility.rules') ?? AccessibilityPlugin::RULES);

        return array_values(array_filter(self::RULES, static fn (string $rule): bool => in_array($rule, $configured, strict: true)));
    }

    /**
     * What the document has to say about itself - `subjectsOf()` in the script, without the
     * positions, which only a panel that selects things needs.
     *
     * @param  array<string, mixed>  $document
     * @return array<int, array<string, mixed>>
     */
    public static function subjectsOf(array $document): array
    {
        $subjects = [];

        // The link the walk is still on. Text under one link may be several text nodes -
        // `click <strong>here</strong>` is two - and only text that touches is one link:
        // any node in between, a break or the end of a paragraph, parts them.
        $link = null;

        foreach (DocumentContent::nodes($document) as $node) {
            $type = $node['type'] ?? null;
            $attrs = is_array($node['attrs'] ?? null) ? $node['attrs'] : [];

            if ($type !== 'text') {
                $link = null;
            }

            if ($type === 'image') {
                $subjects[] = [
                    'kind' => 'image',
                    'alt' => $attrs['alt'] ?? null,
                    'src' => $attrs['src'] ?? null,
                    'decorative' => ($attrs['decorative'] ?? null) === true,
                    'href' => $attrs['href'] ?? null,
                ];
            } elseif ($type === 'heading') {
                $subjects[] = ['kind' => 'heading', 'level' => (int) ($attrs['level'] ?? 1), 'text' => self::textOf($node)];
            } elseif ($type === 'table') {
                $row = is_array($node['content'][0] ?? null) ? $node['content'][0] : [];

                $subjects[] = [
                    'kind' => 'table',
                    'headerCells' => count(array_filter(
                        is_array($row['content'] ?? null) ? $row['content'] : [],
                        static fn (mixed $cell): bool => is_array($cell) && (($cell['type'] ?? null) === 'tableHeader'),
                    )),
                    // The first row, so that three tables with no header row are three rows
                    // saying which is which rather than the same sentence three times.
                    'text' => self::textOf($row),
                ];
            } elseif ($type === 'text') {
                $href = self::hrefOf($node);
                $text = is_string($node['text'] ?? null) ? $node['text'] : '';

                if ($href === null) {
                    $link = null;
                } elseif (($link !== null) && ($subjects[$link]['href'] === $href)) {
                    $subjects[$link]['text'] .= $text;
                } else {
                    $subjects[] = ['kind' => 'link', 'href' => $href, 'text' => $text];
                    $link = array_key_last($subjects);
                }
            }
        }

        return $subjects;
    }

    /**
     * Every rule, over what the walk wrote down - `findingsFor()` in the script, rule for rule.
     *
     * @param  array<int, array<string, mixed>>  $subjects
     * @param  array<int, string>  $rules
     * @param  array<int, string>  $weakPhrases
     * @return array<int, array{rule: string, text: string}>
     */
    public static function judge(array $subjects, array $rules, array $weakPhrases): array
    {
        $enabled = array_flip($rules);
        $findings = [];
        $previousLevel = null;

        $finding = static function (string $rule, mixed $text) use (&$findings): void {
            $findings[] = ['rule' => $rule, 'text' => is_scalar($text) ? (string) $text : ''];
        };

        foreach ($subjects as $subject) {
            switch ($subject['kind'] ?? null) {
                case 'image':
                    $decorative = ($subject['decorative'] ?? false) === true;

                    // A picture marked as carrying nothing is a decision, not an omission.
                    if (isset($enabled['missing_alt']) && ! $decorative && (self::trim($subject['alt'] ?? null) === '')) {
                        $finding('missing_alt', $subject['src'] ?? '');
                    }

                    if (isset($enabled['decorative_link']) && $decorative && (self::trim($subject['href'] ?? null) !== '')) {
                        $finding('decorative_link', $subject['href']);
                    }

                    break;

                case 'link':
                    $text = is_string($subject['text'] ?? null) ? $subject['text'] : '';

                    // Empty first: one finding is the useful number.
                    if (isset($enabled['empty_link']) && (preg_replace('/[\s\x{00A0}]+/u', '', $text) === '')) {
                        $finding('empty_link', $subject['href'] ?? '');
                    } elseif (isset($enabled['weak_link_text']) && self::isWeakLinkText($text, $weakPhrases)) {
                        $finding('weak_link_text', $text);
                    }

                    break;

                case 'heading':
                    $level = (int) ($subject['level'] ?? 1);

                    // Only a jump, and never the level a document starts at.
                    if (isset($enabled['skipped_heading']) && ($previousLevel !== null) && ($level > $previousLevel + 1)) {
                        $finding('skipped_heading', $subject['text'] ?? '');
                    }

                    $previousLevel = $level;

                    break;

                case 'table':
                    if (isset($enabled['table_without_header']) && ! ($subject['headerCells'] ?? 0)) {
                        $finding('table_without_header', $subject['text'] ?? '');
                    }

                    break;
            }
        }

        return $findings;
    }

    /**
     * Whether a link's whole text is one of the phrases - `isWeakLinkText()` in the script.
     *
     * @param  array<int, string>  $phrases
     */
    public static function isWeakLinkText(string $text, array $phrases): bool
    {
        $normalised = mb_strtolower((string) preg_replace('/[\s\x{00A0}]+/u', ' ', $text));
        $normalised = trim((string) preg_replace('/[.!?,:;…"\'»«„“”]+$/u', '', trim($normalised)));

        if ($normalised === '') {
            return false;
        }

        foreach ($phrases as $phrase) {
            if ($normalised === trim(mb_strtolower((string) $phrase))) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function hrefOf(array $node): ?string
    {
        foreach (is_array($node['marks'] ?? null) ? $node['marks'] : [] as $mark) {
            if (is_array($mark) && (($mark['type'] ?? null) === 'link')) {
                return is_string($mark['attrs']['href'] ?? null) ? $mark['attrs']['href'] : '';
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $node
     */
    private static function textOf(array $node): string
    {
        $text = '';

        foreach (DocumentContent::nodes($node) as $child) {
            if (is_string($child['text'] ?? null)) {
                $text .= $child['text'];
            }
        }

        return $text;
    }

    /**
     * Whitespace off both ends, the non-breaking space included, the way a script's `trim()`
     * takes it.
     */
    private static function trim(mixed $value): string
    {
        return is_scalar($value) ? (string) preg_replace('/^[\s\x{00A0}]+|[\s\x{00A0}]+$/u', '', (string) $value) : '';
    }
}
