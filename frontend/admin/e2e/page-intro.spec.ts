import { expect, test, type Page } from './fixtures'
import AxeBuilder from './approvedAccessibility'
import { mockContentWorkspace } from './contentWorkspaceApi'
import type { ContentPage } from '../src/features/pages/types/page.types'

const pixel =
  'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg=='

async function setup(page: Page) {
  await mockContentWorkspace(page)
  let stored: ContentPage = {
    id: 2,
    title: 'О нас',
    slug: 'about',
    body: 'О компании',
    blocks: [],
    intro: null,
    is_published: true,
    has_unpublished_changes: false,
    published_at: null,
    created_at: '',
    updated_at: '',
  }
  let published = stored.intro
  const writes: Record<string, unknown>[] = []
  await page.route('**/api/v1/admin/pages**', (route) => {
    const path = new URL(route.request().url()).pathname
    const method = route.request().method()
    if (path.endsWith('/publish')) {
      published = stored.intro
      stored = { ...stored, has_unpublished_changes: false }
    } else if (method === 'PATCH') {
      const payload = route.request().postDataJSON()
      writes.push(payload)
      stored = { ...stored, ...payload, has_unpublished_changes: true }
    }
    return route.fulfill({
      json:
        path.endsWith('/pages') && method === 'GET'
          ? {
              data: [stored],
              meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
            }
          : { data: stored },
    })
  })
  let uploaded = false
  const image = {
    id: 18,
    kind: 'image',
    title: 'Керамика',
    alt: 'Образцы керамики',
    url: pixel,
    thumbnail_url: pixel,
    mime_type: 'image/png',
    size: 70,
    width: 1,
    height: 1,
    created_at: '',
    updated_at: '',
  }
  await page.route('**/api/v1/admin/media**', (route) => {
    if (route.request().method() === 'POST') {
      uploaded = true
      return route.fulfill({ status: 201, json: { data: image } })
    }
    return route.fulfill({
      json: {
        data: uploaded ? [image] : [],
        meta: {
          current_page: 1,
          last_page: 1,
          per_page: 25,
          total: uploaded ? 1 : 0,
        },
      },
    })
  })
  return { writes, stored: () => stored, published: () => published }
}

test('intro image upload protects pending files and follows draft, publish and reset workflow', async ({
  page,
}) => {
  const state = await setup(page)
  await page.goto('/content?page=2')
  await page.getByRole('tab', { name: 'Настройки', exact: true }).click()
  const card = page.getByRole('region', { name: 'Вступительный блок страницы' })
  const save = page.getByRole('button', {
    name: 'Сохранить черновик блоков',
    exact: true,
  })
  const publish = page.getByRole('button', {
    name: 'Опубликовать блоки страницы',
    exact: true,
  })
  await card.locator('input[type="file"]').setInputFiles({
    name: 'ceramic.png',
    mimeType: 'image/png',
    buffer: Buffer.from(pixel.split(',')[1]!, 'base64'),
  })
  await expect(save).toBeDisabled()
  await expect(publish).toBeDisabled()
  page.once('dialog', (dialog) => dialog.dismiss())
  await page.getByRole('link', { name: 'Главная', exact: false }).last().click()
  await expect(page).toHaveURL(/page=2/)
  await card.getByRole('textbox', { name: /^Название файла/ }).fill('Керамика')
  await card
    .getByRole('textbox', {
      name: 'Описание загружаемого изображения',
      exact: true,
    })
    .fill('Образцы керамики')
  await card.getByRole('button', { name: 'Загрузить и выбрать' }).click()
  await card
    .getByRole('textbox', { name: 'Описание изображения вступительного блока' })
    .fill('Светлые образцы керамики')
  await expect(save).toBeEnabled()
  await save.click()
  await expect.poll(() => state.stored().intro?.image_media_id).toBe(18)
  expect(state.published()).toBeNull()
  expect(state.writes[0]).toMatchObject({
    intro: {
      image_media_id: 18,
      image_url: null,
      image_alt: 'Светлые образцы керамики',
    },
  })
  await publish.click()
  await expect.poll(() => state.published()?.image_media_id).toBe(18)
  await page.reload()
  await page.getByRole('tab', { name: 'Настройки', exact: true }).click()
  await expect(
    card.getByRole('textbox', {
      name: 'Описание изображения вступительного блока',
    }),
  ).toHaveValue('Светлые образцы керамики')
  const reset = card.getByRole('button', {
    name: 'Вернуть стандартную иллюстрацию',
    exact: true,
  })
  await reset.focus()
  await page.keyboard.press('Enter')
  await expect(
    card.getByRole('checkbox', { name: 'Стандартная иллюстрация страницы' }),
  ).toBeFocused()
  await save.click()
  await expect.poll(() => state.stored().intro).toBeNull()
  expect(state.published()?.image_media_id).toBe(18)
  await publish.click()
  await expect.poll(() => state.published()).toBeNull()
  const libraryButton = card.getByRole('button', {
    name: 'Выбрать из загруженных',
    exact: true,
  })
  await libraryButton.click()
  await card
    .getByRole('button', {
      name: 'Изображение вступительного блока',
      exact: true,
    })
    .click()
  await page
    .getByRole('button', { name: 'Керамика (#18)', exact: true })
    .click()
  await expect(libraryButton).toBeFocused()
})

test('intro settings fit required widths and keep accessible controls', async ({
  page,
}) => {
  await setup(page)
  await page.goto('/content?page=2')
  await page.getByRole('tab', { name: 'Настройки', exact: true }).click()
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await expect(
      page.getByRole('heading', { name: 'Вступительный блок страницы' }),
    ).toBeVisible()
    expect(
      await page.evaluate(
        () =>
          document.documentElement.scrollWidth >
          document.documentElement.clientWidth,
      ),
      `overflow at ${width}`,
    ).toBe(false)
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    await page.screenshot({
      path: test.info().outputPath(`page-intro-${width}.png`),
      fullPage: true,
      animations: 'disabled',
    })
  }
})

test('intro visibility warning follows the first enabled block instead of any lower slider', async ({
  page,
}) => {
  const state = await setup(page)
  state.stored().blocks = [
    {
      id: 'text-first',
      type: 'text',
      enabled: true,
      data: { title: 'Текст', body: 'Описание' },
    },
    {
      id: 'hero-later',
      type: 'hero',
      enabled: true,
      data: { slider_id: null },
    },
  ]
  await page.goto('/content?page=2')
  await page.getByRole('tab', { name: 'Настройки', exact: true }).click()
  await expect(page.getByText(/вступительный блок скрыт/)).toHaveCount(0)
  state.stored().blocks![0]!.enabled = false
  await page.reload()
  await page.getByRole('tab', { name: 'Настройки', exact: true }).click()
  await expect(page.getByText(/вступительный блок скрыт/)).toBeVisible()
})
