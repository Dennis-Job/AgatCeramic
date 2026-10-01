import { expect, test } from '@playwright/test'

test('development preview authenticates in a cross-origin iframe without DevTools errors', async ({
  page,
  context,
}, testInfo) => {
  await context.addCookies([
    {
      name: 'preview_test_session',
      value: 'employee',
      url: 'http://127.0.0.1:8015',
      sameSite: 'Lax',
    },
  ])
  const errors: string[] = []
  page.on('console', (message) => {
    if (message.type() === 'error') {
      errors.push(message.text())
      console.error(message.text())
    }
  })
  page.on('pageerror', (error) => errors.push(error.message))
  const response = page.waitForResponse((response) =>
    response.url().endsWith('/admin/content-preview/about'),
  )
  await page.goto('http://127.0.0.1:8015/__editor')
  const previewResponse = await response
  expect(previewResponse.status()).toBe(200)
  const headers = await previewResponse.request().allHeaders()
  expect(headers.origin).toBe('http://127.0.0.1:3016')
  expect(headers.referer).toBe('http://127.0.0.1:3016/')
  expect(headers.cookie).toContain('preview_test_session=employee')
  const frame = page.frameLocator('iframe')
  await expect(frame.getByText('Черновая шапка')).toBeVisible()
  const previewFrame = page
    .frames()
    .find((frame) => frame.url().includes('/preview/'))!
  expect(await previewFrame.evaluate(() => window.self !== window.top)).toBe(
    true,
  )
  expect(
    await previewFrame.evaluate(
      () =>
        (window as Window & { __NUXT_DEVTOOLS_DISABLE__?: boolean })
          .__NUXT_DEVTOOLS_DISABLE__,
    ),
  ).toBe(true)
  await page.screenshot({
    path: testInfo.outputPath('cross-origin-preview.png'),
    fullPage: true,
  })
  expect(errors).toEqual([])
})

test('standalone development pages keep Nuxt DevTools enabled', async ({
  page,
}) => {
  await page.goto('http://127.0.0.1:3016/')
  await expect
    .poll(() =>
      page.evaluate(() =>
        Boolean(
          (window as Window & { __NUXT_DEVTOOLS_HOST__?: unknown })
            .__NUXT_DEVTOOLS_HOST__,
        ),
      ),
    )
    .toBe(true)
  expect(
    await page.evaluate(
      () =>
        (window as Window & { __NUXT_DEVTOOLS_DISABLE__?: boolean })
          .__NUXT_DEVTOOLS_DISABLE__,
    ),
  ).toBeUndefined()
})
