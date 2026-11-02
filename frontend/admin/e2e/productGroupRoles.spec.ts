import type { Page } from '@playwright/test'
import AxeBuilder from './approvedAccessibility'
import { expect, test } from './fixtures'
import {
  attribute,
  mockCatalogApi,
  product,
  sharedAttribute,
} from './catalogApi'

const area = {
  ...attribute,
  id: 4,
  name: 'Площадь плитки в упаковке',
  slug: 'pack-area',
  unit: 'м²',
  is_required: false,
}
const note = {
  ...sharedAttribute,
  id: 5,
  name: 'Описание упаковки',
  slug: 'pack-note',
  type: 'text',
}
const attrs = [attribute, sharedAttribute, area, note]

async function setup(page: Page) {
  await mockCatalogApi(page, { sourceProductInGroup: true })
  const group = {
    id: 1,
    name: 'Монте Тиберио',
    code: 'MONTE-TIBERIO-GROUP',
    axes: [attribute],
    individual_attributes: [] as (typeof attribute)[],
    products: [
      product,
      {
        ...product,
        id: 2,
        sku: 'SECOND',
        name: 'Монте Тиберио другая упаковка',
      },
    ],
    created_at: product.created_at,
    updated_at: product.updated_at,
  }
  await page.route('**/api/v1/admin/categories/1/attributes', (route) =>
    route.fulfill({ json: { data: attrs } }),
  )
  await page.route('**/api/v1/admin/product-groups**', async (route) => {
    if (route.request().method() === 'PATCH') {
      const payload = route.request().postDataJSON()
      group.individual_attributes = attrs.filter((a) =>
        payload.individual_attribute_ids.includes(a.id),
      )
      await route.fulfill({ json: { data: group } })
    } else
      await route.fulfill({
        json: {
          data: [group],
          meta: { current_page: 1, last_page: 1, total: 1, per_page: 25 },
        },
      })
  })
  await page.route('**/api/v1/admin/products/1/attributes', async (route) => {
    const data = [
      { attribute_id: 1, value: '60.00', attribute },
      { attribute_id: 2, value: 'Матовая', attribute: sharedAttribute },
      { attribute_id: 4, value: 1.44, attribute: area },
    ]
    await route.fulfill({ json: { data } })
  })
  await page.goto('/products')
  await page
    .getByRole('button', { name: 'Редактировать товар Монте Тиберио' })
    .click()
  return page.getByRole('dialog', { name: 'Монте Тиберио' })
}

for (const width of [320, 640, 768, 1024, 1280]) {
  test(`three characteristic roles save, protect shared values and fit at ${width}px`, async ({
    page,
  }, testInfo) => {
    await page.setViewportSize({ width, height: 900 })
    const dialog = await setup(page)
    await dialog
      .getByRole('button', { name: 'Варианты модели', exact: false })
      .click()
    const role = dialog.getByRole('button', {
      name: 'Роль: Площадь плитки в упаковке',
      exact: true,
    })
    await expect(role).toContainText('Общая')
    await role.focus()
    await role.press('Enter')
    const menu = page.getByRole('group', {
      name: 'Роль: Площадь плитки в упаковке: варианты',
      exact: true,
    })
    await expect(menu.getByRole('button')).toHaveText([
      'Общая',
      'Для выбора варианта',
      'Индивидуальная',
    ])
    await menu
      .getByRole('button', { name: 'Индивидуальная', exact: true })
      .press('Enter')
    await expect(role).toBeFocused()
    await expect(role).toContainText('Индивидуальная')
    await dialog
      .getByRole('button', {
        name: 'Роль: Описание упаковки',
        exact: true,
      })
      .click()
    const textMenu = page.getByRole('group', {
      name: 'Роль: Описание упаковки: варианты',
      exact: true,
    })
    await expect(textMenu.getByRole('button')).toHaveText([
      'Общая',
      'Индивидуальная',
    ])
    await page.keyboard.press('Escape')
    await expect(dialog).toBeVisible()
    await dialog.screenshot({ path: testInfo.outputPath(`roles-${width}.png`) })
    expect(
      await dialog.evaluate(
        (element) => element.scrollWidth <= element.clientWidth,
      ),
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

    await dialog
      .getByRole('button', { name: 'Характеристики', exact: false })
      .click()
    await expect(
      dialog.getByText('Сначала сохраните роли характеристик', {
        exact: false,
      }),
    ).toBeVisible()
    await expect(
      dialog.getByRole('button', {
        name: 'Сохранить и продолжить',
        exact: true,
      }),
    ).toBeDisabled()
    await dialog
      .getByRole('button', { name: 'Варианты модели', exact: false })
      .click()
    const saved = page.waitForRequest(
      (request) =>
        request.method() === 'PATCH' &&
        request.url().endsWith('/product-groups/1'),
    )
    await dialog
      .getByRole('button', { name: 'Сохранить группу', exact: true })
      .click()
    expect((await saved).postDataJSON()).toMatchObject({
      axis_attribute_ids: [1],
      individual_attribute_ids: [4],
    })
    await dialog
      .getByRole('button', { name: 'Характеристики', exact: false })
      .click()
    const packArea = dialog.getByRole('spinbutton', {
      name: 'Площадь плитки в упаковке (м²), индивидуальная',
      exact: true,
    })
    await expect(packArea).toHaveValue('1.44')
    await packArea.fill('1.08')
    const valuesSaved = page.waitForRequest(
      (request) =>
        request.method() === 'PUT' &&
        request.url().endsWith('/products/1/attributes'),
    )
    await dialog
      .getByRole('button', { name: 'Сохранить и продолжить', exact: true })
      .click()
    expect((await valuesSaved).postDataJSON().attributes).toContainEqual({
      attribute_id: 4,
      value: 1.08,
    })
    await dialog
      .getByRole('button', { name: 'Варианты модели', exact: false })
      .click()
    await expect(role).toContainText('Индивидуальная')
  })
}
