import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
import { defineConfig, loadEnv } from 'vite';

type ExposeDevServer = {
    publicUrl: string;
    hmr: { clientPort: number; host: string; protocol: 'ws' | 'wss' };
};

function resolveExposeDevServerPublicUrl(env: Record<string, string>): ExposeDevServer | null {
    const raw = env.VITE_DEV_SERVER_PUBLIC_URL?.trim();

    if (!raw) {
        return null;
    }

    try {
        const url = new URL(raw);
        const isSecure = url.protocol === 'https:';

        return {
            publicUrl: raw.replace(/\/$/, ''),
            hmr: {
                host: url.hostname,
                protocol: isSecure ? 'wss' : 'ws',
                clientPort: url.port ? parseInt(url.port, 10) : isSecure ? 443 : 80,
            },
        };
    } catch {
        return null;
    }
}

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '');
    const isLocal = env.VITE_LOCAL === 'true';
    const exposeDevServer = resolveExposeDevServerPublicUrl(env);
    const useHerdTls = isLocal && !exposeDevServer;

    const parsedVitePort = env.VITE_PORT ? parseInt(env.VITE_PORT, 10) : 5173;
    const vitePort = Number.isFinite(parsedVitePort) ? parsedVitePort : 5173;

    const exposeCorsOrigins = [env.APP_URL, exposeDevServer?.publicUrl].filter(
        (origin): origin is string => typeof origin === 'string' && origin.length > 0,
    );

    return {
        plugins: [
            laravel({
                input: ['resources/js/app.ts'],
                ssr: 'resources/js/ssr.ts',
                ...(useHerdTls && { detectTls: 'vgc-draft.test' }),
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
        ...(exposeDevServer && {
            server: {
                host: true,
                strictPort: true,
                port: vitePort,
                origin: exposeDevServer.publicUrl,
                hmr: exposeDevServer.hmr,
                cors: {
                    origin: exposeCorsOrigins.length > 0 ? exposeCorsOrigins : true,
                },
            },
        }),
    };
});
