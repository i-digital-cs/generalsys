import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    plugins: [
        laravel({
            input: [
                'resources/scss/app.scss',
                'resources/scss/app.rtl.scss',
                'resources/js/app.js',
                'resources/js/pages/login.js',
                'resources/js/pages/employee-form.js',
                'resources/js/pages/valuation-form.js',
                'resources/js/pages/clock-photos.js',
            ],
            refresh: true,
        }),
    ],
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3 internally uses the legacy @import/if() syntax
                // that Dart Sass deprecates (full migration lands in Bootstrap
                // v6) — silence dependency noise plus our own @import usage.
                quietDeps: true,
                silenceDeprecations: ['import', 'if-function'],
            },
        },
    },
});
