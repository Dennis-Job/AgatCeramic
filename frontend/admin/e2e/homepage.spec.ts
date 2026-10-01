import { expect, test } from './fixtures'
import AxeBuilder from '@axe-core/playwright'
import { blankBlockData } from '../src/features/pages/validation/blocks'

test('home route uses typed blocks with saved preview, pending notice and separate publication', async ({
  page,
}) => {
  const data = blankBlockData()
  let stored = {
    id: 1,
    title: 'Главная',
    slug: 'home',
    body: '',
    blocks: [
      {
        id: 'promo',
        type: 'promo',
        enabled: true,
        data: { ...data.promo, title: 'Исходное промо' },
      },
    ],
    seo: {
      title: 'AgatCeramic',
      description: '',
      og_title: '',
      og_description: '',
      og_image_url: '',
      og_image_media_id: null,
    },
    is_published: true,
    has_unpublished_changes: false,
    published_at: null,
    created_at: '',
    updated_at: '',
  }
  let published = stored.blocks[0]!.data.title
  let previewLoads = 0
  await page.route('http://localhost:3000/preview/home', (route) => {
    previewLoads++
    return route.fulfill({
      contentType: 'text/html; charset=utf-8',
      body: `<!doctype html><html lang="ru"><head><title>Черновик</title></head><body><main aria-label="Содержимое предпросмотра сайта"><h1>${stored.blocks[0]!.data.title}</h1></main></body></html>`,
    })
  })
  await page.route('**/sanctum/csrf-cookie', (route) =>
    route.fulfill({ status: 204 }),
  )
  await page.route('**/api/v1/**', (route) => {
    const path = new URL(route.request().url()).pathname.replace('/api/v1', '')
    if (path === '/admin/auth/me')
      return route.fulfill({
        json: {
          data: {
            id: 1,
            name: 'Редактор',
            email: 'editor@example.test',
            status: 'active',
            permissions: ['content.manage'],
          },
        },
      })
    if (path === '/admin/home-page')
      return route.fulfill({ json: { data: { page_id: 1 } } })
    if (path === '/admin/pages')
      return route.fulfill({
        json: {
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
        },
      })
    if (path === '/admin/pages/1' && route.request().method() === 'PATCH')
      stored = {
        ...stored,
        ...route.request().postDataJSON(),
        has_unpublished_changes: true,
      }
    if (path === '/admin/pages/1/publish') {
      published = stored.blocks[0]!.data.title
      stored.has_unpublished_changes = false
    }
    if (path.startsWith('/admin/pages/1'))
      return route.fulfill({ json: { data: stored } })
    return route.fulfill({ status: 404 })
  })
  await page.goto('/home-page')
  await expect(page).toHaveURL(/\/content\?section=pages&page=home/)
  await expect(
    page.getByRole('heading', { name: 'Главная страница' }),
  ).toBeVisible()
  await expect(
    page.getByRole('region', { name: 'Настройки: Промо' }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Блоки и порядок', exact: true }),
  ).toHaveCount(0)
  await expect(
    page.getByRole('heading', { name: 'SEO страницы' }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Изображение Open Graph', exact: true }),
  ).toHaveCount(0)
  const frame = page.frameLocator('iframe[title="Сохранённый черновик: home"]')
  await expect(frame.getByRole('heading')).toHaveText('Исходное промо')
  const loads = previewLoads
  await page
    .getByRole('region', { name: 'Настройки: Промо' })
    .getByRole('textbox', { name: /^Заголовок/ })
    .fill('Сохранённое промо')
  await expect(
    page.getByText('Несохранённые изменения ещё не вошли в предпросмотр.'),
  ).toBeVisible()
  await expect(frame.getByRole('heading')).toHaveText('Исходное промо')
  expect(previewLoads).toBe(loads)
  await expect(
    page.getByRole('button', {
      name: 'Опубликовать блоки страницы',
      exact: true,
    }),
  ).toBeDisabled()
  await page
    .getByRole('button', { name: 'Сохранить черновик блоков', exact: true })
    .click()
  await expect(frame.getByRole('heading')).toHaveText('Сохранённое промо')
  expect(previewLoads).toBeGreaterThan(loads)
  expect(published).toBe('Исходное промо')
  await page.getByRole('button', { name: 'Телефон', exact: true }).click()
  await expect(page.locator('iframe')).toHaveClass('preview-mobile')
  await expect(
    page.getByRole('link', { name: 'Открыть полноразмерный просмотр' }),
  ).toHaveAttribute('rel', 'noopener noreferrer')
  await expect(
    page.getByRole('link', { name: 'Открыть опубликованную страницу' }),
  ).toHaveAttribute('href', 'http://localhost:3000/')
  // Audit the settled size control, after its secondary → primary transition.
  await page
    .getByRole('group', { name: 'Размер предпросмотра' })
    .evaluate(async (element) => {
      await Promise.all(
        element
          .getAnimations({ subtree: true })
          .map((animation) => animation.finished),
      )
    })
  expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    expect(
      await page.evaluate(
        () =>
          document.documentElement.scrollWidth >
          document.documentElement.clientWidth,
      ),
      `overflow ${width}`,
    ).toBe(false)
    await page.screenshot({
      path: test.info().outputPath(`home-preview-${width}.png`),
      fullPage: true,
      animations: 'disabled',
    })
  }
  await page
    .getByRole('button', { name: 'Опубликовать блоки страницы', exact: true })
    .click()
  await expect(
    page.getByText('Сохранённый черновик опубликован.', { exact: true }),
  ).toBeVisible()
  expect(published).toBe('Сохранённое промо')
})
