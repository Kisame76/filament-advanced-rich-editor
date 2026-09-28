<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\Plugins\AutosavePlugin;

/**
 * The draft the browser keeps while the form is open.
 *
 * It lives in `localStorage` under a key built from the record, so two records never share
 * one draft and two browsers never fight over it. Nothing here reaches the application: a
 * draft that is never restored is a draft that never existed.
 */
trait KeepsADraft
{
    protected bool|Closure|null $hasAutosave = null;

    protected bool|Closure|null $warnsOnLeave = null;

    /**
     * Keeping a draft of this field in the browser, so a lost reply is not a lost article.
     *
     * Nothing about it reaches the application: the draft lives in the browser's own
     * storage, it is offered back the next time the same field on the same record is
     * opened, and it is dropped as soon as the document on screen says the same thing.
     *
     * It is content in a browser's storage on whatever machine somebody was working on, and
     * it outlives the session that wrote it - `autosave.ttl` is how long, and a field
     * holding something that should not sit there switches this off.
     */
    public function autosave(bool|Closure $condition = true): static
    {
        $this->hasAutosave = $condition;

        return $this;
    }

    public function hasAutosave(): bool
    {
        return (bool) ($this->evaluate($this->hasAutosave) ?? config('filament-advanced-rich-editor.autosave.enabled') ?? true);
    }

    /**
     * Whether closing the tab with unsaved changes asks first.
     *
     * The browser writes the question and always has; what a page decides is only whether
     * it is asked. Asked means asked for a stray space as much as for an afternoon's work,
     * which is why it is a switch of its own.
     */
    public function autosaveWarnOnLeave(bool|Closure $condition = true): static
    {
        $this->warnsOnLeave = $condition;

        return $this;
    }

    public function warnsOnLeave(): bool
    {
        return (bool) ($this->evaluate($this->warnsOnLeave) ?? config('filament-advanced-rich-editor.autosave.warn_on_leave') ?? true);
    }

    /**
     * What tells one field's draft from another's.
     *
     * The record, the model it belongs to, the Livewire component the form is in and the
     * path to this field within it - and none of it in the clear: it is a key in storage
     * that anything on the origin can read, so what it says is that two drafts are different
     * rather than what either of them is about. The browser adds the page it is on, which is
     * the half PHP cannot answer: to Livewire every request looks like the same endpoint.
     */
    public function getAutosaveKey(): string
    {
        $record = $this->getRecord();

        // A schema's record is a model most of the time and an array some of the time, and
        // an array has neither a class nor a key worth telling two of them apart by. It
        // therefore counts as a record that does not exist yet, which is what a form on a
        // page with nothing saved behind it already is.
        $model = is_object($record) ? $record::class : '';
        $key = ($record instanceof Model ? $record->getKey() : null) ?? 'new';

        return substr(hash('sha256', implode('|', [
            $this->getLivewire()::class,
            $model,
            (string) $key,
            $this->getStatePath(),
        ])), 0, 16);
    }

    /**
     * What the extension reads off the editor element. Null while drafts are switched off,
     * which is also when the extension that would read them was never registered.
     *
     * @return array<string, mixed>|null
     */
    public function getAutosaveSettingsForJs(): ?array
    {
        if (! $this->hasAutosave()) {
            return null;
        }

        return AutosavePlugin::getSettings([
            'key' => $this->getAutosaveKey(),
            'debounce' => (int) (config('filament-advanced-rich-editor.autosave.debounce') ?? 1500),
            // Seconds in the config file, because that is how a person writes a day;
            // milliseconds by the time the browser compares it to a timestamp.
            'ttl' => (int) (config('filament-advanced-rich-editor.autosave.ttl') ?? 86400) * 1000,
            'warnOnLeave' => $this->warnsOnLeave(),
        ]);
    }
}
