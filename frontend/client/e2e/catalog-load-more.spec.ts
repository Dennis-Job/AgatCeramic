import { expect, test } from '@playwright/test'
import { selectChoice } from './helpers/selectChoice'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=standalone-default')
})

test('SSR next link appends products once, reports progress and resets on sorting', async ({
  page,
  request,
}, testInfo) => {
  await page.setViewportSize({ width: 1228, height: 882 })
  const html = await (await request.get('/catalog')).text()
  expect(html).toContain('Показать ещё 3')
  expect(html).toContain('page=2')
  await page.goto('/catalog')
  const products = page.locator('.product-card')
  const first = await products.first().locator('h3').innerText()
  const more = page.getByRole('link', { name: 'Показать ещё 3' })
  await expect(more).toHaveAttribute('href', /page=2/)
  await expect(page.locator('.catalog-load-more')).toContainText(
    'Показано 24 из 27',
  )
  await more.scrollIntoViewIfNeeded()
  await page.screenshot({ path: testInfo.outputPath('load-more-ready.png') })
  await more.hover()
  await expect(more).toHaveCSS('background-color', 'rgb(20, 20, 20)')
  await page.screenshot({ path: testInfo.outputPath('load-more-hover.png') })
  const scroll = await page.evaluate(() => scrollY)
  await more.click()
  await expect(products).toHaveCount(27)
  await expect(products.first().locator('h3')).toHaveText(first)
  await expect(page.locator('.catalog-load-more [role=status]')).toBeFocused()
  expect(Math.abs((await page.evaluate(() => scrollY)) - scroll)).toBeLessThan(
    2,
  )
  await expect(page).not.toHaveURL(/page=2/)
  await expect(page.locator('.catalog-load-more')).toContainText(
    'Показано 27 из 27',
  )
  await expect(page.getByRole('link', { name: /Показать ещё/ })).toHaveCount(0)
  const ids = await products
    .locator('h3 a')
    .evaluateAll((els) => els.map((el) => el.getAttribute('href')))
  expect(new Set(ids).size).toBe(27)
  await selectChoice(
    page.getByRole('combobox', { name: 'Сортировка' }),
    'Цена по убыванию',
  )
  await expect(products).toHaveCount(24)
  await expect(page.getByRole('link', { name: 'Показать ещё 3' })).toBeVisible()
})

test('a failed next request keeps cards and supports retry', async ({
  page,
}) => {
  await page.goto('/catalog')
  let fail = true
  await page.route('**/api/v1/catalog?**', async (route) => {
    if (
      new URL(route.request().url()).searchParams.get('page') === '2' &&
      fail
    ) {
      fail = false
      await route.fulfill({ status: 503, body: '{}' })
    } else await route.continue()
  })
  await page.getByRole('link', { name: 'Показать ещё 3' }).click()
  await expect(page.getByRole('alert')).toContainText(
    'Не удалось загрузить товары',
  )
  await expect(page.locator('.product-card')).toHaveCount(24)
  await page.getByRole('link', { name: 'Показать ещё 3' }).click()
  await expect(page.locator('.product-card')).toHaveCount(27)
  await expect(page.getByRole('alert')).toHaveCount(0)
})

test('repeated activation is ignored and an old next page cannot enter new filters', async ({
  page,
}) => {
  await page.goto('/catalog?category=tile')
  let release!: () => void
  const gate = new Promise<void>((resolve) => {
    release = resolve
  })
  let calls = 0
  await page.route('**/api/v1/catalog?**', async (route) => {
    if (new URL(route.request().url()).searchParams.get('page') !== '2')
      return route.continue()
    calls++
    const response = await route.fetch()
    await gate
    await route.fulfill({ response }).catch(() => {})
  })
  await page.getByRole('link', { name: 'Показать ещё 3' }).click()
  const loading = page.getByRole('link', { name: 'Загружаем…' })
  await expect(loading).toHaveAttribute('aria-disabled', 'true')
  await loading.press('Enter')
  await expect(page.locator('.product-card')).toHaveCount(24)
  await page
    .getByRole('complementary')
    .getByRole('switch', { name: 'В наличии' })
    .check()
  await expect(page).toHaveURL(/in_stock=1/)
  await expect(page.locator('.product-card')).toHaveCount(14)
  release()
  await expect(page.locator('.catalog-load-more')).toContainText(
    'Показано 14 из 14',
  )
  expect(calls).toBe(1)
})

test('direct page URLs remain usable and a narrow screen can append', async ({
  page,
}) => {
  await page.goto('/catalog?page=2')
  await expect(page.locator('.product-card')).toHaveCount(3)
  await page.getByRole('link', { name: '← Предыдущая' }).click()
  await expect(page.locator('.product-card')).toHaveCount(24)
  await page.setViewportSize({ width: 320, height: 900 })
  await page.getByRole('link', { name: 'Показать ещё 3' }).click()
  await expect(page.locator('.product-card')).toHaveCount(27)
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true)
})
