import type { Page } from '@playwright/test'
import AxeBuilder from './approvedAccessibility'
import { expect, test } from './fixtures'
import { mockCatalogApi, product } from './catalogApi'

const found = [
  { ...product, id: 2, name: 'Смеситель', sku: '6000034' },
  { ...product, id: 3, name: 'Клей', sku: '5000033' },
]
const existing = {
  ...product,
  id: 8,
  name: 'Ранее выбранный товар с длинным названием для проверки переноса',
  sku: '4000006',
}

async function setup(page: Page, candidates = found) {
  await mockCatalogApi(page)
  const writes: unknown[] = []
  let saved = [
    {
      id: 1,
      product_id: 1,
      related_product_id: 8,
      type: 'related',
      sort_order: 0,
      related_product: existing,
    },
  ]
  let failSearch = false
  await page.route('**/api/v1/admin/products/1/relations', async (route) => {
    if (route.request().method() === 'PUT') {
      const payload = route.request().postDataJSON()
      writes.push(payload)
      saved = payload.relations.map(
        (
          row: { related_product_id: number; type: string; sort_order: number },
          index: number,
        ) => ({
          ...row,
          id: index + 1,
          product_id: 1,
          related_product: [existing, ...candidates].find(
            (item) => item.id === row.related_product_id,
          )!,
        }),
      )
    }
    await route.fulfill({ json: { data: saved } })
  })
  await page.route(
    '**/api/v1/admin/products/1/relation-candidates?**',
    async (route) => {
      if (failSearch) {
        await route.fulfill({
          status: 500,
          json: { error: { message: 'Поиск временно недоступен.' } },
        })
        return
      }
      const query =
        new URL(route.request().url()).searchParams
          .get('search')
          ?.toLowerCase() ?? ''
      await route.fulfill({
        json: {
          data: candidates.filter(
            (item) =>
              `${item.name} ${item.sku}`.toLowerCase().includes(query) &&
              !saved.some((row) => row.related_product_id === item.id),
          ),
        },
      })
    },
  )
  await page.goto('/products')
  await page
    .getByRole('button', { name: 'Редактировать товар Монте Тиберио' })
    .click()
  const dialog = page.getByRole('dialog', { name: 'Монте Тиберио' })
  await dialog.getByRole('button', { name: 'Проверка', exact: false }).click()
  const section = dialog.getByRole('region', {
    name: 'Сопутствующие и рекомендуемые товары',
    exact: true,
  })
  return {
    dialog,
    section,
    writes,
    failSearch: () => {
      failSearch = true
    },
    recover: () => {
      failSearch = false
    },
  }
}

for (const width of [320, 640, 768, 1024, 1280]) {
  test(`single SKU lookup adds both relation types and keeps draft rows usable at ${width}px`, async ({
    page,
  }, testInfo) => {
    await page.setViewportSize({ width, height: 900 })
    const { dialog, section, writes } = await setup(page)
    const input = section.getByRole('textbox', {
      name: 'SKU товара для добавления',
      exact: true,
    })
    const result = section.getByRole('region', {
      name: 'Найденный товар',
      exact: true,
    })
    const rows = section.getByTestId('product-relation-row')
    await expect(result).toHaveCount(0)
    await expect(
      section.getByRole('button', {
        name: 'Товар для добавления',
        exact: true,
      }),
    ).toHaveCount(0)
    await expect(
      section.getByRole('button', { name: 'Найти', exact: true }),
    ).toHaveCount(0)
    await input.fill('6000034')
    await expect(result).toContainText('Смеситель')
    await expect(result).toContainText('SKU: 6000034')
    await result
      .getByRole('button', { name: 'В сопутствующие', exact: true })
      .click()
    await expect(input).toHaveValue('')
    await expect(input).toBeFocused()
    await expect(rows).toHaveCount(2)
    await expect(rows.nth(1)).toContainText('Смеситель')
    await expect(
      rows.nth(1).getByRole('button', { name: 'Тип связи 2', exact: true }),
    ).toHaveText('Сопутствующий товар')
    await input.fill('5000033')
    await input.press('Enter')
    await expect(result).toContainText('Клей')
    await expect(result).toContainText('SKU: 5000033')
    await result.screenshot({
      path: testInfo.outputPath(`sku-result-${width}.png`),
    })
    await result
      .getByRole('button', { name: 'В рекомендуемые', exact: true })
      .click()
    await expect(input).toHaveValue('')
    await expect(input).toBeFocused()
    await expect(
      section
        .getByRole('status')
        .filter({ hasText: 'Товар добавлен в рекомендуемые.' }),
    ).toBeVisible()
    await expect(rows).toHaveCount(3)
    await expect(rows.nth(0)).toContainText(existing.sku)
    await expect(rows.nth(1)).toContainText('6000034')
    await expect(rows.nth(2)).toContainText('5000033')
    expect(writes).toEqual([])
    const type = rows
      .nth(2)
      .getByRole('button', { name: 'Тип связи 3', exact: true })
    await type.click()
    const menu = page.getByRole('group', {
      name: 'Тип связи 3: варианты',
      exact: true,
    })
    const box = (await menu.boundingBox())!
    expect(box.y).toBeGreaterThanOrEqual(0)
    expect(box.y + box.height).toBeLessThanOrEqual(900)
    expect(
      await menu.evaluate((el) => {
        const b = el.getBoundingClientRect()
        return el.contains(
          document.elementFromPoint(b.x + b.width / 2, b.bottom - 5),
        )
      }),
    ).toBe(true)
    await menu
      .getByRole('button', { name: 'Сопутствующий товар', exact: true })
      .press('Escape')
    await expect(menu).toHaveCount(0)
    await expect(type).toBeFocused()
    await expect(type).toHaveText('Рекомендуемый товар')
    await expect(dialog).toBeVisible()
    expect(
      await dialog.evaluate((el) => el.scrollWidth <= el.clientWidth),
    ).toBe(true)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    expect(
      (await new AxeBuilder({ page }).include('[role="dialog"]').analyze())
        .violations,
    ).toEqual([])
    await section
      .getByRole('button', { name: 'Сохранить связи', exact: true })
      .scrollIntoViewIfNeeded()
    await dialog.screenshot({
      path: testInfo.outputPath(`sku-relations-${width}.png`),
    })
    await section
      .getByRole('button', { name: 'Сохранить связи', exact: true })
      .click()
    await expect(
      dialog.getByText('Связи товаров сохранены.', { exact: true }),
    ).toBeVisible()
    expect(writes).toEqual([
      {
        relations: [
          { related_product_id: 8, type: 'related', sort_order: 0 },
          { related_product_id: 2, type: 'related', sort_order: 1 },
          { related_product_id: 3, type: 'recommended', sort_order: 2 },
        ],
      },
    ])
    if (width === 1280) {
      await dialog
        .getByRole('button', { name: 'Закрыть карточку товара', exact: true })
        .click()
      await page
        .getByRole('button', { name: 'Редактировать товар Монте Тиберио' })
        .click()
      await dialog
        .getByRole('button', { name: 'Проверка', exact: false })
        .click()
      await expect(rows).toHaveCount(3)
      await expect(
        rows.nth(2).getByRole('button', { name: 'Тип связи 3', exact: true }),
      ).toHaveText('Рекомендуемый товар')
    }
  })
}

test('exact SKU lookup handles self, saved/draft duplicates, removal and readdition', async ({
  page,
}) => {
  const { section, writes } = await setup(page)
  const input = section.getByRole('textbox', {
    name: 'SKU товара для добавления',
    exact: true,
  })
  const result = section.getByRole('region', {
    name: 'Найденный товар',
    exact: true,
  })
  const rows = section.getByTestId('product-relation-row')
  await input.fill(product.sku)
  await expect(
    section.getByRole('status').filter({ hasText: 'редактируемого товара' }),
  ).toBeVisible()
  await expect(result).toHaveCount(0)
  await input.fill(existing.sku)
  await expect(
    section.getByRole('status').filter({ hasText: 'уже добавлен' }),
  ).toBeVisible()
  await expect(result).toHaveCount(0)
  await input.fill('6000034')
  await expect(result).toContainText('Смеситель')
  await result
    .getByRole('button', { name: 'В сопутствующие', exact: true })
    .click()
  await input.fill('6000034')
  await expect(
    section.getByRole('status').filter({ hasText: 'уже добавлен' }),
  ).toBeVisible()
  await expect(result).toHaveCount(0)
  await section
    .getByRole('button', {
      name: 'Удалить связь с товаром 4000006',
      exact: true,
    })
    .click()
  await expect(rows).toHaveCount(1)
  await input.fill(existing.sku)
  await input.press('Enter')
  await expect(result).toContainText(existing.name)
  await result
    .getByRole('button', { name: 'В рекомендуемые', exact: true })
    .click()
  await expect(rows).toHaveCount(2)
  await expect(
    rows.nth(1).getByRole('button', { name: 'Тип связи 2', exact: true }),
  ).toHaveText('Рекомендуемый товар')
  expect(writes).toEqual([])
})

test('partial/unknown SKU and failed lookup show feedback with retry without losing added products', async ({
  page,
  browserIssueGuard,
}) => {
  browserIssueGuard.allowApiError(
    500,
    '/api/v1/admin/products/1/relation-candidates',
  )
  const { dialog, section, failSearch, recover } = await setup(page)
  const input = section.getByRole('textbox', {
    name: 'SKU товара для добавления',
    exact: true,
  })
  const result = section.getByRole('region', {
    name: 'Найденный товар',
    exact: true,
  })
  for (const query of ['60000', 'unknown-SKU']) {
    await input.fill(query)
    await expect(
      section
        .getByRole('status')
        .filter({ hasText: 'Товар с таким SKU не найден' }),
    ).toBeVisible()
    await expect(result).toHaveCount(0)
  }
  failSearch()
  await input.fill(' 6000034 ')
  await expect(section.getByRole('alert')).toContainText(
    'Поиск временно недоступен.',
  )
  await expect(result).toHaveCount(0)
  await expect(section.getByTestId('product-relation-row')).toContainText(
    existing.sku,
  )
  recover()
  await section
    .getByRole('button', { name: 'Повторить поиск', exact: true })
    .click()
  await expect(result).toContainText('SKU: 6000034')
  await result
    .getByRole('button', { name: 'В рекомендуемые', exact: true })
    .click()
  await expect(section.getByTestId('product-relation-row')).toHaveCount(2)
  await expect(input).toHaveValue('')
  await expect(input).toBeFocused()
  await expect(dialog).toBeVisible()
})
