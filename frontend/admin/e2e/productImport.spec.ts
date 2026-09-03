import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'
import { mockCatalogApi } from './catalogApi'

async function openImport(page: Page) {
  await page.goto('/products')
  await page.getByRole('button', { name: 'Загрузить массово', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: 'Массовая загрузка' })
  await dialog.getByRole('button', { name: 'Категория товаров для загрузки' }).click()
  await dialog.getByRole('button', { name: 'Керамогранит', exact: true }).click()
  return dialog
}
async function attach(page: Page) {
  await page.locator('input[type="file"][aria-label="Заполненный шаблон"]').setInputFiles({
    name: 'products.xlsx', mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', buffer: Buffer.from('mock xlsx'),
  })
}

test('product Excel import downloads category template and preserves processing when closed', async ({ page }) => {
  await mockCatalogApi(page, { delayPath: '/admin/product-imports/1' })
  const dialog = await openImport(page)
  const templateRequest = page.waitForRequest(request => request.url().includes('/products/import-template?category_id=1'))
  const download = page.waitForEvent('download')
  await dialog.getByRole('button', { name: 'Скачать шаблон Excel' }).click()
  await templateRequest
  expect((await download).suggestedFilename()).toBe('products-category-1.xlsx')
  await attach(page)
  const requestPromise = page.waitForRequest(request => new URL(request.url()).pathname.endsWith('/admin/products/import'))
  await dialog.getByRole('button', { name: 'Загрузить', exact: true }).click()
  const request = await requestPromise
  expect(request.method()).toBe('POST')
  expect(request.postDataBuffer()?.toString()).toContain('name="category_id"\r\n\r\n1')
  await expect(dialog.getByRole('progressbar')).toBeVisible()
  await expect(dialog.getByRole('button', { name: 'Загрузка…' })).toBeDisabled()
  await page.keyboard.press('Escape')
  await expect(dialog).toBeHidden()
  await page.getByRole('button', { name: 'Загрузить массово', exact: true }).click()
  await expect(dialog.getByRole('status').filter({ hasText: 'Успешно: 5. С ошибками: 0.' })).toBeVisible()
  await expect(dialog.getByRole('progressbar')).toHaveCount(0)
})

test('product Excel import errors are downloadable and modal is accessible at supported widths', async ({ page }, testInfo) => {
  await mockCatalogApi(page, { importErrors: true })
  const dialog = await openImport(page)
  await attach(page)
  await dialog.getByRole('button', { name: 'Загрузить', exact: true }).click()
  await expect(dialog.getByText('Успешно: 4. С ошибками: 1.')).toBeVisible()
  await expect(dialog.getByText('Товар с таким наименованием уже существует.')).toBeVisible()
  const download = page.waitForEvent('download')
  await dialog.getByRole('button', { name: 'Скачать Excel с ошибками' }).click()
  expect((await download).suggestedFilename()).toBe('product-import-1-errors.xlsx')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 800 })
    expect(await dialog.evaluate(node => node.scrollWidth <= node.clientWidth)).toBe(true)
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    await dialog.screenshot({ path: testInfo.outputPath(`modal-errors-${width}.png`) })
  }
  const accessibility = await new AxeBuilder({ page }).include('[role="dialog"]').analyze()
  expect(accessibility.violations.filter(v => ['serious', 'critical'].includes(v.impact ?? ''))).toEqual([])
  await dialog.getByRole('tab', { name: 'Загрузка товаров' }).focus()
  await page.keyboard.press('ArrowRight')
  await expect(dialog.getByRole('tab', { name: 'Загрузка изображений' })).toBeFocused()
  await expect(dialog.getByText('Массовая загрузка изображений появится позже.')).toBeVisible()
  await page.keyboard.press('Home')
  await expect(dialog.getByRole('tab', { name: 'Загрузка товаров' })).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(page.getByRole('button', { name: 'Загрузить массово', exact: true })).toBeFocused()
})

test('product Excel import shows upload failure and allows retry', async ({ page }) => {
  await mockCatalogApi(page, { errorPath: '/admin/products/import' })
  const dialog = await openImport(page)
  await attach(page)
  await dialog.getByRole('button', { name: 'Загрузить', exact: true }).click()
  await expect(dialog.getByRole('alert')).toContainText('Тестовая ошибка каталога')
  await expect(dialog.getByRole('button', { name: 'Загрузить', exact: true })).toBeEnabled()
  await expect(dialog.getByRole('progressbar')).toHaveCount(0)
})

test('product Excel import retains job on polling failure and refreshes status', async ({ page }) => {
  await mockCatalogApi(page)
  await page.route('**/admin/product-imports/1', route => route.fulfill({ status: 503, json: {} }))
  const dialog = await openImport(page)
  await attach(page)
  await dialog.getByRole('button', { name: 'Загрузить', exact: true }).click()
  await expect(dialog.getByRole('alert')).toContainText('Не удалось получить статус')
  await expect(dialog.getByRole('button', { name: 'Загрузка…' })).toBeDisabled()
  await page.unroute('**/admin/product-imports/1')
  await dialog.getByRole('button', { name: 'Обновить статус' }).click()
  await expect(dialog.getByText('Успешно: 5. С ошибками: 0.')).toBeVisible()
})
