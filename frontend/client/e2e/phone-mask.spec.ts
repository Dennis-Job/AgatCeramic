import { expect, test } from '@playwright/test'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
})

test('topbar places editable text left and canonical contacts right using reference spacing', async ({
  page,
}, testInfo) => {
  await page.goto('/about')
  const topbar = page.getByRole('region', { name: 'Информация магазина' })
  const text = topbar.getByText('Общее оформление сайта')
  const button = topbar.getByRole('button', { name: 'заказать звонок' })
  const phone = topbar.getByRole('link', { name: '+7 (900) 000-00-00' })
  await expect(phone).toHaveAttribute('href', 'tel:+79000000000')
  expect((await text.boundingBox())!.x).toBeLessThan(
    (await button.boundingBox())!.x,
  )
  await expect(topbar.locator('.store-contacts')).toHaveCSS(
    'column-gap',
    '12px',
  )
  await expect(button).toHaveCSS('text-decoration-line', 'none')
  await expect(topbar.locator('.store-contacts__separator')).toHaveText([
    '·',
    '·',
  ])
  await expect(topbar.locator('.store-contacts')).toContainText(
    /заказать звонок\s*·\s*shop@example\.test\s*·\s*\+7 \(900\) 000-00-00/,
  )
  await expect(topbar).toHaveCSS('font-size', '11px')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 707 })
    if (width <= 760) await expect(text).toBeHidden()
    else await expect(text).toBeVisible()
    await expect(phone).toBeVisible()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({ path: testInfo.outputPath(`topbar-${width}.png`) })
  }
})

test('phone mask supplies fixed +7, edits ten local digits and submits canonical phone', async ({
  page,
}) => {
  await page.route('**/api/v1/callback-requests', async (route) => {
    expect(route.request().postDataJSON()).toEqual({
      name: null,
      phone: '+79991234567',
    })
    await route.fulfill({ status: 201, body: '' })
  })
  await page.goto('/about')
  await page
    .getByRole('button', { name: 'заказать звонок', exact: true })
    .click()
  const phone = page.getByLabel('Телефон', { exact: true })
  await expect(phone).toHaveValue('+7')
  await phone.pressSequentially('9991234567')
  await expect(phone).toHaveValue('+7 (999) 123-45-67')
  await phone.press('Backspace')
  await expect(phone).toHaveValue('+7 (999) 123-45-6')
  await phone.press('7')
  await phone.press('ControlOrMeta+A')
  await phone.press('Backspace')
  await expect(phone).toHaveValue('+7')
  await phone.pressSequentially('9991234567')
  await page.getByRole('button', { name: 'Отправить заявку' }).click()
  await expect(page.getByRole('dialog').getByRole('status')).toContainText(
    'Заявка отправлена',
  )
})

test('paste, middle deletion, prefix protection and incomplete input work without storing a mask', async ({
  page,
}) => {
  await page.goto('/about')
  await page
    .getByRole('button', { name: 'заказать звонок', exact: true })
    .click()
  const phone = page.getByLabel('Телефон', { exact: true })
  await phone.fill('8 (495) 120-45-67')
  await expect(phone).toHaveValue('+7 (495) 120-45-67')
  await phone.evaluate((input: HTMLInputElement) =>
    input.setSelectionRange(9, 9),
  )
  await phone.press('Backspace')
  await expect(phone).toHaveValue('+7 (491) 204-56-7')
  await phone.fill('9991234567')
  await phone.evaluate((input: HTMLInputElement) =>
    input.setSelectionRange(0, 2),
  )
  await phone.press('Backspace')
  await expect(phone).toHaveValue('+7 (999) 123-45-67')
  await phone.fill('123')
  await page.getByRole('button', { name: 'Отправить заявку' }).click()
  expect(
    await phone.evaluate((input: HTMLInputElement) => input.validity.valid),
  ).toBe(false)
})

test('public store and seller phone text is masked while tel href stays canonical', async ({
  page,
}) => {
  await page.goto('/contacts')
  const sellerPhone = page.locator('.seller a[href^="tel:"]').first()
  await expect(sellerPhone).toHaveText('+7 (900) 000-00-00')
  await expect(sellerPhone).toHaveAttribute('href', 'tel:+79000000000')
})
