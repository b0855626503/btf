// packages/Gametech/Admin/vite.config.js
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue2'
import inject from '@rollup/plugin-inject'
import laravel from 'laravel-vite-plugin'
import path from 'path'
import {fileURLToPath} from 'url';

let __dirname;
__dirname = path.dirname(fileURLToPath(import.meta.url));
const adminPkgDir = __dirname
console.log(__dirname)
console.log(adminPkgDir)
const laravelRoot = path.resolve(adminPkgDir, '../../../')
console.log(laravelRoot)
// คีย์ที่ Blade เรียก (ต้องตรงตัวอักษร)
const ENTRY_KEY  = 'packages/Gametech/Admin/src/Resources/assets/js/app.js'
console.log(ENTRY_KEY)
// ไฟล์จริง (absolute)
const ENTRY_FILE = path.join(adminPkgDir, 'src/Resources/assets/js/app.js')
console.log(ENTRY_FILE)

export default defineConfig({
    plugins: [
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        inject({
            $: 'jquery',
            jQuery: 'jquery',
            // ห้าม map 'window.$' / 'window.jQuery' เพราะจะชนตอนคุณเซ็ต window.$ เอง
        }),
        laravel({
            input: { [ENTRY_KEY]: ENTRY_FILE },
            hotFile: path.join(laravelRoot, 'storage/framework/vite.hot'),
            publicDirectory: path.join(laravelRoot, 'public'),
            buildDirectory: 'build',
            refresh: [
                path.join(laravelRoot, 'resources/views/**/*.blade.php'),
                path.join(adminPkgDir, 'src/Resources/views/**/*.blade.php'),
                path.join(adminPkgDir, 'src/Resources/assets/**/*'),
            ],
        }),
    ],
    root: path.resolve(__dirname),
    resolve: {
        alias: {
            'vue$': 'vue/dist/vue.esm.js',
            'bootstrap-vue$': 'bootstrap-vue/dist/bootstrap-vue.esm.js',
            'portal-vue$': 'portal-vue/dist/portal-vue.esm.js',
            'vue-functional-data-merge$': 'vue-functional-data-merge/dist/index.js',
        },
    },
    server: {
        host: 'admin.localhost',
        port: 5173,
        strictPort: true,

        // อนุญาตให้หน้า Laravel :8000 โหลด asset จาก :5173
        cors: {
            origin: ['http://admin.localhost:8000'],
            credentials: true,
            methods: ['GET','POST','PUT','PATCH','DELETE','OPTIONS'],
            allowedHeaders: ['*'],
            exposedHeaders: ['*'],
        },

        // HMR ให้ชี้โดเมนเดียวกัน (แก้ overlay/ws ข้ามโดเมน)
        hmr: {
            host: 'admin.localhost',
            protocol: 'ws',
            clientPort: 5173,
            overlay: true,
        },

        fs: { allow: [adminPkgDir] },
    },
    build: {
        target: 'es2018',
        outDir: path.join(laravelRoot, 'public', 'build'),
        emptyOutDir: true,
        commonjsOptions: { transformMixedEsModules: true },
    },
    define: { 'process.env': {} },
})
