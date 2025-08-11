import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import {defineConfig} from 'vite';

export default defineConfig(({mode}) => {
    return {
        plugins: [
            laravel({
                input: ["resources/css/app.css", "resources/js/app.jsx"],
                refresh: true,
            }),
            react(),
            tailwindcss(),
        ],
        server: {
            hmr: {
                host: 'mysql-dumper.test'
            },
            host: '0.0.0.0',
            cors: true
        },
        esbuild: {
            pure: mode === 'production' ? ['console.log'] : [],
        },
        resolve: {
            alias: {
                // 'ziggy-js': resolve(__dirname, 'vendor/tightenco/ziggy'),
            },
        },
    }
});
