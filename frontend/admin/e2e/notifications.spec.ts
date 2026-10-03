import AxeBuilder from '@axe-core/playwright'
import { expect, test } from './fixtures'
import { mockCatalogApi } from './catalogApi'
import { mockAdminBaseline } from './adminBaselineApi'

const host = '#admin-notifications'
test('Excel feedback floats at the top right without moving filters, supports repeat and clears on navigation', async ({
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
    name: 'Скачать Excel',
    exact: true,
  })
  await download.click()
  const toast = page.locator(host).getByRole('status')
  await expect(toast).toContainText('Все товары экспортированы в Excel.')
  await expect(toast).toHaveAttribute('aria-live', 'polite')
  expect((await search.boundingBox())?.y).toBe(before?.y)
  await toast.hover()
  for (const width of [320, 602, 640, 768, 1024, 1280, 1440, 1920, 2560]) {
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
  }
  const close = toast.getByRole('button', { name: 'Закрыть уведомление' })
  await close.focus()
  await expect(page.getByRole('tooltip')).toHaveText('Закрыть уведомление')
  await page.keyboard.press('Escape')
  await expect(page.getByRole('tooltip')).toHaveCount(0)
  await expect(toast).toBeVisible()
  await expect(close).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(toast).toBeHidden()
  await expect(download).toBeFocused()
  await download.click()
  await expect(toast).toBeVisible()
  await page.getByRole('link', { name: 'Главная', exact: true }).click()
  await expect(page.locator(host).getByRole('status')).toHaveCount(0)
})

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
      .evaluate((el) => el.scrollHeight > el.clientHeight),
  ).toBe(true)
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true)
  await page.screenshot({ path: '.tmp/notifications/stack-320.png' })
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  await page
    .locator(host)
    .getByRole('button', { name: 'Закрыть уведомление' })
    .first()
    .click()
  await expect(page.locator(host).getByRole('alert')).toHaveCount(5)
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
  await expect(page.getByRole('tooltip')).toHaveText('Закрыть уведомление')
  await page.keyboard.press('Escape')
  await expect(page.getByRole('tooltip')).toHaveCount(0)
  await expect(toast).toBeVisible()
  await expect(close).toBeFocused()
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
