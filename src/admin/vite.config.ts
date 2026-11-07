import { fileURLToPath, URL } from 'node:url'
import path from 'node:path'
import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import vueDevTools from 'vite-plugin-vue-devtools'

// https://vite.dev/config/
export default defineConfig({
  plugins: [
    vue(),
    vueDevTools(),
  ],
  build:{
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
      '@': fileURLToPath(new URL('./src', import.meta.url)),
    },
  },
})
