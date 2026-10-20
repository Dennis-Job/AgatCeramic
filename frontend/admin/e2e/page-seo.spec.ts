import { expect, test, type Page } from './fixtures'
import AxeBuilder from './approvedAccessibility'
import { mockContentWorkspace } from './contentWorkspaceApi'
import type { ContentPage } from '../src/features/pages/types/page.types'

const indexingLabel = 'Разрешить индексацию поисковыми системами'

async function setup(page: Page) {
  await mockContentWorkspace(page)
  let stored: ContentPage = {
    id: 2,
    title: 'О нас',
    slug: 'about',
    body: 'О компании',
    blocks: [],
    seo: {
      title: 'О нас',
      description: '',
      og_title: 'О нас',
      og_description: '',
      og_image_url: null,
      og_image_media_id: null,
    },
    is_published: true,
    has_unpublished_changes: false,
    published_at: null,
    created_at: '',
    updated_at: '',
  }
  let published: boolean | undefined = true
  const writes: Record<string, unknown>[] = []
  await page.route('**/api/v1/admin/pages**', (route) => {
    const path = new URL(route.request().url()).pathname
    const method = route.request().method()
    if (path.endsWith('/publish')) {
      published = stored.is_indexable
      stored = { ...stored, has_unpublished_changes: false }
    } else if (method === 'POST' || method === 'PATCH') {
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
  return { writes, stored: () => stored, published: () => published }
}

test('new page exposes the default noindex choice and initializes SEO title', async ({
  page,
}) => {
  const state = await setup(page)
  await page.goto('/content')
  await page.getByRole('button', { name: 'Добавить страницу' }).click()
  const dialog = page.getByRole('dialog', { name: 'Новая страница' })
  await expect(
    dialog.getByRole('checkbox', { name: indexingLabel }),
  ).not.toBeChecked()
  await dialog
    .getByRole('textbox', { name: /^Заголовок/ })
    .fill('Новая страница о доставке')
  await dialog
    .getByRole('textbox', { name: /^Адрес страницы/ })
    .fill('delivery')
  await dialog
    .getByRole('textbox', { name: /^Текст страницы/ })
    .fill('Условия доставки')
  await dialog.getByRole('checkbox', { name: indexingLabel }).press('Space')
  await dialog
    .getByRole('button', { name: 'Сохранить черновик', exact: true })
    .click()
  await expect.poll(() => state.writes.length).toBe(1)
  expect(state.writes[0]).toMatchObject({
    is_indexable: true,
    seo: { title: 'Новая страница о доставке' },
  })
})

test('About SEO retains metadata with noindex and publishes only the saved draft', async ({
  page,
}) => {
  const errors: string[] = []
  page.on('pageerror', (error) => errors.push(error.message))
  const state = await setup(page)
  await page.goto('/content?page=2')
  await page.getByRole('tab', { name: 'SEO', exact: true }).click()
  const indexing = page.getByRole('checkbox', { name: indexingLabel })
  await expect(indexing).toBeChecked()
  await expect(
    page.getByRole('textbox', { name: /^Заголовок страницы/ }),
  ).toHaveValue('О нас')
  await page
    .getByRole('textbox', { name: /^Заголовок страницы/ })
    .fill('О нас — AgatCeramic')
  await page
    .getByRole('textbox', { name: /^Описание страницы/ })
    .fill('Керамика для вашего дома')
  await page
    .getByRole('textbox', { name: /^Заголовок Open Graph/ })
    .fill('AgatCeramic в соцсетях')
  await indexing.press('Space')
  await expect(
    page.getByRole('textbox', { name: /^Описание страницы/ }),
  ).toHaveValue('Керамика для вашего дома')
  const publish = page.getByRole('button', {
    name: 'Опубликовать блоки страницы',
    exact: true,
  })
  await expect(publish).toBeDisabled()
  page.once('dialog', (dialog) => dialog.dismiss())
  await page.getByRole('link', { name: 'Главная', exact: false }).last().click()
  await expect(page).toHaveURL(/page=2/)
  await page
    .getByRole('button', { name: 'Сохранить черновик блоков', exact: true })
    .click()
  await expect.poll(() => state.stored().is_indexable).toBe(false)
  expect(state.published()).toBe(true)
  expect(state.stored().seo).toMatchObject({
    title: 'О нас — AgatCeramic',
    description: 'Керамика для вашего дома',
    og_title: 'AgatCeramic в соцсетях',
  })
  await expect(publish).toBeEnabled()
  await publish.click()
  await expect.poll(() => state.published()).toBe(false)
  await page.reload()
  await page.getByRole('tab', { name: 'SEO', exact: true }).click()
  await expect(indexing).not.toBeChecked()
  await expect(
    page.getByRole('textbox', { name: /^Описание страницы/ }),
  ).toHaveValue('Керамика для вашего дома')
  expect(errors).toEqual([])
})

test('page indexing controls stay usable and accessible across required widths', async ({
  page,
}) => {
  await setup(page)
  await page.goto('/content?page=2')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    await page.getByRole('tab', { name: 'SEO', exact: true }).click()
    await expect(
      page.getByRole('checkbox', { name: indexingLabel }),
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
      path: test.info().outputPath(`page-seo-${width}.png`),
      fullPage: true,
      animations: 'disabled',
    })
    await page.getByRole('button', { name: 'Добавить страницу' }).click()
    const dialog = page.getByRole('dialog', { name: 'Новая страница' })
    await expect(
      dialog.getByRole('checkbox', { name: indexingLabel }),
    ).not.toBeChecked()
    const indexingChoice = dialog
      .locator('label')
      .filter({ hasText: indexingLabel })
    await expect(indexingChoice).toBeInViewport()
    await indexingChoice.click()
    await expect(
      dialog.getByRole('checkbox', { name: indexingLabel }),
    ).toBeChecked()
    await indexingChoice.click()
    expect(await dialog.evaluate((el) => el.scrollWidth > el.clientWidth)).toBe(
      false,
    )
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    await page.screenshot({
      path: test.info().outputPath(`page-indexing-dialog-${width}.png`),
      fullPage: true,
      animations: 'disabled',
    })
    await page.keyboard.press('Escape')
    await expect(dialog).toHaveCount(0)
    await expect(
      page.getByRole('button', { name: 'Добавить страницу' }),
    ).toBeFocused()
  }
})
