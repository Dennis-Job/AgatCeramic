import { expect, test, type Locator } from '@playwright/test'

test.use({ reducedMotion: 'no-preference' })

test.beforeEach(async ({ request, page }) => {
  await request.get('http://127.0.0.1:8015/__scenario?name=default')
  await page.emulateMedia({ reducedMotion: 'no-preference' })
})

async function finishMotion(element: Locator) {
  await element.evaluate(async (node) => {
    await Promise.all(
      node
        .getAnimations()
        .map((animation) => animation.finished.catch(() => {})),
    )
  })
}

test('menu and modal animate in and out with the reference timing and retain focus until closed', async ({
  page,
}, testInfo) => {
  await page.goto('/about')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 707 })
    const trigger = page.getByRole('button', { name: 'Открыть меню' })
    await trigger.click()
    const menu = page.locator('#site-menu')
    await expect(menu).toBeAttached()
    await expect
      .poll(() =>
        menu.evaluate((node) =>
          node
            .getAnimations()
            .some(
              (animation) => animation.effect?.getTiming().duration === 650,
            ),
        ),
      )
      .toBe(true)
    await expect(menu).toBeFocused()
    await page.screenshot({
      path: testInfo.outputPath(`menu-enter-${width}.png`),
    })
    await finishMotion(menu)
    await expect(menu).toHaveCSS('transform', 'none')
    await page.keyboard.press('Escape')
    await expect(page.locator('body')).toHaveClass('has-overlay')
    await expect(menu).toBeAttached()
    await expect(menu).toHaveCount(0)
    await expect(trigger).toBeFocused()
    await expect(page.locator('body')).not.toHaveClass('has-overlay')

    const callback = page.getByRole('button', {
      name: 'заказать звонок',
      exact: true,
    })
    await callback.click()
    const dialog = page.locator('dialog[open]')
    await expect(dialog).toBeAttached()
    await expect
      .poll(() =>
        dialog.evaluate((node) =>
          node
            .getAnimations()
            .some(
              (animation) => animation.effect?.getTiming().duration === 650,
            ),
        ),
      )
      .toBe(true)
    await expect(dialog).toBeFocused()
    await expect(dialog.locator('input:focus, button:focus')).toHaveCount(0)
    await page.screenshot({
      path: testInfo.outputPath(`modal-enter-${width}.png`),
    })
    await finishMotion(dialog)
    await expect(dialog).toHaveCSS('opacity', '1')
    await dialog.getByRole('button', { name: 'Закрыть', exact: true }).click()
    await expect(page.locator('body')).toHaveCSS('overflow', 'hidden')
    await expect(dialog).toBeAttached()
    await expect(dialog).toHaveCount(0)
    await expect(callback).toBeFocused()
    await expect(page.locator('body')).not.toHaveCSS('overflow', 'hidden')
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
  }
})

test('reduced motion removes layer movement and stagger delays', async ({
  page,
}) => {
  await page.emulateMedia({ reducedMotion: 'reduce' })
  await page.goto('/about')
  await page.getByRole('button', { name: 'Открыть меню' }).click()
  const menu = page.getByRole('dialog', { name: 'Меню сайта' })
  await expect(menu).toBeFocused()
  await expect(menu.getByRole('link').first()).toHaveCSS(
    'animation-delay',
    '0s',
  )
  await expect(menu.getByRole('link').last()).toHaveCSS('animation-delay', '0s')
  await expect(menu.getByRole('link').last()).toHaveCSS('opacity', '1')
  expect(
    await menu.evaluate((node) =>
      node
        .getAnimations({ subtree: true })
        .every(
          (animation) =>
            Number(animation.effect?.getTiming().duration ?? 0) <= 0.01,
        ),
    ),
  ).toBe(true)
  await page.keyboard.press('Escape')
  await expect(menu).toHaveCount(0)
  await page
    .getByRole('button', { name: 'заказать звонок', exact: true })
    .click()
  const dialog = page.getByRole('dialog', { name: 'Заказать звонок' })
  await expect(dialog).toBeFocused()
  expect(
    await dialog.evaluate((node) =>
      node
        .getAnimations()
        .every(
          (animation) =>
            Number(animation.effect?.getTiming().duration ?? 0) <= 0.01,
        ),
    ),
  ).toBe(true)
  await page.keyboard.press('Escape')
  await expect(dialog).toHaveCount(0)
})

test('switching layers and closing during entry restore focus and scroll only after the last exit', async ({
  page,
}) => {
  await page.goto('/about')
  const trigger = page.getByRole('button', { name: 'Открыть меню' })
  await trigger.click()
  const menu = page.locator('#site-menu')
  await menu.getByRole('button', { name: 'Поиск по разделам страницы' }).click()
  const search = page.locator('#site-search')
  await page.waitForFunction(() =>
    document
      .getElementById('site-search')
      ?.getAnimations()
      .some((animation) => Number(animation.currentTime) >= 400),
  )
  await page.keyboard.press('Escape')
  await expect(menu).toHaveCount(0)
  await expect(search).toBeAttached()
  await expect(page.locator('body')).toHaveClass('has-overlay')
  await expect(search).toHaveCount(0)
  await expect(trigger).toBeFocused()
  await expect(page.locator('body')).not.toHaveClass('has-overlay')

  const callback = page.getByRole('button', {
    name: 'заказать звонок',
    exact: true,
  })
  await callback.click()
  const dialog = page.locator('dialog[open]')
  await expect(dialog).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(dialog).toHaveAttribute('inert', '')
  await expect(page.locator('body')).toHaveCSS('overflow', 'hidden')
  await expect(dialog).toHaveCount(0)
  await expect(callback).toBeFocused()
  await callback.click()
  await expect(page.getByRole('dialog')).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(page.locator('dialog')).toHaveCount(0)
  await expect(page.locator('body')).not.toHaveCSS('overflow', 'hidden')
})

test('successful callback uses the same animated close for Done', async ({
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
  const dialog = page.locator('dialog[open]')
  await dialog.getByLabel('Телефон', { exact: true }).fill('9991234567')
  await dialog.getByRole('button', { name: 'Отправить заявку' }).click()
  await dialog.getByRole('button', { name: 'Готово' }).click()
  await expect(dialog).toBeAttached()
  await expect(dialog).toHaveAttribute('inert', '')
  await expect(page.locator('body')).toHaveCSS('overflow', 'hidden')
  await expect(dialog).toHaveCount(0)
  await expect(trigger).toBeFocused()
})
