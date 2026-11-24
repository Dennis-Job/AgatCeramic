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
  await expect(page).toHaveURL(/price_from=150/)
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
  await page.goto('/catalog')
  const categories = page.getByRole('navigation', {
    name: 'Категории каталога',
  })
  await categories
    .getByRole('button', { name: 'Материалы', exact: true })
    .click()
  await categories.getByRole('link', { name: 'Плитка', exact: true }).click()
  const form = page.getByRole('complementary', { name: 'Фильтры каталога' })
  // Wait for the tile filter options before driving the brand picker by keyboard.
  await expect(form.getByText('Цвет', { exact: true })).toBeVisible()
  const brand = form.getByRole('combobox', { name: 'Бренд', exact: true })
  await brand.focus()
  await brand.press('Enter')
  await expect(
    form.getByRole('option', { name: 'Atlas', exact: true }),
  ).toBeVisible()
  await brand.press('ArrowDown')
  await brand.press('Enter')
  await expect(page).toHaveURL(/\/catalog\?category=tile&brand=atlas/)
  await expect(brand).toHaveText('Atlas')
  await expect(page.locator('.catalog-results')).toHaveAttribute(
    'aria-busy',
    'false',
  )
  await expect(brand).toBeFocused()
  await expect(
    page.getByRole('heading', { name: 'Каталог', level: 1 }),
  ).toBeAttached()
  await expect(
    categories.getByRole('button', { name: 'Сантехника', exact: true }),
  ).toBeVisible()
  await categories
    .getByRole('button', { name: 'Материалы', exact: true })
    .click()
  await categories.getByRole('link', { name: 'Мозаика', exact: true }).click()
  await expect(page).toHaveURL(/\/catalog\?category=mosaic&brand=atlas/)
  await expect(page.locator('.product-card')).toHaveCount(1)
  await expect(page.locator('.product-card')).toContainText('Мозаика 01')
  await page.goBack()
  await expect(page).toHaveURL(/\/catalog\?category=tile&brand=atlas/)
  await expect(page.locator('.product-card')).toHaveCount(2)
  await page.goBack()
  await expect(page).toHaveURL(/\/catalog\?category=tile/)
  await expect(brand).toHaveText('Все бренды')
  await selectChoice(brand, 'Atlas')
  await expect(page).toHaveURL(/\/catalog\?category=tile&brand=atlas/)
  await selectChoice(brand, 'Все бренды')
  await expect(page).toHaveURL(/\/catalog\?category=tile#catalog-products$/)
})

test('mobile Apply filters the catalog by brand and reopening keeps the selection', async ({
  page,
}) => {
  await page.setViewportSize({ width: 640, height: 900 })
  await page.goto('/catalog')
  const opener = page.getByRole('button', { name: 'Фильтры', exact: true })
  const drawer = page.getByRole('dialog', { name: 'Фильтры каталога' })
  const brand = drawer.getByRole('combobox', { name: 'Бренд', exact: true })
  await expect(page.locator('.product-card')).toHaveCount(5)
  await opener.click()
  await drawer.getByRole('button', { name: 'Материалы', exact: true }).click()
  await drawer.getByRole('button', { name: 'Плитка', exact: true }).click()
  await selectChoice(brand, 'Atlas')
  await expect(page).toHaveURL(/\/catalog$/)
  await expect(page.locator('.product-card')).toHaveCount(5)
  await drawer.getByRole('button', { name: 'Применить', exact: true }).click()
  await expect(drawer).toHaveCount(0)
  await expect(page).toHaveURL(/\/catalog\?category=tile&brand=atlas/)
  await expect(page.locator('.product-card')).toHaveCount(2)
  await opener.click()
  await expect(brand).toHaveText('Atlas')
  await expect(
    drawer.getByRole('button', { name: 'Плитка', exact: true }),
  ).toHaveAttribute('aria-current', 'true')
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

test('cancelled mobile edits cannot leak into desktop automatic filters on a brand page', async ({
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
  await expect(
    sidebar.getByRole('button', { name: 'Применить', exact: true }),
  ).toBeHidden()
  await expect(page).toHaveURL(/\/brand\/atlas\?category=tile/)
  await expect(page).not.toHaveURL(/price_from=|brand=/)
  await expect(page.locator('.product-card')).toHaveCount(2)
  await sidebar.getByLabel('В наличии', { exact: true }).check()
  await expect(page).toHaveURL(/in_stock=1/)
  await expect(page).not.toHaveURL(/price_from=|brand=/)
  await expect(page).toHaveURL(/category=tile/)
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
