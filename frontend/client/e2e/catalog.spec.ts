import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=standalone-default')
})

test('standalone SSR catalog survives missing and failed CMS, and absent catalog blocks', async ({
  request,
}) => {
  for (const scenario of [
    'standalone-default',
    'standalone-missing',
    'standalone-cms-error',
  ]) {
    await request.get('http://127.0.0.1:8015/__scenario?name=' + scenario)
    const response = await request.get('/catalog')
    expect(response.status()).toBe(200)
    const html = await response.text()
    expect(html).toContain('Плитка 01')
    expect(html).toContain('Фильтры каталога')
    expect(html).toContain('CollectionPage')
  }
})

test('filter drafts commit to URL, scoped characteristics reset and history restores', async ({
  page,
}) => {
  await page.goto('/catalog?category=tile&brand=atlas&collection=Stone')
  const filters = page.getByRole('complementary', { name: 'Фильтры каталога' })
  await expect(
    filters.getByText('Морозостойкость', { exact: true }),
  ).toBeVisible()
  await filters.getByLabel('Цена от').fill('120')
  await filters.getByLabel('Нет', { exact: true }).check()
  expect(page.url()).not.toContain('price_from')
  await filters.getByRole('button', { name: 'Применить' }).click()
  await expect(page).toHaveURL(/price_from=120/)
  await expect(page).toHaveURL(/attributes=/)
  await expect(page.locator('.product-card')).toHaveCount(1)
  await page
    .getByRole('navigation', { name: 'Категории каталога' })
    .getByRole('link', { name: 'Мозаика', exact: true })
    .click()
  await expect(page).toHaveURL(/category=mosaic/)
  expect(page.url()).not.toContain('attributes')
  await expect(
    filters.getByText('Морозостойкость', { exact: true }),
  ).toHaveCount(0)
  await page.goBack()
  await expect(filters.getByLabel('Цена от')).toHaveValue('120,00')
  await expect(filters.getByLabel('Нет', { exact: true })).toBeChecked()
})

test('pagination, sorting, empty, validation error and retry are usable', async ({
  page,
  request,
}) => {
  await page.goto('/catalog')
  await expect(page.locator('.product-card')).toHaveCount(24)
  await page.getByRole('link', { name: 'Следующая →' }).click()
  await expect(page.locator('.product-card')).toHaveCount(3)
  await page.getByLabel('Сортировка').selectOption('price_desc')
  await expect(page).not.toHaveURL(/page=2/)
  await page.goto('/catalog?category=mosaic')
  await expect(
    page.getByText('По вашему запросу ничего не найдено'),
  ).toBeVisible()
  await page.goto(
    '/catalog?attributes=%7B%221%22%3A%7B%22values%22%3A%5B%22gray%22%5D%7D%7D',
  )
  await expect(page.getByRole('alert')).toContainText('Проверьте фильтры')
  await request.get('http://127.0.0.1:8015/__scenario?name=standalone-error')
  await page.goto('/catalog')
  await expect(page.getByRole('alert')).toContainText(
    'Не удалось загрузить каталог',
  )
  await request.get('http://127.0.0.1:8015/__scenario?name=standalone-default')
  await page.getByRole('button', { name: 'Повторить загрузку' }).click()
  await expect(page.locator('.product-card')).toHaveCount(24)
})

test('responsive filters, drawer focus and keyboard, motion, axe and hydration', async ({
  page,
}, testInfo) => {
  const issues: string[] = []
  page.on('pageerror', (error) => issues.push(error.message))
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) issues.push(message.text())
  })
  await page.goto('/catalog?category=tile')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    if (width < 1024) {
      const opener = page.getByRole('button', { name: 'Фильтры', exact: true })
      await opener.click()
      const dialog = page.getByRole('dialog', { name: 'Фильтры каталога' })
      await expect(dialog).toBeFocused()
      await page.keyboard.press('Tab')
      await expect(
        dialog.getByRole('button', { name: 'Закрыть' }),
      ).toBeFocused()
      await page.screenshot({
        path: testInfo.outputPath('catalog-drawer-' + width + '.png'),
        fullPage: true,
      })
      await page.keyboard.press('Escape')
      await expect(dialog).toHaveCount(0)
      await expect(opener).toBeFocused()
    }
    if (width >= 1024) {
      const date = page.getByRole('complementary').getByLabel('Дата выпуска от')
      expect(
        await date.evaluate((el) => el.clientWidth >= el.scrollWidth),
      ).toBe(true)
      await date.scrollIntoViewIfNeeded()
      await page.screenshot({
        path: testInfo.outputPath('catalog-date-' + width + '.png'),
      })
    }
    await page.screenshot({
      path: testInfo.outputPath('catalog-' + width + '.png'),
      fullPage: true,
    })
  }
  const violations = (await new AxeBuilder({ page }).analyze()).violations
  // Keep the existing approved ProductCard placeholder contrast finding explicit.
  const known = violations.filter(
    (violation) =>
      violation.id === 'color-contrast' &&
      violation.nodes.every((node) =>
        node.target.every(
          (target) =>
            typeof target === 'string' && target.endsWith('.image-empty'),
        ),
      ),
  )
  expect(known).toHaveLength(1)
  expect(violations.filter((violation) => !known.includes(violation))).toEqual(
    [],
  )
  expect(issues).toEqual([])
})

test('price fields format editable rubles while URL carries exact decimals', async ({
  page,
}) => {
  await page.goto('/catalog')
  const field = page.getByRole('complementary').getByLabel('Цена от')
  await field.fill('17926.5')
  await field.blur()
  await expect(field).toHaveValue('17\u00a0926,50')
  await page
    .getByRole('complementary')
    .getByRole('button', { name: 'Применить' })
    .click()
  await expect(page).toHaveURL(/price_from=17926.50/)
})

test('malformed nested and repeated query values show a recoverable error', async ({
  page,
}) => {
  const issues: string[] = []
  page.on('pageerror', (error) => issues.push(error.message))
  for (const query of [
    'category=tile&attributes=' +
      encodeURIComponent(
        JSON.stringify({ 17: { min: 1 }, 2: { values: 'wrong' } }),
      ),
    'brand=atlas&brand=empty',
    'price_from=10&price_from=20',
  ]) {
    await page.goto('/catalog?' + query)
    await expect(page.getByRole('alert')).toContainText('Проверьте фильтры')
    await page
      .getByRole('button', { name: 'Сбросить всё', exact: true })
      .first()
      .click()
    await expect(page.locator('.product-card')).toHaveCount(24)
  }
  expect(issues).toEqual([])
})

test('draft brand changes clear incompatible collections and restore applied choices', async ({
  page,
}) => {
  await page.goto('/catalog?brand=atlas&collection=Stone')
  const form = page.getByRole('complementary')
  await form.getByLabel('Бренд', { exact: true }).selectOption('empty')
  await expect(form.getByLabel('Коллекция', { exact: true })).toHaveValue('')
  await form.getByLabel('Бренд', { exact: true }).selectOption('atlas')
  await expect(
    form
      .getByLabel('Коллекция', { exact: true })
      .getByRole('option', { name: 'Stone', exact: true }),
  ).toHaveCount(1)
  await form.getByLabel('Коллекция', { exact: true }).selectOption('Stone')
  await form.getByLabel('Бренд', { exact: true }).selectOption('')
  await expect(form.getByLabel('Коллекция', { exact: true })).toHaveValue('')
  await expect(form.getByLabel('Коллекция', { exact: true })).toBeDisabled()
  await form.getByRole('button', { name: 'Применить', exact: true }).click()
  await expect(page).not.toHaveURL(/collection=/)
  await expect(page).not.toHaveURL(/brand=/)
})

test('reset retains sorting; applied chips describe selected values and bounds', async ({
  page,
}) => {
  const attributes = {
    1: { values: ['gray'] },
    3: { values: ['false'] },
    4: { min: '0', max: '600' },
  }
  await page.goto(
    '/catalog?category=tile&sort=price_desc&attributes=' +
      encodeURIComponent(JSON.stringify(attributes)),
  )
  await expect(
    page.getByRole('button', {
      name: 'Убрать фильтр: Цвет: Серый',
      exact: true,
    }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', {
      name: 'Убрать фильтр: Морозостойкость: Нет',
      exact: true,
    }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', {
      name: 'Убрать фильтр: Длина: от 0 до 600 мм',
      exact: true,
    }),
  ).toBeVisible()
  await page
    .getByRole('button', { name: 'Сбросить всё', exact: true })
    .last()
    .click()
  await expect(page).toHaveURL(/\/catalog\?sort=price_desc#catalog-products$/)
  expect(page.url()).not.toContain('attributes=')
  expect(page.url()).not.toContain('category=')
})

test('pending category result disables stale characteristic controls', async ({
  page,
}) => {
  await page.goto('/catalog?category=tile')
  let release: () => void = () => {}
  const wait = new Promise<void>((resolve) => (release = resolve))
  await page.route('**/catalog?**', async (route) => {
    await wait
    await route.continue()
  })
  await page
    .getByRole('navigation', { name: 'Категории каталога' })
    .getByRole('link', { name: 'Мозаика', exact: true })
    .click()
  await expect(
    page.getByRole('complementary').getByLabel('Рисунок', { exact: true }),
  ).toBeDisabled()
  release()
  await expect(
    page.getByRole('complementary').getByLabel('Рисунок', { exact: true }),
  ).toHaveCount(0)
})

test('reset clears draft and money validity even when URL and hash are unchanged', async ({
  page,
}) => {
  await page.goto('/catalog#catalog-products')
  const form = page.getByRole('complementary')
  await form.getByRole('button', { name: 'Сбросить всё', exact: true }).click()
  await form.getByLabel('Цена от').fill('wrong')
  await form.getByLabel('Цена от').blur()
  expect(
    await form
      .getByLabel('Цена от')
      .evaluate((el: HTMLInputElement) => el.validity.valid),
  ).toBe(false)
  await form.getByLabel('Бренд', { exact: true }).selectOption('atlas')
  await form.getByRole('button', { name: 'Сбросить всё', exact: true }).click()
  await expect(form.getByLabel('Цена от')).toHaveValue('')
  await expect(form.getByLabel('Бренд', { exact: true })).toHaveValue('')
  expect(
    await form
      .getByLabel('Цена от')
      .evaluate((el: HTMLInputElement) => el.validity.valid),
  ).toBe(true)
  await form.getByLabel('Цена от').fill('120')
  await form.getByRole('button', { name: 'Применить', exact: true }).click()
  await expect(page).toHaveURL(/price_from=120.00/)
})

test('all characteristic input types serialize exact selected values including zero and false', async ({
  page,
}) => {
  await page.goto('/catalog?category=tile')
  const form = page.getByRole('complementary')
  await form
    .getByRole('group', { name: 'Цвет', exact: true })
    .getByLabel('Серый')
    .check()
  await form
    .getByRole('group', { name: 'Поверхность', exact: true })
    .getByLabel('Белый')
    .check()
  await form
    .getByRole('group', { name: 'Морозостойкость', exact: true })
    .getByLabel('Нет', { exact: true })
    .check()
  await form.getByLabel('Длина от', { exact: true }).fill('0')
  await form.getByLabel('Длина до', { exact: true }).fill('600')
  await form.getByLabel('Толщина от', { exact: true }).fill('0.5')
  await form.getByLabel('Дата выпуска от', { exact: true }).fill('2026-01-01')
  await form.getByLabel('Дата выпуска до', { exact: true }).fill('2026-12-31')
  await form.getByLabel('Рисунок', { exact: true }).fill('камень')
  await form.getByLabel('Описание', { exact: true }).fill('матовая')
  await expect(form.getByLabel('Описание', { exact: true })).toHaveAttribute(
    'maxlength',
    '200',
  )
  await form.getByRole('button', { name: 'Применить', exact: true }).click()
  await expect(page).toHaveURL(/attributes=/)
  expect(
    JSON.parse(new URL(page.url()).searchParams.get('attributes')!),
  ).toEqual({
    1: { values: ['gray'] },
    2: { values: ['white'] },
    3: { values: ['false'] },
    4: { min: '0', max: '600' },
    5: { min: '0.5' },
    6: { min: '2026-01-01', max: '2026-12-31' },
    7: { text: 'камень' },
    8: { text: 'матовая' },
  })
})

test('an old collection lookup cannot replace metadata after category navigation', async ({
  page,
}) => {
  await page.goto('/catalog?category=tile&brand=atlas')
  let release: () => void = () => {}
  const gate = new Promise<void>((resolve) => (release = resolve))
  let started: () => void = () => {}
  const seen = new Promise<void>((resolve) => (started = resolve))
  await page.route('**/catalog?**', async (route) => {
    const url = new URL(route.request().url())
    if (
      url.searchParams.get('brand') === 'empty' &&
      url.searchParams.get('category') === 'tile'
    ) {
      started()
      await gate
      try {
        await route.fulfill({
          json: {
            data: [],
            categories: [],
            meta: { current_page: 1, last_page: 1, total: 0 },
            filters: {
              brands: [],
              attributes: [],
              collections: [
                { id: 99, name: 'Устаревшая коллекция', brand_slug: 'empty' },
              ],
            },
          },
        })
      } catch {}
    } else await route.continue()
  })
  const form = page.getByRole('complementary')
  await form.getByLabel('Бренд', { exact: true }).selectOption('empty')
  await seen
  await page
    .getByRole('navigation', { name: 'Категории каталога' })
    .getByRole('link', { name: 'Мозаика', exact: true })
    .click()
  await expect(form.getByLabel('Бренд', { exact: true })).toHaveValue('atlas')
  await expect(
    form
      .getByLabel('Коллекция', { exact: true })
      .getByRole('option', { name: 'Stone', exact: true }),
  ).toHaveCount(1)
  release()
  await page.waitForTimeout(150)
  await expect(
    form
      .getByLabel('Коллекция', { exact: true })
      .getByRole('option', { name: 'Устаревшая коллекция', exact: true }),
  ).toHaveCount(0)
})
