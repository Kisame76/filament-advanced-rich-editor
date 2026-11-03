<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Console;

use Filament\Forms\Components\RichEditor\Models\Contracts\HasRichContent;
use Filament\Forms\Components\RichEditor\RichContentAttribute;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AccessibilityFindings;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\AdvancedRichContentRenderer;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\DocumentAttachments;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Links\DocumentLinks;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Links\LinkCheck;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Links\LinkState;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Links\LinkStatus;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Media\FileAttachments;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\AccessibilityPlugin;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\StoredDocuments;
use ReflectionClass;
use RuntimeException;
use Symfony\Component\Finder\Finder;
use Throwable;

/**
 * What is wrong in the documents a project keeps: files they point at that are gone, links
 * that lead nowhere, and what the accessibility check would say about each of them.
 *
 * Where the documents are is found the way `model:prune` finds what to prune: every model
 * under `app/Models` that registered rich content, plus the places the `documents` key names.
 * `--model` replaces both, for a project that keeps its documents somewhere else or wants one
 * table at a time.
 *
 * Fails where something is broken for every reader - a file that is gone, a page that is gone,
 * a host that no longer exists - so a schedule can mail the output on failure. The rest is
 * listed without failing: a server that turns checkers away, or is down for the afternoon, is
 * not a link anybody has to fix, and a document written before anybody asked for alt text
 * would otherwise fail every run until somebody rewrote the archive.
 */
class DiagnoseCommand extends Command
{
    /**
     * What it looks for, and the only names `--only` takes.
     *
     * @var array<int, string>
     */
    public const CHECKS = ['files', 'links', 'accessibility'];

    protected $signature = 'arte:diagnose
        {--only=* : files, links or accessibility - default all three}
        {--model=* : A model to read, as App\Models\Page or App\Models\Page:body,sidebar - default every model that registered rich content}';

    protected $description = 'Find what is wrong in your stored documents: files that are gone, links that lead nowhere, and what the accessibility check would say.';

    public function handle(): int
    {
        $checks = $this->checks();
        $documents = ($checks === null) ? null : $this->documents();

        if (($checks === null) || ($documents === null)) {
            return static::FAILURE;
        }

        if ($documents === []) {
            $this->components->error('No documents to read. Register rich content on a model in app/Models, name one in the documents key, or pass --model.');

            return static::FAILURE;
        }

        $reads = array_flip($checks);
        $rules = AccessibilityFindings::rules();
        $phrases = AccessibilityPlugin::getWeakPhrases();

        // What was found, each where it was found, by the name of its entry rather than the
        // record: a table of thousands of rows is read a hundred at a time, and not kept.
        $files = [];
        $links = [];
        $findings = [];

        // Where files were looked for, and how many of them were there - and where they could
        // not be, because nothing says where that model keeps them.
        $places = [];
        $unknown = [];
        $anywhere = [];

        $read = 0;
        $unreadable = 0;

        foreach ((new StoredDocuments($documents))->each() as [$record, $column, $content]) {
            $entry = StoredDocuments::entry($record);
            $read++;

            if (isset($reads['links'])) {
                foreach (DocumentLinks::of($content) as $href) {
                    if (LinkCheck::target($href) !== null) {
                        $links[] = [$entry, $column, $href];
                    }
                }
            }

            if (! isset($reads['files']) && ! isset($reads['accessibility'])) {
                continue;
            }

            // Read through the schema the editor writes with, so markup and a stored tree are
            // one shape, and through what the model says about the column - which is where it
            // says where its files are, as it says it to every page that renders them.
            $attribute = ($record instanceof HasRichContent) ? $record->getRichContentAttribute($column) : null;
            $renderer = AdvancedRichContentRenderer::make($content);

            if ($attribute !== null) {
                $renderer->attribute($attribute);
            }

            // One document that cannot be read - a block whose configuration throws, a value
            // nothing can parse - is reported and passed over, rather than ending the run for
            // every document after it.
            try {
                $tree = $renderer->toArray();
            } catch (Throwable $exception) {
                report($exception);
                $unreadable++;

                continue;
            }

            if (isset($reads['files'])) {
                $place = $record::class.'.'.$column;

                foreach (DocumentAttachments::files($tree) as $id => $name) {
                    if ($attribute === null) {
                        $unknown[$place] = true;

                        break;
                    }

                    $id = (string) $id;
                    $places[$place] ??= ['found' => 0, 'missing' => 0, 'unasked' => 0];

                    // An id the browser hands out for an upload in progress is never one a
                    // saved document should hold: the save that would have stored the file
                    // did not happen.
                    $found = FileAttachments::pending($id) ? false : $this->lookUp($attribute, $renderer, $id);

                    if (($found === false) && (! FileAttachments::pending($id)) && ($attribute->getFileAttachmentProvider() !== null)) {
                        $anywhere[$place] ??= $this->anywhere($record, $column, $attribute);
                        [$model, $everywhere] = $anywhere[$place];
                        $found = $this->lookUp($model, $everywhere, $id);
                    }

                    $places[$place][match ($found) {
                        true => 'found',
                        false => 'missing',
                        null => 'unasked',
                    }]++;

                    if ($found === false) {
                        $files[] = [$entry, $column, Str::limit($name, 60)];
                    }
                }
            }

            if (isset($reads['accessibility'])) {
                foreach (AccessibilityFindings::of($tree, $rules, $phrases) as $finding) {
                    $findings[] = [$entry, $column, $finding['rule'], $finding['text']];
                }
            }
        }

        $missing = isset($reads['files']) && $this->reportFiles($files, $places, array_keys($unknown));
        $dead = isset($reads['links']) && $this->reportLinks($links);

        if (isset($reads['accessibility'])) {
            $this->reportAccessibility($findings, $rules, $read);
        }

        if ($unreadable > 0) {
            $this->newLine();
            $this->components->warn(sprintf('%d %s could not be read and %s passed over. What went wrong is in the log.', $unreadable, Str::plural('document', $unreadable), ($unreadable === 1) ? 'was' : 'were'));
        }

        return ($missing || $dead) ? static::FAILURE : static::SUCCESS;
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string}>  $files
     * @param  array<string, array{found: int, missing: int, unasked: int}>  $places
     * @param  array<int, string>  $unknown
     */
    protected function reportFiles(array $files, array $places, array $unknown): bool
    {
        $this->heading('Files');

        $found = array_sum(array_column($places, 'found'));
        $unasked = array_sum(array_column($places, 'unasked'));
        $looked = $found + count($files) + $unasked;

        if ($places === []) {
            $this->components->info('No files to look for.');
        } else {
            $this->components->info(sprintf('Looked for %d %s.', $looked, Str::plural('file', $looked)));
        }

        if ($files !== []) {
            $this->table(['Entry', 'Column', 'File'], $files);
            $this->line(sprintf('%d missing, %d found.', count($files), $found));
        }

        // Not one file found in a place says more about what the model says than about the
        // files: a media collection the attribute was never told about looks, from here,
        // exactly like a library somebody emptied.
        foreach ($places as $place => $count) {
            if (($count['found'] === 0) && ($count['missing'] > 1)) {
                $this->components->warn(sprintf('Not one file in %s was found. Does its registerRichContent() say where they are kept - the disk, or the media library plugin?', $place));
            }
        }

        // Not known either way, and not failed over: a disk or a library that did not answer
        // this once is not a list of files somebody deleted.
        if ($unasked > 0) {
            $this->components->warn(sprintf('%d %s could not be looked for: where they are kept did not answer. What it said is in the log.', $unasked, Str::plural('file', $unasked)));
        }

        foreach ($unknown as $place) {
            $this->components->warn(sprintf('Files in %s were not looked for: the model does not register it as rich content, which is where it would say where they are kept.', $place));
        }

        return $files !== [];
    }

    /**
     * Every link asked, and whether one of them is broken.
     *
     * @param  array<int, array{0: string, 1: string, 2: string}>  $found
     */
    protected function reportLinks(array $found): bool
    {
        $this->heading('Links');

        $hrefs = array_values(array_unique(array_column($found, 2)));

        if ($hrefs === []) {
            $this->components->info('No links to check.');

            return false;
        }

        $statuses = LinkCheck::make()->check($hrefs);

        // What was asked, once per address: `page#a` and `page#b` are two links and one answer.
        $answers = [];

        foreach ($hrefs as $href) {
            $answers[(string) LinkCheck::target($href)] = $statuses[$href];
        }

        $entries = count(array_unique(array_column($found, 0)));

        $this->components->info(sprintf('Asked %d %s, found in %d %s.', count($answers), Str::plural('address', count($answers)), $entries, Str::plural('entry', $entries)));

        $order = [LinkState::Broken, LinkState::Unreachable, LinkState::Erroring];
        $rows = [];

        foreach ($found as [$entry, $column, $href]) {
            $status = $statuses[$href];
            $problem = $status->problem();

            if ($problem !== null) {
                $rows[] = [(int) array_search($status->state, $order, strict: true), $entry, $column, Str::limit($href, 80), $problem];
            }
        }

        usort($rows, static fn (array $a, array $b): int => $a[0] <=> $b[0]);

        if ($rows !== []) {
            $this->table(['Entry', 'Column', 'Link', 'Answer'], array_map(static fn (array $row): array => array_slice($row, 1), $rows));
        }

        $count = static fn (LinkState $state): int => count(array_filter($answers, static fn (LinkStatus $status): bool => $status->state === $state));

        $this->line(sprintf(
            '%d broken, %d not answering, %d answering with an error, %d working.',
            $count(LinkState::Broken),
            $count(LinkState::Unreachable),
            $count(LinkState::Erroring),
            $count(LinkState::Working),
        ));

        // Not one answer of any kind says more about the machine asking than about the links.
        // Nor does it from the site itself, whose links are asked of `app.url` - which on a
        // machine that is not the site, a container or a build server, may be nobody.
        $own = array_filter(
            $answers,
            static fn (string $target): bool => parse_url($target, PHP_URL_HOST) === parse_url((string) config('app.url'), PHP_URL_HOST),
            ARRAY_FILTER_USE_KEY,
        );

        if ((count($answers) > 1) && $this->noneAnswered($answers)) {
            $this->components->warn('Not one address answered. Can this machine reach them?');
        } elseif (($own !== []) && $this->noneAnswered($own)) {
            $this->components->warn(sprintf('Not one link to this site answered at %s. Is app.url right for the machine this runs on?', config('app.url')));
        }

        return $count(LinkState::Broken) > 0;
    }

    /**
     * @param  array<int, array{0: string, 1: string, 2: string, 3: string}>  $findings
     * @param  array<int, string>  $rules
     */
    protected function reportAccessibility(array $findings, array $rules, int $read): void
    {
        $this->heading('Accessibility');

        if ($rules === []) {
            $this->components->info('No rules to ask: accessibility.rules leaves out every one a stored document can answer.');

            return;
        }

        $this->components->info(sprintf('Read %d %s%s', $read, Str::plural('document', $read), ($findings === []) ? ': nothing to report.' : '.'));

        if ($findings === []) {
            return;
        }

        $label = static fn (string $rule): string => (string) __("filament-advanced-rich-editor::advanced-rich-editor.accessibility.rules.{$rule}");

        $this->table(['Entry', 'Column', 'Problem', 'Where'], array_map(
            static fn (array $finding): array => [$finding[0], $finding[1], $label($finding[2]), Str::limit(trim($finding[3]), 60)],
            $findings,
        ));

        $counts = array_count_values(array_column($findings, 2));

        $this->line(implode(', ', array_map(
            static fn (string $rule, int $count): string => "{$label($rule)}: {$count}",
            array_keys($counts),
            $counts,
        )).'.');
    }

    /**
     * Whether a file is there - or null where the place it is kept did not answer.
     *
     * A disk is asked directly rather than through the renderer, which answers "no address"
     * alike for a file that is gone and a disk that could not be reached: an outage on the
     * night of a scheduled run would otherwise be a list of every file, and a failure. A
     * provider is asked for the address, and an error from it is the same not knowing.
     */
    protected function lookUp(RichContentAttribute $attribute, AdvancedRichContentRenderer $renderer, string $id): ?bool
    {
        try {
            if ($attribute->getFileAttachmentProvider() === null) {
                return Storage::disk($attribute->getFileAttachmentsDiskName() ?? config('filament.default_filesystem_disk'))->exists($id);
            }

            return filled($renderer->getFileAttachmentUrl($id));
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /**
     * What a model says about where it keeps its files, without saying which entry is asking.
     *
     * The entry's own attribute answers for its own files, and for a library shared between
     * entries only where a field widened it - which a document read outside a form never was.
     * A picture another entry uploaded to a shared library would be called gone although it is
     * right there; asked with no entry, a provider looks wherever the model keeps its files.
     *
     * @return array{0: RichContentAttribute, 1: AdvancedRichContentRenderer}
     */
    protected function anywhere(Model $record, string $column, RichContentAttribute $fallback): array
    {
        $attribute = ($record instanceof HasRichContent) ? $record->newInstance()->getRichContentAttribute($column) : null;
        $attribute ??= $fallback;

        return [$attribute, AdvancedRichContentRenderer::make()->attribute($attribute)];
    }

    protected function heading(string $title): void
    {
        $this->newLine();
        $this->line("  <options=bold>{$title}</>");
    }

    /**
     * @param  array<string, LinkStatus>  $answers
     */
    protected function noneAnswered(array $answers): bool
    {
        return ! array_filter($answers, static fn (LinkStatus $status): bool => $status->status !== null);
    }

    /**
     * The checks to run, or null where an option named one that is not.
     *
     * @return array<int, string>|null
     */
    protected function checks(): ?array
    {
        $named = array_values(array_filter(array_map('strval', (array) $this->option('only'))));

        foreach ($named as $check) {
            if (! in_array($check, static::CHECKS, strict: true)) {
                $this->components->error(sprintf('%s is not a check. Pass %s.', $check, implode(', ', static::CHECKS)));

                return null;
            }
        }

        return ($named === []) ? static::CHECKS : $named;
    }

    /**
     * The models and columns to read, or null where an option named something that is not one.
     *
     * @return array<class-string<Model>, array<int, string>>|null
     */
    protected function documents(): ?array
    {
        $named = [];

        foreach ((array) $this->option('model') as $option) {
            $model = Str::before((string) $option, ':');
            $columns = str_contains((string) $option, ':') ? array_filter(explode(',', Str::after((string) $option, ':'))) : [];

            if (! is_subclass_of($model, Model::class)) {
                $this->components->error("{$model} is not a model.");

                return null;
            }

            $named[$model] = [...($named[$model] ?? []), ...$columns];
        }

        if ($named !== []) {
            return StoredDocuments::from($named);
        }

        return StoredDocuments::merge(
            StoredDocuments::from(array_fill_keys($this->discovered(), [])),
            StoredDocuments::configured(),
        );
    }

    /**
     * Every model under `app/Models` that registered rich content.
     *
     * @return array<int, class-string<Model>>
     */
    protected function discovered(): array
    {
        $path = app_path('Models');

        if (! is_dir($path)) {
            return [];
        }

        try {
            $namespace = app()->getNamespace().'Models\\';
        } catch (RuntimeException) {
            return [];
        }

        $models = [];

        foreach (Finder::create()->in($path)->files()->name('*.php') as $file) {
            $class = $namespace.str_replace(['/', '.php'], ['\\', ''], $file->getRelativePathname());

            if (class_exists($class) && is_subclass_of($class, Model::class) && is_subclass_of($class, HasRichContent::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $models[] = $class;
            }
        }

        return $models;
    }
}
