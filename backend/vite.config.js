import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue'; // 引入插件

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        vue(), // 啟動 Vue 支援
    ],
    server: {
        host: '0.0.0.0',
        hmr: {
            host: '35.201.244.184' // 您的 GCP IP
        },
    },
});