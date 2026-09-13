import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from './fixtures'
import { mockCatalogApi } from './catalogApi'
import { createDeferredApiRequests } from './deferredApi'

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
  const deferredRequests = createDeferredApiRequests()
  await mockCatalogApi(page, { deferredRequests })
  const dialog = await openImport(page)
  const templateRequest = page.waitForRequest(request => request.url().includes('/products/import-template?category_id=1'))
  const download = page.waitForEvent('download')
  await dialog.getByRole('button', { name: 'Скачать шаблон Excel' }).click()
  await templateRequest
  expect((await download).suggestedFilename()).toBe('products-category-1.xlsx')
  await attach(page)
  const requestPromise = page.waitForRequest(request => new URL(request.url()).pathname.endsWith('/admin/products/import'))
  const statusRequest = deferredRequests.defer('/admin/product-imports/1')
  await dialog.getByRole('button', { name: 'Загрузить', exact: true }).click()
  const request = await requestPromise
  expect(request.method()).toBe('POST')
  expect(request.postDataBuffer()?.toString()).toContain('name="category_id"\r\n\r\n1')
  await statusRequest.requested
  await expect(dialog.getByRole('progressbar')).toBeVisible()
  await expect(dialog.getByRole('button', { name: 'Загрузка…' })).toBeDisabled()
  await page.keyboard.press('Escape')
  await expect(dialog).toBeHidden()
  statusRequest.release()
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
  await expect(dialog.getByRole('heading', { name: 'Подготовьте ZIP-архив' })).toBeVisible()
  await expect(dialog.getByRole('button', { name: 'Выбрать ZIP-архив' })).toBeVisible()
  await page.keyboard.press('Home')
  await expect(dialog.getByRole('tab', { name: 'Загрузка товаров' })).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(page.getByRole('button', { name: 'Загрузить массово', exact: true })).toBeFocused()
})

test('category edit template includes SKU and uses the category import flow', async ({ page }) => {
  await mockCatalogApi(page, { importErrors: true })
  const dialog = await openImport(page)
  await dialog.getByRole('radio', { name: 'Редактировать товары' }).locator('..').click()
  await expect(dialog.getByText('SKU определяет редактируемый товар', { exact: false })).toBeVisible()
  const templateRequest = page.waitForRequest(request => request.url().includes('/products/import-template?category_id=1&editing=1'))
  const download = page.waitForEvent('download')
  await dialog.getByRole('button', { name: 'Скачать шаблон Excel' }).click()
  await templateRequest
  expect((await download).suggestedFilename()).toBe('products-category-1-edit.xlsx')
  await dialog.locator('input[type="file"][aria-label="Заполненный шаблон"]').setInputFiles({
    name: 'catalogue.xlsx', mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', buffer: Buffer.from('mock xlsx'),
  })
  const requestPromise = page.waitForRequest(request => new URL(request.url()).pathname.endsWith('/admin/products/import'))
  await dialog.getByRole('button', { name: 'Загрузить', exact: true }).click()
  expect((await requestPromise).postDataBuffer()?.toString()).toContain('name="category_id"\r\n\r\n1')
  await expect(dialog.getByText('Успешно: 4. С ошибками: 1.')).toBeVisible()
  const errorsDownload = page.waitForEvent('download')
  await dialog.getByRole('button', { name: 'Скачать Excel с ошибками' }).click()
  await errorsDownload
})

test('product Excel import shows upload failure and allows retry', async ({ page, browserIssueGuard }) => {
  browserIssueGuard.allowApiError(500, '/api/v1/admin/products/import')
  await mockCatalogApi(page, { errorPath: '/admin/products/import' })
  const dialog = await openImport(page)
  await attach(page)
  await dialog.getByRole('button', { name: 'Загрузить', exact: true }).click()
  await expect(dialog.getByRole('alert')).toContainText('Тестовая ошибка каталога')
  await expect(dialog.getByRole('button', { name: 'Загрузить', exact: true })).toBeEnabled()
  await expect(dialog.getByRole('progressbar')).toHaveCount(0)
})

test('product Excel import retains job on polling failure and refreshes status', async ({ page, browserIssueGuard }) => {
  browserIssueGuard.allowApiError(503, '/api/v1/admin/product-imports/1')
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

test('variation-group Excel import downloads, uploads and reports its accessible result', async ({ page }, testInfo) => {
  await mockCatalogApi(page, { importErrors: true })
  await page.goto('/products')
  await page.getByRole('button', { name: 'Группы вариантов', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: 'Группы вариантов из Excel' })
  const templateRequest = page.waitForRequest(request => new URL(request.url()).pathname.endsWith('/admin/products/group-import-template'))
  const download = page.waitForEvent('download')
  await dialog.getByRole('button', { name: 'Скачать Excel с группами' }).click()
  await templateRequest
  expect((await download).suggestedFilename()).toBe('product-groups-template.xlsx')
  await dialog.locator('input[type="file"]').setInputFiles({ name: 'groups.xlsx', mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', buffer: Buffer.from('mock xlsx') })
  const uploadRequest = page.waitForRequest(request => new URL(request.url()).pathname.endsWith('/admin/products/group-import'))
  await dialog.getByRole('button', { name: 'Запустить обработку' }).click()
  expect((await uploadRequest).method()).toBe('POST')
  await expect(dialog.getByText('Обработано групп: 2 из 2. Изменено: 1. Ошибок: 1.')).toBeVisible()
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 800 })
    expect(await dialog.evaluate(node => node.scrollWidth <= node.clientWidth)).toBe(true)
    await dialog.screenshot({ path: testInfo.outputPath(`group-import-${width}.png`) })
  }
  const accessibility = await new AxeBuilder({ page }).include('[role="dialog"]').analyze()
  expect(accessibility.violations.filter(v => ['serious', 'critical'].includes(v.impact ?? ''))).toEqual([])
})

test('price and status Excel import preserves its contract and responsive accessible states', async ({ page }, testInfo) => {
  await mockCatalogApi(page, { importErrors: true })
  await page.goto('/products')
  await page.getByRole('button', { name: 'Цены и статусы', exact: true }).click()
  const dialog = page.getByRole('dialog', { name: 'Цены и статусы из Excel' })

  const templateRequest = page.waitForRequest(request => new URL(request.url()).pathname.endsWith('/admin/products/price-status-template'))
  const templateDownload = page.waitForEvent('download')
  await dialog.getByRole('button', { name: 'Скачать шаблон Excel' }).click()
  await templateRequest
  expect((await templateDownload).suggestedFilename()).toBe('product-price-status-template.xlsx')

  await dialog.locator('input[type="file"]').setInputFiles({ name: 'price-status.xlsx', mimeType: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', buffer: Buffer.from('mock xlsx') })
  const uploadRequest = page.waitForRequest(request => new URL(request.url()).pathname.endsWith('/admin/products/price-status-import'))
  await dialog.getByRole('button', { name: 'Запустить обработку' }).click()
  expect((await uploadRequest).method()).toBe('POST')
  await expect(dialog.getByText('Обработано: 3 из 3. Изменено: 2. Ошибок: 1.')).toBeVisible()

  const errorDownload = page.waitForEvent('download')
  await dialog.getByRole('button', { name: 'Скачать Excel с ошибками' }).click()
  expect((await errorDownload).suggestedFilename()).toBe('product-price-status-import-1-errors.xlsx')

  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 800 })
    expect(await dialog.evaluate(node => node.scrollWidth <= node.clientWidth)).toBe(true)
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    await dialog.screenshot({ path: testInfo.outputPath(`price-status-import-${width}.png`) })
  }
  const accessibility = await new AxeBuilder({ page }).include('[role="dialog"]').analyze()
  expect(accessibility.violations.filter(v => ['serious', 'critical'].includes(v.impact ?? ''))).toEqual([])
  await page.keyboard.press('Escape')
  await expect(page.getByRole('button', { name: 'Цены и статусы', exact: true })).toBeFocused()
})
