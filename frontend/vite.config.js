import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import path from 'path'

export default defineConfig(({ mode }) => {
  const env = loadEnv(mode, process.cwd(), '')
  const platform = env.VITE_PLATFORM || 'sales'
  const apiBase = env.VITE_API_BASE || '/api'
  const apiTarget = env.VITE_API_TARGET || 'http://localhost:8000'
  const defaultPort = platform === 'admin' ? 8081 : (platform === 'client' ? 8082 : 8080)
  const devPort = parseInt(env.VITE_DEV_PORT || String(defaultPort), 10)

  const entryMap = {
    admin: 'admin.html',
    sales: 'sales.html',
    client: 'client.html'
  }

  return {
    plugins: [vue()],
    resolve: {
      alias: {
 '@': path.resolve(__dirname, 'src')
      }
    },
    root: '.',
    base: '/',
    define: {
 __PLATFORM__: JSON.stringify(platform)
    },
    server: {
 port: devPort,
 host: '0.0.0.0',
 proxy: {
 [apiBase]: {
 target: apiTarget,
 changeOrigin: true
 }
 }
 },
 build: {
 outDir: path.resolve(__dirname, `dist/${platform}`),
 rollupOptions: {
 input: entryMap[platform] || 'sales.html',
 output: {
 manualChunks: {
 vendor: ['vue', 'vue-router', 'pinia'],
 elementPlus: ['element-plus', '@element-plus/icons-vue'],
 echarts: ['echarts']
 }
 }
 }
 }
 }
})
