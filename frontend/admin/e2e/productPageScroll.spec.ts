import AxeBuilder from '@axe-core/playwright'
import { expect, test } from './fixtures'
import { mockCatalogApi, product } from './catalogApi'

test('products use document scrolling and the original header follows the menu and table boundary', async ({
  page,
}) => {
  await mockCatalogApi(page)
  await page.route('**/api/v1/admin/products?*', (route) =>
    route.fulfill({
      json: {
        data: Array.from({ length: 25 }, (_, index) => ({
          ...product,
          id: index + 1,
          name: `${product.name} ${index + 1}`,
        })),
        meta: { current_page: 1, last_page: 1, per_page: 25, total: 25 },
      },
    }),
  )
  await page.goto('/products')
  const region = page.getByRole('region', { name: 'Таблица товаров' })
  await expect(region).toBeVisible()
  await page.evaluate(() => document.fonts.ready)

  for (const width of [320, 640, 768, 1024, 1280, 1920]) {
    await page.setViewportSize({ width, height: 900 })
    await page.evaluate(() => window.scrollTo(0, 0))
    await region.evaluate((element) => {
      element.scrollLeft = 0
    })
    const naturalTop = await region.evaluate(
      (element) =>
        element.querySelector('thead')!.getBoundingClientRect().top + scrollY,
    )
    const menuBottom = await page
      .locator('.admin-header')
      .evaluate((element) => element.getBoundingClientRect().bottom)
    const headerTop = () =>
      region.evaluate(
        (element) =>
          element.querySelector('thead')!.getBoundingClientRect().top,
      )
    await page.evaluate(
      (y) => window.scrollTo(0, y),
      naturalTop - menuBottom - 40,
    )
    await expect.poll(headerTop).toBeCloseTo(menuBottom + 40, 0)
    await page.evaluate(
      (y) => window.scrollTo(0, y),
      naturalTop - menuBottom + 350,
    )
    await expect.poll(headerTop).toBeCloseTo(menuBottom, 0)
    await expect(region.locator('thead')).toHaveCount(1)
    expect(
      await region.evaluate((element) => ({
        nestedVerticalScroll: element.scrollHeight > element.clientHeight,
        pageOverflow: document.documentElement.scrollWidth > innerWidth,
      })),
    ).toEqual({ nestedVerticalScroll: false, pageOverflow: false })
    await region.hover()
    const beforeWheel = await page.evaluate(() => scrollY)
    await page.mouse.wheel(0, 200)
    await expect
      .poll(() => page.evaluate(() => scrollY))
      .toBeGreaterThan(beforeWheel)
    await expect.poll(headerTop).toBeCloseTo(menuBottom, 0)
    await region.evaluate((element) => {
      element.scrollLeft = element.scrollWidth
    })
    await expect.poll(headerTop).toBeCloseTo(menuBottom, 0)
    await expect(region.locator('thead th').last()).toBeInViewport()
    await page.screenshot({
      path: `.tmp/product-page-scroll/pinned-${width}.png`,
    })
    const tableBottom = await region
      .locator('table')
      .evaluate((element) => element.getBoundingClientRect().bottom + scrollY)
    const headerHeight = await region
      .locator('thead')
      .evaluate((element) => element.getBoundingClientRect().height)
    // Allow enough document below the table to exercise its release boundary.
    await page.locator('#admin-main').evaluate((element) => {
      element.style.paddingBottom = '1000px'
    })
    await page.evaluate(
      (y) => window.scrollTo(0, y),
      tableBottom - menuBottom - headerHeight / 2,
    )
    await expect
      .poll(() =>
        region
          .locator('thead')
          .evaluate((element) => element.getBoundingClientRect().bottom),
      )
      .toBeCloseTo(menuBottom + headerHeight / 2, 0)
    await page.locator('#admin-main').evaluate((element) => {
      element.style.paddingBottom = ''
    })
    await page.evaluate(() => window.scrollTo(0, 0))
    await expect.poll(headerTop).toBeCloseTo(naturalTop, 0)
  }

  await page.setViewportSize({ width: 1280, height: 900 })
  await region.evaluate((element) => {
    element.scrollLeft = 0
    window.scrollTo(0, element.getBoundingClientRect().top + scrollY)
  })
  const sort = region.getByRole('button', { name: 'Наименование', exact: true })
  await sort.focus()
  await expect(sort).toBeFocused()
  await page.keyboard.press('Enter')
  await expect(region.locator('th').first()).toHaveAttribute(
    'aria-sort',
    'ascending',
  )
  await sort.focus()
  await page.keyboard.press('Enter')
  await expect(region.locator('th').first()).toHaveAttribute(
    'aria-sort',
    'descending',
  )
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  await page
    .getByRole('button', { name: 'Редактировать товар', exact: false })
    .first()
    .click()
  await expect(page.getByRole('dialog')).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await page.goto('/brands')
  await expect(
    page.getByRole('heading', { name: 'Бренды', exact: true }),
  ).toBeVisible()
  await page.goto('/products')
  await expect(region).toBeVisible()
  await region.evaluate((element) =>
    window.scrollTo(0, element.getBoundingClientRect().top + scrollY),
  )
  await expect
    .poll(() =>
      region
        .locator('thead')
        .evaluate(
          (element) =>
            element.getBoundingClientRect().top -
            document.querySelector('.admin-header')!.getBoundingClientRect()
              .bottom,
        ),
    )
    .toBeCloseTo(0, 0)
})
