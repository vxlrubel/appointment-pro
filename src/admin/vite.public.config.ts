import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'node:path'
export default defineConfig({
  define: { 'process.env.NODE_ENV': JSON.stringify('production') },
  plugins: [vue()],
  resolve: {
    alias: Object.fromEntries(
      ['vue', 'pinia', 'vue-router'].map((name) => [
        name,
        path.resolve(import.meta.dirname, 'node_modules', name),
      ]),
    ),
  },
  build: {
    outDir: path.resolve(import.meta.dirname, '../../lib/public'),
    emptyOutDir: true,
    cssCodeSplit: false,
    lib: {
      entry: path.resolve(import.meta.dirname, '../public/main.js'),
      name: 'AppointmentPublic',
      formats: ['iife'],
      fileName: () => 'public.js',
    },
    rollupOptions: { output: { assetFileNames: 'public.css' } },
  },
})
