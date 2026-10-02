import AxeBuilder from '@axe-core/playwright'
import { expect, test } from './fixtures'
import { mockCatalogApi, product } from './catalogApi'

const widths = [320, 602, 640, 768, 1024, 1280, 1440, 1920, 2560]
const name =
  'Керамогранит коллекционный полированный с декоративной фактурой белого мрамора 60×120 см'

test('product segmented filters support keyboard, reset and compact layouts', async ({
  page,
}) => {
  await mockCatalogApi(page)
  await page.goto('/products')
  const filters = page.getByRole('search')
  const activity = filters.getByRole('group', { name: 'Активность' })
  const sale = filters.getByRole('group', { name: 'Распродажа', exact: true })
  await expect(filters.getByLabel('Поиск', { exact: true })).toBeVisible()
  for (const label of ['Поиск', 'Категория', 'Бренд']) {
    await expect(filters.getByText(label, { exact: true })).toHaveCount(0)
  }
  await activity.getByRole('radio', { name: 'Все', exact: true }).focus()
  await page.keyboard.press('ArrowRight')
  await expect(activity.getByRole('radio', { name: 'Активные' })).toBeChecked()
  await page.keyboard.press('ArrowRight')
  await expect(activity.getByRole('radio', { name: 'Скрытые' })).toBeChecked()
  await sale.getByRole('radio', { name: 'Все', exact: true }).focus()
  await page.keyboard.press('ArrowLeft')
  await expect(sale.getByRole('radio', { name: 'Не распродажа' })).toBeChecked()
  await expect(activity.getByRole('radio', { name: 'Скрытые' })).toBeChecked()
  await filters.getByRole('button', { name: 'Сбросить' }).click()
  await expect(
    activity.getByRole('radio', { name: 'Все', exact: true }),
  ).toBeChecked()
  await expect(
    sale.getByRole('radio', { name: 'Все', exact: true }),
  ).toBeChecked()

  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await page.evaluate(() => document.fonts.ready)
    await expect
      .poll(() =>
        page.evaluate(() => document.documentElement.scrollWidth <= innerWidth),
      )
      .toBe(true)
    await filters.screenshot({
      path: `.tmp/product-filter-review/filters-${width}.png`,
    })
  }
  await activity.getByRole('radio', { name: 'Все', exact: true }).focus()
  await page.keyboard.press('ArrowRight')
  const selected = activity.getByRole('radio', { name: 'Активные' })
  await expect(selected).toBeFocused()
  await expect(selected.locator('..')).toHaveCSS(
    'box-shadow',
    'rgba(100, 125, 209, 0.14) 0px 0px 0px 4px',
  )
  await filters.screenshot({
    path: '.tmp/product-filter-review/filters-focus.png',
  })
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])

  await page.goto('/ui-kit')
  const disabled = page.getByRole('group', {
    name: 'Недоступный переключатель',
  })
  for (const radio of await disabled.getByRole('radio').all())
    await expect(radio).toBeDisabled()
  await expect(disabled.getByRole('radio', { name: 'Активные' })).toBeChecked()
  await page
    .locator('[data-ui-kit-section="segmented"]')
    .screenshot({ path: '.tmp/product-filter-review/segmented-disabled.png' })
})

test('wide product workspace keeps details, page sticky headers and horizontal actions usable', async ({
  page,
}) => {
  await mockCatalogApi(page)
  await page.route('**/api/v1/admin/products?*', (route) =>
    route.fulfill({
      json: {
        data: Array.from({ length: 25 }, (_, index) => ({
          ...product,
          id: index + 1,
          name: `${name} ${index + 1}`,
          price: index === 24 ? '9999999999.99' : product.price,
          stock_quantity: index === 24 ? 2147483647 : product.stock_quantity,
        })),
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 25,
          from: 1,
          to: 25,
        },
      },
    }),
  )
  await page.goto('/products')
  const region = page.getByRole('region', { name: 'Таблица товаров' })
  await expect(region).toBeVisible()
  await page.evaluate(() => document.fonts.ready)
  for (const width of widths) {
    await page.setViewportSize({ width, height: 900 })
    await page.evaluate(() => window.scrollTo(0, 0))
    await region.evaluate((element) => {
      element.scrollTop = 0
      element.scrollLeft = 0
    })
    await expect(page.getByTestId('product-name-preview').first()).toHaveText(
      `${name} 1`,
    )
    const dimensions = await region.evaluate((element) => ({
      regionWidth: element.clientWidth,
      viewportWidth: document.documentElement.clientWidth,
      pageOverflow: document.documentElement.scrollWidth > innerWidth,
      nameWidth: element.querySelector('td')!.getBoundingClientRect().width,
      headerTop:
        element.querySelector('th')!.getBoundingClientRect().top -
        element.getBoundingClientRect().top,
      overflowingCells: Array.from(element.querySelectorAll('tbody td')).filter(
        (cell) => cell.scrollWidth > cell.clientWidth,
      ).length,
    }))
    expect(dimensions.pageOverflow, `page overflow at ${width}`).toBe(false)
    expect(dimensions.overflowingCells, `cell overflow at ${width}`).toBe(0)
    expect(dimensions.regionWidth).toBe(dimensions.viewportWidth)
    if (width >= 1920) expect(dimensions.nameWidth).toBeGreaterThan(650)
    await region.evaluate((element) => {
      element.scrollTop = 500
      element.scrollLeft = 500
    })
    expect(await region.evaluate((element) => element.scrollTop)).toBe(0)
    await region.evaluate((element) => {
      window.scrollTo(0, element.getBoundingClientRect().top + scrollY)
    })
    await expect
      .poll(() =>
        region.evaluate(
          (element) =>
            element.querySelector('th')!.getBoundingClientRect().top -
            document.querySelector('.admin-header')!.getBoundingClientRect()
              .bottom,
        ),
      )
      .toBeCloseTo(0, 0)
    const sticky = await region.evaluate((element) => {
      const rect = element.getBoundingClientRect()
      const header = element.querySelector('th')!.getBoundingClientRect()
      const row = element.querySelector('tbody tr')!
      return {
        top: header.top,
        first: row.firstElementChild!.getBoundingClientRect().left - rect.left,
        last: rect.right - row.lastElementChild!.getBoundingClientRect().right,
      }
    })
    expect(sticky.top).toBeGreaterThan(0)
    if (width >= 1280) {
      expect(sticky.first).toBeCloseTo(0, 0)
      expect(sticky.last).toBeCloseTo(0, 0)
    }
    await region.evaluate((element) => {
      element.scrollTop = 0
      element.scrollLeft = 0
    })
    await page.evaluate(() => {
      window.scrollTo(0, 0)
    })
    await page.screenshot({ path: `.tmp/a059-visual/products-${width}.png` })
    if (width === 320 || width === 602) {
      await region.scrollIntoViewIfNeeded()
      await page.screenshot({
        path: `.tmp/a059-visual/products-${width}-table.png`,
      })
      await region.evaluate((element) => {
        element.scrollLeft = element.scrollWidth
      })
      await page.screenshot({
        path: `.tmp/a059-visual/products-${width}-actions.png`,
      })
    }
  }
  await page.setViewportSize({ width: 1280, height: 900 })
  await page.evaluate(() => window.scrollTo(0, 0))
  await region.focus()
  await page.keyboard.press('ArrowRight')
  await expect
    .poll(() => region.evaluate((element) => element.scrollLeft))
    .toBeGreaterThan(0)
  const result = await new AxeBuilder({ page }).analyze()
  expect(
    result.violations.filter((violation) =>
      ['serious', 'critical'].includes(violation.impact ?? ''),
    ),
  ).toEqual([])
  await region.evaluate((element) => {
    element.scrollTop = 0
    element.scrollLeft = 500
  })
  await page
    .getByRole('button', { name: `Редактировать товар ${name} 1`, exact: true })
    .click()
  await expect(
    page.getByRole('dialog', { name: `${name} 1`, exact: true }),
  ).toBeVisible()
})

test('product image failure displays a compact fallback and unit labels use real data', async ({
  page,
  browserIssueGuard,
}) => {
  await mockCatalogApi(page)
  browserIssueGuard.allowApiError(404, '/missing-product.png')
  await page.route('**/missing-product.png', (route) =>
    route.fulfill({ status: 404 }),
  )
  await page.route('**/api/v1/admin/products?*', (route) =>
    route.fulfill({
      json: {
        data: [
          {
            ...product,
            primary_image: {
              id: 1,
              url: '/missing-product.png',
              alt: 'Плитка 60×120',
            },
          },
        ],
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: 1,
          from: 1,
          to: 1,
        },
      },
    }),
  )
  await page.goto('/products')
  await expect(
    page
      .getByRole('status')
      .filter({ hasText: 'Не удалось загрузить изображение' }),
  ).toBeVisible()
  const row = page.locator('tbody tr').first()
  await expect(row.getByRole('cell', { name: '1990.00 ₽ за м²' })).toBeVisible()
  await expect(row.getByRole('cell', { name: '12 м²' })).toBeVisible()
})
