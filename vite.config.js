import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';

export default defineConfig({
    build: {
        // Browser source is never secret, but public production source maps expose
        // more implementation detail than required. Opt in only for a separately
        // governed deployment that restricts the generated artifacts.
        sourcemap: false,
        minify: true,
    },
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
    ],
});
