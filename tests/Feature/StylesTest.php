<?php

declare(strict_types=1);

use Kisame76\FilamentAdvancedRichEditor\RichEditor\Styles;

/**
 * @return array<int, string>
 */
function styleKeys(mixed $styles): array
{
    return array_column($styles, 'key');
}

it('reads the styles out of the config in the order they are written', function (): void {
    withStyles([
        'lead' => ['label' => 'Lead', 'class' => 'text-lg', 'scope' => 'block'],
        'kicker' => ['label' => 'Kicker', 'class' => 'uppercase', 'scope' => 'inline'],
    ]);

    expect(styleKeys(Styles::all()))->toBe(['lead', 'kicker'])
        ->and(Styles::all()[0])->toMatchArray([
            'key' => 'lead',
            'label' => 'Lead',
            'class' => 'text-lg',
            'scope' => 'block',
        ]);
});

it('treats a style without a scope as a block style', function (): void {
    // The common case by a distance, and a missing key should pick the likely answer
    // rather than drop somebody's entry without saying anything.
    withStyles(['lead' => ['label' => 'Lead', 'class' => 'text-lg']]);

    expect(Styles::all()[0]['scope'])->toBe('block');
});

it('gives a block style the types that carry a direction, and lets it name its own', function (): void {
    withStyles([
        'lead' => ['label' => 'Lead', 'class' => 'text-lg'],
        'kicker' => ['label' => 'Kicker', 'class' => 'up', 'types' => ['heading']],
    ]);

    expect(Styles::all()[0]['types'])->toBe(Styles::BLOCK_TYPES)
        ->and(Styles::all()[1]['types'])->toBe(['heading']);
});

it('keeps only the block types it knows about', function (): void {
    withStyles(['lead' => ['label' => 'Lead', 'class' => 'text-lg', 'types' => ['heading', 'nonsense']]]);

    expect(Styles::all()[0]['types'])->toBe(['heading']);
});

it('drops a style that names no type it knows', function (): void {
    // An entry the editor could never apply anywhere is not an entry.
    withStyles(['lead' => ['label' => 'Lead', 'class' => 'text-lg', 'types' => ['nonsense']]]);

    expect(Styles::all())->toBe([]);
});

it('leaves an inline style without types, because a mark has none', function (): void {
    withStyles(['kicker' => ['label' => 'Kicker', 'class' => 'up', 'scope' => 'inline', 'types' => ['heading']]]);

    expect(Styles::all()[0]['types'])->toBe([]);
});

it('drops a style with an unknown scope', function (): void {
    withStyles(['lead' => ['label' => 'Lead', 'class' => 'text-lg', 'scope' => 'sideways']]);

    expect(Styles::all())->toBe([]);
});

it('drops a style with nothing to show or nothing to apply', function (): void {
    withStyles([
        'a' => ['label' => '', 'class' => 'text-lg'],
        'b' => ['label' => 'B', 'class' => '   '],
        'c' => ['label' => 'C'],
        'd' => 'not an array',
    ]);

    expect(Styles::all())->toBe([]);
});

it('keeps the classes a real design system is written in', function (): void {
    // Tailwind is what this lands in, and its class names carry colons, slashes, brackets
    // and leading hyphens. A pattern narrow enough to feel safe would reject most of them.
    withStyles(['t' => ['label' => 'T', 'class' => 'md:text-xl w-1/2 -mt-2 bg-[#fff]']]);

    expect(Styles::all()[0]['class'])->toBe('md:text-xl w-1/2 -mt-2 bg-[#fff]');
});

it('drops a class that could not be one', function (): void {
    // None of these can appear in a class attribute, so their presence means the config is
    // wrong rather than that somebody is attacking - and a wrong entry should be visibly
    // absent rather than quietly rendered as nonsense.
    foreach (['a"b', "a'b", 'a<b', 'a>b', 'a&b'] as $class) {
        withStyles(['x' => ['label' => 'X', 'class' => $class]]);

        expect(Styles::all())->toBe([]);
    }
});

it('collapses the whitespace between classes', function (): void {
    withStyles(['x' => ['label' => 'X', 'class' => "  text-lg\n  text-slate-600 "]]);

    expect(Styles::all()[0]['class'])->toBe('text-lg text-slate-600');
});

it('splits the styles by what they apply to', function (): void {
    withStyles([
        'lead' => ['label' => 'Lead', 'class' => 'text-lg'],
        'kicker' => ['label' => 'Kicker', 'class' => 'up', 'scope' => 'inline'],
    ]);

    expect(styleKeys(Styles::block()))->toBe(['lead'])
        ->and(styleKeys(Styles::inline()))->toBe(['kicker']);
});

it('lets a field say something else than the config does', function (): void {
    withStyles(['lead' => ['label' => 'Lead', 'class' => 'text-lg']]);

    $field = editor()->styles(['own' => ['label' => 'Own', 'class' => 'x']]);

    expect(styleKeys(Styles::for($field)))->toBe(['own'])
        // An empty list is a field saying it wants none, not a field saying nothing.
        ->and(Styles::for(editor()->styles([])))->toBe([])
        ->and(styleKeys(Styles::for(editor())))->toBe(['lead']);
});
