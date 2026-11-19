import { expect, test } from '@playwright/test'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=standalone-default')
})

test('desktop filters apply without a button, retain cards while pending and ignore stale responses', async ({
  page,
}) => {
  await page.goto('/catalog?category=tile&page=2#catalog-products')
  const form = page.getByRole('complementary')
  await expect(
    form.getByRole('button', { name: 'Применить', exact: true }),
  ).toBeHidden()
  let release: () => void = () => {}
  const held = new Promise<void>((resolve) => {
    release = resolve
  })
  await page.route('**/catalog?**', async (route) => {
    if (new URL(route.request().url()).searchParams.get('in_stock') === '1') {
      await held
    }
    await route.continue().catch(() => {})
  })
  await form.getByLabel('В наличии', { exact: true }).check()
  await expect(page).toHaveURL(/in_stock=1/)
  await expect(page.locator('.catalog-results')).toHaveAttribute(
    'aria-busy',
    'true',
  )
  await expect(page.locator('.product-card')).toHaveCount(3)
  await expect(form.getByLabel('В наличии', { exact: true })).toBeEnabled()
  await expect(form.getByLabel('Серый', { exact: true }).first()).toBeEnabled()
  await form.getByLabel('В наличии', { exact: true }).uncheck()
  await expect(page).not.toHaveURL(/in_stock=|page=/)
  await expect(page.locator('.product-card')).toHaveCount(24)
  release()
  await expect(page.locator('.catalog-results')).toHaveAttribute(
    'aria-busy',
    'false',
  )
  await expect(page.locator('.product-card')).toHaveCount(24)
  await form.getByLabel('В наличии', { exact: true }).check()
  await expect(page.locator('.product-card')).toHaveCount(14)
  await page.goBack()
  await expect(form.getByLabel('В наличии', { exact: true })).not.toBeChecked()
  await expect(page.locator('.product-card')).toHaveCount(24)
})

test('price input debounces complete valid values and an immediate selection flushes the latest price', async ({
  page,
}) => {
  await page.goto('/catalog?category=tile')
  const form = page.getByRole('complementary')
  const requests: string[] = []
  page.on('request', (request) => {
    const url = new URL(request.url())
    if (
      url.pathname === '/api/v1/catalog' &&
      url.searchParams.has('price_from')
    )
      requests.push(url.searchParams.get('price_from')!)
  })
  await form.getByLabel('Цена от').fill('120')
  await form.getByLabel('Цена от').fill('150')
  await expect(page).toHaveURL(/price_from=150/)
  await expect(page.locator('.product-card')).toHaveCount(21)
  expect(requests).toEqual(['150'])
  await form.getByLabel('Цена от').fill('1.234')
  await page.waitForTimeout(450)
  await expect(page).toHaveURL(/price_from=150/)
  expect(requests).toEqual(['150'])
  await form.getByLabel('Цена от').fill('200')
  await form.getByLabel('Цена до').fill('100')
  await form.getByLabel('Цена до').press('Enter')
  await page.waitForTimeout(450)
  await expect(page).not.toHaveURL(/price_to=/)
  await form.getByLabel('Цена до').fill('250')
  await form.getByLabel('В наличии', { exact: true }).check()
  await expect(page).toHaveURL(/price_from=200/)
  await expect(page).toHaveURL(/price_to=250/)
  await expect(page).toHaveURL(/in_stock=1/)
  await expect(page.locator('.product-card')).toHaveCount(3)
  await form
    .getByRole('group', { name: 'Длина · мм', exact: true })
    .getByLabel('600', { exact: true })
    .check()
  await expect
    .poll(() =>
      JSON.parse(new URL(page.url()).searchParams.get('attributes') || '{}'),
    )
    .toEqual({ 4: { values: ['600'] } })
})

test('auto filtering preserves scroll and resize cancels pending desktop input before mobile editing', async ({
  page,
}) => {
  await page.goto('/catalog?category=tile#catalog-products')
  const form = page.getByRole('complementary')
  const stock = form.getByLabel('В наличии', { exact: true })
  await stock.scrollIntoViewIfNeeded()
  const before = await page.evaluate(() => window.scrollY)
  await stock.check()
  await expect(page.locator('.product-card')).toHaveCount(14)
  expect(
    Math.abs((await page.evaluate(() => window.scrollY)) - before),
  ).toBeLessThan(5)
  await form.getByLabel('Цена от').fill('120')
  await page.setViewportSize({ width: 640, height: 900 })
  await page.getByRole('button', { name: 'Фильтры', exact: true }).click()
  const drawer = page.getByRole('dialog')
  await expect(
    drawer.getByRole('button', { name: 'Применить', exact: true }),
  ).toBeVisible()
  await drawer.getByLabel('В наличии', { exact: true }).uncheck()
  await page.waitForTimeout(450)
  await expect(page).toHaveURL(/in_stock=1/)
  await expect(page).not.toHaveURL(/price_from=/)
  await page.keyboard.press('Escape')
  await expect(page.locator('.product-card')).toHaveCount(14)
})
