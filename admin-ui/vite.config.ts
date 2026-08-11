import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  base: '/admin/vue/',
  build: {
    outDir: '../admin/vue',
    emptyOutDir: true,
    cssCodeSplit: false,
    sourcemap: false,
    rollupOptions: {
      output: {
        entryFileNames: 'admin2.js',
        chunkFileNames: 'chunk-[name]-[hash].js',
        assetFileNames: (assetInfo) => assetInfo.name?.endsWith('.css') ? 'admin2.css' : 'asset-[name]-[hash][extname]'
      }
    }
  }
})
