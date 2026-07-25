import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import { resolve } from 'node:path';
import { glob } from 'glob';

const workdoPackages = glob.sync('packages/workdo/*/src/Resources/js/app.tsx');

export default defineConfig({
    base: './',
    plugins: [
        laravel({
            input:
            [
                'resources/css/app.css',
                'resources/js/app.tsx',
                ...workdoPackages
            ],
            refresh: true,
        }),
        react(),
    ],
    server: {
        host: 'localhost',
        headers: {
            'Access-Control-Allow-Origin': '*',
            'Access-Control-Allow-Methods': 'GET,POST,PUT,DELETE,OPTIONS',
            'Access-Control-Allow-Headers': '*',
        },
        watch: {
            ignored: ['**/vendor/**', '**/node_modules/**']
        },
        fs: {
            allow: ['..', 'packages']
        }
    },

    esbuild: {
        jsx: 'automatic',
        jsxImportSource: 'react',
    },
    resolve: {
        alias: {
            'ziggy-js': resolve(__dirname, 'vendor/tightenco/ziggy'),
        },
    },
    build: {
        target: 'es2020',
        cssCodeSplit: true,
        chunkSizeWarningLimit: 1200,
        rollupOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        if (id.includes('react') || id.includes('react-dom') || id.includes('@inertiajs')) {
                            return 'vendor-react';
                        }
                        if (id.includes('@radix-ui') || id.includes('lucide-react')) {
                            return 'vendor-ui';
                        }
                        if (id.includes('@fullcalendar')) {
                            return 'vendor-calendar';
                        }
                        if (id.includes('@tiptap') || id.includes('tinymce')) {
                            return 'vendor-editor';
                        }
                        if (id.includes('recharts') || id.includes('d3')) {
                            return 'vendor-charts';
                        }
                        if (id.includes('html2pdf') || id.includes('jspdf') || id.includes('html2canvas')) {
                            return 'vendor-pdf';
                        }
                        if (id.includes('xlsx') || id.includes('papaparse')) {
                            return 'vendor-excel';
                        }
                    }
                }
            },
        },
        assetsDir: 'assets',
    }
});
