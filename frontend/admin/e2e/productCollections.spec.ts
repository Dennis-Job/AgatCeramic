import AxeBuilder from './approvedAccessibility'
import { expect, test, type Page } from './fixtures'
import { mockAdminBaseline } from './adminBaselineApi'
import { mockCatalogApi, brand, category, product } from './catalogApi'

const storyName =
  'Мраморная коллекция с длинным русским названием для ванной и просторной гостиной'
const record = {
  id: 1,
  brand_id: 1,
  name: storyName,
  slug: 'marble',
  headline: null,
  description: null,
  image_id: null,
  image: null,
  brand,
  is_active: true,
  products_count: 3,
  created_at: brand.created_at,
  updated_at: brand.updated_at,
}
const paged = (data: unknown[], page = 1, last = 1) => ({
  data,
  meta: {
    current_page: page,
    last_page: last,
    per_page: 25,
    total: last === 1 ? data.length : 26,
  },
})
async function mockCollections(page: Page) {
  await mockAdminBaseline(page, false, 'default', undefined, {
    '/admin/brands': paged([brand]),
  })
  const records = [{ ...record }]
  await page.route('**/api/v1/admin/collections**', async (route) => {
    const url = new URL(route.request().url())
    const method = route.request().method()
    if (method === 'POST') {
      const body = route.request().postDataJSON()
      const created = { ...record, ...body, id: 2, brand, products_count: 0 }
      records.push(created)
      return route.fulfill({ status: 201, json: { data: created } })
    }
    if (method === 'PATCH') {
      Object.assign(records[0]!, route.request().postDataJSON())
      return route.fulfill({ json: { data: records[0] } })
    }
    if (method === 'DELETE')
      return route.fulfill({
        status: 422,
        json: {
          error: {
            details: {
              collection: [
                'Коллекция используется товарами. Деактивируйте её.',
              ],
            },
          },
        },
      })
    const search = url.searchParams.get('search')?.toLocaleLowerCase('ru') ?? ''
    return route.fulfill({
      json: paged(
        records.filter((item) =>
          item.name.toLocaleLowerCase('ru').includes(search),
        ),
      ),
    })
  })
}
test('collection list and editor keep long content accessible at five widths', async ({
  page,
}) => {
  await mockCollections(page)
  await page.goto('/collections')
  await expect(page.getByRole('heading', { name: storyName })).toBeVisible()
  await expect(
    page.getByText('Блок не заполнен', { exact: true }),
  ).toBeVisible()
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await page.evaluate(() => document.fonts.ready)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: `.tmp/collections-review/list-${width}.png`,
      fullPage: true,
    })
    await page
      .getByRole('button', { name: `Редактировать коллекцию ${storyName}` })
      .click()
    const dialog = page.getByRole('dialog', {
      name: 'Редактирование коллекции',
    })
    await expect(
      dialog.getByRole('button', { name: 'Бренд коллекции' }),
    ).toBeDisabled()
    await expect(dialog.getByText('Россия', { exact: true })).toBeVisible()
    expect(
      await dialog.evaluate((el) => el.scrollWidth <= el.clientWidth),
    ).toBe(true)
    await dialog.screenshot({
      path: `.tmp/collections-review/dialog-${width}.png`,
    })
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    await page.keyboard.press('Escape')
    await expect(dialog).not.toBeVisible()
    await expect(
      page.getByRole('button', {
        name: `Редактировать коллекцию ${storyName}`,
      }),
    ).toBeFocused()
  }
})
test('collection search, create/edit and protected deletion have explicit feedback', async ({
  page,
  browserIssueGuard,
}) => {
  await mockCollections(page)
  browserIssueGuard.allowApiError(422, /\/admin\/collections\/1$/)
  await page.goto('/collections')
  await page.getByRole('textbox', { name: 'Поиск коллекций' }).fill('missing')
  await expect(
    page.getByText('Коллекции не найдены. Попробуйте изменить фильтры.'),
  ).toBeVisible()
  await page.getByRole('textbox', { name: 'Поиск коллекций' }).fill('')
  await page.getByRole('button', { name: 'Добавить коллекцию' }).click()
  const dialog = page.getByRole('dialog', { name: 'Новая коллекция' })
  await dialog.getByLabel('Название', { exact: false }).fill('Новая коллекция')
  await dialog.getByRole('button', { name: 'Бренд коллекции' }).click()
  await page.getByRole('button', { name: brand.name }).click()
  await dialog.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(
    page.getByText('Коллекция сохранена.', { exact: true }),
  ).toBeVisible()
  await page
    .getByRole('button', { name: `Редактировать коллекцию ${storyName}` })
    .click()
  const editor = page.getByRole('dialog', { name: 'Редактирование коллекции' })
  await editor
    .getByRole('textbox', { name: 'Заголовок блока', exact: true })
    .fill('Новый заголовок')
  await editor.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(editor).not.toBeVisible()
  await page
    .getByRole('button', { name: `Удалить коллекцию ${storyName}` })
    .click()
  await page
    .getByRole('dialog', { name: 'Удалить коллекцию?' })
    .getByRole('button', { name: 'Удалить', exact: true })
    .click()
  await expect(
    page.getByRole('alert').filter({
      hasText: 'Коллекция используется товарами. Деактивируйте её.',
    }),
  ).toBeVisible()
})
test('product retains inactive collection on brand change and explicitly clears it', async ({
  page,
}) => {
  await mockCatalogApi(page)
  const current = { ...record, is_active: false }
  const allowed = { ...category, uses_collections: true }
  await page.route('**/api/v1/admin/categories/tree', (route) =>
    route.fulfill({ json: { data: [allowed] } }),
  )
  await page.route('**/api/v1/admin/products?*', (route) =>
    route.fulfill({
      json: paged([
        {
          ...product,
          category: allowed,
          collection_id: 1,
          collection: current,
        },
      ]),
    }),
  )
  const requests: URL[] = []
  await page.route('**/api/v1/admin/collections**', (route) => {
    const url = new URL(route.request().url())
    requests.push(url)
    const id = url.searchParams.get('brand_id') === '2' ? 2 : 1
    const more = url.searchParams.get('page') === '2'
    return route.fulfill({
      json: paged(
        [
          {
            ...record,
            id: more ? 26 : 2,
            brand_id: id,
            name: more ? 'Коллекция со второй страницы' : 'Другая коллекция',
          },
        ],
        more ? 2 : 1,
        2,
      ),
    })
  })
  await page.goto('/products')
  await page
    .getByRole('button', { name: `Редактировать товар ${product.name}` })
    .click()
  const dialog = page.getByRole('dialog', { name: product.name, exact: true })
  await expect(
    dialog.getByRole('button', { name: 'Коллекция товара' }),
  ).toContainText('неактивна')
  await dialog.getByRole('button', { name: 'Бренд', exact: true }).click()
  await dialog.getByRole('button', { name: 'Italon' }).click()
  await expect(dialog.getByText(/Текущая коллекция несовместима/)).toBeVisible()
  await expect(
    dialog.getByRole('button', { name: 'Коллекция товара' }),
  ).toContainText('неактивна')
  await expect
    .poll(() => requests.at(-1)?.searchParams.get('brand_id'))
    .toBe('2')
  await dialog.getByRole('button', { name: 'Загрузить ещё коллекции' }).click()
  await dialog.getByRole('button', { name: 'Коллекция товара' }).click()
  await expect(
    dialog.getByRole('button', { name: 'Коллекция со второй страницы' }),
  ).toBeVisible()
  await dialog
    .getByRole('button', { name: 'Без коллекции', exact: true })
    .click()
  await expect(
    dialog.getByText(/Текущая коллекция несовместима/),
  ).not.toBeVisible()
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await dialog.screenshot({
      path: `.tmp/collections-review/product-${width}.png`,
    })
  }
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
})

test('category collection setting is explicit and usable at five widths', async ({
  page,
}) => {
  await mockCatalogApi(page)
  await page.goto('/categories')
  await page
    .getByRole('button', { name: `Редактировать категорию ${category.name}` })
    .click()
  const dialog = page.getByRole('dialog', { name: 'Редактирование категории' })
  const toggle = dialog.getByRole('checkbox', {
    name: 'Использовать коллекции',
  })
  await expect(toggle).not.toBeChecked()
  await dialog.getByText('Использовать коллекции', { exact: true }).click()
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await dialog
      .getByText('Использовать коллекции', { exact: true })
      .scrollIntoViewIfNeeded()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await dialog.screenshot({
      path: `.tmp/collections-review/category-${width}.png`,
    })
  }
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  const request = page.waitForRequest(
    (req) =>
      req.method() === 'PATCH' && req.url().endsWith('/admin/categories/1'),
  )
  await dialog.getByRole('button', { name: 'Сохранить', exact: true }).click()
  expect((await request).postDataJSON().uses_collections).toBe(true)
})
