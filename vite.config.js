import { defineConfig } from 'vite'
import path from 'path'

export default defineConfig({
  plugins: [],
  publicDir: false,
  build: {
    manifest: true,
    outDir: 'public_html/build',
    rollupOptions: {
      input: {
        app: path.resolve(__dirname, 'resources/js/app.js'),
        css: path.resolve(__dirname, 'resources/css/app.css')
      }
    }
  },
  server: {
    host: 'localhost',
    port: 5173,
    strictPort: false,
    hmr: {
      host: 'localhost'
    }
  },
  resolve: {
    alias: {
      '@': path.resolve(__dirname, 'resources/js')
    }
  }
})
