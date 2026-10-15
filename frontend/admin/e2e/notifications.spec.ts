import AxeBuilder from './approvedAccessibility'
import { expect, test } from './fixtures'
import { mockCatalogApi } from './catalogApi'
import { mockAdminBaseline } from './adminBaselineApi'

const host = '#admin-notifications'
for (const width of [320, 602, 640, 768, 1024, 1280, 1440, 1920, 2560]) {
  test(`Excel feedback floats at the top right without moving filters, supports repeat and clears on navigation at ${width}px`, async ({
    page,
  }) => {
    await mockCatalogApi(page)
    await page.setViewportSize({ width: 602, height: 910 })
    await page.goto('/products')
    await expect(
      page.getByRole('heading', { name: 'Товары', exact: true }),
    ).toBeVisible()
    const search = page.getByRole('textbox', { name: 'Поиск', exact: true })
    const before = await search.boundingBox()
    const download = page.getByRole('button', {
      name: 'Скачать',
      exact: true,
    })
    await download.click()
    await page
      .getByRole('button', { name: 'Все товары в Excel', exact: true })
      .click()
    const toast = page.locator(host).getByRole('status')
    await expect(toast).toContainText('Все товары экспортированы в Excel.')
    await expect(toast).toHaveAttribute('aria-live', 'polite')
    await expect(toast).toHaveCSS('box-shadow', 'none')
    await expect(toast).toHaveCSS('border-radius', '16px')
    expect((await search.boundingBox())?.y).toBe(before?.y)
    const close = toast.getByRole('button', { name: 'Закрыть уведомление' })
    // Focus pauses the approved 5s lifetime across viewport changes and axe scans.
    await close.focus()
    await toast.hover()

    await page.setViewportSize({ width, height: 910 })
    const box = await toast.boundingBox()
    expect(box?.y).toBe(16)
    expect(Math.round((box?.x ?? 0) + (box?.width ?? 0))).toBe(width - 16)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({ path: `.tmp/notifications/export-${width}.png` })
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])

    await close.focus()
    await expect(page.getByRole('tooltip')).toHaveCount(0)
    await page.keyboard.press('Escape')
    await expect(toast).toBeHidden()
    await expect(download).toBeFocused()
    await download.click()
    await page
      .getByRole('button', { name: 'Все товары в Excel', exact: true })
      .click()
    await expect(toast).toBeVisible()
    if (width < 1024)
      await page.getByRole('button', { name: 'Открыть меню' }).click()
    await page.getByRole('link', { name: 'Главная', exact: true }).click()
    await expect(page.locator(host).getByRole('status')).toHaveCount(0)
  })
}

test('long errors and multiple notifications fit a scrollable stack at 320px', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.setViewportSize({ width: 320, height: 500 })
  await page.goto('/ui-kit')
  for (let i = 0; i < 6; i++)
    await page
      .getByRole('button', { name: 'Уведомление об ошибке', exact: true })
      .dispatchEvent('click')
  await expect(page.locator(host).getByRole('alert')).toHaveCount(6)
  expect(
    await page
      .locator(host)
      .getByRole('alert')
      .evaluateAll((notifications) =>
        notifications.map((notification) => {
          const style = getComputedStyle(notification)
          return {
            boxShadow: style.boxShadow,
            borderRadius: style.borderRadius,
            color: style.color,
            backgroundColor: style.backgroundColor,
          }
        }),
      ),
  ).toEqual(
    Array.from({ length: 6 }, () => ({
      boxShadow: 'none',
      borderRadius: '16px',
      color: 'rgb(240, 68, 56)',
      backgroundColor: 'rgb(254, 243, 242)',
    })),
  )
  expect(
    await page
      .locator(host)
      .evaluate((el) => el.scrollHeight > el.clientHeight),
  ).toBe(true)
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true)
  await page.screenshot({ path: '.tmp/notifications/stack-320.png' })
  await page
    .locator(host)
    .getByRole('button', { name: 'Закрыть уведомление' })
    .first()
    .click()
  await expect(page.locator(host).getByRole('alert')).toHaveCount(5)
  // Scan the live stack itself; the full UI-kit is scanned in its own tests.
  expect(
    (await new AxeBuilder({ page }).include(host).analyze()).violations,
  ).toEqual([])
  await expect(page.locator(host).getByRole('alert')).toHaveCount(5)
})

test('notification close button stays transparent inside an open dialog', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.goto('/ui-kit')
  await page
    .getByRole('button', { name: 'Confirm: error', exact: true })
    .click()
  const dialog = page.getByRole('dialog', {
    name: 'Удалить демонстрационный элемент?',
    exact: true,
  })
  await expect(dialog).toBeVisible()
  const close = dialog
    .locator(host)
    .getByRole('alert')
    .getByRole('button', { name: 'Закрыть уведомление' })
  await expect(close).toHaveCSS('background-color', 'rgba(0, 0, 0, 0)')
})

test('notification Escape and Tab work inside a dialog without closing it', async ({
  page,
}) => {
  await mockAdminBaseline(page)
  await page.goto('/ui-kit')
  await page
    .getByRole('button', { name: 'Confirm: error', exact: true })
    .click()
  const dialog = page.getByRole('dialog', {
    name: 'Удалить демонстрационный элемент?',
    exact: true,
  })
  await expect(dialog).toBeVisible()
  const toast = dialog.locator(host).getByRole('alert')
  await expect(toast).toContainText(
    'Не удалось выполнить демонстрационное действие.',
  )
  await expect(toast).toHaveAttribute('aria-live', 'assertive')
  const close = toast.getByRole('button', { name: 'Закрыть уведомление' })
  await close.focus()
  await page.screenshot({ path: '.tmp/notifications/dialog-error.png' })
  await page.keyboard.press('Tab')
  expect(
    await dialog.evaluate((el) => el.contains(document.activeElement)),
  ).toBe(true)
  await close.focus()
  await expect(page.getByRole('tooltip')).toHaveCount(0)
  await page.keyboard.press('Escape')
  await expect(toast).toBeHidden()
  await expect(dialog).toBeVisible()
  expect(
    await dialog.evaluate((el) => el.contains(document.activeElement)),
  ).toBe(true)
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  await page.keyboard.press('Escape')
  await expect(dialog).toBeHidden()
  await page
    .getByRole('button', { name: 'Уведомление об успехе', exact: true })
    .click()
  await expect(
    page.locator('body > ' + host).getByRole('status'),
  ).toContainText('Изменения сохранены.')
})
