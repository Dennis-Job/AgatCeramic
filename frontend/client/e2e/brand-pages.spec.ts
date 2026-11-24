import { selectChoice } from './helpers/selectChoice'
import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test.beforeEach(async ({ request, page }) => {
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=standalone-brand-scope',
  )
  await page.setViewportSize({ width: 1280, height: 900 })
})

test('the brand intro uses the collection layout and keeps the entire logo visible', async ({
  page,
  request,
}) => {
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=standalone-collection-scope',
  )
  const intro = page.locator('main > section').first()
  const heading = intro.getByRole('heading', { level: 1 })

  for (const width of [320, 640, 768, 900, 901, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/collection/atlas/stone')
    await expect(heading).toHaveText('Камень Atlas')
    const collectionStyles = await bannerStyles()

    await page.goto('/brand/atlas')
    await expect(heading).toHaveText('Atlas')
    const logo = intro.getByRole('img', { name: 'Изображение бренда Atlas' })
    await expect(logo).toHaveCSS('object-fit', 'contain')
    expect(await bannerStyles()).toEqual(collectionStyles)

    const textBox = (await heading.locator('..').boundingBox())!
    const logoBox = (await logo.boundingBox())!
    if (width <= 900) {
      expect(logoBox.y).toBeGreaterThanOrEqual(textBox.y + textBox.height - 1)
    } else {
      expect(logoBox.x).toBeGreaterThanOrEqual(textBox.x + textBox.width - 1)
    }
  }

  async function bannerStyles() {
    return intro.evaluate((section) => {
      const title = section.querySelector('h1')!
      return {
        background: getComputedStyle(section).backgroundColor,
        columns: getComputedStyle(section).gridTemplateColumns,
        padding: getComputedStyle(title.parentElement!).padding,
        titleFont: getComputedStyle(title).font,
      }
    })
  }
})

test('brand menu opens a clean brand page with its own intro and scoped categories', async ({
  page,
}) => {
  await page.goto('/catalog?category=bath&price_from=999')
  const menu = page.getByRole('navigation', { name: 'Бренды по алфавиту' })
  await menu.getByRole('button', { name: 'A', exact: true }).click()
  await menu.getByRole('link', { name: 'Atlas', exact: true }).click()
  await expect(page).toHaveURL(/\/brand\/atlas$/)
  const intro = page.getByRole('region', { name: 'Atlas', exact: true })
  await expect(
    intro.getByRole('heading', { level: 1, name: 'Atlas', exact: true }),
  ).toBeVisible()
  await expect(
    intro.getByText('Керамическая плитка и мозаика Atlas.', { exact: true }),
  ).toBeVisible()
  await expect(
    intro.getByRole('img', { name: 'Изображение бренда Atlas', exact: true }),
  ).toBeVisible()
  await expect(page.getByText('Выбран бренд:', { exact: false })).toHaveCount(0)
  await expect(
    page.getByRole('heading', { name: 'Товары Atlas', exact: true }),
  ).toHaveCount(0)
  const categories = page.getByRole('navigation', {
    name: 'Категории каталога',
  })
  await categories
    .getByRole('button', { name: 'Материалы', exact: true })
    .click()
  await expect(
    categories.getByRole('link', { name: 'Плитка', exact: true }),
  ).toBeVisible()
  await expect(
    categories.getByRole('link', { name: 'Мозаика', exact: true }),
  ).toBeVisible()
  await expect(
    categories.getByRole('link', { name: 'Сантехника', exact: true }),
  ).toHaveCount(0)
  await expect(page.locator('.product-card')).toHaveCount(3)
})

test('sitemap includes active brand pages and refuses partial output on brand upstream errors', async ({
  request,
}) => {
  const response = await request.get('/sitemap.xml')
  expect(response.status()).toBe(200)
  const xml = await response.text()
  for (const slug of ['atlas', 'empty', 'beta'])
    expect(xml).toContain(
      '<loc>https://agatceramic.example/brand/' + slug + '</loc>',
    )
  expect(xml).not.toContain('/brand/disabled')
  for (const scenario of [
    'sitemap-brands-error',
    'sitemap-brands-page-error',
    'sitemap-brands-malformed',
    'sitemap-brands-loop',
    'sitemap-brands-moving-pages',
    'sitemap-brands-summary-malformed',
  ]) {
    await request.get('http://127.0.0.1:8015/__scenario?name=' + scenario)
    const failed = await request.get('/sitemap.xml')
    expect(failed.status()).toBe(503)
    expect(failed.headers()['cache-control']).toContain('no-store')
    expect(await failed.text()).not.toContain('<urlset')
  }
})

test('brand profiles are SSR content with clean canonical and filters remain noindex', async ({
  page,
  request,
}) => {
  const response = await request.get('/brand/atlas')
  expect(response.status()).toBe(200)
  expect(await response.text()).toContain(
    'Керамическая плитка и мозаика Atlas.',
  )
  await page.goto('/brand/atlas')
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
    'href',
    'https://agatceramic.example/brand/atlas',
  )
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
    'content',
    'index,follow',
  )
  await page.goto('/brand/atlas?category=tile&price_from=150')
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
    'href',
    'https://agatceramic.example/brand/atlas',
  )
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
    'content',
    'noindex,follow',
  )
  await expect(page.locator('.product-card')).toHaveCount(1)
})

test('unknown and inactive brands return 404 while an empty brand keeps its real identity', async ({
  page,
  request,
}) => {
  for (const slug of ['missing', 'disabled']) {
    const response = await request.get('/brand/' + slug)
    expect(response.status()).toBe(404)
    expect(await response.text()).toContain('noindex')
  }
  const response = await page.goto('/brand/empty')
  expect(response?.status()).toBe(200)
  const intro = page.getByRole('region', { name: 'Другой бренд', exact: true })
  await expect(
    intro.getByRole('heading', { name: 'Другой бренд', exact: true, level: 1 }),
  ).toBeVisible()
  await expect(intro.getByRole('img')).toHaveCount(0)
  await expect(intro.locator('.split-banner__description')).toHaveCount(0)
  await expect(page.locator('.product-card')).toHaveCount(0)
  await expect(
    page.getByText('По вашему запросу ничего не найдено', { exact: true }),
  ).toBeVisible()
})

test('switching brands changes the intro and full range without retaining old category or price', async ({
  page,
}) => {
  await page.goto('/brand/atlas?category=tile&price_from=150')
  const menu = page.getByRole('navigation', { name: 'Бренды по алфавиту' })
  await menu.getByRole('button', { name: 'B', exact: true }).click()
  await menu.getByRole('link', { name: 'Beta', exact: true }).click()
  await expect(page).toHaveURL(/\/brand\/beta$/)
  await expect(
    page.getByRole('heading', { name: 'Beta', exact: true, level: 1 }),
  ).toBeVisible()
  await expect(
    page.getByText('Сантехника бренда Beta.', { exact: true }),
  ).toBeVisible()
  await expect(
    page.getByRole('img', { name: 'Изображение бренда Beta', exact: true }),
  ).toBeVisible()
  await expect(page.locator('.product-card')).toHaveCount(2)
  const categories = page.getByRole('navigation', {
    name: 'Категории каталога',
  })
  await categories
    .getByRole('button', { name: 'Сантехника', exact: true })
    .click()
  await expect(
    categories.getByRole('link', { name: 'Ванны', exact: true }),
  ).toBeVisible()
  await expect(
    categories.getByRole('link', { name: 'Плитка', exact: true }),
  ).toHaveCount(0)
})

test('a catalog brand filter renders on the catalog route and stays after reload', async ({
  request,
  page,
}) => {
  const path = '/catalog?brand=atlas&category=tile&price_from=150'
  const response = await request.get(path, { maxRedirects: 0 })
  expect(response.status()).toBe(200)
  const html = await response.text()
  expect(html).toContain('Бренд: Atlas')
  expect(html).toContain('Плитка 02')
  await page.goto(path)
  await page.reload()
  await expect(page).toHaveURL(
    /\/catalog\?brand=atlas&category=tile&price_from=150$/,
  )
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
    'href',
    'https://agatceramic.example/catalog',
  )
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
    'content',
    'noindex,follow',
  )
  await expect(page.locator('.product-card')).toHaveCount(1)
})

test('route brand cannot be overridden by query', async ({ page }) => {
  await page.goto('/brand/atlas?brand=beta')
  await expect(
    page.getByRole('heading', { name: 'Atlas', exact: true, level: 1 }),
  ).toBeVisible()
  await expect(page.locator('.product-card')).toHaveCount(3)
  await page
    .getByRole('navigation', { name: 'Категории каталога' })
    .getByRole('button', { name: 'Материалы', exact: true })
    .click()
  await page
    .getByRole('navigation', { name: 'Категории каталога' })
    .getByRole('link', { name: 'Плитка', exact: true })
    .click()
  await expect(page).not.toHaveURL(/brand=/)
  await expect(page).toHaveURL(/\/brand\/atlas\?category=tile/)
})

test('brand pagination and sorting stay under the same route without a brand query', async ({
  page,
  request,
}) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=standalone-default')
  await page.goto('/brand/atlas')
  await expect(page.locator('.product-card')).toHaveCount(24)
  await page.getByRole('link', { name: 'Показать ещё 3' }).click()
  await expect(page).toHaveURL(/\/brand\/atlas(?:#|$)/)
  await expect(page).not.toHaveURL(/brand=/)
  await expect(page.locator('.product-card')).toHaveCount(27)
  await selectChoice(
    page.getByRole('combobox', { name: 'Сортировка', exact: true }),
    'Цена по убыванию',
  )
  await expect(page).not.toHaveURL(/page=2|brand=/)
  await expect(page).toHaveURL(/\/brand\/atlas\?sort=price_desc/)
})

test('brand profile errors return 503, stay noindex and retry into the real brand page', async ({
  page,
  request,
}) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=brand-page-error')
  const response = await page.goto('/brand/atlas')
  expect(response?.status()).toBe(503)
  await expect(page.getByRole('alert')).toContainText(
    'Не удалось загрузить бренд',
  )
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
    'content',
    'noindex,follow',
  )
  await expect(page.locator('#catalog-products')).toHaveCount(0)
  await expect(
    page.getByRole('region', { name: 'Бренд', exact: true }).getByRole('img'),
  ).toHaveCount(0)
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=standalone-brand-scope',
  )
  await page
    .getByRole('button', { name: 'Повторить загрузку', exact: true })
    .click()
  await expect(
    page.getByRole('heading', { name: 'Atlas', exact: true, level: 1 }),
  ).toBeVisible()
  await expect(
    page.getByText('Керамическая плитка и мозаика Atlas.', { exact: true }),
  ).toBeVisible()
  await expect(page.locator('.product-card')).toHaveCount(3)
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
    'content',
    'index,follow',
  )
})

test('broken brand images keep a literal long description readable without catalog artwork or executable HTML', async ({
  page,
  request,
}, testInfo) => {
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=brand-page-long-broken',
  )
  const errors: string[] = []
  page.on('pageerror', (error) => errors.push(error.message))
  for (const width of [320, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/brand/atlas')
    const intro = page.getByRole('region', { name: 'Atlas', exact: true })
    await expect(
      intro.getByRole('heading', { name: 'Atlas', exact: true, level: 1 }),
    ).toBeVisible()
    await expect(intro.getByRole('img')).toHaveCount(0)
    const description = intro.locator('.split-banner__description')
    await expect(description).toContainText('Описание Atlas.')
    await expect(description).toContainText('Отдельная строка описания.')
    await expect(description).toContainText(
      '<script>window.brandLeak = true</script>',
    )
    await expect(intro.locator('script')).toHaveCount(0)
    expect(
      await page.evaluate(
        () => (window as Window & { brandLeak?: boolean }).brandLeak,
      ),
    ).toBeUndefined()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: testInfo.outputPath('brand-long-broken-' + width + '.png'),
      fullPage: true,
    })
  }
  expect(errors).toEqual([])
})

test('catalog and brand navigation retain 55px top spacing and centered groups', async ({
  page,
}, testInfo) => {
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    for (const path of ['/catalog', '/brand/atlas']) {
      await page.goto(path)
      const intro = await page.locator('main > section').first().boundingBox()
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
      ).toBe(true)
      if (width <= 1024) {
        await expect(page.locator('.catalog-brand-navigation')).toBeHidden()
        await page.screenshot({
          path: testInfo.outputPath(
            (path === '/catalog' ? 'catalog' : 'brand') +
              '-layout-' +
              width +
              '.png',
          ),
          fullPage: true,
        })
        continue
      }
      const brands = await page
        .locator('.catalog-brand-navigation')
        .boundingBox()
      expect(
        Math.abs(brands!.y - (intro!.y + intro!.height) - 55),
      ).toBeLessThan(2)
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
      ).toBe(true)
      const menu = page.getByRole('navigation', { name: 'Бренды по алфавиту' })
      const label = await menu
        .getByText('Бренды:', { exact: true })
        .boundingBox()
      const letters = await menu.getByRole('button').all()
      const boxes = await Promise.all(
        letters.map((letter) => letter.boundingBox()),
      )
      const left = Math.min(label!.x, ...boxes.map((box) => box!.x))
      const right = Math.max(
        label!.x + label!.width,
        ...boxes.map((box) => box!.x + box!.width),
      )
      expect(
        Math.abs((left + right) / 2 - (brands!.x + brands!.width / 2)),
      ).toBeLessThan(2)
      if (width >= 1024) {
        const nav = page.getByRole('navigation', { name: 'Категории каталога' })
        const bounds = (await page
          .locator('.catalog-category-navigation')
          .boundingBox())!
        const links = await nav
          .locator(
            '.catalog-categories__roots > li > a, .ui-navigation-dropdown__trigger',
          )
          .all()
        const boxes = await Promise.all(links.map((link) => link.boundingBox()))
        const left = Math.min(...boxes.map((box) => box!.x))
        const right = Math.max(...boxes.map((box) => box!.x + box!.width))
        expect(
          Math.abs((left + right) / 2 - (bounds.x + bounds.width / 2)),
        ).toBeLessThan(2)
      }
      await page.screenshot({
        path: testInfo.outputPath(
          (path === '/catalog' ? 'catalog' : 'brand') +
            '-layout-' +
            width +
            '.png',
        ),
        fullPage: true,
      })
    }
  }
  expect(
    (
      await new AxeBuilder({ page })
        .include('.split-banner')
        .include('.catalog-brand-navigation')
        .analyze()
    ).violations,
  ).toEqual([])
})
