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
            ...(isLocal && {
                watch: {
                    include: ['resources/**'],
                },
            }),
        },
    };
});
