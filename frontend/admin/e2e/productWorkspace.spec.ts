import AxeBuilder from '@axe-core/playwright'
import { expect, test } from './fixtures'
import { mockCatalogApi, product } from './catalogApi'

const widths = [320, 640, 768, 1024, 1280, 1440, 1920, 2560]
const name =
  'Керамогранит коллекционный полированный с декоративной фактурой белого мрамора 60×120 см'

test('wide product workspace keeps details, sticky headers and actions inside its scroll region', async ({
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
    await region.evaluate((element) => {
      element.scrollTop = 0
      element.scrollLeft = 0
    })
    await expect(page.getByTestId('product-name-preview').first()).toHaveText(
      `${name} 1`,
    )
    const dimensions = await region.evaluate((element) => ({
      regionWidth: element.clientWidth,
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
    expect(dimensions.regionWidth).toBe(width - (width < 640 ? 32 : 48))
    if (width >= 1920) expect(dimensions.nameWidth).toBeGreaterThan(650)
    await region.evaluate((element) => {
      element.scrollTop = 500
      element.scrollLeft = 500
    })
    const sticky = await region.evaluate((element) => {
      const rect = element.getBoundingClientRect()
      const header = element.querySelector('th')!.getBoundingClientRect()
      const row = element.querySelector('tbody tr')!
      return {
        top: header.top - rect.top,
        first: row.firstElementChild!.getBoundingClientRect().left - rect.left,
        last: rect.right - row.lastElementChild!.getBoundingClientRect().right,
      }
    })
    expect(sticky.top).toBeCloseTo(dimensions.headerTop, 0)
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
    if (width === 320) {
      await region.scrollIntoViewIfNeeded()
      await page.screenshot({ path: '.tmp/a059-visual/products-320-table.png' })
      await region.evaluate((element) => {
        element.scrollLeft = element.scrollWidth
      })
      await page.screenshot({
        path: '.tmp/a059-visual/products-320-actions.png',
      })
    }
  }
  await page.setViewportSize({ width: 1280, height: 900 })
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
