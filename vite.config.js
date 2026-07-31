import { defineConfig } from 'vite';
import Symfony from '@symfony/reprise/vite';

// The deployed app lives under a sub path, so the deploy sets ASSET_BASE (see
// the build task in deploy.php). Keying this off the build mode instead - as
// the Encore config used to - silently breaks every local production build,
// because the assets end up at a path the dev server does not serve.
const base = process.env.ASSET_BASE || '/build/';

export default defineConfig({
    css: {
        preprocessorOptions: {
            scss: {
                // Bootstrap 5.3, Font Awesome and select2 are all still written against the
                // @import based Sass, so their own deprecation warnings say nothing about this
                // codebase - hundreds of lines of them per build, none of them actionable here.
                quietDeps: true,
            },
        },
    },
    base,
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        manifest: true,
        rollupOptions: {
            input: {
                app: './assets/app.js',
            },
        },
    },
    plugins: [
        Symfony({
            stimulus: 'assets/controllers.json',
        }),
    ],
    optimizeDeps: {
        // @symfony/reprise/stimulus imports virtual:symfony/controllers, which only
        // the plugin above can resolve. Pre-bundling runs through esbuild without
        // plugins, so the dev server fails to start unless it is left out of it.
        exclude: ['@symfony/reprise'],
    },
});
