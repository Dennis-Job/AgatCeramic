import { expect, test } from './fixtures'

test('content manager creates and publishes a banner', async ({ page }) => {
  let banner: Record<string, unknown> | null = null

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
    if (path === '/admin/pages')
      return route.fulfill({
        json: {
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
        },
      })
    if (path === '/admin/banners' && route.request().method() === 'GET')
      return route.fulfill({
        json: {
          data: banner ? [banner] : [],
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 25,
            total: banner ? 1 : 0,
          },
        },
      })
    if (path === '/admin/banners' && route.request().method() === 'POST') {
      banner = {
        id: 3,
        ...route.request().postDataJSON(),
        created_at: '2026-09-26T10:00:00Z',
        updated_at: '2026-09-26T10:00:00Z',
      }
      return route.fulfill({ status: 201, json: { data: banner } })
    }
    if (path === '/admin/banners/3' && route.request().method() === 'PATCH') {
      banner = { ...banner, ...route.request().postDataJSON() }
      return route.fulfill({ json: { data: banner } })
    }
    return route.fulfill({
      status: 404,
      json: { error: { message: 'Unknown endpoint' } },
    })
  })

  await page.goto('/content')
  await page.getByRole('button', { name: 'Баннеры' }).click()
  await expect(page.getByText('Баннеров пока нет.')).toBeVisible()
  await page.getByRole('button', { name: 'Добавить баннер' }).click()
  await expect(page.getByText('Изображение не задано')).toBeVisible()
  await page.getByLabel('Заголовок').fill('Керамика для дома')
  await page.getByLabel('Описание').fill('Новая коллекция')
  await page.getByRole('button', { name: 'Сохранить' }).click()
  await expect(page.getByText('Черновик')).toBeVisible()
  await page
    .getByRole('button', { name: 'Редактировать баннер Керамика для дома' })
    .click()
  await page.getByText('Опубликовать баннер', { exact: true }).click()
  await page.getByRole('button', { name: 'Сохранить' }).click()
  await expect(page.getByText('Опубликована')).toBeVisible()
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 800 })
    const horizontalOverflow = await page.evaluate(
      () =>
        document.documentElement.scrollWidth >
        document.documentElement.clientWidth,
    )
    expect(horizontalOverflow, `horizontal overflow at ${width}px`).toBe(false)
  }
})
