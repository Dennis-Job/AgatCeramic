import AxeBuilder from './approvedAccessibility'
import { expect, test, type Page } from './fixtures'
import { mockAdminBaseline } from './adminBaselineApi'
import { brand } from './catalogApi'
import { createDeferredApiRequests } from './deferredApi'

const pixel =
  'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg=='
const logo = {
  id: 77,
  kind: 'image',
  url: pixel,
  thumbnail_url: pixel,
  title: 'Логотип',
  alt: 'Логотип Kerama Marazzi',
  mime_type: 'image/png',
  size: 100,
  width: 1,
  height: 1,
  created_at: brand.created_at,
  updated_at: brand.updated_at,
}
const document = {
  ...logo,
  id: 78,
  kind: 'document',
  title: 'Каталог плитки',
  mime_type: 'application/pdf',
  url: '/catalog.pdf',
  thumbnail_url: null,
}

async function mockBrands(page: Page, longText = false) {
  await mockAdminBaseline(page)
  const deferred = createDeferredApiRequests()
  const saves: Record<string, unknown>[] = []
  let item = {
    ...brand,
    logo_id: 77,
    logo,
    documents: [document],
    name: longText ? 'ДлинноеНазваниеБрендаБезПробелов'.repeat(8) : brand.name,
    description: longText
      ? 'ПодробноеОписаниеБезПробелов'.repeat(16)
      : brand.description,
  }
  await page.route('**/api/v1/admin/brands**', async (route) => {
    if (['POST', 'PATCH'].includes(route.request().method())) {
      const payload = route.request().postDataJSON()
      saves.push(payload)
      item = { ...item, ...payload }
      return route.fulfill({ json: { data: item } })
    }
    return route.fulfill({
      json: {
        data: [
          item,
          {
            ...brand,
            id: 2,
            name: 'Без логотипа',
            country_code: null,
            description: null,
            is_active: false,
          },
        ],
        meta: { current_page: 1, last_page: 1, per_page: 25, total: 2 },
      },
    })
  })
  await page.route('**/api/v1/admin/media**', async (route) => {
    if (route.request().method() === 'POST') {
      await deferred.wait('upload')
      return route.fulfill({ status: 201, json: { data: logo } })
    }
    return route.fulfill({
      json: {
        data: [logo, document].filter(
          (media) =>
            media.kind ===
            new URL(route.request().url()).searchParams.get('kind'),
        ),
        meta: { current_page: 1, last_page: 1, per_page: 100, total: 1 },
      },
    })
  })
  return { saves, deferred }
}

test('brands show detailed information, logos and linked documents', async ({
  page,
}) => {
  await mockBrands(page)
  await page.goto('/brands')
  const list = page.getByRole('list', { name: 'Бренды и подробная информация' })
  const item = list.getByRole('article', { name: brand.name })
  await expect(item.getByRole('img', { name: logo.alt })).toBeVisible()
  await expect(item).toContainText('Россия')
  await expect(item).toContainText(brand.description)
  await expect(
    item.getByRole('link', { name: document.title }),
  ).toHaveAttribute('href', '/catalog.pdf')
  await expect(
    list.getByRole('article', { name: 'Без логотипа' }),
  ).toContainText('Страна не указана')
  await expect(
    list.getByRole('article', { name: 'Без логотипа' }),
  ).toContainText('Скрыт')
  await expect(
    list.getByRole('article', { name: 'Без логотипа' }).getByRole('status'),
  ).toContainText('Изображение не задано')
  const results = await new AxeBuilder({ page }).analyze()
  expect(results.violations).toEqual([])
  await page.screenshot({
    path: '../../.tmp/brands-ui-review/brands-overview.png',
    fullPage: true,
  })
})

for (const mode of ['create', 'edit'] as const) {
  test(`brand ${mode} uploads logo before save and restores focus after preview`, async ({
    page,
  }) => {
    const api = await mockBrands(page)
    await page.goto('/brands')
    const trigger = page.getByRole('button', {
      name:
        mode === 'create'
          ? 'Добавить бренд'
          : `Редактировать бренд ${brand.name}`,
      exact: true,
    })
    await trigger.click()
    const dialog = page.getByRole('dialog', {
      name: mode === 'create' ? 'Новый бренд' : 'Редактирование бренда',
      exact: true,
    })
    await page.keyboard.press('Escape')
    await expect(dialog).toBeHidden()
    await expect(trigger).toBeFocused()
    await trigger.click()
    if (mode === 'edit')
      await page.screenshot({
        path: '../../.tmp/brands-ui-review/brand-editor.png',
      })
    await dialog
      .getByRole('textbox', { name: /^Название/ })
      .fill('Новый бренд плитки')
    await dialog
      .getByLabel('Загрузить файл — Логотип бренда', { exact: true })
      .setInputFiles({
        name: 'logo.png',
        mimeType: 'image/png',
        buffer: Buffer.from(pixel.split(',')[1]!, 'base64'),
      })
    await expect(
      dialog.getByRole('button', { name: 'Сохранить', exact: true }),
    ).toBeDisabled()
    await dialog
      .getByRole('textbox', {
        name: 'Описание загружаемого изображения',
        exact: true,
      })
      .fill(logo.alt)
    const upload = api.deferred.defer('upload')
    await dialog
      .getByRole('button', { name: 'Загрузить и выбрать', exact: true })
      .click()
    await upload.requested
    await page.keyboard.press('Escape')
    await expect(dialog).toBeVisible()
    upload.release()
    const preview = dialog.getByRole('button', {
      name: 'Увеличить логотип бренда',
      exact: true,
    })
    await preview.click()
    await expect(
      page.getByRole('dialog', { name: 'Просмотр изображения', exact: true }),
    ).toBeVisible()
    await page.keyboard.press('Escape')
    await expect(preview).toBeFocused()
    await dialog.getByRole('button', { name: 'Сохранить', exact: true }).click()
    await expect(dialog).toBeHidden()
    expect(api.saves).toHaveLength(1)
    expect(api.saves[0]).toMatchObject({
      name: 'Новый бренд плитки',
      logo_id: 77,
      document_ids: mode === 'create' ? [] : [78],
    })
  })
}

test('brand list and editor fit narrow screens with long content and reachable footer', async ({
  page,
}) => {
  await mockBrands(page, true)
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 720 })
    await page.goto('/brands')
    await expect(
      page.getByRole('list', { name: 'Бренды и подробная информация' }),
    ).toBeVisible()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page
      .getByRole('button', { name: /Редактировать бренд/ })
      .first()
      .click()
    const dialog = page.getByRole('dialog', {
      name: 'Редактирование бренда',
      exact: true,
    })
    await expect(
      dialog.getByRole('button', { name: 'Сохранить', exact: true }),
    ).toBeInViewport()
    expect(
      await dialog.evaluate((el) => el.scrollWidth <= el.clientWidth),
    ).toBe(true)
    if (width === 320 || width === 1280) {
      const results = await new AxeBuilder({ page }).analyze()
      expect(results.violations).toEqual([])
      await page.screenshot({
        path: `../../.tmp/brands-ui-review/brand-editor-${width}.png`,
      })
    }
    await page.keyboard.press('Escape')
    await expect(dialog).toBeHidden()
  }
})

test('country menu stays visible inside a narrow brand dialog and Escape returns to the field', async ({
  page,
}) => {
  await mockBrands(page)
  await page.setViewportSize({ width: 320, height: 720 })
  await page.goto('/brands')
  await page
    .getByRole('button', { name: 'Добавить бренд', exact: true })
    .click()
  const dialog = page.getByRole('dialog', { name: 'Новый бренд', exact: true })
  const country = dialog.getByRole('button', {
    name: 'Страна происхождения',
    exact: true,
  })
  await country.click()
  const search = page.getByRole('searchbox', {
    name: 'Поиск: Страна происхождения',
    exact: true,
  })
  await expect(search).toBeInViewport()
  await page.keyboard.press('Escape')
  await expect(dialog).toBeVisible()
  await expect(country).toBeFocused()
  await country.click()
  await search.fill('Италия')
  await page.getByRole('button', { name: 'Италия', exact: true }).click()
  await expect(country).toContainText('Италия')
})
