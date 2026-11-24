<?php

declare(strict_types=1);

namespace Kisame76\FilamentAdvancedRichEditor\Tests\Fixtures\Livewire;

use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

/**
 * The Livewire component a test schema is bound to.
 *
 * A schema always resolves its operation, model and utilities through its Livewire
 * component, so the field needs one even in tests that never render anything.
 *
 * @property-read Schema $form the schema a subclass declares in `form()`, which Filament
 *                             hands out under the method's name
 */
class TestSchemaComponent extends Component implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;
}
