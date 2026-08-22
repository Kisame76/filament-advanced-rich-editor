<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

/**
 * Who a document mentioned.
 *
 * The question every mention feature is eventually asked - "notify the people this names" -
 * and the one thing neither the editor nor the renderer answers. The editor knows while it
 * is open; the renderer knows while it is rendering; the model observer that has to send
 * the mail has a column of markup and nothing else.
 *
 * Deliberately provider-free. Resolving a mention needs configuration, and a `saved()` hook
 * that had to be handed the same providers as the field would be one more place for the two
 * to drift apart. This reads what the document itself carries: the trigger, the id, and the
 * label as it stood when it was typed. Whoever wants the record now has the id to look it
 * up with.
 */
class Mentions
{
    /**
     * @param  array<int, array{char: string, id: string, label: string|null}>  $mentions
     */
    final protected function __construct(protected array $mentions) {}

    /**
     * @param  string|array<string, mixed>|null  $content
     */
    public static function in(string|array|null $content): static
    {
        if (blank($content)) {
            return new static([]);
        }

        $mentions = [];

        // Parsed through the renderer rather than by hand, so the extension list is the one
        // the field saves with: a document parsed against a shorter list quietly loses the
        // attributes that list does not declare.
        AdvancedRichContentRenderer::make($content)->getEditor()->descendants(
            function (object &$node) use (&$mentions): void {
                if (($node->type ?? null) !== 'mention') {
                    return;
                }

                $id = $node->attrs->id ?? null;

                // The id is the only part that identifies anyone. A mention without one is
                // markup that looks like a mention.
                if (blank($id)) {
                    return;
                }

                $label = $node->attrs->label ?? null;

                $mentions[] = [
                    'char' => (string) ($node->attrs->char ?? '@'),
                    'id' => (string) $id,
                    'label' => filled($label) ? (string) $label : null,
                ];
            },
        );

        return new static($mentions);
    }

    /**
     * Every mention, in the order it was written, including a name typed twice.
     *
     * @return array<int, array{char: string, id: string, label: string|null}>
     */
    public function all(): array
    {
        return $this->mentions;
    }

    /**
     * The ids one trigger mentioned, each of them once.
     *
     * What a notification wants: the people, not how many times somebody typed them. Without
     * a trigger it answers for all of them, which is the right answer only where one kind of
     * thing is mentioned - ids from two triggers are ids in two different tables.
     *
     * @return array<int, string>
     */
    public function ids(?string $char = null): array
    {
        $ids = [];

        foreach ($this->mentions as $mention) {
            if ($char !== null && $mention['char'] !== $char) {
                continue;
            }

            $ids[] = $mention['id'];
        }

        return array_values(array_unique($ids));
    }

    /**
     * The ids each trigger mentioned, keyed by the trigger.
     *
     * @return array<string, array<int, string>>
     */
    public function grouped(): array
    {
        $grouped = [];

        foreach ($this->mentions as $mention) {
            $grouped[$mention['char']][] = $mention['id'];
        }

        return array_map(
            static fn (array $ids): array => array_values(array_unique($ids)),
            $grouped,
        );
    }
}
