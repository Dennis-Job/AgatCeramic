import { defineConfig } from 'vite'
import type { ViteUserConfigExport } from 'vitest/config'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

const previewBackendUrl =
  process.env.ADMIN_PREVIEW_BACKEND_URL ?? 'http://127.0.0.1:8000'

// https://vite.dev/config/
export default defineConfig({
  plugins: [vue(), tailwindcss()],
  preview: {
    allowedHosts: ['admin-smoke'],
    proxy: {
      '/api': { target: previewBackendUrl },
      '/sanctum': { target: previewBackendUrl },
    },
  },
  test: {
    environment: 'jsdom',
    include: ['tests/**/*.test.ts'],
    restoreMocks: true,
  },
} as ViteUserConfigExport)
