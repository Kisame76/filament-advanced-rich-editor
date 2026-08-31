<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\RichEditor\Concerns;

use Closure;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\MentionProvider;
use Kisame76\FilamentAdvancedRichEditor\RichEditor\SlashMenu;

/**
 * The two menus that open on a keystroke: `/` for blocks and `@` for mentions.
 *
 * Both are the same shape - a character, a search, a list of entries grouped under headings -
 * and both are handed to the browser as one settings object, because a TipTap extension has
 * no other channel to what the field knows.
 */
trait OpensMenus
{
    protected bool|Closure|null $hasSlashMenu = null;

    /**
     * @var array<string, array<int, string>> | Closure | null
     */
    protected array|Closure|null $slashGroups = null;

    protected string|Closure|null $slashChar = null;

    protected bool|Closure|null $hasMentionMenu = null;

    /**
     * Whether typing the slash character opens a menu of the commands this field offers.
     */
    public function slashMenu(bool|Closure $condition = true): static
    {
        $this->hasSlashMenu = $condition;

        return $this;
    }

    public function hasSlashMenu(): bool
    {
        return (bool) ($this->evaluate($this->hasSlashMenu)
            ?? $this->notionDefaultFor('slashMenu')
            ?? config('filament-advanced-rich-editor.slash.enabled')
            ?? true);
    }

    /**
     * Whether the mention menu is this package's own.
     *
     * Filament draws a mention as a label and nothing else. This one has room for a picture
     * and a line of context beneath the name, which is what tells two people with the same
     * name apart. Switched off, the field falls back to Filament's menu - the node, and
     * everything stored, is the same either way.
     */
    public function mentionMenu(bool|Closure $condition = true): static
    {
        $this->hasMentionMenu = $condition;

        return $this;
    }

    public function hasMentionMenu(): bool
    {
        return (bool) ($this->evaluate($this->hasMentionMenu) ?? config('filament-advanced-rich-editor.mentions.menu') ?? true);
    }

    /**
     * What the mention menu offers, for the view to hand to the script.
     *
     * Null where the menu is switched off and where the field mentions nothing: an
     * extension that replaces Filament's own has no business loading for a field that never
     * asked for mentions.
     *
     * The triggers are Filament's own description of them - the same array its extension is
     * configured with - so a provider written against Filament works here untouched. The key
     * is what lets the script call back for a search, the same way the media browser does.
     *
     * @return array<string, mixed>|null
     */
    public function getMentionMenuForJs(): ?array
    {
        if (! $this->hasMentionMenu()) {
            return null;
        }

        $triggers = $this->getMentionsForJs();

        if ($triggers === []) {
            return null;
        }

        // The rows go in front of the labels where a provider has them. Both are read from
        // the same list in the same order, which is how a trigger is matched to the provider
        // it was built from - Filament's own description carries no way back to it.
        $providers = array_values($this->getMentionProviders());

        foreach ($triggers as $index => $trigger) {
            $provider = $providers[$index] ?? null;

            if ($provider instanceof MentionProvider && $provider->hasRows()) {
                $triggers[$index]['items'] = $provider->getRows();
            }
        }

        return [
            'key' => $this->getKey(),
            'triggers' => $triggers,
        ];
    }

    /**
     * What the slash menu offers, and in which groups.
     *
     * Keys are group names, which are also the translation keys their headings are read
     * from; values are tool names, in the order they appear. `'headings'` expands to the
     * levels this field offers. A name the field does not have is dropped, exactly as it is
     * inside a toolbar dropdown.
     *
     * @param  array<string, array<int, string>> | Closure  $groups
     */
    public function slashGroups(array|Closure $groups): static
    {
        $this->slashGroups = $groups;

        return $this;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function getSlashGroups(): array
    {
        $groups = $this->evaluate($this->slashGroups)
            ?? config('filament-advanced-rich-editor.slash.groups');

        return is_array($groups) && $groups !== [] ? $groups : SlashMenu::GROUPS;
    }

    /**
     * The character that opens the menu.
     */
    public function slashChar(string|Closure $char): static
    {
        $this->slashChar = $char;

        return $this;
    }

    public function getSlashChar(): string
    {
        return (string) ($this->evaluate($this->slashChar)
            ?? config('filament-advanced-rich-editor.slash.char')
            ?? '/');
    }

    /**
     * What the slash menu offers, for the view to hand to the script. Null while the menu
     * is switched off, and while it has nothing to offer - a panel that can only ever say
     * "no matching command" is one that should not open.
     *
     * @return array<string, mixed>|null
     */
    public function getSlashMenuForJs(): ?array
    {
        if (! $this->hasSlashMenu()) {
            return null;
        }

        $menu = SlashMenu::for($this);

        return $menu['groups'] === [] ? null : $menu;
    }
}
