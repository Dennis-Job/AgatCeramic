import { expect, test } from './fixtures'

test('content manager composes and publishes an ordered slider', async ({
  page,
}) => {
  const banners = [
    { id: 1, title: 'Керамика', is_published: true },
    { id: 2, title: 'Мозаика', is_published: true },
  ]
  let slider: Record<string, unknown> | null = null

  await page.route('**/sanctum/csrf-cookie', (route) =>
    route.fulfill({ status: 204 }),
  )
  await page.route('**/api/v1/**', (route) => {
    const url = new URL(route.request().url())
    const path = url.pathname.replace('/api/v1', '')
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
    if (path === '/admin/sliders/banner-options')
      return route.fulfill({
        json: {
          data: banners.filter((banner) =>
            banner.title
              .toLowerCase()
              .includes((url.searchParams.get('q') ?? '').toLowerCase()),
          ),
        },
      })
    if (path === '/admin/sliders' && route.request().method() === 'GET')
      return route.fulfill({
        json: {
          data: slider ? [slider] : [],
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 25,
            total: slider ? 1 : 0,
          },
        },
      })
    if (path === '/admin/sliders' && route.request().method() === 'POST') {
      const payload = route.request().postDataJSON() as Record<string, unknown>
      slider = {
        id: 3,
        ...payload,
        banners: (payload.banner_ids as number[]).map((id) =>
          banners.find((banner) => banner.id === id),
        ),
        created_at: '2026-09-26T10:00:00Z',
        updated_at: '2026-09-26T10:00:00Z',
      }
      return route.fulfill({ status: 201, json: { data: slider } })
    }
    if (path === '/admin/sliders/3' && route.request().method() === 'PATCH') {
      const payload = route.request().postDataJSON() as Record<string, unknown>
      slider = {
        ...slider,
        ...payload,
        banners: (payload.banner_ids as number[]).map((id) =>
          banners.find((banner) => banner.id === id),
        ),
      }
      return route.fulfill({ json: { data: slider } })
    }
    return route.fulfill({
      status: 404,
      json: { error: { message: 'Unknown endpoint' } },
    })
  })

  await page.goto('/content')
  await page.getByRole('button', { name: 'Слайдеры' }).click()
  await expect(page.getByText('Слайдеров пока нет.')).toBeVisible()
  await page.getByRole('button', { name: 'Добавить слайдер' }).click()
  await page.getByLabel('Название').fill('Главная')
  await page.getByLabel('Код (slug)').fill('home')
  await page.getByRole('button', { name: 'Добавить баннер Керамика' }).click()
  await page.getByRole('button', { name: 'Добавить баннер Мозаика' }).click()
  await page.getByRole('button', { name: 'Поднять баннер Мозаика' }).click()
  await expect(page.getByText('1. Мозаика')).toBeVisible()
  await page.getByLabel('Найти баннер по названию').fill('Керамика')
  await page.getByLabel('Найти баннер по названию').press('Enter')
  await expect(
    page.getByRole('heading', { name: 'Новый слайдер' }),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Сохранить' }).click()
  await expect(page.getByText('Черновик')).toBeVisible()
  await page
    .getByRole('button', { name: 'Редактировать слайдер Главная' })
    .click()
  await expect(page.getByLabel('Найти баннер по названию')).toHaveValue('')
  await page.getByText('Опубликовать слайдер', { exact: true }).click()
  await page.getByRole('button', { name: 'Сохранить' }).click()
  await expect(page.getByText('Опубликован')).toBeVisible()
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
