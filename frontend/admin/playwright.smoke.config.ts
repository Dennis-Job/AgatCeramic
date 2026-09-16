import { defineConfig, devices } from '@playwright/test'

export default defineConfig({
  testDir: './smoke',
  fullyParallel: false,
  workers: 1,
  forbidOnly: Boolean(process.env.CI),
  retries: 0,
  reporter: process.env.CI ? 'github' : 'list',
  outputDir: '/tmp/admin-smoke-playwright-results',
  timeout: 45_000,
  expect: { timeout: 10_000 },
  use: {
    baseURL: process.env.ADMIN_SMOKE_BASE_URL ?? 'http://admin-smoke:4173',
    screenshot: 'off',
    trace: 'off',
    video: 'off',
  },
  projects: [{ name: 'chromium', use: { ...devices['Desktop Chrome'] } }],
})
