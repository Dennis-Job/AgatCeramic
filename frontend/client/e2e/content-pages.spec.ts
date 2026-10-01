import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
})

test('published content and SEO are in SSR HTML; blocks preserve order and escape HTML', async ({
  page,
  request,
}) => {
  for (const route of ['/', '/contacts', '/about', '/catalog']) {
    const response = await request.get(route)
    expect(response.status()).toBe(200)
    const html = await response.text()
    expect(html).toContain('rel="canonical"')
    expect(html).toContain('application/ld+json')
    expect(html).toContain('property="og:title"')
  }
  expect(await (await request.get('/contacts')).text()).toContain('Магазин 2')
  expect(await (await request.get('/catalog')).text()).toContain(
    'Керамогранит с длинным русским названием 1',
  )
  const issues: string[] = []
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) issues.push(message.text())
  })
  page.on('pageerror', (error) => issues.push(error.message))
  await page.goto('/about')
  const headings = await page.locator('main h2').allTextContents()
  expect(headings).toEqual([
    'Второй блок поставлен первым',
    'Первый блок поставлен вторым',
  ])
  await expect(page.getByText('Скрытый блок')).toHaveCount(0)
  await expect(
    page.getByText('Текст из API <script>window.unsafe = true</script>'),
  ).toBeVisible()
  expect(issues).toEqual([])
})

test('responsive routes, accessible states and navigation', async ({
  page,
}, testInfo) => {
  for (const route of ['/', '/contacts', '/about', '/catalog']) {
    await page.goto(route)
    for (const width of [320, 640, 768, 1024, 1280]) {
      await page.setViewportSize({ width, height: 900 })
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
      ).toBe(true)
      await expect(page.locator('main h1')).toHaveCount(1)
      await page.screenshot({
        path: testInfo.outputPath(
          `${route === '/' ? 'home' : route.slice(1)}-${width}.png`,
        ),
        fullPage: true,
      })
    }
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  }
  await page.setViewportSize({ width: 320, height: 900 })
  await page.getByRole('button', { name: 'Открыть меню' }).click()
  await expect(page.getByRole('dialog', { name: 'Меню сайта' })).toBeVisible()
  await page.getByRole('dialog').getByRole('link', { name: /О нас/ }).click()
  await expect(page).toHaveURL(/\/about$/)
  await expect(page.getByRole('dialog')).toHaveCount(0)
})

test('global shell renders independently of an unpublished homepage and hydrates once', async ({
  page,
  request,
}) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=missing')
  for (const route of ['/', '/contacts', '/about', '/catalog']) {
    const html = await (await request.get(route)).text()
    expect(html).toContain('Общее оформление сайта')
    expect(html).toContain('Материалы для дома')
  }
  let requests = 0
  const issues: string[] = []
  page.on('request', (req) => {
    if (req.url().includes('/site-appearance')) requests++
  })
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) issues.push(message.text())
  })
  page.on('pageerror', (error) => issues.push(error.message))
  await page.goto('/about')
  await expect(page.getByText('Общее оформление сайта')).toBeVisible()
  await page.getByRole('link', { name: 'Каталог', exact: true }).first().click()
  await expect(page).toHaveURL(/\/catalog$/)
  expect(requests).toBe(0)
  expect(issues).toEqual([])
})

test('global appearance failure retains usable navigation', async ({
  page,
  request,
}) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=appearance-error')
  await page.goto('/about')
  await expect(page.locator('main h1')).toHaveText('О нас')
  await expect(
    page
      .getByRole('banner')
      .getByRole('link', { name: 'AgatCeramic — на главную' }),
  ).toBeVisible()
})

test('long Russian navigation and all sixteen links stay usable without page overflow', async ({
  page,
  request,
}, testInfo) => {
  for (const scenario of ['long-navigation', 'max-navigation']) {
    await request.get(`http://127.0.0.1:8015/__scenario?name=${scenario}`)
    await page.goto('/about')
    for (const width of [320, 640, 768, 1024, 1280]) {
      await page.setViewportSize({ width, height: 900 })
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
      ).toBe(true)
      await page.getByRole('button', { name: 'Открыть меню' }).click()
      const menu = page.getByRole('dialog', { name: 'Меню сайта' })
      await expect(menu.getByRole('link')).toHaveCount(
        scenario === 'max-navigation' ? 16 : 4,
      )
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
      ).toBe(true)
      expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
      await page.screenshot({
        path: testInfo.outputPath(`${scenario}-menu-${width}.png`),
        fullPage: true,
      })
      await page.keyboard.press('Escape')
      await expect(
        page.getByRole('button', { name: 'Открыть меню' }),
      ).toBeFocused()
      expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
      await page.screenshot({
        path: testInfo.outputPath(`${scenario}-${width}.png`),
        fullPage: true,
      })
    }
  }
})

test('catalog pagination uses URL and API page', async ({ page }) => {
  await page.goto('/catalog')
  await expect(page.getByText(/1.*234,50/)).toBeVisible()
  await page.getByRole('link', { name: 'Следующая →' }).click()
  await expect(page).toHaveURL(/page=2/)
  await expect(
    page.getByRole('heading', {
      name: 'Керамогранит с длинным русским названием 2',
    }),
  ).toBeVisible()
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
    'content',
    'noindex,follow',
  )
})

test('API failures, unpublished and empty pages have honest states and retry', async ({
  request,
  page,
}) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=missing')
  expect((await request.get('/about')).status()).toBe(404)
  await page.goto('/about')
  await expect(page.getByText('Страница не опубликована')).toBeVisible()
  await expect(page.locator('meta[name="robots"]')).toHaveAttribute(
    'content',
    'noindex,follow',
  )
  await request.get('http://127.0.0.1:8015/__scenario?name=error')
  expect((await request.get('/about')).status()).toBe(503)
  await page.goto('/about')
  await expect(page.getByRole('alert')).toContainText(
    'Страница временно недоступна',
  )
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
  await page.getByRole('button', { name: 'Повторить загрузку' }).click()
  await expect(
    page.getByRole('heading', { name: 'Второй блок поставлен первым' }),
  ).toBeVisible()
  await request.get('http://127.0.0.1:8015/__scenario?name=empty')
  await page.goto('/contacts')
  await expect(
    page.getByText('Контакты продавца пока не опубликованы.'),
  ).toBeVisible()
  await page.goto('/catalog')
  await expect(
    page.getByRole('heading', { name: 'Товары пока не опубликованы' }),
  ).toBeVisible()
  await request.get('http://127.0.0.1:8015/__scenario?name=empty-page')
  await page.goto('/about')
  await expect(
    page.getByRole('heading', { name: 'Содержимое пока не добавлено' }),
  ).toBeVisible()
})

test('catalog and contacts errors can be retried independently', async ({
  request,
  page,
}) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=catalog-error')
  await page.goto('/catalog')
  await expect(page.getByRole('alert')).toContainText(
    'Не удалось загрузить каталог.',
  )
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
  await page.getByRole('button', { name: 'Повторить загрузку' }).click()
  await expect(
    page.getByRole('heading', {
      name: 'Керамогранит с длинным русским названием 1',
    }),
  ).toBeVisible()
  await request.get('http://127.0.0.1:8015/__scenario?name=contacts-error')
  await page.goto('/contacts')
  await expect(page.getByRole('alert')).toHaveCount(2)
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
  await page
    .getByRole('alert')
    .filter({ hasText: 'Реквизиты продавца' })
    .getByRole('button')
    .click()
  await expect(page.getByText('Тестовый продавец')).toBeVisible()
  await expect(page.getByRole('alert')).toHaveCount(1)
})

test('standalone categories have no dead action; long content wraps at 320', async ({
  request,
  page,
}) => {
  await request.get(
    'http://127.0.0.1:8015/__scenario?name=standalone-categories',
  )
  await page.goto('/')
  await expect(page.locator('main article.category-card')).toHaveCount(1)
  await expect(
    page.getByRole('button', { name: 'Подробнее: Керамическая плитка' }),
  ).toHaveCount(0)
  await expect(page.locator('#materials')).toHaveCount(0)
  await request.get('http://127.0.0.1:8015/__scenario?name=long-strings')
  await page.setViewportSize({ width: 320, height: 900 })
  await page.goto('/contacts')
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= window.innerWidth,
    ),
  ).toBe(true)
})
