import { defineConfig } from 'vite';
import Symfony from '@symfony/reprise/vite';

// The deployed app lives under a sub path, so the deploy sets ASSET_BASE (see
// the build task in deploy.php). Keying this off the build mode instead - as
// the Encore config used to - silently breaks every local production build,
// because the assets end up at a path the dev server does not serve.
//
// It has to reach the reprise plugin: the plugin sets vite's `base` from its own
// publicPath, so setting `base` here does nothing but leave the font urls Font
// Awesome bakes into the stylesheet pointing at the domain root.
const publicPath = process.env.ASSET_BASE || '/build/';

export default defineConfig({
    css: {
        preprocessorOptions: {
            scss: {
                // Font Awesome is still written against the @import based Sass, so its own
                // deprecation warnings say nothing about this codebase - hundreds of lines of
                // them per build, none of them actionable here.
                quietDeps: true,
            },
        },
    },
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
        // The plugin's own publicPath has to stay at the document root: it ends up in
        // entrypoints.json, and rendering the tags runs those references through Symfony's
        // asset packages, which prepend the base path of the request themselves. Only the
        // urls vite writes into the built stylesheet need the sub path spelled out, and the
        // last config hook wins - hence a plugin, and hence one after reprise.
        {
            name: 'asset-base',
            config: () => ({ base: publicPath }),
        },
    ],
    optimizeDeps: {
        // @symfony/reprise/stimulus imports virtual:symfony/controllers, which only
        // the plugin above can resolve. Pre-bundling runs through esbuild without
        // plugins, so the dev server fails to start unless it is left out of it.
        exclude: ['@symfony/reprise'],
    },
});
