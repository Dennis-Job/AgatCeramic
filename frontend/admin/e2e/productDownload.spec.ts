import AxeBuilder from './approvedAccessibility'
import { expect, test } from './fixtures'
import { mockCatalogApi } from './catalogApi'
import { createDeferredApiRequests } from './deferredApi'

test('SKU download normalizes the selection, prevents empty exports and stays locked while downloading', async ({
  page,
}) => {
  const deferred = createDeferredApiRequests()
  await mockCatalogApi(page, { deferredRequests: deferred })
  await page.goto('/products')
  const trigger = page.getByRole('button', { name: 'Скачать', exact: true })
  await trigger.hover()
  await page.getByRole('button', { name: 'Товары по SKU', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: 'Скачать товары по SKU' })
  const input = dialog.getByRole('textbox', { name: 'SKU товаров' })
  await expect(input).toBeFocused()
  await input.fill(' , , ')
  await dialog
    .getByRole('button', { name: 'Скачать Excel', exact: true })
    .click()
  await expect(dialog.getByRole('alert')).toContainText(
    'Укажите хотя бы один SKU',
  )

  await input.fill(' 00123, ABC-7, 00123, ,\n 00042 ')
  const pending = deferred.defer('/admin/products/export')
  const requestPromise = page.waitForRequest('**/admin/products/export')
  const downloadPromise = page.waitForEvent('download')
  await dialog
    .getByRole('button', { name: 'Скачать Excel', exact: true })
    .click()
  const request = await requestPromise
  await pending.requested
  expect(request.method()).toBe('POST')
  expect(request.postDataJSON()).toEqual({ skus: ['00123', 'ABC-7', '00042'] })
  await expect(input).toBeDisabled()
  await expect(dialog.getByRole('button', { name: 'Отмена' })).toBeDisabled()
  await page.keyboard.press('Escape')
  await expect(dialog).toBeVisible()
  await page.screenshot({ path: '.tmp/product-download/busy-1280.png' })
  pending.release()
  expect((await downloadPromise).suggestedFilename()).toBe(
    'products-2026-09-01-120000.xlsx',
  )
  await expect(dialog).toBeHidden()
  await expect(trigger).toBeFocused()
  await expect(
    page
      .getByRole('status')
      .filter({ hasText: 'Товары по SKU экспортированы в Excel.' }),
  ).toBeVisible()
})

test('SKU errors keep the entered selection available for correction and retry', async ({
  page,
  browserIssueGuard,
}) => {
  browserIssueGuard.allowApiError(422, '/api/v1/admin/products/export')
  browserIssueGuard.allowApiError(500, '/api/v1/admin/products/export')
  await mockCatalogApi(page)
  await page.setViewportSize({ width: 320, height: 700 })
  let attempt = 0
  await page.route('**/admin/products/export', async (route) => {
    attempt++
    if (attempt === 1)
      await route.fulfill({
        status: 422,
        json: {
          error: {
            message: 'Ошибка проверки.',
            details: { 'skus.1': ['SKU MISSING не найден.'] },
          },
        },
      })
    else if (attempt === 2)
      await route.fulfill({
        status: 500,
        json: { error: { message: 'Не удалось создать Excel.', details: {} } },
      })
    else await route.fallback()
  })
  await page.goto('/products')
  const trigger = page.getByRole('button', { name: 'Скачать', exact: true })
  await trigger.focus()
  await page.keyboard.press('ArrowUp')
  await page.keyboard.press('Enter')
  const dialog = page.getByRole('dialog', { name: 'Скачать товары по SKU' })
  const input = dialog.getByRole('textbox', { name: 'SKU товаров' })
  await input.fill('00123, MISSING')
  await dialog
    .getByRole('button', { name: 'Скачать Excel', exact: true })
    .click()
  await expect(dialog.getByRole('alert')).toContainText(
    'SKU MISSING не найден.',
  )
  await expect(input).toHaveValue('00123, MISSING')
  await page.screenshot({ path: '.tmp/product-download/error-320.png' })
  await input.fill('00123')
  await dialog
    .getByRole('button', { name: 'Скачать Excel', exact: true })
    .click()
  await expect(dialog.getByRole('alert')).toContainText(
    'Не удалось создать Excel.',
  )
  await expect(input).toBeEnabled()
  const download = page.waitForEvent('download')
  await dialog
    .getByRole('button', { name: 'Скачать Excel', exact: true })
    .click()
  await download
  await expect(dialog).toBeHidden()
})

for (const width of [320, 640, 768, 1024, 1280]) {
  test(`download menu and SKU dialog support keyboard, dismissal and accessibility at ${width}px`, async ({
    page,
  }) => {
    await mockCatalogApi(page)
    await page.setViewportSize({ width, height: 900 })
    await page.goto('/products')
    const trigger = page.getByRole('button', { name: 'Скачать', exact: true })
    await trigger.hover()
    const menu = page.getByRole('region', { name: 'Скачать товары' })
    await expect(menu).toBeVisible()
    await page.screenshot({ path: `.tmp/product-download/menu-${width}.png` })
    await page.getByRole('heading', { name: 'Товары', exact: true }).click()
    await expect(menu).toBeHidden()
    await trigger.focus()
    await page.keyboard.press('ArrowDown')
    await expect(
      page.getByRole('button', { name: 'Все товары в Excel', exact: true }),
    ).toBeFocused()
    await page.keyboard.press('Escape')
    await expect(trigger).toBeFocused()
    await page.keyboard.press('ArrowUp')
    await page.keyboard.press('Enter')
    const dialog = page.getByRole('dialog', { name: 'Скачать товары по SKU' })
    await dialog
      .getByRole('textbox', { name: 'SKU товаров' })
      .fill('00123, ABC-456, 00042')
    await page.screenshot({ path: `.tmp/product-download/dialog-${width}.png` })
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.keyboard.press('Escape')
    await expect(dialog).toBeHidden()
    await expect(trigger).toBeFocused()
  })
}

test('SKU dialog traps keyboard focus, handles a short viewport and closes on backdrop click', async ({
  page,
}) => {
  await mockCatalogApi(page)
  await page.setViewportSize({ width: 320, height: 480 })
  await page.goto('/products')
  const trigger = page.getByRole('button', { name: 'Скачать', exact: true })
  await trigger.click()
  await page.getByRole('button', { name: 'Товары по SKU', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: 'Скачать товары по SKU' })
  const input = dialog.getByRole('textbox', { name: 'SKU товаров' })
  await input.fill('a'.repeat(256))
  await dialog
    .getByRole('button', { name: 'Скачать Excel', exact: true })
    .click()
  await expect(dialog.getByRole('alert')).toContainText('255')
  await page.keyboard.press('Tab')
  await expect(
    dialog.getByRole('button', { name: 'Закрыть окно' }),
  ).toBeFocused()
  await page.keyboard.press('Shift+Tab')
  await expect(
    dialog.getByRole('button', { name: 'Скачать Excel', exact: true }),
  ).toBeFocused()
  expect(
    await page.evaluate(
      () => document.documentElement.scrollWidth <= innerWidth,
    ),
  ).toBe(true)
  await page.screenshot({ path: '.tmp/product-download/short-error-320.png' })
  await page.mouse.click(2, 2)
  await expect(dialog).toBeHidden()
  await expect(trigger).toBeFocused()
})

test('download control stays hidden without imports.manage', async ({
  page,
}) => {
  await mockCatalogApi(page)
  await page.route('**/api/v1/admin/auth/me', (route) =>
    route.fulfill({
      json: {
        data: {
          id: 1,
          name: 'Менеджер каталога',
          email: 'catalog@example.test',
          status: 'active',
          last_login_at: null,
          permissions: ['catalog.manage'],
        },
      },
    }),
  )
  await page.goto('/products')
  await expect(page.getByText('Монте Тиберио', { exact: true })).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Скачать', exact: true }),
  ).toHaveCount(0)
})

test.describe('touch download', () => {
  test.use({
    hasTouch: true,
    isMobile: true,
    viewport: { width: 390, height: 844 },
  })
  test('tap opens and dismisses the menu and opens the SKU dialog', async ({
    page,
  }) => {
    await mockCatalogApi(page)
    await page.goto('/products')
    const trigger = page.getByRole('button', { name: 'Скачать', exact: true })
    const menu = page.getByRole('region', { name: 'Скачать товары' })
    await trigger.tap()
    await expect(menu).toBeVisible()
    await trigger.tap()
    await expect(menu).toBeHidden()
    await trigger.tap()
    await page.getByRole('button', { name: 'Товары по SKU', exact: true }).tap()
    const dialog = page.getByRole('dialog', { name: 'Скачать товары по SKU' })
    await expect(dialog).toBeVisible()
    await dialog.getByRole('button', { name: 'Отмена' }).tap()
    await expect(dialog).toBeHidden()
  })
})
