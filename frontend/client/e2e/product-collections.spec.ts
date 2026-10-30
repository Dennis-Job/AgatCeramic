import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const api = 'http://127.0.0.1:8015'
const path = '/products/product-1'
const headline = 'Спокойный камень для светлых пространств'
test.beforeEach(async ({ request }) => {
  await request.get(api + '/__scenario?name=product-collection')
})

test('complete collection renders in SSR between details and accessories with a brand-scoped catalog link', async ({
  page,
  request,
}) => {
  const html = await (await request.get(path)).text()
  expect(html).toContain('class="product-collection-story"')
  expect(html).toContain(headline)
  const issues: string[] = []
  page.on('pageerror', (error) => issues.push(error.message))
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) issues.push(message.text())
  })
  await page.goto(path)
  const story = page.getByRole('region', { name: headline })
  await expect(story).toBeVisible()
  await expect(story).toContainText('Тестовая коллекция')
  await expect(story).toContainText('Тестовый бренд · Россия')
  await expect(story).toContainText('Настоящее описание коллекции из API.')
  await expect(story.getByRole('img')).toHaveAttribute(
    'alt',
    'Коллекция в интерьере светлой ванной',
  )
  await expect(story.getByRole('img')).toHaveAttribute('loading', 'lazy')
  expect(
    await story.evaluate(
      (el) =>
        !!el.previousElementSibling?.querySelector('[role="tablist"]') &&
        !!el.nextElementSibling?.querySelector('.related-section'),
    ),
  ).toBe(true)
  await expect(story.getByText('Получить визуализацию')).toHaveCount(0)
  const link = story.getByRole('link', { name: 'Товары коллекции' })
  await link.focus()
  await page.keyboard.press('Tab')
  await page.keyboard.press('Shift+Tab')
  await expect(link).toBeFocused()
  expect(
    await link.evaluate((el) => getComputedStyle(el).outlineStyle),
  ).not.toBe('none')
  await page.keyboard.press('Enter')
  const url = new URL(page.url())
  expect(url.pathname).toBe('/catalog')
  expect(url.searchParams.get('brand')).toBe('test-brand')
  expect(url.searchParams.get('collection')).toBe('Тестовая коллекция')
  expect(issues).toEqual([])
})

for (const scenario of [
  'default',
  'product-collection-null',
  'product-collection-no-description',
  'product-collection-no-image',
  'product-collection-private-image',
]) {
  test(`no section or spacing wrapper when public collection is incomplete: ${scenario}`, async ({
    page,
    request,
  }) => {
    await request.get(api + '/__scenario?name=' + scenario)
    const html = await (await request.get(path)).text()
    expect(html).not.toContain('class="product-collection-story"')
    await page.goto(path)
    await expect(page.locator('.product-collection-story')).toHaveCount(0)
    // The existing independent variants section remains available.
    await expect(
      page.getByRole('heading', { name: 'Варианты товара' }),
    ).toBeVisible()
  })
}

test('broken public image removes the whole section and a new product does not retain old collection content', async ({
  page,
  request,
}) => {
  await request.get(api + '/__scenario?name=product-collection-broken-image')
  await page.goto(path)
  await page
    .locator('.product-collection-story img')
    .evaluateAll((images) => images[0]?.scrollIntoView({ block: 'center' }))
  await expect(page.locator('.product-collection-story')).toHaveCount(0)
  await request.get(api + '/__scenario?name=product-collection')
  await page.goto(path)
  await expect(page.getByRole('region', { name: headline })).toBeVisible()
  await page.getByRole('link', { name: 'Цвет: Светлый', exact: true }).click()
  await expect(page).toHaveURL(/\/products\/product-2$/)
  await expect(page.locator('.product-collection-story')).toHaveCount(0)
  await page.getByRole('link', { name: 'Цвет: Серый', exact: true }).click()
  await expect(page.getByRole('region', { name: headline })).toBeVisible()
})

test('blank headline uses collection name and missing optional country and alt stay meaningful', async ({
  page,
  request,
}) => {
  await request.get(api + '/__scenario?name=product-collection-fallback')
  await page.goto(path)
  const story = page.getByRole('region', {
    name: 'Тестовая коллекция',
    exact: true,
  })
  await expect(story.getByRole('heading')).toHaveText('Тестовая коллекция')
  await expect(story.getByRole('img')).toHaveAttribute(
    'alt',
    'Тестовая коллекция',
  )
  await expect(story).toContainText('Тестовый бренд')
  await expect(story).not.toContainText('·')
})

test('collection reference layout handles long copy, five widths and accessibility', async ({
  page,
  request,
}, testInfo) => {
  for (const variant of ['long', 'complete']) {
    await request.get(
      api +
        '/__scenario?name=' +
        (variant === 'long' ? 'product-collection-long' : 'product-collection'),
    )
    await page.goto(path)
    const story = page.locator('.product-collection-story')
    for (const width of [320, 640, 768, 1024, 1280]) {
      await page.setViewportSize({ width, height: 900 })
      await story.scrollIntoViewIfNeeded()
      await expect(story.getByRole('img')).toBeVisible()
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
      ).toBe(true)
      const text = await story
        .locator('.product-collection-story__text')
        .boundingBox()
      const image = await story.getByRole('img').boundingBox()
      expect(text).not.toBeNull()
      expect(image).not.toBeNull()
      if (width <= 900)
        expect(image!.y).toBeGreaterThanOrEqual(text!.y + text!.height)
      else expect(image!.x).toBeGreaterThanOrEqual(text!.x + text!.width)
      // Fit the full long section so the sticky header does not cover its title in review evidence.
      const sectionHeight = await story.evaluate(
        (el) => el.getBoundingClientRect().height,
      )
      await page.setViewportSize({
        width,
        height: Math.ceil(sectionHeight) + 200,
      })
      await story.evaluate((el) =>
        window.scrollTo(
          0,
          window.scrollY + el.getBoundingClientRect().top - 120,
        ),
      )
      await story.screenshot({
        path: testInfo.outputPath(
          'collection-' + variant + '-' + width + '.png',
        ),
      })
    }
    expect(
      (
        await new AxeBuilder({ page })
          .include('.product-collection-story')
          .analyze()
      ).violations,
    ).toEqual([])
  }
})
