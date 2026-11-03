import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const productPath = '/products/product-1'
test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
})

test('specification groups retain their own values, order and SSR headings', async ({
  page,
  request,
}, testInfo) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=product-specs')
  const html = await (await request.get(productPath)).text()
  expect(html).toContain('class="spec-group-title eyebrow"')
  await page.goto(productPath)
  const panel = page.getByRole('tabpanel', {
    name: 'Характеристики',
    exact: true,
  })
  const groups = panel.locator('.spec-group')
  await expect(groups.locator('h2')).toHaveText([
    'Поверхность',
    'Размеры и вес',
    'Поверхность',
    'Прочие характеристики',
  ])
  await expect(groups.nth(0).locator('dt')).toHaveText(['Текстура'])
  await expect(groups.nth(1).locator('dt')).toHaveText(['Вес', 'Размер'])
  await expect(groups.nth(1).locator('dd')).toHaveText([
    '26,16 кг',
    '300×300 мм',
  ])
  await expect(groups.nth(2).locator('dt')).toHaveText(['Фактура'])
  await expect(groups.nth(3).locator('dt')).toHaveText(['Особенность'])
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.locator('#product-details').screenshot({
      path: testInfo.outputPath('spec-groups-' + width + '.png'),
    })
  }
  expect(
    (await new AxeBuilder({ page }).include('#product-details').analyze())
      .violations,
  ).toEqual([])
})

test('specifications handle legacy, empty and long content without losing values or overflowing', async ({
  page,
  request,
}, testInfo) => {
  for (const scenario of ['legacy', 'empty', 'long']) {
    await request.get(
      'http://127.0.0.1:8015/__scenario?name=product-specs-' + scenario,
    )
    await page.goto(productPath)
    const panel = page.getByRole('tabpanel', {
      name: 'Характеристики',
      exact: true,
    })
    if (scenario === 'empty') {
      await expect(panel).toHaveText('Характеристики пока не добавлены.')
      await expect(panel.locator('h2, dl')).toHaveCount(0)
    } else if (scenario === 'legacy') {
      await expect(panel.locator('h2')).toHaveCount(0)
      await expect(panel.locator('dt')).toHaveCount(5)
    } else {
      for (const width of [320, 640, 768, 1024, 1280]) {
        await page.setViewportSize({ width, height: 900 })
        expect(
          await panel
            .locator('h2, dt, dd')
            .evaluateAll((elements) =>
              elements.every(
                (element) => element.scrollWidth <= element.clientWidth + 1,
              ),
            ),
        ).toBe(true)
        expect(
          await page.evaluate(
            () => document.documentElement.scrollWidth <= innerWidth,
          ),
        ).toBe(true)
        await page.locator('#product-details').screenshot({
          path: testInfo.outputPath('spec-long-' + width + '.png'),
        })
      }
    }
  }
})

test('purchase stays compact and tabs keep the centered active underline from the reference', async ({
  page,
}) => {
  await page.goto(productPath)
  await expect(
    page.locator(
      '.product-purchase .consultation, .product-purchase .quick-links',
    ),
  ).toHaveCount(0)
  await expect(page.getByText('Целые единицы', { exact: true })).toHaveCount(0)
  const description = page.getByRole('tab', { name: 'Описание', exact: true })
  await page.mouse.move(0, 0)
  const underline = () =>
    description.evaluate((el) => {
      const style = getComputedStyle(el, '::after')
      return {
        transform: style.transform,
        height: style.height,
        bottom: style.bottom,
        originX: parseFloat(style.transformOrigin),
        width: el.getBoundingClientRect().width,
      }
    })
  expect((await underline()).transform).toBe('matrix(0, 0, 0, 1, 0, 0)')
  await description.hover()
  await expect(description).toHaveAttribute('aria-selected', 'false')
  expect((await underline()).transform).toBe('matrix(0, 0, 0, 1, 0, 0)')
  await description.click()
  await expect
    .poll(async () => (await underline()).transform)
    .toBe('matrix(1, 0, 0, 1, 0, 0)')
  const activeLine = await underline()
  expect(activeLine).toMatchObject({ height: '1px', bottom: '0px' })
  expect(activeLine.originX).toBeCloseTo(activeLine.width / 2, 1)
  await page.mouse.move(0, 0)
  expect((await underline()).transform).toBe('matrix(1, 0, 0, 1, 0, 0)')
  await description.press('ArrowLeft')
  await expect
    .poll(async () => (await underline()).transform)
    .toBe('matrix(0, 0, 0, 1, 0, 0)')
})

test('empty optional product sections leave one divider at each boundary', async ({
  page,
  request,
}) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=product-empty')
  await page.goto(productPath)

  const purchase = page.locator('.product-purchase')
  const information = page.locator('.product-information')
  await expect(purchase.locator('.variants')).toHaveCount(0)
  await expect(purchase.locator('.price-box')).toHaveCSS(
    'border-bottom-width',
    '1px',
  )
  await expect(purchase.locator('.purchase-form')).toHaveCSS(
    'border-top-width',
    '0px',
  )
  await expect(
    page.locator(
      '.product-collection-story, .related-section, .recommended-section',
    ),
  ).toHaveCount(0)
  await expect(information).toHaveCSS('border-bottom-width', '0px')
  await expect(page.locator('.site-footer')).toHaveCSS(
    'border-top-width',
    '1px',
  )

  await request.get('http://127.0.0.1:8015/__scenario?name=default')
  await page.reload()
  await expect(purchase.locator('.variants')).toBeVisible()
  await expect(purchase.locator('.purchase-form')).toHaveCSS(
    'border-top-width',
    '1px',
  )
  await expect(page.locator('.related-section')).toBeVisible()
  await expect(information).toHaveCSS('border-bottom-width', '1px')
})

test('product, related and recommended actions add their own IDs to the shared server cart', async ({
  page,
  request,
}) => {
  await request.get('http://127.0.0.1:8015/__cart/reset')
  await page.goto(productPath)
  await page
    .getByRole('spinbutton', { name: 'Количество, м²', exact: true })
    .fill('2')
  await page.getByRole('button', { name: 'В корзину', exact: true }).click()
  await expect(
    page.getByRole('status').filter({ hasText: 'Товар добавлен в корзину.' }),
  ).toBeVisible()
  await page
    .locator('.related-section')
    .getByRole('button', { name: 'Добавить', exact: true })
    .click()
  await expect(
    page.getByRole('button', { name: 'Добавить', exact: true }).first(),
  ).toBeEnabled()
  await page.locator('.recommended-section .product-card').hover()
  await page
    .locator('.recommended-section')
    .getByRole('button', { name: 'В корзину +', exact: true })
    .click()
  await page.getByRole('button', { name: 'Корзина', exact: true }).click()
  const cart = page.getByRole('dialog', { name: 'Корзина', exact: true })
  await expect(cart).toContainText('Керамогранит с длинным русским названием 1')
  await expect(cart).toContainText('Тестовая затирка')
  await expect(cart).toContainText('Рекомендуемый керамогранит')
  const stats = await (
    await request.get('http://127.0.0.1:8015/__cart/stats')
  ).json()
  expect(
    stats.requests
      .filter((entry: { method: string }) => entry.method === 'POST')
      .map(
        (entry: { payload: { product_id: number; quantity: number } }) =>
          entry.payload,
      ),
  ).toEqual([
    { product_id: 1, quantity: 2 },
    { product_id: 3, quantity: 1 },
    { product_id: 4, quantity: 1 },
  ])
})

test('product data and one canonical URL are rendered by SSR', async ({
  request,
}) => {
  const response = await request.get(productPath)
  expect(response.status()).toBe(200)
  const html = await response.text()
  expect(html).toContain('Керамогранит с длинным русским названием 1')
  expect(html).toContain('TEST-1')
  expect(html).toContain('TEST-ARTICLE-1')
  expect(html).toContain('https://agatceramic.example/products/product-1')
  expect(html).toContain('BreadcrumbList')
  expect(html).toContain('"@type":"Product"')
  expect(html).not.toContain('stock_quantity')
  expect(html).not.toContain('data:image/')
  expect(html).toContain('product-sale-badge')
  expect(html).toContain('Распродажа')
})

test('sale badge follows the product flag, not old price, and does not block gallery actions', async ({
  page,
  request,
}) => {
  await page.goto(productPath)
  const gallery = page.locator('.product-gallery')
  await expect(gallery.locator('.product-sale-badge')).toHaveText('Распродажа')
  await expect(
    page.locator('.recommended-section .product-sale-badge'),
  ).toHaveText('Распродажа')
  await expect(
    page.locator('.related-section .product-sale-badge'),
  ).toHaveCount(0)
  await gallery.getByRole('button', { name: 'Увеличить фото товара' }).click()
  await expect(
    page.getByRole('dialog', { name: 'Просмотр фото' }),
  ).toBeVisible()
  await page.keyboard.press('Escape')
  await request.get('http://127.0.0.1:8015/__scenario?name=product-not-sale')
  await page.goto(productPath)
  await expect(gallery.locator('.product-sale-badge')).toHaveCount(0)
  await expect(page.locator('.product-purchase del')).toBeVisible()
  await request.get('http://127.0.0.1:8015/__scenario?name=product-empty')
  await page.goto(productPath)
  await expect(gallery.locator('.product-sale-badge')).toHaveText('Распродажа')
})

test('gallery, centered tabs and independent color variants use actual API data', async ({
  page,
}) => {
  const issues: string[] = []
  page.on('pageerror', (error) => issues.push(error.message))
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) issues.push(message.text())
  })
  await page.goto(productPath)
  await expect(page.locator('main h1')).toHaveText(
    'Керамогранит с длинным русским названием 1',
  )
  await page.getByRole('button', { name: 'Фото 2 из 3', exact: true }).click()
  await expect(
    page.locator('[data-testid="product-main-image"]'),
  ).toHaveAttribute('src', /category-porcelain/)
  await page.getByRole('button', { name: 'Увеличить фото товара' }).click()
  const zoom = page.getByRole('dialog', { name: 'Просмотр фото' })
  await expect(zoom).toBeVisible()
  await page.keyboard.press('ArrowRight')
  await expect(zoom.getByText('3 / 3', { exact: true })).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(zoom).toHaveCount(0)
  const specs = page.getByRole('tab', { name: 'Характеристики', exact: true })
  await specs.click()
  await expect(
    page.getByRole('tabpanel', { name: 'Характеристики' }),
  ).toContainText('Тестовая коллекция')
  await specs.press('ArrowRight')
  await expect(
    page.getByRole('tab', { name: 'Описание', exact: true }),
  ).toHaveAttribute('aria-selected', 'true')
  await expect(page.getByRole('tabpanel', { name: 'Описание' })).toContainText(
    'Описание из API',
  )
  expect(
    await page
      .getByRole('tablist')
      .evaluate((el) => getComputedStyle(el).justifyContent),
  ).toBe('center')
  expect(
    await page
      .getByRole('tablist')
      .evaluate((el) => getComputedStyle(el).borderBottomWidth),
  ).toBe('0px')
  await page.getByRole('link', { name: 'Цвет: Светлый', exact: true }).click()
  await expect(page).toHaveURL(/\/products\/product-2$/)
  await expect(page.locator('main h1')).toHaveText('Керамогранит Светлый')
  await expect(page.locator('link[rel="canonical"]')).toHaveAttribute(
    'href',
    'https://agatceramic.example/products/product-2',
  )
  expect(issues).toEqual([])
})

test('brand, collection and category links filter the actual catalog without duplicate products', async ({
  page,
}) => {
  await page.goto(productPath)
  await page
    .getByRole('link', { name: 'Все товары бренда', exact: true })
    .click()
  await expect(page).toHaveURL(/\/catalog\?brand=test-brand/)
  await expect(page.locator('#catalog-products')).toContainText(
    'Тестовый бренд',
  )
  await expect(
    page.locator('#catalog-products a[href="/products/product-1"]'),
  ).not.toHaveCount(0)
  await page.goto(productPath)
  await page
    .getByRole('link', { name: 'Коллекция «Тестовая коллекция»', exact: true })
    .click()
  await expect(page).toHaveURL(/brand=test-brand.*collection=/)
  await expect(page.locator('#catalog-products')).toContainText(
    'Тестовая коллекция',
  )
})

test('integer quantity is enforced and null or unavailable products stay usable', async ({
  page,
  request,
}) => {
  await page.goto(productPath)
  let writes = 0
  page.on('request', (req) => {
    if (req.method() === 'POST' && req.url().endsWith('/cart/items')) writes++
  })
  const quantity = page.getByRole('spinbutton', {
    name: 'Количество, м²',
    exact: true,
  })
  await quantity.fill('1.5')
  await page
    .getByRole('button', { name: 'В корзину', exact: true })
    .first()
    .click()
  expect(writes).toBe(0)
  await request.get('http://127.0.0.1:8015/__scenario?name=product-empty')
  await page.goto(productPath)
  await expect(page.getByText('Изображение пока не добавлено')).toBeVisible()
  await expect(
    page.getByText('Цена по запросу', { exact: true }).first(),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'В корзину', exact: true }).first(),
  ).toBeDisabled()
  await page.getByRole('tab', { name: 'Описание', exact: true }).click()
  await expect(
    page.getByText('Подробное описание пока не добавлено.'),
  ).toBeVisible()
})

test('missing and failed product requests return correct SSR status and noindex', async ({
  request,
}) => {
  const missing = await request.get('/products/not-published')
  expect(missing.status()).toBe(404)
  expect(await missing.text()).toContain('noindex')
  await request.get('http://127.0.0.1:8015/__scenario?name=product-error')
  const failed = await request.get(productPath)
  expect(failed.status()).toBe(503)
  expect(await failed.text()).toContain('noindex')
})

test('real product composition is responsive and accessible at five widths', async ({
  page,
  request,
}, testInfo) => {
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=product-recommendations',
  )
  await page.goto(productPath)
  const related = page.getByRole('region', { name: 'Что ещё понадобится' })
  const recommended = page.getByRole('region', {
    name: 'Рекомендуем посмотреть',
  })
  await expect(
    page.getByRole('heading', { name: 'Варианты товара' }),
  ).toHaveCount(0)
  await expect(recommended.locator('.section-heading .eyebrow')).toHaveText(
    'Обратите внимание',
  )
  await expect(recommended.locator('.section-heading p')).toHaveCount(0)
  const cards = recommended.locator('.product-card')
  await expect(cards.first().locator('.card-brand')).toHaveText(
    'Тестовый бренд',
  )
  await expect(cards.first().locator('.card-brand')).toHaveCSS(
    'text-transform',
    'uppercase',
  )
  await expect(cards.first().locator('.card-info > :first-child')).toHaveClass(
    /card-brand/,
  )
  await expect(cards.first().locator('h3 + .card-meta')).toHaveText(
    'Керамогранит · 300×300 мм',
  )
  await expect(cards.nth(1).locator('.card-meta')).toHaveText('Керамогранит')
  await expect(cards.nth(1).locator('.card-brand')).toHaveCount(0)
  await expect(cards.first().locator('.card-info')).not.toContainText(
    'В наличии',
  )
  await expect(related.locator('.eyebrow')).toHaveText('Сопутствующие товары')
  await expect(related.locator('.section-heading p')).toHaveText(
    'К этому товару',
  )
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    const dividerWidth = await recommended.evaluate(
      (section) =>
        parseFloat(getComputedStyle(section).borderBottomWidth) +
        parseFloat(
          getComputedStyle(document.querySelector('footer')!).borderTopWidth,
        ),
    )
    expect(dividerWidth).toBe(1)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await expect(
      page.getByRole('tab', { name: 'Характеристики', exact: true }),
    ).toBeVisible()
    await page.screenshot({
      path: testInfo.outputPath('product-' + width + '.png'),
      fullPage: true,
    })
    await related.screenshot({
      path: testInfo.outputPath('related-' + width + '.png'),
    })
    await recommended.screenshot({
      path: testInfo.outputPath('recommended-' + width + '.png'),
    })
  }
  const imageLink = recommended.getByRole('link', { name: /^Открыть / }).first()
  await imageLink.focus()
  await page.keyboard.press('Tab')
  const recommendedAdd = recommended
    .getByRole('button', {
      name: 'В корзину +',
      exact: true,
    })
    .first()
  await expect(recommendedAdd).toBeFocused()
  await expect(recommendedAdd).toHaveCSS('transform', 'none')
  await recommended.screenshot({
    path: testInfo.outputPath('recommended-focus.png'),
  })
  const add = related.getByRole('button', { name: 'Добавить', exact: true })
  await expect(add).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)')
  await add.hover()
  await expect(add).toHaveCSS('background-color', 'rgb(20, 20, 20)')
  await expect(add).toHaveCSS('color', 'rgb(255, 255, 255)')
  await add.focus()
  await expect(add).toBeFocused()
  const violations = (await new AxeBuilder({ page }).analyze()).violations
  expect(violations).toEqual([])
})

test.describe('touch recommendations', () => {
  test.use({
    hasTouch: true,
    isMobile: true,
    viewport: { width: 320, height: 800 },
  })

  test('add action stays visible below the image without hover', async ({
    page,
  }, testInfo) => {
    await page.goto(productPath)
    const recommended = page.getByRole('region', {
      name: 'Рекомендуем посмотреть',
    })
    const add = recommended.getByRole('button', {
      name: 'В корзину +',
      exact: true,
    })
    await expect(add).toHaveCSS('position', 'static')
    await expect(add).toHaveCSS('transform', 'none')
    await recommended.screenshot({
      path: testInfo.outputPath('recommended-touch-320.png'),
    })
    await add.click()
    await expect(
      page.getByRole('status').filter({ hasText: 'Товар добавлен в корзину.' }),
    ).toBeVisible()
  })
})
