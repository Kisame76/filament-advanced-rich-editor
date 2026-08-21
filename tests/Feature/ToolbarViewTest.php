<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Blade;

it('compiles the forked editor view', function (): void {
    // The view is a fork carrying this package's own markup, and nothing else in the suite
    // renders it - so this is what catches a directive left unbalanced by an edit.
    $compiled = Blade::compileString(file_get_contents(__DIR__.'/../../resources/views/rich-editor.blade.php'));

    $file = tempnam(sys_get_temp_dir(), 'arte-view-').'.php';
    file_put_contents($file, $compiled);

    exec('php -l '.escapeshellarg($file).' 2>&1', $output, $status);

    unlink($file);

    expect($status)->toBe(0, implode(PHP_EOL, $output))
        // The two containers a pinned toolbar renders, and the flat loop it falls back to.
        ->and($compiled)->toContain('fi-arte-toolbar-flow')
        ->and($compiled)->toContain('fi-arte-toolbar-pinned');
});
