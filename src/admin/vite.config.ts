import { fileURLToPath, URL } from 'node:url'
import path from 'node:path'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

/** Vite dev server used by the WordPress admin page. Must match AdminMenu.php. */
const DEV_SERVER_HOST = '127.0.0.1'
const DEV_SERVER_PORT = 5173

// https://vite.dev/config/
export default defineConfig(({ command }) => ({
  plugins: [
    vue(),
    vueDevTools(),
  ],
  // In dev the app is served from the Vite server root. In production the
  // files are loaded from lib/admin/, so asset URLs must stay relative.
  base: command === 'build' ? './' : '/',
  server: {
    host: DEV_SERVER_HOST,
    port: DEV_SERVER_PORT,
    // Keep the port fixed so the WordPress enqueue URL always matches.
    strictPort: true,
    origin: `http://${DEV_SERVER_HOST}:${DEV_SERVER_PORT}`,
    // The WordPress page runs on a different origin than the dev server.
    cors: true,
    hmr: {
      host: DEV_SERVER_HOST,
      protocol: 'ws',
    },
  },
  build: {
    outDir: path.resolve(import.meta.dirname, '../../lib/admin'),
    emptyOutDir: true,
    rollupOptions: {
      input: path.resolve(import.meta.dirname, 'src/main.ts'),

      output: {
        entryFileNames: 'main.js',
        chunkFileNames: 'chunk-[name].js',
        assetFileNames: '[name].[ext]',
      },
    },
  },
  resolve: {
    alias: {
      'vue': path.resolve(import.meta.dirname, 'node_modules/vue'),
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
}))
