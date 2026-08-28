<?php

declare(strict_types=1);

/**
 * Registers this package's view namespace for the container Larastan analyses against.
 *
 * `view-string` is not a syntactic check: `ViewStringType::accepts()` asks a live
 * `View\Factory` whether the name exists. In an application that Factory is configured by
 * the time Larastan boots; in a package it is not, because the namespace is registered by
 * this package's own service provider, which nothing here ever runs. Without this file,
 * every `view('filament-advanced-rich-editor::…')` in `src` is reported as a string where
 * a view-string was expected - a finding about the analyser's setup rather than about the
 * code.
 *
 * Registering it here rather than silencing the errors keeps the check doing its job: a
 * view name with a typo in it is still a name the Factory cannot find.
 */

use Illuminate\Support\Facades\View;

if (! function_exists('view')) {
    return;
}

try {
    View::addNamespace('filament-advanced-rich-editor', __DIR__.'/resources/views');
} catch (Throwable) {
    // No container to configure - the analysis then behaves exactly as it did before this
    // file existed, which is a worse result but not a broken run.
}
