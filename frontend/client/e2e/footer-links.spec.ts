import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=footer-links')
})

test('footer uses independent links, social column and linked bottom text in public SSR and saved preview', async ({
  page,
  request,
  context,
}, testInfo) => {
  const errors: string[] = []
  page.on('pageerror', (error) => errors.push(error.message))
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) errors.push(message.text())
  })
  const html = await (await request.get('/about')).text()
  expect(html).toContain('Мы в соцсетях')
  expect(html).toContain('Политика конфиденциальности')
  await page.goto('/about')
  const footer = page.getByRole('contentinfo')
  await expect(
    footer.getByRole('link', { name: 'Доставка плитки' }),
  ).toHaveAttribute('href', '/about#delivery')
  await expect(
    footer.getByRole('link', { name: 'Главная', exact: true }),
  ).toHaveCount(0)
  await expect(footer.getByRole('link', { name: 'Telegram' })).toHaveAttribute(
    'href',
    'https://t.me/agat_test',
  )
  await expect(
    footer.getByRole('link', { name: 'Политика конфиденциальности' }),
  ).toHaveAttribute('href', '/about#privacy')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await footer.scrollIntoViewIfNeeded()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await expect(
      footer.getByRole('heading', { name: 'Мы в соцсетях' }),
    ).toBeVisible()
    await footer.screenshot({
      path: testInfo.outputPath(`footer-${width}.png`),
    })
  }
  const social = footer.getByRole('link', { name: 'Telegram' })
  await social.focus()
  await expect(social).toBeFocused()
  expect(
    (await new AxeBuilder({ page }).include('footer').analyze()).violations,
  ).toEqual([])
  await context.addCookies([
    {
      name: 'preview_test_session',
      value: 'employee',
      domain: '127.0.0.1',
      path: '/',
    },
  ])
  await page.goto('/preview/about')
  await expect(
    page.getByRole('contentinfo').getByRole('link', { name: 'Telegram' }),
  ).toHaveAttribute('href', 'https://t.me/agat_test')
  expect(errors).toEqual([])
})

test('explicit empty lists hide columns and absent or unsafe bottom URL stays plain text', async ({
  page,
  request,
}) => {
  for (const scenario of ['footer-empty', 'footer-unsafe']) {
    await request.get(`http://127.0.0.1:8015/__scenario?name=${scenario}`)
    await page.goto('/about')
    const footer = page.getByRole('contentinfo')
    await expect(
      footer.getByRole('heading', { name: 'Навигация' }),
    ).toHaveCount(0)
    await expect(
      footer.getByRole('heading', { name: 'Мы в соцсетях' }),
    ).toHaveCount(0)
    await expect(footer.getByText('Политика конфиденциальности')).toBeVisible()
    await expect(
      footer.getByRole('link', { name: 'Политика конфиденциальности' }),
    ).toHaveCount(0)
    await expect(
      footer.locator('a[href^="javascript:"],a[href^="//"]'),
    ).toHaveCount(0)
  }
})
