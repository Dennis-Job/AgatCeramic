import type { Page } from '@playwright/test'
import { attribute, mockCatalogApi, sourceProductGroup } from './catalogApi'
import { createDeferredApiRequests } from './deferredApi'
import { expect, test } from './fixtures'

async function openEditor(page: Page) {
  await page
    .getByRole('button', { name: 'Редактировать товар Монте Тиберио' })
    .click()
  return page.getByRole('dialog', { name: 'Монте Тиберио' })
}

test('requests section data only when needed and reloads after reopening', async ({
  page,
}) => {
  await mockCatalogApi(page)
  await page.goto('/products')
  const requested: string[] = []
  page.on('request', (request) => {
    const path = new URL(request.url()).pathname.replace('/api/v1/admin/', '')
    if (/^(product-groups|categories\/1\/|products\/1\/)/.test(path))
      requested.push(path)
  })
  const dialog = await openEditor(page)
  const step = (name: string) => dialog.getByRole('button', { name }).click()
  await expect(
    dialog.getByRole('button', { name: 'Сохранить и продолжить' }),
  ).toBeVisible()
  expect(requested).toEqual([])

  await step('Характеристики')
  const width = dialog.getByRole('spinbutton', { name: /Ширина/ })
  await expect(width).toBeVisible()
  await width.fill('61.5')
  expect(requested.toSorted()).toEqual([
    'categories/1/attribute-groups',
    'categories/1/attributes',
    'products/1/attributes',
  ])

  await step('Фото')
  await expect(dialog.getByText('Фото этой позиции')).toBeVisible()
  await step('Проверка')
  await expect(
    dialog.getByText('Сопутствующие и рекомендуемые товары'),
  ).toBeVisible()
  await step('Характеристики')
  await expect(width).toHaveValue('61.5')
  expect(requested.slice(3)).toEqual([
    'products/1/images',
    'products/1/relations',
  ])

  await dialog.getByRole('button', { name: 'Закрыть карточку товара' }).click()
  await openEditor(page)
  await step('Фото')
  await expect(dialog.getByText('Фото этой позиции')).toBeVisible()
  expect(requested.slice(5)).toEqual(['products/1/images'])
})

test('section failure leaves other steps usable and retries the failed request', async ({
  page,
  browserIssueGuard,
}) => {
  await mockCatalogApi(page)
  browserIssueGuard.allowApiError(503, '/api/v1/admin/products/1/images')
  await page.route(
    '**/api/v1/admin/products/1/images**',
    (route) =>
      route.fulfill({
        status: 503,
        json: { error: { message: 'Фото недоступны' } },
      }),
    { times: 1 },
  )
  await page.goto('/products')
  const dialog = await openEditor(page)
  await dialog.getByRole('button', { name: 'Фото' }).click()
  await expect(dialog.getByRole('alert')).toContainText('Фото недоступны')
  await expect(dialog.getByText('Фото этой позиции')).toHaveCount(0)
  await dialog.getByRole('button', { name: 'Основное и продажа' }).click()
  await expect(dialog.getByRole('alert')).toHaveCount(0)
  await dialog.getByRole('button', { name: 'Фото' }).click()
  await dialog.getByRole('button', { name: 'Повторить загрузку' }).click()
  await expect(dialog.getByText('Фото этой позиции')).toBeVisible()
  const steps = dialog.getByRole('navigation', {
    name: 'Этапы карточки товара',
  })
  await expect(steps.getByRole('button', { name: 'Фото' })).toBeFocused()
})

test('a late response cannot replace drafts after reopening the same product', async ({
  page,
}) => {
  await mockCatalogApi(page)
  const deferred = createDeferredApiRequests()
  const path = '/admin/products/1/attributes'
  const old = deferred.defer(path)
  await page.route(
    `**/api/v1${path}`,
    async (route) => {
      await deferred.wait(path)
      await route.fulfill({
        json: {
          data: [{ attribute_id: attribute.id, attribute, value: '98.00' }],
        },
      })
    },
    { times: 1 },
  )
  await page.goto('/products')
  const dialog = await openEditor(page)
  const step = (name: string) => dialog.getByRole('button', { name }).click()
  await step('Характеристики')
  await old.requested
  await expect(dialog.getByRole('status')).toContainText(
    'Загрузка характеристик',
  )
  await expect(
    dialog.getByRole('button', { name: 'Сохранить и продолжить' }),
  ).toBeDisabled()
  await dialog.getByRole('button', { name: 'Закрыть карточку товара' }).click()
  await openEditor(page)
  await step('Характеристики')
  const width = dialog.getByRole('spinbutton', { name: /Ширина/ })
  await width.fill('61.5')
  const late = page.waitForResponse((response) =>
    new URL(response.url()).pathname.endsWith(path),
  )
  old.release()
  await (await late).finished()
  await step('Фото')
  await expect(dialog.getByText('Фото этой позиции')).toBeVisible()
  await step('Характеристики')
  await expect(width).toHaveValue('61.5')
})

test('search keeps the current group and loads another group only after selection', async ({
  page,
}) => {
  await mockCatalogApi(page, { sourceProductInGroup: true })
  const other = { id: 9, name: 'Новая коллекция', code: 'OTHER-009' }
  await page.route('**/api/v1/admin/product-groups/lookup?**', (route) => {
    const search = new URL(route.request().url()).searchParams.get('search')
    return route.fulfill({
      json: { data: search === 'OTHER-009' ? [other] : [] },
    })
  })
  await page.route('**/api/v1/admin/product-groups/9', (route) =>
    route.fulfill({
      json: {
        data: {
          ...sourceProductGroup,
          ...other,
          products: [sourceProductGroup.products[1]],
        },
      },
    }),
  )
  const requested: string[] = []
  page.on('request', (request) => {
    const { pathname, search } = new URL(request.url())
    if (pathname.includes('/product-groups'))
      requested.push(pathname.split('/admin/')[1] + search)
  })
  await page.goto('/products')
  const dialog = await openEditor(page)
  await dialog.getByRole('button', { name: 'Варианты модели' }).click()
  const selector = dialog.getByRole('button', {
    name: 'Группа товаров',
    exact: true,
  })
  await expect(selector).toContainText('MONTE-TIBERIO-GROUP')
  expect(requested).toEqual(['product-groups/1'])

  await selector.click()
  const menu = page.getByRole('group', { name: 'Группа товаров: варианты' })
  await expect(menu.getByRole('status')).toContainText('Группы не найдены')
  await expect(
    menu.getByRole('button', { name: /MONTE-TIBERIO-GROUP/ }),
  ).toBeVisible()
  await menu.getByRole('searchbox').fill('OTHER-009')
  const result = menu.getByRole('button', { name: /OTHER-009/ })
  await expect(result).toBeVisible()
  expect(requested).toEqual([
    'product-groups/1',
    'product-groups/lookup?search=',
    'product-groups/lookup?search=OTHER-009',
  ])
  await result.click()
  await expect(selector).toContainText('OTHER-009')
  await expect(selector).toBeFocused()
  expect(requested.at(-1)).toBe('product-groups/9')
  await selector.click()
  await expect(menu.getByRole('status')).toContainText('Группы не найдены')
  await expect(menu.getByRole('button', { name: /OTHER-009/ })).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(selector).toBeFocused()
})

test('reopening after removing a group uses the refreshed product membership', async ({
  page,
}) => {
  await mockCatalogApi(page, { sourceProductInGroup: true })
  let detailsRequests = 0
  page.on('request', (request) => {
    if (
      request.method() === 'GET' &&
      request.url().endsWith('/product-groups/1')
    )
      detailsRequests++
  })
  await page.goto('/products')
  const dialog = await openEditor(page)
  const variants = dialog.getByRole('button', { name: 'Варианты модели' })
  const selector = dialog.getByRole('button', {
    name: 'Группа товаров',
    exact: true,
  })
  await variants.click()
  await expect(selector).toContainText('MONTE-TIBERIO-GROUP')
  await dialog
    .getByRole('button', { name: 'Удалить группу', exact: true })
    .click()
  await page
    .getByRole('dialog', { name: 'Удалить группу вариантов?' })
    .getByRole('button', { name: 'Удалить группу', exact: true })
    .click()
  await expect(selector).toContainText('Новая группа')
  await dialog.getByRole('button', { name: 'Закрыть карточку товара' }).click()
  await openEditor(page)
  await variants.click()
  await expect(selector).toContainText('Новая группа')
  expect(detailsRequests).toBe(1)
})
