import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from './fixtures'
import { mockCatalogApi } from './catalogApi'
import { createDeferredApiRequests } from './deferredApi'

async function openProductMenu(page: Page) {
  const desktop = page.getByRole('navigation', { name: 'Основная навигация' })
  if (await desktop.isVisible()) {
    await desktop.getByRole('button', { name: 'Товары', exact: true }).click()
  } else {
    await page.getByRole('button', { name: 'Открыть меню' }).click()
  }
}

async function openImport(page: Page) {
  await page.goto('/products')
  await openProductMenu(page)
  await page
    .getByRole('link', { name: 'Добавить массово товары', exact: true })
    .click()
  await expect(page).toHaveURL('/products/import')
  await expect(
    page.getByRole('heading', { level: 1, name: 'Добавить массово товары' }),
  ).toBeVisible()
  await expect(page.getByRole('dialog')).toHaveCount(0)
  const workspace = page.locator('body')
  await workspace
    .getByRole('button', { name: 'Категория товаров для загрузки' })
    .click()
  await workspace
    .getByRole('button', { name: 'Керамогранит', exact: true })
    .click()
  return workspace
}
async function attach(page: Page) {
  await page
    .locator('input[type="file"][aria-label="Заполненный шаблон"]')
    .setInputFiles({
      name: 'products.xlsx',
      mimeType:
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      buffer: Buffer.from('mock xlsx'),
    })
}

test('Excel dropzone selects and replaces a spreadsheet while preserving upload permissions', async ({
  page,
}) => {
  await mockCatalogApi(page)
  await page.goto('/products/import')
  const dropzone = page.locator('[data-product-excel-dropzone]')
  await expect(dropzone).toBeDisabled()
  await expect(dropzone).toContainText('Выберите или перетащите Excel')
  await expect(dropzone.locator('.ui-file-dropzone-icon')).toHaveCSS(
    'color',
    'rgb(4, 120, 87)',
  )
  await page
    .getByRole('button', { name: 'Категория товаров для загрузки' })
    .click()
  await page.getByRole('button', { name: 'Керамогранит', exact: true }).click()
  const dataTransfer = await page.evaluateHandle(() => {
    const transfer = new DataTransfer()
    transfer.items.add(
      new File(['workbook'], 'dropped.xlsx', {
        type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      }),
    )
    return transfer
  })
  await dropzone.dispatchEvent('dragenter', { dataTransfer })
  await expect(dropzone).toContainText('Отпустите файл Excel')
  await dropzone.dispatchEvent('drop', { dataTransfer })
  await expect(page.locator('#product-import-file-selection')).toContainText(
    'dropped.xlsx',
  )
  await expect(
    page.getByRole('button', { name: 'Загрузить', exact: true }),
  ).toBeEnabled()
  const picker = page.waitForEvent('filechooser')
  await dropzone.click()
  await (
    await picker
  ).setFiles({
    name: 'replacement.xlsx',
    mimeType:
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    buffer: Buffer.from('replacement'),
  })
  await expect(page.locator('#product-import-file-selection')).toContainText(
    'replacement.xlsx',
  )
  await page.getByRole('button', { name: 'Загрузить', exact: true }).click()
  await expect(page.getByText('Успешно: 5. С ошибками: 0.')).toBeVisible()
  await dataTransfer.dispose()
})

for (const kind of ['products', 'images'] as const) {
  test(`${kind}: import progress shows idle, transfer, server counts, failure and completion`, async ({
    page,
  }, testInfo) => {
    const deferredRequests = createDeferredApiRequests()
    await mockCatalogApi(page, { deferredRequests })
    const uploadPath =
      kind === 'products'
        ? '/admin/products/import'
        : '/admin/product-image-imports'
    const statusPath =
      kind === 'products'
        ? '/admin/product-imports/1'
        : '/admin/product-image-imports/1'
    let phase: 'pending' | 'processing' | 'failed' | 'completed' = 'pending'
    await page.route(`**${statusPath}`, async (route) => {
      const completed = phase === 'completed'
      const total = phase === 'pending' ? 0 : 5
      const processed = completed ? 5 : phase === 'pending' ? 0 : 2
      await route.fulfill({
        json: {
          data: {
            id: 1,
            filename: kind === 'products' ? 'products.xlsx' : 'images.zip',
            status: phase,
            created_at: '2026-10-06T12:00:00Z',
            started_at: null,
            completed_at: null,
            error_message: phase === 'failed' ? 'Обработка остановлена.' : null,
            has_error_file: false,
            ...(kind === 'products'
              ? {
                  category_id: 1,
                  total_rows: total,
                  processed_rows: processed,
                  created_rows: processed,
                  updated_rows: 0,
                  failed_rows: 0,
                  row_errors: [],
                }
              : {
                  total_folders: total,
                  processed_folders: processed,
                  created_images: processed,
                  replaced_images: 0,
                  failed_folders: 0,
                  errors: [],
                }),
          },
        },
      })
    })
    await page.goto('/products/import')
    if (kind === 'images') {
      await page.getByRole('tab', { name: 'Загрузка изображений' }).click()
    } else {
      await page
        .getByRole('button', { name: 'Категория товаров для загрузки' })
        .click()
      await page
        .getByRole('button', { name: 'Керамогранит', exact: true })
        .click()
    }
    const panel = page.locator(`#import-panel-${kind}`)
    const status = panel.getByRole('region', {
      name: 'Статус импорта',
      exact: true,
    })
    const progress = status.getByRole('progressbar')
    await expect(progress).toHaveAttribute('value', '0')
    await expect(status.locator('[data-import-progress-label]')).toHaveText(
      '0%',
    )
    const input = panel.locator(
      kind === 'products' ? '#product-import-file' : '#image-import-file',
    )
    const file = {
      name: kind === 'products' ? 'products.xlsx' : 'images.zip',
      mimeType:
        kind === 'products'
          ? 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'
          : 'application/zip',
      buffer: Buffer.from('mock import'),
    }
    await input.setInputFiles(file)
    const transfer = deferredRequests.defer(uploadPath)
    await panel
      .getByRole('button', {
        name: kind === 'products' ? 'Загрузить' : 'Загрузить архив',
        exact: true,
      })
      .click()
    await transfer.requested
    await expect(progress).not.toHaveAttribute('value')
    await expect(status).toContainText(
      kind === 'products' ? 'Загружаем XLSX-файл' : 'Загружаем ZIP-архив',
    )
    await page.emulateMedia({ reducedMotion: 'no-preference' })
    await expect(progress).toHaveCSS(
      'animation-name',
      /^import-progress-active/,
    )
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await expect(progress).toHaveCSS('animation-name', 'none')
    await status.screenshot({
      path: testInfo.outputPath('progress-unknown.png'),
    })
    await page.emulateMedia({ reducedMotion: 'no-preference' })
    transfer.release()
    await expect(status).toContainText(
      kind === 'products'
        ? 'Файл ожидает обработки'
        : 'Архив ожидает обработки',
    )
    await expect(progress).not.toHaveAttribute('value')
    phase = 'processing'
    await expect(progress).toHaveAttribute('value', '40')
    await expect(status.locator('[data-import-progress-label]')).toHaveText(
      '40%',
    )
    const accessibility = await new AxeBuilder({ page })
      .include(
        `#import-panel-${kind} [aria-labelledby="${kind === 'products' ? 'product' : 'image'}-import-result-title"]`,
      )
      .analyze()
    expect(
      accessibility.violations.filter((item) =>
        ['serious', 'critical'].includes(item.impact ?? ''),
      ),
    ).toEqual([])
    for (const width of [320, 1280]) {
      await page.setViewportSize({ width, height: 900 })
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
      ).toBe(true)
      await status.screenshot({
        path: testInfo.outputPath(`progress-${width}.png`),
      })
    }
    phase = 'failed'
    await expect(status).toContainText('Загрузка не завершена')
    await expect(progress).toHaveAttribute('value', '40')
    await expect(status.locator('[data-import-progress-label]')).toHaveText(
      '40%',
    )
    phase = 'completed'
    await input.setInputFiles(file)
    await expect(progress).toHaveAttribute('value', '0')
    await panel
      .getByRole('button', {
        name: kind === 'products' ? 'Загрузить' : 'Загрузить архив',
        exact: true,
      })
      .click()
    await expect(progress).toHaveAttribute('value', '100')
    await expect(status.locator('[data-import-progress-label]')).toHaveText(
      '100%',
    )
    await expect(status).toContainText('Загрузка завершена')
  })
}

test('product Excel import downloads category template and preserves processing across page navigation', async ({
  page,
}) => {
  const deferredRequests = createDeferredApiRequests()
  await mockCatalogApi(page, { deferredRequests })
  const workspace = await openImport(page)
  const templateRequest = page.waitForRequest((request) =>
    request.url().includes('/products/import-template?category_id=1'),
  )
  const download = page.waitForEvent('download')
  await workspace.getByRole('button', { name: 'Скачать шаблон Excel' }).click()
  await templateRequest
  expect((await download).suggestedFilename()).toBe('products-category-1.xlsx')
  await attach(page)
  const requestPromise = page.waitForRequest((request) =>
    new URL(request.url()).pathname.endsWith('/admin/products/import'),
  )
  const statusRequest = deferredRequests.defer('/admin/product-imports/1')
  await workspace
    .getByRole('button', { name: 'Загрузить', exact: true })
    .click()
  const request = await requestPromise
  expect(request.method()).toBe('POST')
  expect(request.postDataBuffer()?.toString()).toContain(
    'name="category_id"\r\n\r\n1',
  )
  await statusRequest.requested
  await expect(workspace.getByRole('progressbar')).toBeVisible()
  await expect(
    workspace.getByRole('button', { name: 'Загрузка…' }),
  ).toBeDisabled()
  await openProductMenu(page)
  await page.getByRole('link', { name: 'Список товаров', exact: true }).click()
  await expect(page).toHaveURL('/products')
  const search = page.getByRole('textbox', { name: 'Поиск', exact: true })
  await search.focus()
  const completedResponse = page.waitForResponse('**/admin/product-imports/1')
  statusRequest.release()
  await completedResponse
  await expect(search).toBeFocused()
  await expect(page.getByRole('alert')).toHaveCount(0)
  await openProductMenu(page)
  await page
    .getByRole('link', { name: 'Добавить массово товары', exact: true })
    .click()
  await expect(
    workspace
      .getByRole('status')
      .filter({ hasText: 'Успешно: 5. С ошибками: 0.' }),
  ).toBeVisible()
  await expect(workspace.getByRole('progressbar')).toHaveAttribute(
    'value',
    '100',
  )
})

test('product Excel import errors are downloadable and page is accessible at supported widths', async ({
  page,
}, testInfo) => {
  await mockCatalogApi(page, { importErrors: true })
  const workspace = await openImport(page)
  await attach(page)
  await workspace
    .getByRole('button', { name: 'Загрузить', exact: true })
    .click()
  await expect(workspace.getByText('Успешно: 4. С ошибками: 1.')).toBeVisible()
  await expect(
    workspace.getByText('Товар с таким наименованием уже существует.'),
  ).toBeVisible()
  const download = page.waitForEvent('download')
  await workspace
    .getByRole('button', { name: 'Скачать Excel с ошибками' })
    .click()
  expect((await download).suggestedFilename()).toBe(
    'product-import-1-errors.xlsx',
  )
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 800 })
    expect(
      await workspace.evaluate((node) => node.scrollWidth <= node.clientWidth),
    ).toBe(true)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
      ),
    ).toBe(true)
    await workspace.screenshot({
      path: testInfo.outputPath(`page-errors-${width}.png`),
    })
  }
  const accessibility = await new AxeBuilder({ page }).analyze()
  // This approved additional-message tone has a known 2.58:1 contrast finding.
  expect(
    accessibility.violations.map((violation) => ({
      id: violation.id,
      targets: violation.nodes.flatMap((node) => node.target),
    })),
  ).toEqual([
    {
      id: 'color-contrast',
      targets: ['p[data-product-import-info=""]'],
    },
  ])
  await workspace.getByRole('tab', { name: 'Загрузка товаров' }).focus()
  await page.keyboard.press('ArrowRight')
  await expect(
    workspace.getByRole('tab', { name: 'Загрузка изображений' }),
  ).toBeFocused()
  await expect(
    workspace.getByRole('heading', { name: '1. Подготовьте архив' }),
  ).toBeVisible()
  const imageSteps = workspace.locator('[data-product-image-import-step]')
  await expect(imageSteps).toHaveCount(2)
  await expect(imageSteps.nth(0).locator(':scope > header h2')).toHaveText(
    '1. Подготовьте архив',
  )
  await expect(imageSteps.nth(1).locator(':scope > header h2')).toHaveText(
    '2. Загрузите архив',
  )
  const imageInfo = workspace.locator('[data-product-image-import-info]')
  await expect(imageInfo).toHaveAttribute('role', 'status')
  await expect(imageInfo).toHaveClass(/bg-blue-light-50/)
  await expect(imageInfo).toHaveClass(/text-blue-light-500/)
  await expect(imageInfo).toContainText(
    'Поддерживаются JPG, PNG и WebP; ZIP — до 500 МБ.',
  )
  await expect(imageInfo).toContainText(
    'Имя папки и начало имени каждого файла должны совпадать со SKU товара.',
  )
  await expect(imageInfo).toContainText('станет обложкой')
  await expect(imageInfo).toContainText('Ошибка одной папки не помешает')
  await expect(workspace.locator('[data-product-zip-dropzone]')).toBeVisible()
  await expect(
    workspace.getByRole('button', {
      name: 'Выберите или перетащите ZIP-архив в эту область',
    }),
  ).toBeVisible()
  await page.keyboard.press('Home')
  await expect(
    workspace.getByRole('tab', { name: 'Загрузка товаров' }),
  ).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(page).toHaveURL('/products/import')
})

test('category edit template includes SKU and uses the category import flow', async ({
  page,
}) => {
  await mockCatalogApi(page, { importErrors: true })
  const workspace = await openImport(page)
  await workspace
    .getByRole('radio', { name: 'Редактировать товары' })
    .locator('..')
    .click()
  await expect(
    workspace.getByText('SKU определяет редактируемый товар', { exact: false }),
  ).toBeVisible()
  const templateRequest = page.waitForRequest((request) =>
    request.url().includes('/products/import-template?category_id=1&editing=1'),
  )
  const download = page.waitForEvent('download')
  await workspace.getByRole('button', { name: 'Скачать шаблон Excel' }).click()
  await templateRequest
  expect((await download).suggestedFilename()).toBe(
    'products-category-1-edit.xlsx',
  )
  await workspace
    .locator('input[type="file"][aria-label="Заполненный шаблон"]')
    .setInputFiles({
      name: 'catalogue.xlsx',
      mimeType:
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      buffer: Buffer.from('mock xlsx'),
    })
  const requestPromise = page.waitForRequest((request) =>
    new URL(request.url()).pathname.endsWith('/admin/products/import'),
  )
  await workspace
    .getByRole('button', { name: 'Загрузить', exact: true })
    .click()
  expect((await requestPromise).postDataBuffer()?.toString()).toContain(
    'name="category_id"\r\n\r\n1',
  )
  await expect(workspace.getByText('Успешно: 4. С ошибками: 1.')).toBeVisible()
  const errorsDownload = page.waitForEvent('download')
  await workspace
    .getByRole('button', { name: 'Скачать Excel с ошибками' })
    .click()
  await errorsDownload
})

test('product Excel import shows upload failure and allows retry', async ({
  page,
  browserIssueGuard,
}) => {
  browserIssueGuard.allowApiError(500, '/api/v1/admin/products/import')
  await mockCatalogApi(page, { errorPath: '/admin/products/import' })
  const workspace = await openImport(page)
  await attach(page)
  await workspace
    .getByRole('button', { name: 'Загрузить', exact: true })
    .click()
  await expect(workspace.getByRole('alert')).toContainText(
    'Тестовая ошибка каталога',
  )
  await expect(
    workspace.getByRole('button', { name: 'Загрузить', exact: true }),
  ).toBeEnabled()
  await expect(workspace.getByRole('progressbar')).toHaveAttribute('value', '0')
})

test('product Excel import retains job on polling failure and refreshes status', async ({
  page,
  browserIssueGuard,
}) => {
  browserIssueGuard.allowApiError(503, '/api/v1/admin/product-imports/1')
  await mockCatalogApi(page)
  await page.route('**/admin/product-imports/1', (route) =>
    route.fulfill({ status: 503, json: {} }),
  )
  const workspace = await openImport(page)
  await attach(page)
  await workspace
    .getByRole('button', { name: 'Загрузить', exact: true })
    .click()
  await expect(workspace.getByRole('alert')).toContainText(
    'Не удалось получить статус',
  )
  await expect(
    workspace.getByRole('button', { name: 'Загрузка…' }),
  ).toBeDisabled()
  await page.unroute('**/admin/product-imports/1')
  await workspace.getByRole('button', { name: 'Обновить статус' }).click()
  await expect(workspace.getByText('Успешно: 5. С ошибками: 0.')).toBeVisible()
})

test('product image ZIP import uploads, polls and downloads its accessible responsive error report', async ({
  page,
}, testInfo) => {
  await mockCatalogApi(page, { importErrors: true })
  const workspace = await openImport(page)
  await workspace.getByRole('tab', { name: 'Загрузка изображений' }).click()
  await workspace.locator('#image-import-file').setInputFiles({
    name: 'images.zip',
    mimeType: 'application/zip',
    buffer: Buffer.from('mock zip'),
  })
  await expect(workspace.getByText('images.zip', { exact: true })).toBeVisible()

  const uploadRequest = page.waitForRequest((request) =>
    new URL(request.url()).pathname.endsWith('/admin/product-image-imports'),
  )
  await workspace.getByRole('button', { name: 'Загрузить архив' }).click()
  expect((await uploadRequest).method()).toBe('POST')
  await expect(
    workspace.getByText('Папок обработано').locator('..'),
  ).toContainText('2 из 2')
  await expect(
    workspace.getByText('Товар с таким SKU не найден.'),
  ).toBeVisible()

  const report = page.waitForEvent('download')
  await workspace
    .getByRole('button', { name: 'Скачать отчёт с ошибками' })
    .click()
  expect((await report).suggestedFilename()).toBe(
    'product-image-import-1-errors.csv',
  )
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 800 })
    expect(
      await workspace.evaluate((node) => node.scrollWidth <= node.clientWidth),
    ).toBe(true)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
      ),
    ).toBe(true)
    await workspace.screenshot({
      path: testInfo.outputPath(`image-import-${width}.png`),
    })
  }
  const accessibility = await new AxeBuilder({ page }).analyze()
  // The owner requested the approved additional blue tone; its text contrast remains a known finding.
  expect(
    accessibility.violations.map((violation) => ({
      id: violation.id,
      targets: violation.nodes.flatMap((node) => node.target),
    })),
  ).toEqual([
    {
      id: 'color-contrast',
      targets: [
        'p[data-product-image-import-info=""] > .block:nth-child(1)',
        'p[data-product-image-import-info=""] > .mt-1.block:nth-child(2)',
        '.mt-1.block:nth-child(3)',
        '.mt-1.block:nth-child(3) > code',
        '.mt-1.block:nth-child(4)',
      ],
    },
  ])
})

test('variation-group Excel import downloads, uploads and reports its accessible result', async ({
  page,
}, testInfo) => {
  await mockCatalogApi(page, { importErrors: true })
  await page.goto('/products')
  await openProductMenu(page)
  await page
    .getByRole('link', { name: 'Объединить товары', exact: true })
    .click()
  await expect(page).toHaveURL('/products/combine')
  await expect(
    page.getByRole('heading', { name: 'Объединить товары', level: 1 }),
  ).toBeVisible()
  await expect(
    page.getByRole('status').filter({
      hasText: 'На листе «Группы» выберите действие',
    }),
  ).toBeVisible()
  await expect(
    page.getByRole('link', { name: 'К списку товаров' }),
  ).toHaveCount(0)
  await expect(page.getByRole('dialog')).toHaveCount(0)
  const workspace = page.locator('body')
  const uploadCard = workspace.locator('section.admin-panel').filter({
    has: page.getByRole('heading', {
      name: '2. Загрузите отредактированный файл',
    }),
  })
  const fileHelp =
    'XLSX до 10 МБ. Если в файле есть ошибка, изменения из него не применяются.'
  await expect(uploadCard.locator('header').getByText(fileHelp)).toHaveCount(0)
  await expect(
    uploadCard.locator('.admin-panel--inset').getByText(fileHelp),
  ).toBeVisible()
  const fileDropzone = workspace.locator('[data-group-import-excel-dropzone]')
  await expect(fileDropzone).toBeVisible()
  await expect(fileDropzone).toContainText(
    'Выберите или перетащите Excel в эту область',
  )
  const templateRequest = page.waitForRequest((request) =>
    new URL(request.url()).pathname.endsWith(
      '/admin/products/group-import-template',
    ),
  )
  const download = page.waitForEvent('download')
  await workspace
    .getByRole('button', { name: 'Скачать Excel с группами' })
    .click()
  await templateRequest
  expect((await download).suggestedFilename()).toBe(
    'product-groups-template.xlsx',
  )
  await workspace.locator('input[type="file"]').setInputFiles({
    name: 'groups.xlsx',
    mimeType:
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    buffer: Buffer.from('mock xlsx'),
  })
  await expect(workspace.locator('#group-import-file-selection')).toContainText(
    'groups.xlsx',
  )
  const uploadRequest = page.waitForRequest((request) =>
    new URL(request.url()).pathname.endsWith('/admin/products/group-import'),
  )
  await workspace.getByRole('button', { name: 'Запустить обработку' }).click()
  expect((await uploadRequest).method()).toBe('POST')
  await expect(
    workspace.getByText('Обработано групп: 2 из 2. Изменено: 1. Ошибок: 1.'),
  ).toBeVisible()
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 800 })
    expect(
      await workspace.evaluate((node) => node.scrollWidth <= node.clientWidth),
    ).toBe(true)
    await workspace.screenshot({
      path: testInfo.outputPath(`group-import-${width}.png`),
    })
  }
  const accessibility = await new AxeBuilder({ page }).analyze()
  expect(
    accessibility.violations.filter((v) =>
      ['serious', 'critical'].includes(v.impact ?? ''),
    ),
  ).toEqual([])
})

test('price and status Excel import preserves its contract and responsive accessible states', async ({
  page,
}, testInfo) => {
  await mockCatalogApi(page, { importErrors: true })
  await page.goto('/products')
  await openProductMenu(page)
  await page.getByRole('link', { name: 'Цены и статусы', exact: true }).click()
  await expect(page).toHaveURL('/products/price-status')
  await expect(
    page.getByRole('heading', { name: 'Цены и статусы', level: 1 }),
  ).toBeVisible()
  await expect(
    page.getByRole('link', { name: 'К списку товаров' }),
  ).toHaveCount(0)
  await expect(page.getByRole('dialog')).toHaveCount(0)
  const workspace = page.locator('body')

  await expect(
    workspace.getByRole('status').filter({
      hasText: '«Цены»: SKU, новая и необязательная старая цена.',
    }),
  ).toBeVisible()
  const uploadCard = workspace.locator('section.admin-panel').filter({
    has: page.getByRole('heading', {
      name: '2. Загрузите заполненный файл',
    }),
  })
  const fileHelp =
    'XLSX до 10 МБ. Строки с ошибками не помешают обработать остальные.'
  await expect(uploadCard.locator('header').getByText(fileHelp)).toHaveCount(0)
  await expect(
    uploadCard.locator('.admin-panel--inset').getByText(fileHelp),
  ).toBeVisible()
  const fileDropzone = workspace.locator('[data-price-status-excel-dropzone]')
  await expect(fileDropzone).toBeVisible()
  await expect(fileDropzone).toContainText(
    'Выберите или перетащите Excel в эту область',
  )

  const templateRequest = page.waitForRequest((request) =>
    new URL(request.url()).pathname.endsWith(
      '/admin/products/price-status-template',
    ),
  )
  const templateDownload = page.waitForEvent('download')
  await workspace.getByRole('button', { name: 'Скачать шаблон Excel' }).click()
  await templateRequest
  expect((await templateDownload).suggestedFilename()).toBe(
    'product-price-status-template.xlsx',
  )

  await workspace.locator('input[type="file"]').setInputFiles({
    name: 'price-status.xlsx',
    mimeType:
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    buffer: Buffer.from('mock xlsx'),
  })
  const uploadRequest = page.waitForRequest((request) =>
    new URL(request.url()).pathname.endsWith(
      '/admin/products/price-status-import',
    ),
  )
  await workspace.getByRole('button', { name: 'Запустить обработку' }).click()
  expect((await uploadRequest).method()).toBe('POST')
  await expect(
    workspace.getByText('Обработано: 3 из 3. Изменено: 2. Ошибок: 1.'),
  ).toBeVisible()

  const errorDownload = page.waitForEvent('download')
  await workspace
    .getByRole('button', { name: 'Скачать Excel с ошибками' })
    .click()
  expect((await errorDownload).suggestedFilename()).toBe(
    'product-price-status-import-1-errors.xlsx',
  )

  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 800 })
    expect(
      await workspace.evaluate((node) => node.scrollWidth <= node.clientWidth),
    ).toBe(true)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
      ),
    ).toBe(true)
    await workspace.screenshot({
      path: testInfo.outputPath(`price-status-import-${width}.png`),
    })
  }
  const accessibility = await new AxeBuilder({ page }).analyze()
  expect(
    accessibility.violations.filter((v) =>
      ['serious', 'critical'].includes(v.impact ?? ''),
    ),
  ).toEqual([])
  await page.keyboard.press('Escape')
  await expect(page).toHaveURL('/products/price-status')
})

const importPages = [
  { path: '/products/import', title: 'Добавить массово товары' },
  { path: '/products/price-status', title: 'Цены и статусы' },
  { path: '/products/combine', title: 'Объединить товары' },
]

for (const { path, title } of importPages) {
  test(`${title}: direct URL, active menu and responsive default page`, async ({
    page,
  }, testInfo) => {
    await mockCatalogApi(page)
    await page.goto(path)
    await expect(
      page.getByRole('heading', { level: 1, name: title }),
    ).toBeVisible()
    await expect(page.getByRole('dialog')).toHaveCount(0)
    if (path === '/products/import') {
      await expect(page.locator('.admin-workspace > .admin-panel')).toHaveCount(
        0,
      )
      const tabList = page.getByRole('tablist', { name: 'Тип загрузки' })
      await expect(tabList).toHaveClass(/rounded-xl/)
      await expect(tabList).toHaveClass(/border-gray-200/)
      await expect(
        page.getByRole('tab', { name: 'Загрузка товаров' }),
      ).toHaveClass(/outline-primary-500/)
      await expect(
        page.getByRole('tab', { name: 'Загрузка товаров' }),
      ).toHaveClass(/outline-1/)
      const imageTab = page.getByRole('tab', {
        name: 'Загрузка изображений',
      })
      const colorBeforeHover = await imageTab.evaluate(
        (element) => getComputedStyle(element).color,
      )
      await imageTab.hover()
      await expect(imageTab).toHaveCSS('color', colorBeforeHover)
      await expect(imageTab).toHaveCSS('background-color', 'rgb(242, 244, 247)')
      const stepPanels = page.locator('[data-product-import-step]')
      await expect(stepPanels).toHaveCount(3)
      for (const [index, heading] of [
        '1. Выберите сценарий',
        '2. Подготовьте шаблон',
        '3. Загрузите заполненный файл',
      ].entries()) {
        await expect(
          stepPanels.nth(index).locator(':scope > header h2'),
        ).toHaveText(heading)
      }
      const importInfo = page.locator('[data-product-import-info]')
      await expect(importInfo).toHaveAttribute('role', 'status')
      await expect(importInfo).toHaveClass(/bg-blue-light-50/)
      await expect(importInfo).toHaveClass(/text-blue-light-500/)
      await expect(importInfo).toContainText('SKU присваивается автоматически.')
      const categoryTrigger = page.getByRole('button', {
        name: 'Категория товаров для загрузки',
      })
      await categoryTrigger.click()
      const categoryMenu = page.getByRole('group', {
        name: 'Категория товаров для загрузки: варианты',
      })
      await expect(categoryMenu).toBeVisible()
      expect(
        await categoryMenu.evaluate(
          (element) =>
            element.parentElement === document.body &&
            getComputedStyle(element).position === 'fixed',
        ),
      ).toBe(true)
      await page.keyboard.press('Escape')
      await expect(categoryMenu).toHaveCount(0)
      await page.mouse.move(0, 0)
      const stepPair = page.locator('[data-product-import-steps-pair]')
      await expect(stepPair).toHaveCount(1)
      await page.setViewportSize({ width: 1280, height: 900 })
      await expect(stepPair).toHaveCSS('display', 'grid')
      expect(
        await stepPair.evaluate(
          (element) =>
            getComputedStyle(element).gridTemplateColumns.split(' ').length,
        ),
      ).toBe(2)
      const secondStepBox = await stepPanels.nth(1).boundingBox()
      const thirdStepBox = await stepPanels.nth(2).boundingBox()
      expect(secondStepBox).not.toBeNull()
      expect(thirdStepBox).not.toBeNull()
      expect(
        Math.abs(secondStepBox!.height - thirdStepBox!.height),
      ).toBeLessThanOrEqual(1)
      await page.setViewportSize({ width: 768, height: 900 })
      expect(
        await stepPair.evaluate(
          (element) =>
            getComputedStyle(element).gridTemplateColumns.split(' ').length,
        ),
      ).toBe(1)
    }
    for (const width of [320, 640, 768, 1024, 1280]) {
      await page.setViewportSize({ width, height: 900 })
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
      ).toBe(true)
      await page.screenshot({
        path: testInfo.outputPath(`default-${width}.png`),
        fullPage: true,
      })
      const violations = (await new AxeBuilder({ page }).analyze()).violations
      if (path === '/products/import') {
        expect(violations).toHaveLength(1)
        expect(violations[0].id).toBe('color-contrast')
        expect(violations[0].nodes).toHaveLength(1)
        expect(violations[0].nodes[0].html).toContain(
          'data-product-import-info',
        )
      } else {
        expect(violations).toEqual([])
      }
    }
    await openProductMenu(page)
    await expect(
      page.getByRole('link', { name: title, exact: true }),
    ).toHaveAttribute('aria-current', 'page')
    await expect(
      page.getByRole('link', { name: 'Список товаров', exact: true }),
    ).not.toHaveAttribute('aria-current', 'page')
    await page.keyboard.press('Escape')
    await page.reload()
    await expect(
      page.getByRole('heading', { level: 1, name: title }),
    ).toBeVisible()
  })
}

for (const permissions of [[], ['catalog.manage'], ['imports.manage']]) {
  test(`import pages require both catalog and import permissions: ${permissions.join(',') || 'none'}`, async ({
    page,
  }) => {
    await mockCatalogApi(page)
    await page.route('**/admin/auth/me', (route) =>
      route.fulfill({
        json: {
          data: {
            id: 1,
            name: 'Сотрудник',
            email: 'restricted@example.test',
            status: 'active',
            permissions,
          },
        },
      }),
    )
    for (const { path } of importPages) {
      await page.goto(path)
      await expect(page).toHaveURL('/')
    }
    if (permissions.includes('catalog.manage')) {
      await openProductMenu(page)
      for (const { title } of importPages) {
        await expect(
          page.getByRole('link', { name: title, exact: true }),
        ).toHaveCount(0)
      }
    }
  })
}

for (const { path, title, endpoint } of [
  {
    path: '/products/price-status',
    title: 'Цены и статусы',
    endpoint: '/admin/product-price-status-imports/1',
  },
  {
    path: '/products/combine',
    title: 'Объединить товары',
    endpoint: '/admin/product-group-imports/1',
  },
]) {
  test(`${title}: processing and selected file survive navigation`, async ({
    page,
  }) => {
    const deferredRequests = createDeferredApiRequests()
    await mockCatalogApi(page, { deferredRequests })
    const status = deferredRequests.defer(endpoint)
    await page.goto(path)
    await page.locator('input[type="file"]').setInputFiles({
      name: 'import.xlsx',
      mimeType:
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
      buffer: Buffer.from('mock xlsx'),
    })
    await page.getByRole('button', { name: 'Запустить обработку' }).click()
    await status.requested
    await openProductMenu(page)
    await page
      .getByRole('link', { name: 'Список товаров', exact: true })
      .click()
    await expect(
      page.getByText('Файл обрабатывается в фоне.', { exact: false }),
    ).toHaveCount(0)
    status.release()
    await openProductMenu(page)
    await page.getByRole('link', { name: title, exact: true }).click()
    await expect(
      page.getByRole('heading', { name: 'Обработка завершена', exact: true }),
    ).toBeVisible()
    await expect(page.getByText('import.xlsx', { exact: true })).toBeVisible()
  })
}

test('import page cache is cleared after logout and a new login', async ({
  page,
}) => {
  await mockCatalogApi(page)
  await page.route('**/admin/auth/logout', (route) =>
    route.fulfill({ status: 204 }),
  )
  await page.route('**/admin/auth/login', (route) =>
    route.fulfill({ status: 204 }),
  )
  await page.goto('/products/price-status')
  await page.locator('input[type="file"]').setInputFiles({
    name: 'previous-session.xlsx',
    mimeType:
      'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
    buffer: Buffer.from('mock xlsx'),
  })
  await page.getByRole('button', { name: 'Меню пользователя' }).click()
  await page.getByRole('button', { name: 'Выйти', exact: true }).click()
  await expect(page).toHaveURL('/login')
  await page
    .getByRole('textbox', { name: 'Email', exact: true })
    .fill('staff@example.test')
  await page
    .locator('input[autocomplete="current-password"]')
    .fill('test-password')
  await page.getByRole('button', { name: 'Войти', exact: true }).click()
  await expect(page).toHaveURL('/')
  await openProductMenu(page)
  await page.getByRole('link', { name: 'Цены и статусы', exact: true }).click()
  await expect(
    page.getByRole('button', {
      name: 'Выберите или перетащите Excel в эту область',
    }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'previous-session.xlsx' }),
  ).toHaveCount(0)
})
