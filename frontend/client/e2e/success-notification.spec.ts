import { expect, test } from '@playwright/test'

test.beforeEach(async ({ request }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
})

test('successful callback closes the dialog and shows a temporary notification without moving focus', async ({
  page,
}) => {
  await page.route('**/api/v1/callback-requests', (route) =>
    route.fulfill({ status: 201, body: '' }),
  )
  await page.goto('/about')
  const trigger = page.getByRole('button', {
    name: 'заказать звонок',
    exact: true,
  })
  await trigger.click()
  const dialog = page.getByRole('dialog', { name: 'Заказать звонок' })
  await dialog.getByLabel('Телефон', { exact: true }).fill('9991234567')
  await dialog.getByRole('button', { name: 'Отправить заявку' }).click()
  await expect(dialog).toHaveCount(0)
  const notification = page
    .getByRole('status')
    .filter({ hasText: 'Заявка отправлена' })
  await expect(notification).toBeVisible()
  await expect(trigger).toBeFocused()
  await expect(notification).not.toContainText('999')
  await expect(notification).toHaveCount(0, { timeout: 5000 })
  await expect(trigger).toBeFocused()
})

test('a repeated partnership success restarts the notification lifetime and leaves a cleared form', async ({
  page,
}) => {
  await page.route('**/api/v1/partner-requests', (route) =>
    route.fulfill({ status: 201, body: '' }),
  )
  await page.goto('/cooperation')
  await page.clock.install()
  const form = page.getByRole('form', { name: 'Предложить сотрудничество' })
  const notification = page
    .getByRole('status')
    .filter({ hasText: 'Предложение получено' })
  async function send() {
    await form.getByLabel('Ваше имя').fill('Иван')
    await form.getByLabel('Телефон', { exact: true }).fill('9991234567')
    await form.getByLabel('Email', { exact: true }).fill('ivan@example.test')
    await form.getByLabel('Ваше предложение').fill('Предлагаем сотрудничество')
    await form.getByRole('button', { name: 'Отправить предложение' }).click()
    await expect(notification).toBeVisible()
    await expect(form.getByLabel('Ваше имя')).toHaveValue('')
    await expect(form.getByLabel('Email', { exact: true })).toHaveValue('')
    await expect(form.getByLabel('Ваше предложение')).toHaveValue('')
  }
  await send()
  await page.clock.fastForward(1800)
  await send()
  await page.clock.fastForward(1200)
  await expect(notification).toBeVisible()
  await page.clock.fastForward(1500)
  await expect(notification).toHaveCount(0)
  await expect(form).toBeVisible()
})

for (const reducedMotion of ['no-preference', 'reduce'] as const) {
  test(`notification follows the reference with ${reducedMotion} motion`, async ({
    page,
  }, testInfo) => {
    await page.emulateMedia({ reducedMotion })
    await page.route('**/api/v1/callback-requests', (route) =>
      route.fulfill({ status: 201, body: '' }),
    )
    await page.goto('/contacts')
    await page
      .getByRole('button', { name: 'заказать звонок', exact: true })
      .click()
    const dialog = page.getByRole('dialog', { name: 'Заказать звонок' })
    await dialog.getByLabel('Телефон', { exact: true }).fill('9991234567')
    await dialog.getByRole('button', { name: 'Отправить заявку' }).click()
    const notification = page.locator('.success-notification__message')
    await expect(notification).toBeVisible()
    if (reducedMotion === 'no-preference') {
      await expect(notification).toHaveCSS('transition-duration', '0.5s')
    } else {
      expect(
        await notification.evaluate((node) =>
          parseFloat(getComputedStyle(node).transitionDuration),
        ),
      ).toBeLessThanOrEqual(0.00001)
    }
    await expect(notification).toHaveCSS('transform', 'none')
    await expect(notification).toHaveCSS('background-color', 'rgb(20, 20, 20)')
    await expect(notification).toHaveCSS('color', 'rgb(255, 255, 255)')
    await expect(notification).toHaveCSS('font-size', '12px')
    await expect(notification).toHaveCSS('letter-spacing', '1.2px')
    await expect(notification).toHaveCSS('padding', '15px 28px')
    await page.screenshot({
      path: testInfo.outputPath(`notification-${reducedMotion}.png`),
    })
    await expect(notification).toHaveCount(0, { timeout: 5000 })
  })
}
