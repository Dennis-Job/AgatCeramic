import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test.beforeEach(async ({ request }) => {
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=catalog-card-standard',
  )
  await request.get('http://127.0.0.1:8015/__cart/reset')
})

test('catalog uses the approved cards in SSR, at five widths and with actual purchase restrictions', async ({
  page,
  request,
}, testInfo) => {
  const html = await (await request.get('/catalog')).text()
  expect(html).toContain('600×600 мм')
  expect(html).toContain('card-brand')
  expect(html).toContain('В корзину +')
  const issues: string[] = []
  page.on('pageerror', (error) => issues.push(error.message))
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) issues.push(message.text())
  })
  await page.goto('/catalog')
  const cards = page.locator('#catalog-products .product-card')
  await expect(cards).toHaveCount(3)
  await expect(cards.first().locator('.card-brand')).toHaveCSS(
    'text-transform',
    'uppercase',
  )
  await expect(cards.first().locator('h3 + .card-meta')).toHaveText(
    'Керамогранит · 600×600 мм',
  )
  await expect(cards.nth(1).locator('.card-brand')).toHaveCount(0)
  await expect(cards.nth(1).locator('.card-meta')).toHaveText('Керамогранит')
  await expect(cards.nth(1)).toContainText('Цена по запросу')
  await expect(cards.nth(1)).toContainText('Изображение пока не добавлено')
  await expect(cards.nth(1).getByRole('button')).toBeDisabled()
  await expect(cards.nth(2).getByRole('button')).toBeDisabled()
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page
      .locator('#catalog-products .products')
      .screenshot({ path: testInfo.outputPath(`catalog-cards-${width}.png`) })
  }
  await cards
    .first()
    .getByRole('link', { name: /^Открыть / })
    .focus()
  await page.keyboard.press('Tab')
  const add = cards
    .first()
    .getByRole('button', { name: 'В корзину +', exact: true })
  await expect(add).toBeFocused()
  await expect(add).toHaveCSS('transform', 'none')
  await page.keyboard.press('Enter')
  await expect(
    page.getByRole('status').filter({ hasText: 'Товар добавлен в корзину.' }),
  ).toBeVisible()
  const stats = await (
    await request.get('http://127.0.0.1:8015/__cart/stats')
  ).json()
  expect(
    stats.requests
      .filter((entry: { method: string }) => entry.method === 'POST')
      .map((entry: { payload: unknown }) => entry.payload),
  ).toEqual([{ product_id: 1, quantity: 1 }])
  const violations = (await new AxeBuilder({ page }).analyze()).violations
  // The owner-approved palette is fixed; keep this existing placeholder finding explicit.
  const placeholderContrast = violations.filter(
    (violation) =>
      violation.id === 'color-contrast' &&
      violation.nodes.every(
        (node) => node.target.length === 1 && node.target[0] === '.image-empty',
      ),
  )
  expect(placeholderContrast).toHaveLength(1)
  expect(
    violations.filter((violation) => !placeholderContrast.includes(violation)),
  ).toEqual([])
  testInfo.annotations.push({
    type: 'known-a11y',
    description:
      'Approved muted/beige placeholder text has contrast 4.25:1; owner approval required before palette changes.',
  })
  expect(issues).toEqual([])
})

test('catalog reports a failed add inline without leaving the listing', async ({
  page,
}) => {
  await page.route('**/cart/items', (route) =>
    route.fulfill({
      status: 503,
      contentType: 'application/json',
      body: JSON.stringify({
        error: { code: 'unavailable', message: 'Unavailable' },
      }),
    }),
  )
  await page.goto('/catalog')
  const card = page.locator('#catalog-products .product-card').first()
  await card.hover()
  await card.getByRole('button', { name: 'В корзину +', exact: true }).click()
  await expect(
    page.locator('#catalog-products').getByRole('alert'),
  ).toBeVisible()
  await expect(page).toHaveURL(/\/catalog$/)
})

test.describe('touch catalog cards', () => {
  test.use({
    hasTouch: true,
    isMobile: true,
    viewport: { width: 320, height: 800 },
  })
  test('the shared cart action is visible without hover', async ({
    page,
  }, testInfo) => {
    await page.goto('/catalog')
    const cards = page.locator('#catalog-products .product-card')
    const add = cards
      .first()
      .getByRole('button', { name: 'В корзину +', exact: true })
    await expect(add).toHaveCSS('position', 'static')
    await expect(add).toHaveCSS('transform', 'none')
    await cards
      .first()
      .screenshot({ path: testInfo.outputPath('catalog-card-touch.png') })
    await add.click()
    await expect(
      page.getByRole('status').filter({ hasText: 'Товар добавлен в корзину.' }),
    ).toBeVisible()
  })
})
