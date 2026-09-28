<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire;

/**
 * A test component that remembers what was dispatched at it.
 *
 * `runCommands()` is how every dialog in this package reaches the editor, and it works by
 * dispatching a Livewire event - which needs a live Livewire request to be observable the
 * usual way, and there is no request in this suite. Overriding the one method is the whole
 * seam, and it is a smaller fixture than a rendered component would be.
 */
class RecordingSchemaComponent extends TestSchemaComponent
{
    /** @var array<int, array{event: string, params: array<string, mixed>}> */
    public array $dispatched = [];

    /**
     * @param  mixed  $event
     * @param  array<string, mixed>  $params
     */
    public function dispatch($event, ...$params): void
    {
        $this->dispatched[] = ['event' => (string) $event, 'params' => $params];
    }

    /**
     * The editor commands out of the last dispatch, which is what a dialog's effect is.
     *
     * @return array<int, array{name: string, arguments: array<mixed>}>
     */
    public function commands(): array
    {
        foreach (array_reverse($this->dispatched) as $entry) {
            if ($entry['event'] === 'run-rich-editor-commands') {
                return $entry['params']['commands'] ?? [];
            }
        }

        return [];
    }
}
