import AxeBuilder from '@axe-core/playwright'
import { expect, test } from './fixtures'
import { mockCatalogApi } from './catalogApi'

test('product badges show scoped totals and reset has surface and hover states', async ({
  page,
}) => {
  await mockCatalogApi(page)
  const countQueries: URLSearchParams[] = []
  let brandRequests = 0
  let categoryRequests = 0
  page.on('request', (request) => {
    const path = new URL(request.url()).pathname
    if (path.endsWith('/admin/brands')) brandRequests += 1
    if (path.endsWith('/admin/categories/tree')) categoryRequests += 1
  })
  await page.route('**/api/v1/admin/products/filter-counts*', (route) => {
    const query = new URL(route.request().url()).searchParams
    countQueries.push(query)
    const scoped =
      query.has('category_id') || query.has('brand_id') || query.has('search')
    const hasActivity = query.has('is_active')
    const hasSale = query.has('is_on_sale')
    const countFor = (activity?: boolean, sale?: boolean) => {
      if (activity !== undefined && sale !== undefined) {
        if (activity) {
          if (sale) return scoped ? 2 : 6
          return scoped ? 1 : 3
        }
        if (sale) return scoped ? 0 : 1
        return scoped ? 4 : 8
      }
      if (activity !== undefined) {
        if (activity) return scoped ? 7 : 137
        return scoped ? 0 : 21
      }
      if (sale) return scoped ? 2 : 34
      return scoped ? 5 : 124
    }
    const selectedActivity = hasActivity
      ? query.get('is_active') === '1'
      : undefined
    const selectedSale = hasSale ? query.get('is_on_sale') === '1' : undefined
    return route.fulfill({
      json: {
        data: {
          active: countFor(true, selectedSale),
          hidden: countFor(false, selectedSale),
          sale: countFor(selectedActivity, true),
          regular: countFor(selectedActivity, false),
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
  expect(countQueries).toHaveLength(1)
  expect(brandRequests).toBe(1)
  expect(categoryRequests).toBe(1)
  const reset = filters.getByRole('button', { name: 'Сбросить' })
  await expect(reset).toBeDisabled()
  await activity.getByRole('radio', { name: 'Активные' }).locator('..').click()
  await expect(reset).toBeEnabled()
  await expect(reset).toHaveCSS('background-color', 'rgb(255, 255, 255)')
  await reset.hover()
  await expect(reset).toHaveCSS('background-color', 'rgb(217, 234, 255)')
  await expect(reset).toHaveCSS('color', 'rgb(0, 80, 224)')
  await page.mouse.move(0, 0)
  await expect(badge('Распродажа')).toHaveText('6')
  await expect(badge('Не распродажа')).toHaveText('3')
  expect(countQueries).toHaveLength(2)
  await sale
    .getByRole('radio', { name: 'Распродажа', exact: true })
    .locator('..')
    .click()
  await expect(badge('Активные')).toHaveText('6')
  await expect(badge('Скрытые')).toHaveText('1')
  expect(countQueries).toHaveLength(3)

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
  await expect(badge('Активные')).toHaveText('2')
  await expect(badge('Скрытые')).toHaveText('0')
  expect(countQueries).toHaveLength(4)
  expect(countQueries.at(-1)?.get('category_id')).toBe('1')
  await filters.getByLabel('Поиск', { exact: true }).fill('монте')
  await expect.poll(() => countQueries.length).toBe(5)
  expect(countQueries.at(-1)?.get('search')).toBe('монте')
  expect(brandRequests).toBe(1)
  expect(categoryRequests).toBe(1)
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

test('a 429 on the counter request preserves usable product results', async ({
  page,
  browserIssueGuard,
}) => {
  await mockCatalogApi(page)
  browserIssueGuard.allowApiError(429, '/api/v1/admin/products/filter-counts')
  let release!: () => void
  const pending = new Promise<void>((resolve) => {
    release = resolve
  })
  await page.route('**/api/v1/admin/products/filter-counts*', async (route) => {
    await pending
    return route.fulfill({
      status: 429,
      json: {
        error: {
          message: 'Слишком много запросов. Повторите попытку позже.',
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
  await expect(page.getByText('Монте Тиберио', { exact: true })).toBeVisible()
  await expect(badge('Активные')).toHaveText('…')
  await filters.screenshot({
    path: '.tmp/product-filter-counts-review/counts-loading.png',
  })
  release()
  await expect(badge('Активные')).toHaveText('—')
  await expect(badge('Скрытые')).toHaveText('—')
  await expect(filters.getByRole('alert')).toContainText(
    'Слишком много запросов',
  )
  await expect(page.getByText('Монте Тиберио', { exact: true })).toBeVisible()
  await filters.screenshot({
    path: '.tmp/product-filter-counts-review/counts-partial-error.png',
  })
})
