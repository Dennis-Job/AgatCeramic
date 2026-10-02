import AxeBuilder from '@axe-core/playwright'
import { expect, test } from './fixtures'
import { mockCatalogApi, product } from './catalogApi'

test('product badges show scoped totals and reset has surface and hover states', async ({
  page,
}) => {
  await mockCatalogApi(page)
  const countQueries: URLSearchParams[] = []
  await page.route('**/api/v1/admin/products?*', (route) => {
    const query = new URL(route.request().url()).searchParams
    if (query.get('per_page') !== '1') return route.fallback()
    countQueries.push(query)
    const scoped =
      query.has('category_id') || query.has('brand_id') || query.has('search')
    const total =
      query.get('is_active') === '1'
        ? scoped
          ? 7
          : 137
        : query.get('is_active') === '0'
          ? scoped
            ? 0
            : 21
          : query.get('is_on_sale') === '1'
            ? scoped
              ? 2
              : 34
            : scoped
              ? 5
              : 124
    return route.fulfill({
      json: {
        data: [product],
        meta: {
          total,
          current_page: 1,
          last_page: Math.max(1, total),
          per_page: 1,
        },
      },
    })
  })
  await page.goto('/products')
  const filters = page.getByRole('search')
  const badge = (name: string) =>
    filters
      .getByRole('radio', { name, exact: true })
      .locator('..')
      .locator('[id]')
  const activity = filters.getByRole('group', { name: 'Активность' })
  const sale = filters.getByRole('group', { name: 'Распродажа', exact: true })
  await expect(activity.locator('legend')).toHaveClass('sr-only')
  await expect(sale.locator('legend')).toHaveClass('sr-only')
  for (const [name, value] of [
    ['Активные', '137'],
    ['Скрытые', '21'],
    ['Распродажа', '34'],
    ['Не распродажа', '124'],
  ])
    await expect(badge(name!)).toHaveText(value!)
  expect(countQueries).toHaveLength(4)
  const reset = filters.getByRole('button', { name: 'Сбросить' })
  await expect(reset).toBeDisabled()
  await activity.getByRole('radio', { name: 'Активные' }).locator('..').click()
  await expect(reset).toBeEnabled()
  await expect(reset).toHaveCSS('background-color', 'rgb(255, 255, 255)')
  await reset.hover()
  await expect(reset).toHaveCSS('background-color', 'rgb(235, 244, 255)')
  await expect(reset).toHaveCSS('color', 'rgb(0, 80, 224)')
  await page.mouse.move(0, 0)
  await sale
    .getByRole('radio', { name: 'Распродажа', exact: true })
    .locator('..')
    .click()
  await expect(badge('Активные')).toHaveText('137')
  expect(countQueries).toHaveLength(4)

  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await expect
      .poll(() =>
        page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
      )
      .toBe(true)
    await filters.screenshot({
      path: `.tmp/product-filter-counts-review/filters-${width}.png`,
    })
    for (const label of await filters.getByRole('radio').locator('..').all())
      expect(
        await label.evaluate(
          (element) => element.scrollWidth <= element.clientWidth,
        ),
      ).toBe(true)
  }
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])

  await filters.getByLabel('Категория').click()
  await page.getByRole('button', { name: 'Керамогранит', exact: true }).click()
  await expect(badge('Активные')).toHaveText('7')
  await expect(badge('Скрытые')).toHaveText('0')
  expect(countQueries).toHaveLength(8)
  for (const query of countQueries.slice(-4)) {
    expect(query.get('category_id')).toBe('1')
    expect(query.has('is_active') && query.has('is_on_sale')).toBe(false)
  }
  await filters.getByLabel('Поиск', { exact: true }).fill('монте')
  await expect.poll(() => countQueries.length).toBe(12)
  for (const query of countQueries.slice(-4))
    expect(query.get('search')).toBe('монте')
  await reset.click()
  await expect(badge('Активные')).toHaveText('137')
  await expect(reset).toBeDisabled()
  await page.emulateMedia({ forcedColors: 'active' })
  await activity.getByRole('radio', { name: 'Активные' }).focus()
  await page.keyboard.press('Space')
  await expect
    .poll(() =>
      activity
        .getByRole('radio', { name: 'Активные' })
        .locator('..')
        .evaluate((element) => {
          const sample = document.createElement('span')
          sample.style.color = 'HighlightText'
          sample.style.forcedColorAdjust = 'none'
          element.append(sample)
          const expected = getComputedStyle(sample).color
          sample.remove()
          return getComputedStyle(element).color === expected
        }),
    )
    .toBe(true)
  await filters.screenshot({
    path: '.tmp/product-filter-counts-review/forced-colors.png',
  })
})

test('counter loading and partial failure preserve usable product results', async ({
  page,
  browserIssueGuard,
}) => {
  await mockCatalogApi(page)
  browserIssueGuard.allowApiError(500, '/api/v1/admin/products')
  let release!: () => void
  const pending = new Promise<void>((resolve) => {
    release = resolve
  })
  await page.route('**/api/v1/admin/products?*', async (route) => {
    const query = new URL(route.request().url()).searchParams
    if (query.get('per_page') !== '1') return route.fallback()
    await pending
    if (query.get('is_active') === '0')
      return route.fulfill({
        status: 500,
        json: { error: { message: 'Счётчик недоступен' } },
      })
    return route.fulfill({
      json: {
        data: [],
        meta: { total: 0, current_page: 1, last_page: 1, per_page: 1 },
      },
    })
  })
  await page.goto('/products')
  const filters = page.getByRole('search')
  const badge = (name: string) =>
    filters
      .getByRole('radio', { name, exact: true })
      .locator('..')
      .locator('[id]')
  await expect(page.getByText('Монте Тиберио', { exact: true })).toBeVisible()
  await expect(badge('Активные')).toHaveText('…')
  await filters.screenshot({
    path: '.tmp/product-filter-counts-review/counts-loading.png',
  })
  release()
  await expect(badge('Активные')).toHaveText('0')
  await expect(badge('Скрытые')).toHaveText('—')
  await expect(filters.getByRole('status')).toContainText(
    'Не удалось обновить некоторые счётчики',
  )
  await expect(page.getByText('Монте Тиберио', { exact: true })).toBeVisible()
  await filters.screenshot({
    path: '.tmp/product-filter-counts-review/counts-partial-error.png',
  })
})
