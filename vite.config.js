import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import { fileURLToPath, URL } from 'node:url';
import fs from 'node:fs';
import path from 'node:path';

function normalizeLaravelManifestPlugin() {
    return {
        name: 'normalize-laravel-manifest',
        writeBundle() {
            const manifestPath = path.resolve('public/build/manifest.json');
            if (!fs.existsSync(manifestPath)) return;

            const manifest = JSON.parse(fs.readFileSync(manifestPath, 'utf8'));
            const normalized = { ...manifest };

            const normalizeKey = (key) => {
                const value = String(key).replace(/\\/g, '/');
                const index = value.indexOf('resources/');
                return index >= 0 ? value.slice(index) : value;
            };

            Object.entries(manifest).forEach(([key, entry]) => {
                const normalizedKey = normalizeKey(key);
                if (normalizedKey !== key) {
                    normalized[normalizedKey] = {
                        ...entry,
                        src: entry.src ? normalizeKey(entry.src) : entry.src,
                        imports: entry.imports?.map(normalizeKey),
                        dynamicImports: entry.dynamicImports?.map(normalizeKey),
                    };
                }
            });

            fs.writeFileSync(manifestPath, `${JSON.stringify(normalized, null, 2)}\n`);
        },
    };
}

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.js'],
            refresh: true,
        }),
        // Disable absolute-URL resolution — /assets/... are served from public/ by the web server
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        normalizeLaravelManifestPlugin(),
    ],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
