import { defineConfig } from '@playwright/test'

export default defineConfig({
  testDir: './e2e',
  testMatch: '*.spec.ts',
  workers: 1,
  timeout: 60_000,
  outputDir: '../../.tmp/client-e2e',
  use: {
    baseURL: 'http://127.0.0.1:3015',
    browserName: 'chromium',
    contextOptions: { reducedMotion: 'reduce' },
  },
  webServer: [
    {
      command: 'node e2e/mock-api.mjs',
      url: 'http://127.0.0.1:8015/api/v1/home-page',
      reuseExistingServer: false,
    },
    {
      command: 'node .output/server/index.mjs',
      url: 'http://127.0.0.1:3015',
      reuseExistingServer: false,
      env: {
        PORT: '3015',
        HOST: '127.0.0.1',
        NUXT_PUBLIC_API_BASE: 'http://127.0.0.1:8015/api/v1',
        NUXT_API_BASE_INTERNAL: 'http://127.0.0.1:8015/api/v1',
        NUXT_PUBLIC_SITE_URL: 'https://agatceramic.example',
      },
    },
  ],
})
