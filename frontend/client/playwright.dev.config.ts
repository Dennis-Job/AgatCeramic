import { defineConfig } from '@playwright/test'

export default defineConfig({
  testDir: './e2e-dev',
  workers: 1,
  timeout: 60_000,
  outputDir: '../../.tmp/client-dev-e2e',
  use: {
    browserName: 'chromium',
    trace: 'retain-on-failure',
    screenshot: 'only-on-failure',
  },
  webServer: [
    {
      command: 'node e2e/mock-api.mjs',
      url: 'http://127.0.0.1:8015/api/v1/home-page',
      reuseExistingServer: false,
      env: { CLIENT_TEST_ORIGIN: 'http://127.0.0.1:3016' },
    },
    {
      command: 'npm run dev -- --host 127.0.0.1 --port 3016',
      url: 'http://127.0.0.1:3016',
      reuseExistingServer: false,
      env: {
        NUXT_PUBLIC_API_BASE: 'http://127.0.0.1:8015/api/v1',
        NUXT_API_BASE_INTERNAL: 'http://127.0.0.1:8015/api/v1',
      },
    },
  ],
})
