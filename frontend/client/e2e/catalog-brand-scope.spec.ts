import { selectChoice } from './helpers/selectChoice'
import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test.beforeEach(async ({ request, page }) => {
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=standalone-brand-scope',
  )
  await page.setViewportSize({ width: 1280, height: 900 })
})

test('brand category and refinements remain independent on its clean URL', async ({
  page,
}) => {
  await page.goto('/brand/atlas')
  await expect(page.locator('.product-card')).toHaveCount(3)
  const categories = page.getByRole('navigation', {
    name: 'Категории каталога',
  })
  const form = page.getByRole('complementary', { name: 'Фильтры каталога' })
  await expect(
    form.getByRole('combobox', { name: 'Бренд', exact: true }),
  ).toHaveCount(0)
  await expect(form.getByText('Цвет', { exact: true })).toHaveCount(0)
  await categories
    .getByRole('button', { name: 'Материалы', exact: true })
    .click()
  await categories.getByRole('link', { name: 'Плитка', exact: true }).click()
  await expect(page).toHaveURL(/\/brand\/atlas\?category=tile/)
  await expect(page).not.toHaveURL(/brand=/)
  await expect(page.locator('.product-card')).toHaveCount(2)
  await expect(form.getByText('Цвет', { exact: true })).toBeVisible()
  await form.getByLabel('Цена от').fill('150')
  await form.getByRole('button', { name: 'Применить', exact: true }).click()
  await expect(page.locator('.product-card')).toHaveCount(1)
  await expect(page.locator('.product-card')).toContainText('Плитка 02')
  await page
    .getByRole('button', {
      name: 'Убрать фильтр: Категория: Плитка',
      exact: true,
    })
    .click()
  await expect(page).not.toHaveURL(/category=/)
  await expect(page).toHaveURL(/\/brand\/atlas\?price_from=150/)
  await expect(page.locator('.product-card')).toHaveCount(2)
})

test('category entry refines by brand and history restores the previous page', async ({
  page,
}) => {
  await page.goto('/catalog?category=tile')
  const form = page.getByRole('complementary', { name: 'Фильтры каталога' })
  await selectChoice(
    form.getByRole('combobox', { name: 'Бренд', exact: true }),
    'Atlas',
  )
  await form.getByRole('button', { name: 'Применить', exact: true }).click()
  await expect(page).toHaveURL(/\/brand\/atlas\?category=tile/)
  await expect(page).not.toHaveURL(/brand=/)
  await expect(
    page.getByRole('heading', { name: 'Atlas', level: 1, exact: true }),
  ).toBeVisible()
  await page
    .getByRole('navigation', { name: 'Категории каталога' })
    .getByRole('link', { name: 'Мозаика', exact: true })
    .click()
  await expect(page.locator('.product-card')).toHaveCount(1)
  await expect(page.locator('.product-card')).toContainText('Мозаика 01')
  await page.goBack()
  await expect(page).toHaveURL(/\/brand\/atlas\?category=tile/)
  await expect(page.locator('.product-card')).toHaveCount(2)
  await page.goBack()
  await expect(page).toHaveURL(/\/catalog\?category=tile/)
  await expect(
    form.getByRole('combobox', { name: 'Бренд', exact: true }),
  ).toHaveText('Все бренды')
})

test('category changes clear dependent values while brand counts ignore refinements', async ({
  page,
}) => {
  const attributes = encodeURIComponent(
    JSON.stringify({ 1: { values: ['gray'] } }),
  )
  await page.goto(
    '/brand/atlas?category=tile&collection=Stone&page=2&price_from=150&attributes=' +
      attributes,
  )
  const categories = page.getByRole('navigation', {
    name: 'Категории каталога',
  })
  await categories
    .getByRole('button', { name: 'Материалы', exact: true })
    .click()
  await expect(
    categories.getByRole('link', { name: 'Плитка', exact: true }),
  ).toContainText('2')
  await expect(
    categories.getByRole('link', { name: /^Все товары бренда/ }),
  ).toContainText('3')
  await categories.getByRole('link', { name: 'Мозаика', exact: true }).click()
  await expect(page).toHaveURL(/\/brand\/atlas\?category=mosaic/)
  await expect(page).toHaveURL(/price_from=150/)
  await expect(page).not.toHaveURL(/collection=|page=|attributes=|brand=/)
  await expect(page.locator('.product-card')).toHaveCount(1)
  await categories
    .getByRole('button', { name: 'Материалы', exact: true })
    .click()
  await expect(
    categories.getByRole('link', { name: 'Плитка', exact: true }),
  ).toContainText('2')
  await categories.getByRole('link', { name: /^Все товары бренда/ }).click()
  await expect(page).not.toHaveURL(/category=/)
  await expect(page).toHaveURL(/\/brand\/atlas\?price_from=150/)
  await expect(page.locator('.product-card')).toHaveCount(2)
})

test('reset of empty refinements preserves the brand page, category and sorting', async ({
  page,
}) => {
  await page.goto(
    '/brand/atlas?category=tile&price_from=9999&in_stock=1&sort=price_desc',
  )
  await expect(
    page.getByText('По вашему запросу ничего не найдено', { exact: true }),
  ).toBeVisible()
  await expect(
    page.getByRole('heading', { name: 'Atlas', exact: true, level: 1 }),
  ).toBeVisible()
  await page
    .getByRole('complementary')
    .getByRole('button', { name: 'Сбросить фильтры', exact: true })
    .click()
  await expect(page).toHaveURL(/\/brand\/atlas\?category=tile/)
  await expect(page).toHaveURL(/sort=price_desc/)
  await expect(page).not.toHaveURL(/price_from=|in_stock=|brand=/)
  await expect(page.locator('.product-card')).toHaveCount(2)
})

test('mobile category selection and reset remain draft-only until Apply on the brand page', async ({
  page,
}) => {
  await page.setViewportSize({ width: 640, height: 900 })
  await page.goto('/brand/atlas?price_from=150')
  const original = page.url()
  const opener = page.getByRole('button', { name: 'Фильтры', exact: true })
  await opener.click()
  const drawer = page.getByRole('dialog', { name: 'Фильтры каталога' })
  await drawer.getByRole('button', { name: 'Мозаика', exact: true }).click()
  expect(page.url()).toBe(original)
  await expect(
    drawer.getByRole('button', { name: /^Все товары бренда/ }),
  ).toContainText('3')
  await page.keyboard.press('Escape')
  await expect(drawer).toHaveCount(0)
  expect(page.url()).toBe(original)
  await expect(opener).toBeFocused()
  await opener.click()
  const materials = drawer.getByRole('button', {
    name: 'Материалы',
    exact: true,
  })
  if ((await materials.getAttribute('aria-expanded')) === 'false')
    await materials.click()
  await drawer.getByRole('button', { name: 'Плитка', exact: true }).click()
  await drawer
    .getByRole('button', { name: 'Сбросить фильтры', exact: true })
    .click()
  await expect(drawer.getByLabel('Цена от')).toHaveValue('')
  await expect(
    drawer.getByRole('button', { name: 'Плитка', exact: true }),
  ).toHaveAttribute('aria-current', 'true')
  expect(page.url()).toBe(original)
  await drawer.getByRole('button', { name: 'Применить', exact: true }).click()
  await expect(drawer).toHaveCount(0)
  await expect(page).toHaveURL(/\/brand\/atlas\?category=tile/)
  await expect(page).not.toHaveURL(/price_from=|brand=/)
  await expect(page.locator('.product-card')).toHaveCount(2)
})

test('cancelled mobile edits cannot leak into desktop Apply on a brand page', async ({
  page,
}) => {
  await page.setViewportSize({ width: 640, height: 900 })
  await page.goto('/brand/atlas?category=tile')
  await page.getByRole('button', { name: 'Фильтры', exact: true }).click()
  const drawer = page.getByRole('dialog', { name: 'Фильтры каталога' })
  await drawer.getByLabel('Цена от').fill('9999')
  await drawer.getByRole('button', { name: 'Мозаика', exact: true }).click()
  await page.keyboard.press('Escape')
  await expect(drawer).toHaveCount(0)
  await page.setViewportSize({ width: 1280, height: 900 })
  const sidebar = page.getByRole('complementary', { name: 'Фильтры каталога' })
  await expect(sidebar.getByText('Цвет', { exact: true })).toBeVisible()
  await expect(sidebar.getByLabel('Цена от')).toHaveValue('')
  await sidebar.getByRole('button', { name: 'Применить', exact: true }).click()
  await expect(page).toHaveURL(/\/brand\/atlas\?category=tile/)
  await expect(page).not.toHaveURL(/price_from=|brand=/)
  await expect(page.locator('.product-card')).toHaveCount(2)
})

test('brand page is SSR-visible, responsive and free of hydration errors', async ({
  page,
  request,
}, testInfo) => {
  const response = await request.get('/brand/atlas?category=tile')
  const html = await response.text()
  expect(html).toContain('Керамическая плитка и мозаика Atlas.')
  expect(html).toContain('Все товары бренда')
  expect(html).toContain('Категория: Плитка')
  const errors: string[] = []
  page.on('pageerror', (error) => errors.push(error.message))
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) errors.push(message.text())
  })
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/brand/atlas?category=tile#catalog-products')
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: testInfo.outputPath('brand-scope-' + width + '.png'),
      fullPage: true,
    })
    if (width < 1024) {
      await page.getByRole('button', { name: 'Фильтры', exact: true }).click()
      await expect(page.getByRole('dialog')).toBeVisible()
      await page.keyboard.press('Escape')
    }
  }
  expect(
    (
      await new AxeBuilder({ page })
        .include('#catalog-products')
        .exclude('.product-card')
        .analyze()
    ).violations,
  ).toEqual([])
  expect(errors).toEqual([])
})
