import { defineConfig } from 'vitest/config'

/**
 * The test runner for the scripts in `resources/js`.
 *
 * There is no bundler in this package and this does not introduce one: Vitest reads the
 * source files as the ES modules they already are, and nothing it does reaches
 * `resources/dist`, which stays a plain copy made by `composer build-assets`.
 *
 * `jsdom` rather than a real browser, because what is tested here is the logic behind the
 * media browser - paging, filtering, what is selected - and not how Filament draws it. A
 * browser would need a whole Filament application to render first, which is a fixture this
 * package has no way to keep honest.
 */
export default defineConfig({
    test: {
        environment: 'jsdom',
        include: ['tests/js/**/*.test.js'],
        restoreMocks: true,
    },
})
