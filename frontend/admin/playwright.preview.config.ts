import { defineConfig, devices } from '@playwright/test'

// Real Admin + Nuxt renderer; only the API data/session are test fixtures.
export default defineConfig({
  testDir: './e2e-preview',
  workers: 1,
  timeout: 60_000,
  outputDir: '../../.tmp/admin-preview-e2e',
  use: {
    baseURL: 'http://127.0.0.1:5176',
    ...devices['Desktop Chrome'],
    reducedMotion: 'reduce',
    trace: 'retain-on-failure',
  },
  webServer: [
    {
      command: 'node e2e/mock-api.mjs',
      cwd: '../client',
      url: 'http://127.0.0.1:8015/api/v1/home-page',
      reuseExistingServer: false,
    },
    {
      command: 'node .output/server/index.mjs',
      cwd: '../client',
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
    {
      command: 'npm run dev -- --host 127.0.0.1 --port 5176',
      url: 'http://127.0.0.1:5176',
      reuseExistingServer: false,
      env: { VITE_CLIENT_URL: 'http://127.0.0.1:3015' },
    },
  ],
})
