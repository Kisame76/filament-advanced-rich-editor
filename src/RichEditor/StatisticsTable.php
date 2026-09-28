<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor;

use Filament\Support\Components\Contracts\HasEmbeddedView;
use Filament\Support\Components\ViewComponent;
use Filament\Support\Concerns\HasExtraAttributes;

/**
 * How long the document is, drawn as a table of names and numbers.
 *
 * A description list rather than a table, for the reason `ShortcutTable` beside it is one:
 * every entry is a name and the one value that belongs to it. It is *drawn* like a table
 * because a column of numbers is read by comparing them, and comparing needs them aligned.
 *
 * A component rather than a string built in the action, and that is the whole of why it
 * exists: the labels come from translation files a project may publish and edit, so the
 * escaping has to be somewhere that cannot be forgotten on the next edit.
 */
class StatisticsTable extends ViewComponent implements HasEmbeddedView
{
    use HasExtraAttributes;

    /**
     * @var array<int, array{label: string, value: string}>
     */
    protected array $rows = [];

    protected string $evaluationIdentifier = 'statisticsTable';

    protected string $viewIdentifier = 'statisticsTable';

    public static function make(): static
    {
        $static = app(static::class);
        $static->configure();

        return $static;
    }

    /**
     * @param  array<int, array{label: string, value: string}>  $rows
     */
    public function rows(array $rows): static
    {
        $this->rows = $rows;

        return $this;
    }

    public function toEmbeddedHtml(): string
    {
        $attributes = $this->getExtraAttributeBag()->class(['fi-arte-statistics']);

        ob_start(); ?>

        <dl <?= $attributes->toHtml() ?>>
            <?php foreach ($this->rows as $row) { ?>
                <dt><?= e($row['label']) ?></dt>
                <dd><?= e($row['value']) ?></dd>
            <?php } ?>
        </dl>

        <?php return (string) ob_get_clean();
    }
}
