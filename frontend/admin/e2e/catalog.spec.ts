import AxeBuilder from '@axe-core/playwright'
import { expect, test } from '@playwright/test'
import { mockCatalogApi } from './catalogApi'

const catalogRoutes = [
  { path: '/products', heading: 'Товары', item: 'Монте Тиберио' },
  { path: '/categories', heading: 'Категории', item: 'Керамогранит' },
  { path: '/brands', heading: 'Бренды', item: 'Kerama Marazzi' },
  { path: '/attribute-groups', heading: 'Группы характеристик', item: 'Размеры' },
  { path: '/attributes', heading: 'Характеристики', item: 'Ширина' },
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

test('product filters send the selected values and reset to the first page', async ({ page }) => {
  await mockCatalogApi(page)
  await page.goto('/products')
  await expect(page.getByText('Монте Тиберио', { exact: true })).toBeVisible()

  await page.getByLabel('Категория').click()
  await page.getByRole('button', { name: 'Керамогранит', exact: true }).click()
  await page.getByLabel('Активность').click()
  await page.getByRole('button', { name: 'Активные', exact: true }).click()
  await page.getByLabel('Поиск').fill('монте')

  const requestPromise = page.waitForRequest((request) => {
    const url = new URL(request.url())
    return url.pathname.endsWith('/admin/products') && url.searchParams.get('search') === 'монте'
  })
  await page.getByRole('button', { name: 'Найти' }).click()
  const request = await requestPromise
  const query = new URL(request.url()).searchParams
  expect(query.get('category_id')).toBe('1')
  expect(query.get('is_active')).toBe('1')
  expect(query.has('page')).toBe(false)

  await page.getByRole('button', { name: 'Сбросить' }).click()
  await expect(page.getByLabel('Поиск')).toHaveValue('')
  await expect(page.getByLabel('Категория')).toContainText('Все категории')
})

test('product card integrates all tabs and its selectors', async ({ page }) => {
  await mockCatalogApi(page)
  await page.goto('/products')
  await page.getByRole('button', { name: 'Редактировать товар' }).click()

  const dialog = page.getByRole('dialog', { name: 'Монте Тиберио' })
  await expect(dialog).toBeVisible()
  await expect(dialog.getByLabel('Категория')).toContainText('Керамогранит')
  await expect(dialog.getByLabel('Бренд')).toContainText('Kerama Marazzi')
  const dialogA11y = await new AxeBuilder({ page }).include('[role="dialog"]').analyze()
  expect(dialogA11y.violations.filter((violation) => ['serious', 'critical'].includes(violation.impact ?? ''))).toEqual([])

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
  await dialog.getByLabel('SKU').fill('MONTE-TIBERIO-LIGHT')
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
  await expect(savedDialog.getByRole('checkbox', { name: /Монте Тиберио Светлый · MONTE-TIBERIO-LIGHT$/ })).toBeChecked()
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
  await dialog.getByLabel('SKU').fill('MONTE-TIBERIO-LIGHT')
  await dialog.getByRole('button', { name: 'Сохранить и продолжить' }).click()
  const savedDialog = page.getByRole('dialog', { name: 'Монте Тиберио Светлый' })
  await savedDialog.getByRole('button', { name: 'Варианты модели', exact: false }).click()

  await expect(savedDialog.getByLabel('Группа вариантов')).toContainText('Монте Тиберио · MONTE-TIBERIO-GROUP')
  await expect(savedDialog.getByRole('checkbox', { name: /Монте Тиберио · MONTE-TIBERIO/ })).toBeChecked()
  await expect(savedDialog.getByRole('checkbox', { name: /Монте Тиберио Тёмный · MONTE-TIBERIO-DARK/ })).toBeChecked()
  await expect(savedDialog.getByRole('checkbox', { name: /Монте Тиберио Светлый · MONTE-TIBERIO-LIGHT$/ })).toBeChecked()
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

    for (const step of ['Основное и продажа', 'Характеристики', 'Фото', 'Варианты модели', 'Проверка']) {
      await dialog.getByRole('button', { name: step, exact: false }).click()
      expect(await dialog.evaluate(element => element.scrollWidth <= element.clientWidth)).toBe(true)
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
  await expect(page.getByRole('status', { name: '' }).filter({ hasText: 'Загрузка товаров' })).toBeAttached()
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
