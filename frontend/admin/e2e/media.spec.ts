import AxeBuilder from '@axe-core/playwright'
import { expect, test } from './fixtures'

const pixel =
  'data:image/png;base64,iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL/nwAAAABJRU5ErkJggg=='

test('media manager uploads, edits and deletes an image', async ({
  page,
}, testInfo) => {
  let media: Record<string, unknown> | null = null
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
            permissions: ['media.manage'],
          },
        },
      })
    if (path === '/admin/media' && route.request().method() === 'GET')
      return route.fulfill({
        json: {
          data: media ? [media] : [],
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 25,
            total: media ? 1 : 0,
          },
        },
      })
    if (path === '/admin/media' && route.request().method() === 'POST') {
      media = {
        id: 7,
        kind: 'image',
        title: 'Плитка',
        alt: 'Белая плитка',
        url: pixel,
        thumbnail_url: pixel,
        mime_type: 'image/png',
        size: 70,
        width: 1,
        height: 1,
        created_at: '2026-09-27T10:00:00Z',
        updated_at: '2026-09-27T10:00:00Z',
      }
      return route.fulfill({ status: 201, json: { data: media } })
    }
    if (path === '/admin/media/7' && route.request().method() === 'PATCH') {
      media = { ...media, ...route.request().postDataJSON() }
      return route.fulfill({ json: { data: media } })
    }
    if (path === '/admin/media/7' && route.request().method() === 'DELETE') {
      media = null
      return route.fulfill({ status: 204 })
    }
    return route.fulfill({
      status: 404,
      json: { error: { message: 'Unknown endpoint' } },
    })
  })

  await page.goto('/media')
  await expect(page.getByText('Файлов пока нет.')).toBeVisible()
  await page.getByLabel('Название').fill('Плитка')
  await page.getByLabel('Alt текст').fill('Белая плитка')
  await page.locator('input[type="file"]').setInputFiles({
    name: 'tile.png',
    mimeType: 'image/png',
    buffer: Buffer.from(pixel.split(',')[1]!, 'base64'),
  })
  await page.getByRole('button', { name: 'Загрузить', exact: true }).click()
  await expect(page.getByRole('link', { name: 'Плитка' })).toBeVisible()
  await page.getByRole('button', { name: 'Изменить файл Плитка' }).click()
  await page.getByLabel('Название').fill('Плитка белая')
  await page.getByRole('button', { name: 'Сохранить' }).click()
  await expect(page.getByRole('link', { name: 'Плитка белая' })).toBeVisible()
  await page.setViewportSize({ width: 1280, height: 800 })
  await page.screenshot({
    path: testInfo.outputPath('media-1280.png'),
    fullPage: true,
  })
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 800 })
    if (width === 320)
      await page.screenshot({
        path: testInfo.outputPath('media-320.png'),
        fullPage: true,
      })
    expect(
      await page.evaluate(
        () =>
          document.documentElement.scrollWidth >
          document.documentElement.clientWidth,
      ),
      `overflow at ${width}px`,
    ).toBe(false)
  }
  const accessibility = await new AxeBuilder({ page }).analyze()
  expect(accessibility.violations).toEqual([])
  await page.getByRole('button', { name: 'Удалить файл Плитка белая' }).click()
  await page.getByRole('button', { name: 'Удалить', exact: true }).click()
  await expect(page.getByText('Файлов пока нет.')).toBeVisible()
})
