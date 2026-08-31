import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'
import { attribute, mockCatalogApi, optionalSelectAttribute } from './catalogApi'

const catalogRoutes = [
  { path: '/products', apiPath: '/admin/products', heading: 'Товары', item: 'Монте Тиберио', loadingLabel: 'Загрузка товаров…' },
  { path: '/categories', apiPath: '/admin/categories/tree', heading: 'Категории', item: 'Керамогранит', loadingLabel: 'Загрузка категорий…' },
  { path: '/brands', apiPath: '/admin/brands', heading: 'Бренды', item: 'Kerama Marazzi', loadingLabel: 'Загрузка брендов…' },
  { path: '/attribute-groups', apiPath: '/admin/attribute-groups', heading: 'Группы характеристик', item: 'Размеры', loadingLabel: 'Загрузка групп характеристик…' },
  { path: '/attributes', apiPath: '/admin/attributes', heading: 'Характеристики', item: 'Ширина', loadingLabel: 'Загрузка характеристик…' },
] as const

for (const route of catalogRoutes) {
  test(`authenticated ${route.path} flow renders data without serious accessibility violations`, async ({ page }) => {
    await mockCatalogApi(page)
    await page.goto(route.path)

    await expect(page).toHaveURL(route.path)
    await expect(page.getByRole('heading', { level: 1, name: route.heading })).toBeVisible()
    await expect(page.getByText(route.item, { exact: false }).first()).toBeVisible()

    // Color tokens are reviewed visually under docs/UI_DESIGN_REVIEW.md; this suite
    // protects semantic/keyboard regressions without turning existing palette debt
    // in the shared layout into unrelated Catalog failures.
    const results = await new AxeBuilder({ page }).disableRules(['color-contrast']).analyze()
    expect(results.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''))).toEqual([])
  })
}

for (const route of catalogRoutes) {
  test(`${route.path} shows a full visible loading state without responsive overflow`, async ({ page }) => {
    await mockCatalogApi(page, { delayPath: route.apiPath })

    for (const width of [320, 640, 768, 1024, 1280]) {
      await page.setViewportSize({ width, height: 720 })
      await page.goto(route.path)

      const status = page.getByRole('status').filter({ hasText: route.loadingLabel })
      await expect(status).toBeVisible()
      const loadingBox = await status.boundingBox()
      expect(loadingBox?.height).toBeGreaterThanOrEqual(100)
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
      await expect(page.getByText(route.item, { exact: false }).first()).toBeVisible()
    }
  })
}

for (const route of [
  { path: '/products', nextItem: 'Про Стоун' },
  { path: '/brands', nextItem: 'Italon' },
  { path: '/attribute-groups', nextItem: 'Поверхность' },
  { path: '/attributes', nextItem: 'Длина' },
] as const) {
  test(`${route.path} pagination requests and renders page two`, async ({ page }) => {
    await mockCatalogApi(page)
    await page.goto(route.path)
    const requestPromise = page.waitForRequest((request) => {
      const url = new URL(request.url())
      return url.pathname.endsWith(route.path === '/attribute-groups' ? '/admin/attribute-groups' : `/admin${route.path}`)
        && url.searchParams.get('page') === '2'
    })
    await page.getByRole('button', { name: 'Следующая страница' }).click()
    await requestPromise
    await expect(page.getByText(route.nextItem, { exact: false }).first()).toBeVisible()
    await expect(page.getByLabel('Текущая страница 2 из 2')).toBeVisible()
  })
}

test('catalog route guards require an authenticated user with catalog permission', async ({ browser }) => {
  const unauthenticated = await browser.newPage()
  await mockCatalogApi(unauthenticated, { auth: 'unauthenticated' })
  await unauthenticated.goto('/products')
  await expect(unauthenticated).toHaveURL('/login')
  await unauthenticated.close()

  const forbidden = await browser.newPage()
  await mockCatalogApi(forbidden, { auth: 'forbidden' })
  await forbidden.goto('/products')
  await expect(forbidden).toHaveURL('/')
  await forbidden.close()
})

test('product filters apply dynamically and reset to the first page', async ({ page }) => {
  await mockCatalogApi(page)
  await page.goto('/products')
  await expect(page.getByText('Монте Тиберио', { exact: true })).toBeVisible()

  const categoryRequest = page.waitForRequest((request) => {
    const url = new URL(request.url())
    return url.pathname.endsWith('/admin/products') && url.searchParams.get('category_id') === '1'
  })
  await page.getByLabel('Категория').click()
  await page.getByRole('button', { name: 'Керамогранит', exact: true }).click()
  await categoryRequest

  const activityRequest = page.waitForRequest((request) => {
    const url = new URL(request.url())
    return url.pathname.endsWith('/admin/products') && url.searchParams.get('is_active') === '1'
  })
  await page.getByRole('radio', { name: 'Активные', exact: true }).locator('..').click()
  await activityRequest

  const saleRequest = page.waitForRequest((request) => {
    const url = new URL(request.url())
    return url.pathname.endsWith('/admin/products') && url.searchParams.get('is_on_sale') === '1'
  })
  await page.getByRole('radio', { name: 'На распродаже', exact: true }).locator('..').click()
  await saleRequest

  const requestPromise = page.waitForRequest((request) => {
    const url = new URL(request.url())
    return url.pathname.endsWith('/admin/products') && url.searchParams.get('search') === 'монте'
  })
  await page.getByLabel('Поиск').fill('монте')
  const request = await requestPromise
  const query = new URL(request.url()).searchParams
  expect(query.get('category_id')).toBe('1')
  expect(query.get('is_active')).toBe('1')
  expect(query.get('is_on_sale')).toBe('1')
  expect(query.has('page')).toBe(false)
  await expect(page.getByRole('status').filter({ hasText: 'Показано' })).toBeVisible()
  await expect(page.getByText('Поиск и фильтры применяются автоматически.')).toBeVisible()
  await expect(page.getByRole('search').getByText('Остаток', { exact: true })).toHaveCount(0)
  await expect(page.getByRole('search').getByText('Цена от', { exact: true })).toHaveCount(0)

  const resetRequest = page.waitForRequest((request) => {
    const url = new URL(request.url())
    return url.pathname.endsWith('/admin/products') && !url.searchParams.has('search') && !url.searchParams.has('category_id') && !url.searchParams.has('is_active') && !url.searchParams.has('is_on_sale')
  })
  await page.getByRole('button', { name: 'Сбросить' }).click()
  await resetRequest
  await expect(page.getByLabel('Поиск')).toHaveValue('')
  await expect(page.getByLabel('Категория')).toContainText('Все категории')
  await expect(page.getByRole('radio', { name: 'Все' }).first()).toBeChecked()

  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 720 })
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    await expect(page.getByRole('search')).toBeVisible()
    await expect(page.getByRole('group', { name: 'Активность' })).toBeVisible()
    await expect(page.getByRole('group', { name: 'Распродажа' })).toBeVisible()
  }
})

test('product table sorts through the API and remains usable at supported widths', async ({ page }) => {
  await mockCatalogApi(page)
  const initialRequest = page.waitForRequest((request) => new URL(request.url()).pathname.endsWith('/admin/products'))
  await page.goto('/products')
  const initialQuery = new URL((await initialRequest).url()).searchParams
  expect(initialQuery.get('sort')).toBe('created_at')
  expect(initialQuery.get('direction')).toBe('desc')

  const createdHeader = page.getByRole('columnheader', { name: /Создан/ })
  await expect(createdHeader).toHaveAttribute('aria-sort', 'descending')
  const ascendingCreatedRequest = page.waitForRequest((request) => {
    const query = new URL(request.url()).searchParams
    return query.get('sort') === 'created_at' && query.get('direction') === 'asc'
  })
  await createdHeader.getByRole('button', { name: 'Создан' }).click()
  await ascendingCreatedRequest
  await expect(createdHeader).toHaveAttribute('aria-sort', 'ascending')

  const nameHeader = page.getByRole('columnheader', { name: /Наименование/ })
  const nameRequest = page.waitForRequest((request) => {
    const query = new URL(request.url()).searchParams
    return query.get('sort') === 'name' && query.get('direction') === 'asc'
  })
  await nameHeader.getByRole('button', { name: 'Наименование' }).click()
  await nameRequest
  await expect(nameHeader).toHaveAttribute('aria-sort', 'ascending')

  await page.route('**/api/v1/admin/products?*', async (route) => {
    const query = new URL(route.request().url()).searchParams
    if (query.get('sort') === 'updated_at') {
      await route.fulfill({ status: 500, json: { error: { message: 'Сортировка временно недоступна.', details: {} } } })
      return
    }
    await route.fallback()
  })
  await page.getByRole('columnheader', { name: /Изменён/ }).getByRole('button', { name: 'Изменён' }).click()
  await expect(page.getByRole('alert')).toContainText('Сортировка временно недоступна.')
  await expect(nameHeader).toHaveAttribute('aria-sort', 'ascending')
  await expect(page.getByRole('columnheader', { name: /Изменён/ })).toHaveAttribute('aria-sort', 'none')

  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 720 })
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    const tableRegion = page.getByRole('region', { name: 'Таблица товаров' })
    const productName = page.getByText('Монте Тиберио', { exact: true }).first()
    const editAction = page.getByRole('button', { name: 'Редактировать товар Монте Тиберио' })
    await expect(tableRegion).toBeVisible()
    await tableRegion.evaluate((element) => { element.scrollLeft = 0 })
    const regionBox = await tableRegion.boundingBox()
    const nameBox = await productName.boundingBox()
    const initialActionBox = await editAction.boundingBox()
    expect(nameBox!.x + nameBox!.width).toBeLessThanOrEqual(regionBox!.x + regionBox!.width)
    expect(initialActionBox!.x).toBeGreaterThanOrEqual(regionBox!.x + regionBox!.width)
    await tableRegion.evaluate((element) => { element.scrollLeft = element.scrollWidth })
    const scrolledActionBox = await editAction.boundingBox()
    expect(scrolledActionBox!.x).toBeGreaterThanOrEqual(regionBox!.x)
    expect(scrolledActionBox!.x + scrolledActionBox!.width).toBeLessThanOrEqual(regionBox!.x + regionBox!.width)
  }
})

test('product table limits long displayed names to 50 characters without losing the full name', async ({ page }) => {
  const longName = 'Керамогранит коллекционный полированный с декоративной фактурой белого мрамора'
  const preview = `${Array.from(longName).slice(0, 49).join('')}…`
  await mockCatalogApi(page, { sourceProduct: { name: longName } })
  await page.goto('/products')

  const productName = page.locator('tbody tr').first().locator('td').first().locator('p').first()
  const visiblePreview = productName.getByTestId('product-name-preview')
  await expect(visiblePreview).toHaveText(preview)
  await expect(visiblePreview).toHaveAttribute('aria-hidden', 'true')
  await expect(productName).toHaveAttribute('title', longName)
  await expect(productName.locator('.sr-only')).toHaveText(longName)
  expect(Array.from(await visiblePreview.innerText())).toHaveLength(50)

  const accessibility = await new AxeBuilder({ page }).include('[aria-label="Таблица товаров"]').disableRules(['color-contrast']).analyze()
  expect(accessibility.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''))).toEqual([])
})

test('product table displays article numbers in a dedicated column', async ({ page }) => {
  await mockCatalogApi(page)
  await page.goto('/products')

  await expect(page.getByRole('columnheader', { name: 'Артикул', exact: true })).toBeVisible()
  const productRow = page.locator('tbody tr').filter({ hasText: 'Монте Тиберио' }).first()
  await expect(productRow.getByRole('cell', { name: 'KM-100', exact: true })).toBeVisible()
})

test('product card integrates all tabs and its selectors', async ({ page }) => {
  await mockCatalogApi(page)
  await page.goto('/products')
  await page.getByRole('button', { name: 'Редактировать товар' }).click()

  const dialog = page.getByRole('dialog', { name: 'Монте Тиберио' })
  await expect(dialog).toBeVisible()
  await expect(dialog.getByLabel('Категория')).toContainText('Керамогранит')
  await expect(dialog.getByLabel('Бренд')).toContainText('Kerama Marazzi')
  await dialog.getByRole('button', { name: 'Бренд', exact: true }).click()
  await expect(dialog.locator('.max-h-64 button')).toHaveText(['Без бренда', 'Italon', 'Kerama Marazzi'])
  await dialog.getByRole('button', { name: 'Бренд', exact: true }).click()
  await dialog.getByRole('button', { name: 'Единица продажи', exact: true }).click()
  await expect(dialog.locator('.max-h-64 button')).toHaveText(['Квадратный метр', 'Килограмм', 'Комплект', 'Литр', 'Погонный метр', 'Упаковка', 'Штука'])
  await dialog.getByRole('button', { name: 'Единица продажи', exact: true }).click()
  await dialog.getByRole('button', { name: 'Проверка', exact: false }).click()
  await expect(dialog.getByText('Не на распродаже', { exact: true })).toBeVisible()
  const dialogA11y = await new AxeBuilder({ page }).include('[role="dialog"]').analyze()
  expect(dialogA11y.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''))).toEqual([])

  await dialog.getByRole('button', { name: 'Характеристики', exact: false }).click()
  await expect(dialog.getByText('Характеристики принадлежат только этой позиции.')).toBeVisible()
  await expect(dialog.getByText('общая для группы')).toHaveCount(0)

  const expectations = [
    ['Характеристики', 'Ширина'],
    ['Фото', 'Фото этой позиции'],
    ['Варианты модели', 'Объедините самостоятельные товары'],
    ['Проверка', 'Сопутствующие товары'],
    ['Основное и продажа', 'Сохранить и продолжить'],
  ] as const

  for (const [tab, expectedText] of expectations) {
    await dialog.getByRole('button', { name: tab, exact: false }).click()
    await expect(dialog.getByText(expectedText, { exact: false }).first()).toBeVisible()
  }

  await page.keyboard.press('Escape')
  await expect(dialog).toBeHidden()
  await expect(page.getByRole('button', { name: 'Редактировать товар' })).toBeFocused()
})

for (const width of [320, 640, 768, 1024, 1280]) {
  test(`grouped product identifies product-specific and shared characteristics at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 800 })
    await mockCatalogApi(page, { sourceProductInGroup: true, includeSharedAttribute: true })
    await page.goto('/products')
    await page.getByRole('button', { name: 'Редактировать товар Монте Тиберио' }).click()

    const dialog = page.getByRole('dialog', { name: 'Монте Тиберио' })
    await dialog.getByRole('button', { name: 'Характеристики', exact: false }).click()

    await expect(dialog.getByText('Общие характеристики автоматически применяются ко всем товарам группы.')).toBeVisible()
    await expect(dialog.getByLabel('Ширина, только для этой позиции', { exact: true })).toHaveValue('60.00')
    await expect(dialog.getByLabel('Тип поверхности и декоративной текстуры, общая для группы', { exact: true })).toHaveValue('Матовая')
    expect(await dialog.evaluate(element => element.scrollWidth <= element.clientWidth)).toBe(true)

    const dialogA11y = await new AxeBuilder({ page }).include('[role="dialog"]').analyze()
    expect(dialogA11y.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''))).toEqual([])
  })
}

for (const width of [320, 640, 768, 1024, 1280]) {
  test(`optional select characteristic can be cleared and saved empty at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 800 })
    await mockCatalogApi(page, { includeOptionalSelectAttribute: true })
    await page.goto('/products')
    await page.getByRole('button', { name: 'Редактировать товар Монте Тиберио' }).click()

    const dialog = page.getByRole('dialog', { name: 'Монте Тиберио' })
    await dialog.getByRole('button', { name: 'Характеристики', exact: false }).click()
    const dimensionsSection = dialog.getByRole('region', { name: 'Размеры' })
    const ungroupedSection = dialog.getByRole('region', { name: 'Без группы' })
    await expect(dimensionsSection.getByLabel('Ширина', { exact: true })).toHaveValue('60.00')
    await expect(ungroupedSection.getByRole('button', { name: 'Рисунок', exact: true })).toContainText('Камень')

    const dialogA11y = await new AxeBuilder({ page }).include('[role="dialog"]').analyze()
    expect(dialogA11y.violations.filter(violation => ['serious', 'critical'].includes(violation.impact ?? ''))).toEqual([])

    await dialog.getByRole('button', { name: 'Очистить выбор: Рисунок', exact: true }).click()
    await expect(dialog.getByRole('button', { name: 'Рисунок', exact: true })).toBeFocused()
    await expect(dialog.getByRole('button', { name: 'Рисунок', exact: true })).toContainText('Выберите значение')
    await expect(dialog.getByRole('button', { name: 'Очистить выбор: Рисунок', exact: true })).toHaveCount(0)
    expect(await dialog.evaluate(element => element.scrollWidth <= element.clientWidth)).toBe(true)

    const requestPromise = page.waitForRequest(request => request.method() === 'PUT' && new URL(request.url()).pathname.endsWith('/admin/products/1/attributes'))
    await dialog.getByRole('button', { name: 'Сохранить и продолжить' }).click()
    const payload = (await requestPromise).postDataJSON() as { attributes: Array<{ attribute_id: number; value: unknown }> }
    expect(payload.attributes).toContainEqual({ attribute_id: attribute.id, value: 60 })
    expect(payload.attributes.some(item => item.attribute_id === optionalSelectAttribute.id)).toBe(false)
  })
}

test('sale flag can be set while creating a product and is shown in the product list', async ({ page }) => {
  await mockCatalogApi(page, { includeOptionalSelectAttribute: true })
  await page.goto('/products')
  await page.getByRole('button', { name: 'Добавить товар' }).click()

  const dialog = page.getByRole('dialog', { name: 'Новый товар' })
  await dialog.getByLabel('Название').fill('Распродажный керамогранит')
  await dialog.getByLabel('Категория').click()
  await page.getByRole('button', { name: 'Керамогранит', exact: true }).click()
  await expect(dialog.getByLabel('SKU')).toHaveText('Будет назначен автоматически после сохранения')
  await dialog.getByText('Распродажа', { exact: true }).click()
  await expect(dialog.getByRole('checkbox', { name: 'Товар участвует в распродаже' })).toBeChecked()
  await dialog.getByRole('button', { name: 'Сохранить и продолжить' }).click()

  const savedDialog = page.getByRole('dialog', { name: 'Распродажный керамогранит' })
  await expect(savedDialog.getByRole('region', { name: 'Размеры' }).getByLabel('Ширина', { exact: true })).toBeVisible()
  await expect(savedDialog.getByRole('region', { name: 'Без группы' }).getByRole('button', { name: 'Рисунок', exact: true })).toBeVisible()
  await savedDialog.getByRole('button', { name: 'Проверка', exact: false }).click()
  await expect(savedDialog.getByText('Распродажа', { exact: true })).toBeVisible()
  const productRow = page.locator('tbody tr').filter({ hasText: 'Распродажный керамогранит' })
  await expect(productRow.getByText('Распродажа', { exact: true })).toBeVisible()
})

test('published product can be hidden without changing its stock', async ({ page }) => {
  await mockCatalogApi(page)
  await page.goto('/products')

  const productRow = page.locator('tbody tr').filter({ hasText: 'Монте Тиберио' }).first()
  await expect(productRow.getByText('Активен', { exact: true })).toBeVisible()
  await expect(productRow.getByText('12', { exact: true })).toBeVisible()
  await productRow.getByRole('button', { name: 'Редактировать товар Монте Тиберио' }).click()

  const dialog = page.getByRole('dialog', { name: 'Монте Тиберио' })
  await dialog.getByRole('button', { name: 'Проверка', exact: false }).click()
  await dialog.getByRole('button', { name: 'Скрыть товар' }).click()

  await expect(dialog.getByRole('status')).toContainText('Товар скрыт и перемещён в черновики')
  await expect(dialog.getByText('Скрыт', { exact: true })).toBeVisible()
  await expect(dialog.getByRole('button', { name: 'Опубликовать товар' })).toBeVisible()
  await expect(productRow.getByText('Скрыт', { exact: true })).toBeVisible()
  await expect(productRow.getByText('12', { exact: true })).toBeVisible()
})

test('product list thumbnail updates immediately when the primary image changes', async ({ page }) => {
  await mockCatalogApi(page, {
    initialProductImages: [
      { id: 1, url: '/first-product-image.jpg', alt: 'Первое фото', is_primary: true, sort_order: 0 },
      { id: 2, url: '/second-product-image.jpg', alt: 'Второе фото', is_primary: false, sort_order: 1 },
    ],
  })
  await page.goto('/products')

  const productRow = page.locator('tbody tr').filter({ hasText: 'Монте Тиберио' }).first()
  await expect(productRow.locator('img')).toHaveAttribute('src', '/first-product-image.jpg')
  await page.getByRole('button', { name: 'Редактировать товар Монте Тиберио' }).click()
  const dialog = page.getByRole('dialog', { name: 'Монте Тиберио' })
  await dialog.getByRole('button', { name: 'Фото', exact: false }).click()
  await dialog.getByRole('button', { name: 'Переместить изображение 1 ниже' }).click()
  await dialog.getByRole('button', { name: 'Закрыть карточку товара' }).click()

  await expect(productRow.locator('img')).toHaveAttribute('src', '/second-product-image.jpg')
})

test('new product thumbnail appears after its first image is uploaded and the editor closes', async ({ page }) => {
  await mockCatalogApi(page)
  await page.goto('/products')
  await page.getByRole('button', { name: 'Добавить товар' }).click()

  const dialog = page.getByRole('dialog', { name: 'Новый товар' })
  await dialog.getByLabel('Название').fill('Новый керамогранит')
  await dialog.getByLabel('Категория').click()
  await page.getByRole('button', { name: 'Керамогранит', exact: true }).click()
  await expect(dialog.getByLabel('SKU')).toHaveText('Будет назначен автоматически после сохранения')
  await dialog.getByRole('button', { name: 'Сохранить и продолжить' }).click()

  const savedDialog = page.getByRole('dialog', { name: 'Новый керамогранит' })
  await savedDialog.getByRole('button', { name: 'Сохранить и продолжить' }).click()
  await savedDialog.getByLabel('Файл изображения').setInputFiles({ name: 'new-tile.jpg', mimeType: 'image/jpeg', buffer: Buffer.from('test image') })
  await savedDialog.getByRole('button', { name: 'Загрузить' }).click()
  await savedDialog.getByRole('button', { name: 'Закрыть карточку товара' }).click()

  const newProductRow = page.locator('tbody tr').filter({ hasText: 'Новый керамогранит' })
  await expect(newProductRow.locator('img')).toHaveAttribute('src', '/uploaded-3-1.jpg')
})

test('creating a similar product requires a different name and generates a clean slug', async ({ page }) => {
  const longSourceSku = 'MONTETIBERIOEXTRALONGSOURCEIDENTIFIERWITHOUTSEPARATORS1234567890'
  await page.setViewportSize({ width: 320, height: 800 })
  await mockCatalogApi(page, { sourceProduct: { sku: longSourceSku } })
  await page.goto('/products')
  await page.getByRole('button', { name: 'Создать похожий товар Монте Тиберио' }).click()

  const dialog = page.getByRole('dialog', { name: 'Новый товар' })
  const nameInput = dialog.getByLabel('Название')
  const slugInput = dialog.getByLabel('URL (slug)')

  await expect(nameInput).toHaveValue('')
  await expect(slugInput).toHaveValue('')
  await expect(dialog.getByText('Укажите название, отличающееся от «Монте Тиберио».')).toBeVisible()
  await expect(dialog.getByRole('status')).toContainText('URL сформируется из названия')

  await nameInput.fill(' МОНТЕ  ТИБЕРИО ')
  await dialog.getByRole('button', { name: 'Сохранить и продолжить' }).click()
  await expect(dialog.getByRole('alert')).toContainText('Название должно отличаться от исходного товара')

  await nameInput.fill('Монте Тиберио Светлый')
  await expect(slugInput).toHaveValue('monte-tiberio-svetlyy')
  await expect(dialog.getByRole('alert')).toHaveCount(0)

  await dialog.getByRole('button', { name: 'Сохранить и продолжить' }).click()
  const savedDialog = page.getByRole('dialog', { name: 'Монте Тиберио Светлый' })
  await expect(savedDialog.getByLabel('Ширина')).toHaveValue('60.00')
  await savedDialog.getByRole('button', { name: 'Варианты модели', exact: false }).click()
  await expect(savedDialog.getByRole('checkbox', { name: new RegExp(`Монте Тиберио · ${longSourceSku}$`) })).toBeChecked()
  await expect(savedDialog.getByRole('checkbox', { name: /Монте Тиберио Светлый · 01000001$/ })).toBeChecked()
  const copyStatus = savedDialog.getByRole('status').filter({ hasText: 'Исходный товар' })
  await expect(copyStatus).toContainText(longSourceSku)
  await expect(savedDialog.getByLabel('Группа вариантов')).toContainText('Новая группа')
  expect(await savedDialog.evaluate(element => element.scrollWidth <= element.clientWidth)).toBe(true)
  expect(await copyStatus.evaluate(element => element.scrollWidth <= element.clientWidth)).toBe(true)
})

test('a similar product is prepared for the source product existing group', async ({ page }) => {
  await mockCatalogApi(page, { sourceProductInGroup: true })
  await page.goto('/products')
  await page.getByRole('button', { name: 'Создать похожий товар Монте Тиберио' }).click()

  const dialog = page.getByRole('dialog', { name: 'Новый товар' })
  await dialog.getByLabel('Название').fill('Монте Тиберио Светлый')
  await dialog.getByRole('button', { name: 'Сохранить и продолжить' }).click()
  const savedDialog = page.getByRole('dialog', { name: 'Монте Тиберио Светлый' })
  await savedDialog.getByRole('button', { name: 'Варианты модели', exact: false }).click()

  await expect(savedDialog.getByLabel('Группа вариантов')).toContainText('Монте Тиберио · MONTE-TIBERIO-GROUP')
  await expect(savedDialog.getByRole('checkbox', { name: /Монте Тиберио · MONTE-TIBERIO/ })).toBeChecked()
  await expect(savedDialog.getByRole('checkbox', { name: /Монте Тиберио Тёмный · MONTE-TIBERIO-DARK/ })).toBeChecked()
  await expect(savedDialog.getByRole('checkbox', { name: /Монте Тиберио Светлый · 01000001$/ })).toBeChecked()
  await expect(savedDialog.getByRole('status').filter({ hasText: 'Исходный товар' })).toContainText('подготовлен для добавления в существующую группу')
})

test('dialog stays open when text selection leaves the panel and still closes on an intentional backdrop click', async ({ page }) => {
  await mockCatalogApi(page)
  await page.goto('/products')
  await page.getByRole('button', { name: 'Редактировать товар' }).click()

  const dialog = page.getByRole('dialog', { name: 'Монте Тиберио' })
  const nameInput = dialog.getByLabel('Название')
  const inputBox = await nameInput.boundingBox()
  expect(inputBox).not.toBeNull()

  await page.mouse.move(inputBox!.x + inputBox!.width / 2, inputBox!.y + inputBox!.height / 2)
  await page.mouse.down()
  await page.mouse.move(5, 5)
  await page.mouse.up()

  await expect(dialog).toBeVisible()
  await expect(nameInput).toHaveValue('Монте Тиберио')

  await page.mouse.click(5, 5)
  await expect(dialog).toBeHidden()
})

for (const width of [320, 640, 768, 1024, 1280]) {
  test(`product editor remains usable at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 800 })
    await mockCatalogApi(page)
    await page.goto('/products')
    await page.getByRole('button', { name: 'Редактировать товар' }).click()
    const dialog = page.getByRole('dialog', { name: 'Монте Тиберио' })
    const editorBody = dialog.getByTestId('product-editor-body')
    const editorFooter = dialog.getByTestId('product-editor-footer')

    await expect(editorFooter.getByRole('button', { name: 'Сохранить и продолжить' })).toBeVisible()
    if (width === 320) {
      const footerTop = await editorFooter.evaluate(element => element.getBoundingClientRect().top)
      expect(await editorBody.evaluate(element => element.scrollHeight > element.clientHeight)).toBe(true)
      await editorBody.evaluate(element => { element.scrollTop = element.scrollHeight })
      expect(await editorBody.evaluate(element => element.scrollTop)).toBeGreaterThan(0)
      expect(await editorFooter.evaluate(element => element.getBoundingClientRect().top)).toBe(footerTop)
    }

    for (const step of ['Основное и продажа', 'Характеристики', 'Фото', 'Варианты модели', 'Проверка']) {
      await dialog.getByRole('button', { name: step, exact: false }).click()
      expect(await dialog.evaluate(element => element.scrollWidth <= element.clientWidth)).toBe(true)
      expect(await editorFooter.evaluate(element => element.scrollWidth <= element.clientWidth)).toBe(true)
      for (const button of await editorFooter.getByRole('button').all()) await expect(button).toBeVisible()
      if (step === 'Фото') {
        const longFileName = 'очень-длинное-название-фотографии-керамогранита-с-ракурсом-и-деталями-поверхности.jpg'
        await expect(dialog.getByText('Допустимые форматы: JPG, PNG или WebP. Максимальный размер — 10 МБ.')).toBeVisible()
        await expect(dialog.getByRole('button', { name: 'Выбрать файл' })).toBeVisible()
        const uploadButton = dialog.getByRole('button', { name: 'Загрузить' })
        await expect(uploadButton).toBeDisabled()
        await expect(dialog.getByLabel('Alt-текст')).toHaveCount(0)
        await dialog.getByLabel('Файл изображения').setInputFiles({ name: longFileName, mimeType: 'image/jpeg', buffer: Buffer.from('test image') })
        await expect(dialog.locator('output')).toHaveText(longFileName)
        expect(await dialog.locator('output').evaluate(element => element.scrollWidth <= element.clientWidth)).toBe(true)
        await expect(uploadButton).toBeEnabled()
        await uploadButton.click()
        await expect(dialog.locator('output')).toHaveText('Файл не выбран')
        await expect(uploadButton).toBeDisabled()
      }
      if (step === 'Проверка') {
        await expect(dialog.getByRole('heading', { name: 'Информация о товаре' })).toBeVisible()
        await expect(dialog.getByRole('button', { name: 'Добавить' })).toBeVisible()
        expect(await dialog.locator('#product-review').evaluate(element => {
          const summary = element.querySelector('#product-review-summary')!.getBoundingClientRect()
          const relations = element.querySelector('#product-review-relations')!.getBoundingClientRect()
          return relations.top >= summary.bottom
        })).toBe(true)
      }
    }

    await expect(dialog.getByRole('heading', { name: 'Сопутствующие товары' })).toBeVisible()
  })
}

test('categories, brands, groups, and attributes expose their dialog and selector flows', async ({ page }) => {
  await mockCatalogApi(page)

  await page.goto('/categories')
  await page.getByRole('button', { name: 'Добавить категорию' }).click()
  let dialog = page.getByRole('dialog', { name: 'Новая категория' })
  await expect(dialog.getByRole('button', { name: 'Родительская категория' })).toContainText('Без родителя')
  await dialog.getByRole('button', { name: 'Закрыть окно категории' }).click()
  await page.getByRole('button', { name: 'Настроить характеристики категории Керамогранит' }).click()
  dialog = page.getByRole('dialog', { name: 'Характеристики: Керамогранит' })
  await expect(dialog.getByRole('heading', { name: 'Размеры', exact: true })).toBeVisible()
  await dialog.getByRole('button', { name: 'Закрыть окно характеристик категории' }).click()

  await page.goto('/brands')
  await page.getByRole('button', { name: 'Добавить бренд' }).click()
  dialog = page.getByRole('dialog', { name: 'Новый бренд' })
  await dialog.getByLabel('Страна происхождения').click()
  await expect(dialog.getByLabel('Поиск: Страна происхождения')).toBeFocused()
  await dialog.getByLabel('Поиск: Страна происхождения').fill('Россия')
  await dialog.getByRole('button', { name: 'Россия', exact: true }).click()
  await expect(dialog.getByLabel('Страна происхождения')).toContainText('Россия')

  await page.goto('/attribute-groups')
  await page.getByRole('button', { name: 'Добавить группу' }).click()
  await expect(page.getByRole('dialog', { name: 'Новая группа характеристик' })).toBeVisible()

  await page.goto('/attributes')
  await page.getByRole('button', { name: 'Добавить', exact: true }).click()
  dialog = page.getByRole('dialog', { name: 'Новая характеристика' })
  await dialog.getByLabel('Группа характеристики').click()
  await dialog.getByRole('button', { name: 'Размеры', exact: true }).click()
  await dialog.getByLabel('Тип характеристики').click()
  await dialog.getByRole('button', { name: 'Список', exact: true }).click()
  await expect(dialog.getByRole('heading', { name: 'Варианты' })).toBeVisible()
  const dialogA11y = await new AxeBuilder({ page }).include('[role="dialog"]').analyze()
  expect(dialogA11y.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''))).toEqual([])
})

test('catalog exposes loading, error, and empty states', async ({ page }) => {
  await mockCatalogApi(page, { delayPath: '/admin/products' })
  await page.goto('/products')
  await expect(page.getByRole('status').filter({ hasText: 'Загрузка товаров' })).toBeVisible()
  await expect(page.getByText('Товары не найдены.')).toBeHidden()
  await expect(page.getByRole('status').filter({ hasText: 'Загрузка товаров' })).toBeHidden()
  await expect(page.getByText('Монте Тиберио', { exact: true })).toBeVisible()

  const emptyPage = await page.context().newPage()
  await mockCatalogApi(emptyPage, { emptyPath: '/admin/products' })
  await emptyPage.goto('/products')
  await expect(emptyPage.getByText('Товары не найдены.')).toBeVisible()
  await expect(emptyPage.getByRole('alert')).toHaveCount(0)
  await emptyPage.close()

  const errorPage = await page.context().newPage()
  await mockCatalogApi(errorPage, { errorPath: '/admin/brands' })
  await errorPage.goto('/brands')
  await expect(errorPage.getByRole('alert')).toContainText('Тестовая ошибка каталога')
  await errorPage.close()
})
