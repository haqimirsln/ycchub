import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/qr.js', 'resources/js/map.js', 'resources/js/email-editor.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    // Fonts referenced from the CSS use relative paths, so the build works whether the app
    // is served from a subdomain (ycc.conservems.my) or a subfolder (conservems.my/ycc-hub).
    experimental: {
        renderBuiltUrl(filename, { hostType }) {
            return hostType === 'css' ? { relative: true } : undefined;
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
