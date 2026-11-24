import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'
import { selectChoice } from './helpers/selectChoice'

const api = 'http://127.0.0.1:8015'
const collectionPath = '/collection/atlas/stone'
const canonicalUrl = 'https://agatceramic.example' + collectionPath

test.beforeEach(async ({ request, page }) => {
  await request.get(api + '/__scenario?name=standalone-collection-scope')
  await page.setViewportSize({ width: 1280, height: 900 })
})

test('a collection has its own canonical and only offers products and categories in its scope', async ({
  page,
}) => {
  await page.goto(collectionPath)
  const banner = page.locator('.collection-banner')
  const products = page.locator('.product-card')
  await expect(
    banner.getByRole('heading', { name: 'Камень Atlas', level: 1 }),
  ).toBeVisible()
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
    'href',
    canonicalUrl,
  )
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
    'content',
    'index,follow',
  )
  await expect(products).toHaveCount(3)
  const aside = page.getByRole('complementary', { name: 'Фильтры каталога' })
  await expect(
    aside.getByRole('combobox', { name: 'Бренд', exact: true }),
  ).toHaveCount(0)
  await expect(
    aside.getByRole('combobox', { name: 'Коллекция', exact: true }),
  ).toHaveCount(0)
  const categories = page.getByRole('navigation', {
    name: 'Категории каталога',
  })
  await expect(
    categories.getByRole('link', { name: 'Все товары коллекции', exact: true }),
  ).toContainText('3')
  await expect(
    categories.getByRole('button', { name: 'Сантехника', exact: true }),
  ).toHaveCount(0)

  await page.goto(
    collectionPath + '?brand=beta&collection=other&collection_slug=other',
  )
  await expect(
    banner.getByRole('heading', { name: 'Камень Atlas', level: 1 }),
  ).toBeVisible()
  await expect(products).toHaveCount(3)

  await categories
    .getByRole('button', { name: 'Материалы', exact: true })
    .click()
  await categories.getByRole('link', { name: 'Плитка', exact: true }).click()
  await expect(page).toHaveURL(
    (url) =>
      url.pathname === collectionPath &&
      url.searchParams.get('category') === 'tile',
  )
  await expect(page).not.toHaveURL(/[?&](brand|collection|collection_slug)=/)
  await expect(products).toHaveCount(2)
  await selectChoice(
    page.getByRole('combobox', { name: 'Сортировка', exact: true }),
    'Цена по убыванию',
  )
  await expect(products.first()).toContainText('Плитка 02')
  await expect(page).toHaveURL(
    (url) =>
      url.pathname === collectionPath &&
      url.searchParams.get('sort') === 'price_desc',
  )
  await page.reload()
  await expect(products).toHaveCount(2)
  await expect(products.first()).toContainText('Плитка 02')
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
    'href',
    canonicalUrl,
  )
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
    'content',
    'noindex,follow',
  )
  await aside.getByLabel('Цена от').fill('150')
  await expect(page).toHaveURL(/price_from=150/)
  await expect(products).toHaveCount(1)
  await expect(products.first()).toContainText('Плитка 02')
  await aside
    .getByRole('button', { name: 'Сбросить фильтры', exact: true })
    .click()
  await expect(page).toHaveURL(
    (url) =>
      url.pathname === collectionPath &&
      url.searchParams.get('category') === 'tile' &&
      url.searchParams.get('sort') === 'price_desc' &&
      !url.searchParams.has('price_from'),
  )
  await expect(products).toHaveCount(2)
})

test('mobile category changes apply only on confirmation and keep the collection', async ({
  page,
}) => {
  await page.setViewportSize({ width: 640, height: 900 })
  await page.goto(collectionPath)
  const products = page.locator('.product-card')
  await expect(products).toHaveCount(3)
  const trigger = page.getByRole('button', { name: 'Фильтры', exact: true })
  await trigger.click()
  const drawer = page.getByRole('dialog', { name: 'Фильтры каталога' })
  await expect(
    drawer.getByRole('heading', { name: 'Категории коллекции' }),
  ).toBeVisible()
  await drawer.getByRole('button', { name: 'Мозаика', exact: true }).click()
  await expect(page).toHaveURL(
    (url) => url.pathname === collectionPath && !url.search,
  )
  await expect(products).toHaveCount(3)
  await drawer.getByRole('button', { name: 'Применить', exact: true }).click()
  await expect(drawer).toBeHidden()
  await expect(trigger).toBeFocused()
  await expect(page).toHaveURL(
    (url) =>
      url.pathname === collectionPath &&
      url.searchParams.get('category') === 'mosaic',
  )
  await expect(products).toHaveCount(1)
  await expect(products.first()).toContainText('Мозаика 01')
})

test('collection pagination keeps the scope in appended cards and direct URLs', async ({
  page,
  request,
}) => {
  await request.get(api + '/__scenario?name=standalone-collection-pagination')
  await page.goto(collectionPath)
  const products = page.locator('.product-card')
  await expect(products).toHaveCount(24)
  const more = page.getByRole('link', { name: 'Показать ещё 2', exact: true })
  await expect(more).toHaveAttribute(
    'href',
    collectionPath + '?page=2#catalog-products',
  )
  await more.click()
  await expect(products).toHaveCount(26)
  await expect(products.last()).toContainText('Плитка 26')
  await expect(page).toHaveURL(
    (url) => url.pathname === collectionPath && !url.search,
  )
  await page.goto(collectionPath + '?page=2')
  await expect(products).toHaveCount(2)
  await expect(products.last()).toContainText('Плитка 26')
})

test('collection identity is brand-specific and available without optional banner assets', async ({
  page,
  request,
}) => {
  for (const path of ['/collection/empty/stone', '/collection/atlas/missing']) {
    const missing = await request.get(path)
    expect(missing.status()).toBe(404)
    expect(await missing.text()).toContain('noindex')
  }
  const response = await page.goto('/collection/beta/stone')
  expect(response?.status()).toBe(200)
  const banner = page.locator('.collection-banner')
  await expect(
    banner.getByRole('heading', { name: 'Stone', level: 1, exact: true }),
  ).toBeVisible()
  await expect(banner.getByText('Beta', { exact: true })).toBeVisible()
  await expect(banner.getByRole('img')).toHaveCount(0)
  await expect(page.locator('.product-card')).toHaveCount(2)
  await expect(page.locator('.product-card').first()).toContainText('Ванна 01')
})

test('a failed collection lookup is retryable and never shows an unscoped catalog', async ({
  page,
  request,
}) => {
  await request.get(api + '/__scenario?name=collection-page-error')
  const response = await page.goto(collectionPath)
  expect(response?.status()).toBe(503)
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
    'content',
    'noindex,follow',
  )
  await expect(page.locator('#catalog-products')).toHaveCount(0)
  await request.get(api + '/__scenario?name=standalone-collection-scope')
  await page
    .getByRole('button', { name: 'Повторить загрузку', exact: true })
    .click()
  await expect(
    page.getByRole('heading', { name: 'Камень Atlas', level: 1 }),
  ).toBeVisible()
  await expect(page.locator('.product-card')).toHaveCount(3)
})

test('an empty collection offers a link back to the full catalog', async ({
  page,
}) => {
  const response = await page.goto('/collection/atlas/empty-series')
  expect(response?.status()).toBe(200)
  await expect(
    page.getByRole('heading', { name: 'Пустая коллекция', level: 1 }),
  ).toBeVisible()
  const listing = page.locator('#catalog-products')
  await expect(listing.locator('.product-card')).toHaveCount(0)
  await listing.getByRole('link', { name: 'Весь каталог', exact: true }).click()
  await expect(page).toHaveURL(
    (url) => url.pathname === '/catalog' && !url.search,
  )
  await expect(page.locator('.product-card')).not.toHaveCount(0)
})

test('the collection banner and products fit common viewport widths', async ({
  page,
}, testInfo) => {
  const issues: string[] = []
  page.on('pageerror', (error) => issues.push(error.message))
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) issues.push(message.text())
  })
  await page.goto(collectionPath)
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await expect(
      page.locator('.collection-banner').getByRole('img'),
    ).toBeVisible()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: testInfo.outputPath(`collection-page-${width}.png`),
      fullPage: true,
    })
  }
  expect(
    (await new AxeBuilder({ page }).include('.collection-banner').analyze())
      .violations,
  ).toEqual([])
  expect(issues).toEqual([])
})

test('sitemap includes all collection profiles and fails closed on collection pagination errors', async ({
  request,
}) => {
  await request.get(api + '/__scenario?name=sitemap-collections')
  const response = await request.get('/sitemap.xml')
  expect(response.status()).toBe(200)
  const xml = await response.text()
  for (const path of [
    collectionPath,
    '/collection/beta/stone',
    '/collection/test-brand/test-collection',
  ])
    expect(xml).toContain(`<loc>https://agatceramic.example${path}</loc>`)
  await request.get(api + '/__scenario?name=sitemap-collections-error')
  const error = await request.get('/sitemap.xml')
  expect(error.status()).toBe(503)
  expect(error.headers()['cache-control']).toContain('no-store')
  expect(await error.text()).not.toContain('<urlset')
})
