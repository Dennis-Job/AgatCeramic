import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
})

async function authenticate(
  context: import('@playwright/test').BrowserContext,
) {
  await context.addCookies([
    {
      name: 'preview_test_session',
      value: 'employee',
      url: 'http://127.0.0.1:8015',
      sameSite: 'Lax',
    },
  ])
}

test('preview HTML is private, contains no draft; unauthenticated preview is denied', async ({
  page,
  request,
}) => {
  const response = await request.get('/preview/about')
  expect(response.headers()['cache-control']).toContain('no-store')
  expect(response.headers()['x-robots-tag']).toContain('noindex')
  expect(response.headers()['referrer-policy']).toBe('no-referrer')
  const html = await response.text()
  expect(html).not.toContain('Сохранённый текст черновика')
  expect(html).toContain('noindex,nofollow,noarchive')
  await page.goto('/preview/about')
  await expect(page.getByRole('alert')).toContainText(
    'Доступ к черновику закрыт',
  )
  await expect(page.getByText('Черновая шапка')).toHaveCount(0)
})

test('saved draft uses actual blocks and chrome; routes are responsive and accessible', async ({
  page,
  context,
  request,
}, testInfo) => {
  await authenticate(context)
  const issues: string[] = []
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) issues.push(message.text())
  })
  page.on('pageerror', (error) => issues.push(error.message))
  for (const slug of ['home', 'about', 'contacts', 'catalog']) {
    await page.goto(`/preview/${slug}`)
    await expect(page.getByText('Черновая шапка')).toBeVisible()
    await expect(
      page.getByRole('heading', { name: 'Сохранённый текст черновика' }),
    ).toBeVisible()
    await expect(page.getByText('Выключенный блок черновика')).toHaveCount(0)
    if (slug === 'contacts')
      await expect(
        page.getByRole('heading', { name: 'Магазин 2' }),
      ).toBeVisible()
    if (slug === 'catalog')
      await expect(
        page.getByText('Керамогранит с длинным русским названием 1'),
      ).toBeVisible()
    for (const width of [320, 640, 768, 1024, 1280]) {
      await page.setViewportSize({ width, height: 900 })
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
      ).toBe(true)
      await page.screenshot({
        path: testInfo.outputPath(`preview-${slug}-${width}.png`),
        fullPage: true,
      })
    }
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  }
  expect(issues).toEqual([])
  expect(
    await page.evaluate(() => (window as Window & { unsafe?: boolean }).unsafe),
  ).toBeUndefined()
  expect(await (await request.get('/about')).text()).not.toContain(
    'Сохранённый текст черновика',
  )
})

test('revoked session/permission removes draft on recheck; retry restores only authorized data', async ({
  page,
  context,
  request,
}, testInfo) => {
  await authenticate(context)
  await page.clock.install()
  await page.goto('/preview/about')
  await expect(page.getByText('Черновая шапка')).toBeVisible()
  await request.get('http://127.0.0.1:8015/__scenario?name=preview-revoked')
  await page.clock.fastForward(15_001)
  await expect(page.getByRole('alert')).toContainText(
    'Доступ к черновику закрыт',
  )
  await expect(page.getByText('Сохранённый текст черновика')).toHaveCount(0)
  await page.screenshot({
    path: testInfo.outputPath('preview-denied.png'),
    fullPage: true,
  })
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
  await page.getByRole('button', { name: 'Повторить загрузку' }).click()
  await expect(page.getByText('Черновая шапка')).toBeVisible()
  await context.clearCookies()
  await page.clock.fastForward(15_001)
  await expect(page.getByRole('alert')).toContainText(
    'Доступ к черновику закрыт',
  )
  await expect(page.getByText('Черновая шапка')).toHaveCount(0)
})

test('loading, failed and missing drafts have honest states and keyboard retry', async ({
  page,
  context,
  request,
}, testInfo) => {
  await authenticate(context)
  await page.route('**/admin/content-preview/about', async (route) => {
    await new Promise((resolve) => setTimeout(resolve, 700))
    await route.continue()
  })
  await page.goto('/preview/about')
  await expect(page.locator('main').getByRole('status')).toContainText(
    'Проверяем доступ',
  )
  await page.screenshot({
    path: testInfo.outputPath('preview-loading.png'),
    fullPage: true,
  })
  await expect(page.getByText('Черновая шапка')).toBeVisible()
  for (const scenario of ['preview-error', 'preview-missing']) {
    await request.get(`http://127.0.0.1:8015/__scenario?name=${scenario}`)
    await page.reload()
    await expect(page.getByRole('alert')).toBeVisible()
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    await page.screenshot({
      path: testInfo.outputPath(`${scenario}.png`),
      fullPage: true,
    })
  }
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
  await page.getByRole('button', { name: 'Повторить загрузку' }).focus()
  await page.keyboard.press('Enter')
  await expect(page.getByText('Черновая шапка')).toBeVisible()
})

test('preview slider uses public motion controls and respects reduced motion', async ({
  page,
  context,
}) => {
  await authenticate(context)
  await page.emulateMedia({ reducedMotion: 'no-preference' })
  await page.clock.install()
  await page.goto('/preview/home')
  await expect(
    page.getByRole('heading', { level: 1, name: 'Материалы для вашего дома' }),
  ).toBeVisible()
  await page.mouse.move(0, 890)
  await page.clock.fastForward(6600)
  await expect(
    page.getByRole('heading', { level: 1, name: 'Второй сохранённый слайд' }),
  ).toBeVisible()
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await page.reload()
  await expect(
    page.getByRole('heading', { level: 1, name: 'Материалы для вашего дома' }),
  ).toBeVisible()
  await page.mouse.move(0, 890)
  await page.clock.fastForward(6600)
  await expect(
    page.getByRole('heading', { level: 1, name: 'Материалы для вашего дома' }),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Следующий слайд' }).click()
  await expect(
    page.getByRole('heading', { level: 1, name: 'Второй сохранённый слайд' }),
  ).toBeVisible()
})

test('actual Nuxt preview stays accessible inside an editor iframe with keyboard menu', async ({
  page,
  context,
}) => {
  await authenticate(context)
  await page.goto('/')
  await page.setContent(
    '<!doctype html><html lang="ru"><head><title>Редактор</title></head><body><nav aria-label="Основная навигация"><a href="#editor">Контент</a></nav><main id="editor"><h1>Редактор</h1><iframe title="Сохранённый черновик" src="http://127.0.0.1:3015/preview/about" width="1280" height="720"></iframe></main></body></html>',
  )
  const frame = page.frameLocator('iframe')
  await expect(frame.getByText('Черновая шапка')).toBeVisible()
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  await frame.getByRole('button', { name: 'Открыть меню' }).focus()
  await page.keyboard.press('Enter')
  await expect(frame.getByRole('dialog', { name: 'Меню сайта' })).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(frame.getByRole('dialog')).toHaveCount(0)
  await expect(
    frame.getByRole('button', { name: 'Открыть меню' }),
  ).toBeFocused()
})
