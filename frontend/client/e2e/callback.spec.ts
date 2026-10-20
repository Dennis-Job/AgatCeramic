import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
})

test('page and header overlays leave controls unselected until keyboard navigation', async ({
  page,
}) => {
  await page.goto('/about')
  await expect(page.locator('input:focus, button:focus, a:focus')).toHaveCount(
    0,
  )
  const trigger = page.getByRole('button', { name: 'Открыть меню' })
  await trigger.click()
  const menu = page.getByRole('dialog', { name: 'Меню сайта' })
  await expect(menu).toBeFocused()
  await expect(menu.locator('input:focus, button:focus, a:focus')).toHaveCount(
    0,
  )
  await page.keyboard.press('Shift+Tab')
  await expect(
    menu.getByRole('button', { name: 'Поиск по разделам страницы' }),
  ).toBeFocused()
  await page.keyboard.press('Tab')
  await expect(menu.getByRole('button', { name: 'Закрыть меню' })).toBeFocused()
  await menu.getByRole('button', { name: 'Поиск по разделам страницы' }).click()
  const search = page.getByRole('dialog', {
    name: 'Поиск по разделам страницы',
  })
  await expect(search).toBeFocused()
  await expect(
    search.locator('input:focus, button:focus, a:focus'),
  ).toHaveCount(0)
  await page.keyboard.press('Tab')
  await expect(
    search.getByRole('button', { name: 'Закрыть поиск' }),
  ).toBeFocused()
  await page.keyboard.press('Tab')
  const input = search.getByRole('searchbox')
  await expect(input).toBeFocused()
  await expect(input).toHaveCSS('outline-style', 'none')
  await expect(input).toHaveCSS('border-bottom-width', '1px')
  await expect(input).toHaveCSS('border-bottom-color', 'rgb(20, 20, 20)')
  expect(
    await input.evaluate((control) => control.matches(':focus-visible')),
  ).toBe(true)
  await page.keyboard.press('Escape')
  await expect(trigger).toBeFocused()
})

test('topbar uses site contacts in SSR and submits a callback without exposing personal data', async ({
  page,
  request,
}) => {
  const html = await (await request.get('/about')).text()
  expect(html).toContain('tel:+79000000000')
  expect(html).toContain('mailto:shop@example.test')
  const topbarHtml = html.slice(
    html.indexOf('class="topbar"'),
    html.indexOf('<header'),
  )
  expect(topbarHtml).not.toContain('Прежний текст слева')
  expect(topbarHtml).not.toContain('+7 (900) 111-11-11')
  const issues: string[] = []
  page.on('pageerror', (error) => issues.push(error.message))
  page.on('console', (message) => {
    if (/hydration/i.test(message.text())) issues.push(message.text())
  })
  let submissions = 0
  await page.route('**/api/v1/callback-requests', async (route) => {
    if (route.request().method() === 'POST') {
      submissions++
      expect(route.request().postDataJSON()).toEqual({
        name: 'Иван',
        phone: '+79991234567',
      })
    }
    await route.fulfill({ status: 201, body: '' })
  })
  await page.goto('/about')
  const trigger = page.getByRole('button', {
    name: 'заказать звонок',
    exact: true,
  })
  await trigger.click()
  const dialog = page.getByRole('dialog', { name: 'Заказать звонок' })
  await expect(dialog).toBeFocused()
  await expect(dialog.locator('input:focus, button:focus')).toHaveCount(0)
  await page.keyboard.press('Tab')
  const close = dialog.getByRole('button', { name: 'Закрыть' })
  await expect(close).toBeFocused()
  expect(
    await close.evaluate((control) => control.matches(':focus-visible')),
  ).toBe(true)
  await page.keyboard.press('Shift+Tab')
  await expect(
    dialog.getByRole('button', { name: 'Отправить заявку' }),
  ).toBeFocused()
  await dialog.getByLabel('Ваше имя').fill('  Иван  ')
  await dialog.getByLabel('Телефон', { exact: true }).fill('+7 (999) 123-45-67')
  await dialog.getByRole('button', { name: 'Отправить заявку' }).click()
  await expect(dialog.getByRole('status')).toContainText('Заявка отправлена')
  expect(submissions).toBe(1)
  await expect(dialog.getByRole('textbox')).toHaveCount(0)
  await page.keyboard.press('Escape')
  await expect(dialog).toHaveCount(0)
  await expect(trigger).toBeFocused()
  expect(page.url()).not.toContain('999')
  expect(issues).toEqual([])
})

test('callback stays usable at all widths with focus containment, backdrop close and no overflow', async ({
  page,
}, testInfo) => {
  await page.goto('/')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 707 })
    const topbar = page.getByRole('region', { name: 'Информация магазина' })
    await expect(
      topbar.getByRole('link', { name: '+7 (900) 000-00-00' }),
    ).toBeVisible()
    await expect(
      topbar.getByRole('link', { name: 'shop@example.test' }),
    ).toBeVisible()
    await topbar.getByRole('button', { name: 'заказать звонок' }).click()
    const dialog = page.getByRole('dialog', { name: 'Заказать звонок' })
    await expect(dialog).toBeFocused()
    await expect(dialog.locator('input:focus, button:focus')).toHaveCount(0)
    await page.screenshot({
      path: testInfo.outputPath(`callback-open-${width}.png`),
    })
    const phone = dialog.getByLabel('Телефон', { exact: true })
    await phone.click()
    await expect(phone).toHaveCSS('outline-style', 'none')
    await expect(phone).toHaveCSS('box-shadow', 'none')
    await expect(phone).toHaveCSS('border-width', '1px')
    await expect(phone).toHaveCSS('border-color', 'rgb(20, 20, 20)')
    await page.screenshot({
      path: testInfo.outputPath(`callback-active-${width}.png`),
    })
    await dialog.getByRole('button', { name: 'Отправить заявку' }).focus()
    await page.keyboard.press('Tab')
    await expect(dialog.getByRole('button', { name: 'Закрыть' })).toBeFocused()
    await page.keyboard.press('Tab')
    await expect(phone).toBeFocused()
    await expect(phone).toHaveCSS('outline-style', 'none')
    await expect(phone).toHaveCSS('border-color', 'rgb(20, 20, 20)')
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    await page.screenshot({
      path: testInfo.outputPath(`callback-${width}.png`),
    })
    await page.mouse.click(2, 2)
    await expect(dialog).toHaveCount(0)
    await expect(
      topbar.getByRole('button', { name: 'заказать звонок' }),
    ).toBeFocused()
  }
})

test('server validation, rate limiting and network errors preserve input and allow retry', async ({
  page,
}) => {
  let status = 422
  await page.route('**/api/v1/callback-requests', async (route) => {
    await route.fulfill({
      status,
      json: { error: { details: { phone: ['Invalid phone'] } } },
    })
  })
  await page.goto('/contacts')
  await page
    .getByRole('button', { name: 'заказать звонок', exact: true })
    .click()
  const dialog = page.getByRole('dialog', { name: 'Заказать звонок' })
  const phone = dialog.getByLabel('Телефон', { exact: true })
  await phone.fill('+7 (999) 123-45-67')
  await dialog.getByRole('button', { name: 'Отправить заявку' }).click()
  await expect(dialog.getByRole('alert')).toContainText(
    'Проверьте номер телефона',
  )
  await expect(phone).toHaveValue('+7 (999) 123-45-67')
  status = 429
  await dialog.getByRole('button', { name: 'Отправить заявку' }).click()
  await expect(dialog.getByRole('alert')).toContainText('Слишком много заявок')
  status = 503
  await dialog.getByRole('button', { name: 'Отправить заявку' }).click()
  await expect(dialog.getByRole('alert')).toContainText('Не удалось отправить')
  status = 201
  await dialog.getByRole('button', { name: 'Отправить заявку' }).click()
  await expect(dialog.getByRole('status')).toContainText('Заявка отправлена')
})

test('empty or unavailable contacts retain the callback action and right text on every page', async ({
  page,
  request,
}) => {
  for (const scenario of ['empty', 'contacts-error']) {
    await request.get(`http://127.0.0.1:8015/__scenario?name=${scenario}`)
    await page.goto('/about')
    const topbar = page.getByRole('region', { name: 'Информация магазина' })
    await expect(
      topbar.getByRole('button', { name: 'заказать звонок' }),
    ).toBeVisible()
    await expect(topbar.getByRole('link')).toHaveCount(0)
    await expect(topbar.locator('.store-contacts__separator')).toHaveCount(0)
    await expect(topbar).toContainText('Общее оформление сайта')
  }
})

test('invalid input never submits and a pending submission blocks duplicates and closing', async ({
  page,
}) => {
  let requests = 0
  let finish: (() => void) | undefined
  await page.route('**/api/v1/callback-requests', async (route) => {
    requests++
    await new Promise<void>((resolve) => {
      finish = resolve
    })
    await route.fulfill({ status: 201, body: '' })
  })
  await page.goto('/about')
  await page
    .getByRole('button', { name: 'заказать звонок', exact: true })
    .click()
  const dialog = page.getByRole('dialog', { name: 'Заказать звонок' })
  const phone = dialog.getByLabel('Телефон', { exact: true })
  await phone.fill('123')
  await dialog.getByRole('button', { name: 'Отправить заявку' }).click()
  expect(
    await phone.evaluate((input: HTMLInputElement) => input.validity.valid),
  ).toBe(false)
  expect(requests).toBe(0)
  await phone.fill('+7 (999) 123-45-67')
  await dialog.getByRole('button', { name: 'Отправить заявку' }).click()
  await expect.poll(() => requests).toBe(1)
  await expect(
    dialog.getByRole('button', { name: 'Отправляем…', exact: true }),
  ).toBeDisabled()
  await expect(dialog.getByRole('button', { name: 'Закрыть' })).toBeDisabled()
  await page.keyboard.press('Escape')
  await expect(dialog).toBeVisible()
  await page.screenshot({ path: '.tmp/callback-pending.png' })
  finish?.()
  await expect(dialog.getByRole('status')).toContainText('Заявка отправлена')
  expect(requests).toBe(1)
})
