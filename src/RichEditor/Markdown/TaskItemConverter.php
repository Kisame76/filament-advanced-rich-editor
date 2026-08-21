<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Markdown;

use League\HTMLToMarkdown\Converter\ListItemConverter;
use League\HTMLToMarkdown\ElementInterface;

/**
 * Teaches the Markdown converter what a ticked box is.
 *
 * A task list is a list of decisions, and the decisions are the boxes. To a converter
 * that has never heard of `data-checked` a task item is an ordinary list item, so the
 * state - the only part anybody was tracking - is dropped on the way out.
 *
 * `- [x] ` is the spelling GitHub, GitLab and every editor that renders task lists agree
 * on. The marker is spliced in after the bullet the parent already decided on rather than
 * written into the document, because a `[` inside the text would be escaped on its way
 * through the converter and come out as `\[x\]`.
 */
class TaskItemConverter extends ListItemConverter
{
    public function convert(ElementInterface $element): string
    {
        $markdown = parent::convert($element);

        $checked = $element->getAttribute('data-checked');

        // An ordinary list item, which is most of them.
        if (! in_array($checked, ['true', 'false'], strict: true)) {
            return $markdown;
        }

        $box = $checked === 'true' ? '[x] ' : '[ ] ';

        // The parent wrote the bullet or the number, and nested items are indented in
        // front of it. Splicing after that prefix keeps both intact.
        return preg_replace('/^(\s*(?:[-*+]|\d+\.) )/', '${1}'.$box, $markdown, limit: 1) ?? $markdown;
    }
}
