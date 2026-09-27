import { expect, test } from './fixtures'

const timestamp = '2026-09-26T10:00:00Z'

type WorkingHour = {
  weekday: number
  is_closed: boolean
  opens_at: string | null
  closes_at: string | null
}

test('content editor creates a store, sets its hours, publishes and deletes it', async ({
  page,
}) => {
  let store: {
    id: number
    name: string
    address: string
    phone: string | null
    is_published: boolean
    working_hours: WorkingHour[]
    created_at: string
    updated_at: string
  } | null = null
  let submittedHours: WorkingHour[] = []

  await page.route('**/sanctum/csrf-cookie', (route) =>
    route.fulfill({ status: 204 }),
  )
  await page.route('**/api/v1/**', (route) => {
    const path = new URL(route.request().url()).pathname.replace('/api/v1', '')
    const method = route.request().method()
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
    if (path === '/admin/pages' && method === 'GET')
      return route.fulfill({
        json: {
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
        },
      })
    if (path === '/admin/stores' && method === 'GET')
      return route.fulfill({
        json: {
          data: store ? [store] : [],
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 25,
            total: store ? 1 : 0,
          },
        },
      })
    if (path === '/admin/stores' && method === 'POST') {
      const payload = route.request().postDataJSON()
      store = {
        id: 7,
        ...payload,
        working_hours: Array.from({ length: 7 }, (_, index) => ({
          weekday: index + 1,
          is_closed: true,
          opens_at: null,
          closes_at: null,
        })),
        created_at: timestamp,
        updated_at: timestamp,
      }
      return route.fulfill({ status: 201, json: { data: store } })
    }
    if (path === '/admin/stores/7/working-hours' && method === 'PUT') {
      submittedHours = route.request().postDataJSON().working_hours
      store = { ...store!, working_hours: submittedHours }
      return route.fulfill({ json: { data: store } })
    }
    if (path === '/admin/stores/7' && method === 'PATCH') {
      store = { ...store!, ...route.request().postDataJSON() }
      return route.fulfill({ json: { data: store } })
    }
    if (path === '/admin/stores/7' && method === 'DELETE') {
      store = null
      return route.fulfill({ status: 204 })
    }
    return route.fulfill({
      status: 404,
      json: { error: { message: 'Unknown endpoint' } },
    })
  })

  await page.goto('/content')
  await page.getByRole('button', { name: 'Магазины' }).click()
  await expect(page.getByText('Магазинов пока нет.')).toBeVisible()
  await page.getByRole('button', { name: 'Добавить магазин' }).click()
  await page.getByLabel('Название').fill('Тёплый дом')
  await page.getByLabel('Адрес').fill('Москва, ул. Ленина, 1')
  await page.getByRole('button', { name: 'Сохранить' }).click()
  await expect(page.getByText('Тёплый дом')).toBeVisible()
  await expect(page.getByText('Черновик')).toBeVisible()
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 800 })
    expect(
      await page.evaluate(() => document.documentElement.scrollWidth),
    ).toBeLessThanOrEqual(width)
  }

  await page
    .getByRole('button', { name: 'Часы работы магазина Тёплый дом' })
    .click()
  await page.setViewportSize({ width: 320, height: 800 })
  expect(
    await page.evaluate(() => document.documentElement.scrollWidth),
  ).toBeLessThanOrEqual(320)
  await page.getByText('Выходной', { exact: true }).first().click()
  await page.getByLabel('Понедельник — открытие').fill('09:00')
  await page.getByLabel('Понедельник — закрытие').fill('18:00')
  await page.getByRole('button', { name: 'Сохранить часы работы' }).click()
  await expect(page.getByText('Часы работы сохранены.')).toBeVisible()
  expect(submittedHours).toHaveLength(7)
  expect(submittedHours[0]).toEqual({
    weekday: 1,
    is_closed: false,
    opens_at: '09:00',
    closes_at: '18:00',
  })

  await page
    .getByRole('button', { name: 'Редактировать магазин Тёплый дом' })
    .click()
  await page.getByText('Опубликовать магазин', { exact: true }).click()
  await page.getByRole('button', { name: 'Сохранить' }).click()
  await expect(page.getByText('Опубликован')).toBeVisible()
  await page.getByRole('button', { name: 'Удалить магазин Тёплый дом' }).click()
  await page
    .getByRole('dialog', { name: 'Удалить магазин?' })
    .getByRole('button', { name: 'Удалить' })
    .click()
  await expect(page.getByText('Магазинов пока нет.')).toBeVisible()
})
