import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
})

test('cooperation renders partner contact and accessible form in SSR and preview at every width', async ({
  page,
  request,
  context,
}, testInfo) => {
  const html = await (await request.get('/cooperation')).text()
  expect(html).toContain('mailto:partners@example.test')
  expect(html).toContain('Ваше предложение')
  const issues: string[] = []
  page.on('pageerror', (error) => issues.push(error.message))
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) issues.push(message.text())
  })
  await page.goto('/cooperation')
  await expect(
    page.locator('main').getByRole('link', { name: 'partners@example.test' }),
  ).toHaveAttribute('href', 'mailto:partners@example.test')
  await expect(
    page
      .getByRole('region', { name: 'Информация магазина' })
      .getByRole('link', { name: 'shop@example.test' }),
  ).toBeVisible()
  await expect(page.getByText('Содержимое пока не добавлено')).toHaveCount(0)
  await expect(
    page.locator('input:focus, textarea:focus, button:focus'),
  ).toHaveCount(0)
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    const overflow = await page.evaluate(() =>
      [...document.querySelectorAll('main *')]
        .filter((node) => node.getBoundingClientRect().right > innerWidth)
        .map((node) => ({
          tag: node.tagName,
          class: node.className,
          width: node.getBoundingClientRect().width,
        })),
    )
    expect(
      await page.evaluate(() => document.documentElement.scrollWidth),
      JSON.stringify(overflow),
    ).toBeLessThanOrEqual(width)
    await page.screenshot({
      path: testInfo.outputPath(`cooperation-${width}.png`),
      fullPage: true,
    })
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  }
  await page.getByLabel('Email', { exact: true }).focus()
  await page.keyboard.press('Tab')
  await expect(page.getByLabel('Ваше предложение')).toBeFocused()
  await expect(page.getByLabel('Ваше предложение')).toHaveCSS(
    'border-color',
    'rgb(20, 20, 20)',
  )
  await expect(page.getByLabel('Ваше предложение')).toHaveCSS(
    'outline-style',
    'none',
  )
  await page.keyboard.press('Tab')
  await expect(
    page.getByRole('button', { name: 'Отправить предложение' }),
  ).toBeFocused()
  await page.keyboard.press('Shift+Tab')
  await expect(page.getByLabel('Ваше предложение')).toBeFocused()
  await page.goto('/contacts')
  await expect(
    page.locator('main').getByRole('link', { name: 'shop@example.test' }),
  ).toBeVisible()
  await expect(
    page.getByRole('link', { name: 'partners@example.test' }),
  ).toHaveCount(0)
  await context.addCookies([
    {
      name: 'preview_test_session',
      value: 'employee',
      url: 'http://127.0.0.1:8015',
      sameSite: 'Lax',
    },
  ])
  await page.goto('/preview/cooperation')
  await expect(
    page.locator('main').getByRole('link', { name: 'partners@example.test' }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Отправить предложение' }),
  ).toBeVisible()
  expect(issues).toEqual([])
})

test('cooperation submits only required contact fields, blocks repeat submission and clears private inputs on success', async ({
  page,
}, testInfo) => {
  let submissions = 0
  let complete!: () => void
  const release = new Promise<void>((resolve) => {
    complete = resolve
  })
  await page.route('**/api/v1/partner-requests', async (route) => {
    submissions++
    expect(route.request().method()).toBe('POST')
    expect(route.request().postDataJSON()).toEqual({
      name: 'Иван Петров',
      phone: '+79991234567',
      email: 'ivan@example.test',
      message: 'Предлагаем сотрудничество.',
    })
    await release
    await route.fulfill({ status: 201, body: '' })
  })
  await page.goto('/cooperation')
  const form = page.getByRole('form', { name: 'Предложить сотрудничество' })
  const send = form.getByRole('button', { name: 'Отправить предложение' })
  await send.click()
  expect(submissions).toBe(0)
  await form.getByLabel('Ваше имя').fill('  Иван Петров  ')
  await form.getByLabel('Телефон', { exact: true }).fill('9991234567')
  await form.getByLabel('Email', { exact: true }).fill('not-an-email')
  await form
    .getByLabel('Ваше предложение')
    .fill('  Предлагаем сотрудничество.  ')
  await send.click()
  expect(submissions).toBe(0)
  await form.getByLabel('Email', { exact: true }).fill('ivan@example.test')
  await form.getByLabel('Ваше предложение').focus()
  await page.keyboard.press('Tab')
  await expect(send).toBeFocused()
  await page.keyboard.press('Enter')
  await expect(form.getByRole('status')).toContainText('Отправляем предложение')
  for (const field of await form.locator('input, textarea, button').all())
    await expect(field).toBeDisabled()
  await page.screenshot({
    path: testInfo.outputPath('cooperation-loading.png'),
    fullPage: true,
  })
  expect(submissions).toBe(1)
  const scrollBeforeSuccess = await page.evaluate(() => scrollY)
  complete()
  const confirmation = page
    .getByRole('status')
    .filter({ hasText: 'Предложение получено' })
  await expect(confirmation).toHaveText(
    'Предложение получено. Мы рассмотрим его и свяжемся с вами по указанным контактам.',
  )
  await page.screenshot({
    path: testInfo.outputPath('cooperation-success-keyboard-viewport.png'),
  })
  await page.screenshot({
    path: testInfo.outputPath('cooperation-success.png'),
    fullPage: true,
  })
  expect(
    await confirmation.evaluate((node) => node.closest('main, dialog')),
  ).toBeNull()
  await expect(form).toBeVisible()
  await expect(form.getByLabel('Ваше имя')).toHaveValue('')
  await expect(form.getByLabel('Телефон', { exact: true })).toHaveValue('+7')
  await expect(form.getByLabel('Email', { exact: true })).toHaveValue('')
  await expect(form.getByLabel('Ваше предложение')).toHaveValue('')
  await expect(send).toBeEnabled()
  expect(await page.evaluate(() => scrollY)).toBe(scrollBeforeSuccess)
  expect(submissions).toBe(1)
  expect(page.url()).not.toContain('ivan')
  expect(
    await page.evaluate(() =>
      JSON.stringify({ ...localStorage, ...sessionStorage }),
    ),
  ).not.toContain('ivan')
})

test('validation, rate limit and connection errors preserve input without automatic retries', async ({
  page,
}, testInfo) => {
  let submissions = 0
  await page.route('**/api/v1/partner-requests', async (route) => {
    submissions++
    if (submissions === 3) return route.abort('failed')
    await route.fulfill({
      status: submissions === 1 ? 422 : 429,
      json: { error: { details: { email: ['Invalid email'] } } },
    })
  })
  await page.goto('/cooperation')
  const form = page.getByRole('form', { name: 'Предложить сотрудничество' })
  await form.getByLabel('Ваше имя').fill('Иван')
  await form.getByLabel('Телефон', { exact: true }).fill('+79991234567')
  await form.getByLabel('Email', { exact: true }).fill('ivan@example.test')
  await form.getByLabel('Ваше предложение').fill('Наше предложение')
  for (const message of [
    'Проверьте email',
    'Слишком много заявок',
    'Не удалось отправить предложение',
  ]) {
    await form.getByRole('button', { name: 'Отправить предложение' }).click()
    await expect(form.getByRole('alert')).toContainText(message)
    await page.screenshot({
      path: testInfo.outputPath(`cooperation-error-${submissions}.png`),
      fullPage: true,
    })
    await expect(form.getByLabel('Ваше имя')).toHaveValue('Иван')
    await expect(form.getByLabel('Email', { exact: true })).toHaveValue(
      'ivan@example.test',
    )
    await expect(form.getByLabel('Ваше предложение')).toHaveValue(
      'Наше предложение',
    )
  }
  expect(submissions).toBe(3)
})

test('missing partner contacts and contact errors retain a working form and never substitute customer email', async ({
  page,
  request,
}) => {
  for (const scenario of ['empty', 'legacy-contacts']) {
    await request.get(`http://127.0.0.1:8015/__scenario?name=${scenario}`)
    await page.goto('/cooperation')
    await expect(page.locator('main').getByRole('status')).toContainText(
      'Email для сотрудничества пока не опубликован.',
    )
    await expect(page.locator('main a[href^="mailto:"]')).toHaveCount(0)
    await expect(
      page.getByRole('button', { name: 'Отправить предложение' }),
    ).toBeVisible()
  }
  await request.get('http://127.0.0.1:8015/__scenario?name=contacts-error')
  await page.goto('/cooperation')
  await expect(page.getByRole('alert')).toContainText(
    'Не удалось загрузить email для сотрудничества.',
  )
  await expect(
    page.getByRole('button', { name: 'Отправить предложение' }),
  ).toBeVisible()
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
  await page.getByRole('button', { name: 'Повторить загрузку' }).click()
  await expect(
    page.locator('main').getByRole('link', { name: 'partners@example.test' }),
  ).toBeVisible()
  await request.get('http://127.0.0.1:8015/__scenario?name=partner-long')
  await page.setViewportSize({ width: 320, height: 900 })
  await page.goto('/cooperation')
  expect(
    await page.evaluate(() => document.documentElement.scrollWidth),
  ).toBeLessThanOrEqual(320)
})

test('success toast remains in the viewport without collapsing or scrolling the form', async ({
  page,
}, testInfo) => {
  await page.route('**/api/v1/partner-requests', (route) =>
    route.fulfill({ status: 201, body: '' }),
  )
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 720 })
    await page.goto('/cooperation')
    const form = page.getByRole('form', { name: 'Предложить сотрудничество' })
    await form.getByLabel('Ваше имя').fill('Иван')
    await form.getByLabel('Телефон', { exact: true }).fill('9991234567')
    await form.getByLabel('Email', { exact: true }).fill('ivan@example.test')
    await form.getByLabel('Ваше предложение').fill('Предлагаем сотрудничество')
    await page.keyboard.press('Tab')
    await expect(
      form.getByRole('button', { name: 'Отправить предложение' }),
    ).toBeFocused()
    const scrollBeforeSuccess = await page.evaluate(() => scrollY)
    await page.keyboard.press('Enter')
    const result = page
      .getByRole('status')
      .filter({ hasText: 'Предложение получено' })
    await expect(result).toHaveText(
      'Предложение получено. Мы рассмотрим его и свяжемся с вами по указанным контактам.',
    )
    await page.screenshot({
      path: testInfo.outputPath(`cooperation-success-visible-${width}.png`),
    })
    const box = (await result.boundingBox())!
    const layoutWidth = await page.evaluate(
      () => document.documentElement.getBoundingClientRect().width,
    )
    expect(box.x).toBeGreaterThanOrEqual(20)
    expect(box.x + box.width).toBeLessThanOrEqual(layoutWidth - 20)
    expect(box.x + box.width / 2).toBeCloseTo(layoutWidth / 2, 0)
    expect(box.y).toBeGreaterThanOrEqual(0)
    expect(720 - box.y - box.height).toBeCloseTo(32, 0)
    expect(await page.evaluate(() => scrollY)).toBe(scrollBeforeSuccess)
    await expect(form).toBeVisible()
    await expect(form.getByLabel('Ваше имя')).toHaveValue('')
    await expect(form.getByLabel('Телефон', { exact: true })).toHaveValue('+7')
    await expect(form.getByLabel('Email', { exact: true })).toHaveValue('')
    await expect(form.getByLabel('Ваше предложение')).toHaveValue('')
  }
})
