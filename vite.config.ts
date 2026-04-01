import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig, loadEnv } from 'vite';

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const isLocal = env.VITE_LOCAL === 'true';

    return {
        plugins: [
            laravel({
                input: ['resources/js/app.ts'],
                ssr: 'resources/js/ssr.ts',
                ...(isLocal && { detectTls: 'vgc-draft.test' }),
                refresh: true,
            }),
            tailwindcss(),
            vue({
                template: {
                    transformAssetUrls: {
                        base: null,
                        includeAbsolute: false,
                    },
                },
            }),
        ],
        build: {
            // Only when VITE_LOCAL=true (e.g. `npm run watch`): widen file watching for `vite build --watch`.
            // Omit on production/CI so `vite build` never enables Rollup watch.
            ...(isLocal && {
                watch: {
                    include: ['resources/**'],
                },
            }),
        },
    };
});
