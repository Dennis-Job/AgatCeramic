import { expect, test } from './fixtures'

const timestamp = '2026-09-25T10:00:00Z'

test('content editor creates a draft, publishes it, and deletes it', async ({
  page,
}) => {
  let storedPage: {
    id: number
    title: string
    slug: string
    body: string
    is_published: boolean
    created_at: string
    updated_at: string
  } | null = null
  const submittedPublicationStates: boolean[] = []

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
    if (path === '/admin/pages' && route.request().method() === 'GET')
      return route.fulfill({
        json: {
          data: storedPage ? [storedPage] : [],
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: 25,
            total: storedPage ? 1 : 0,
          },
        },
      })
    if (path === '/admin/pages' && route.request().method() === 'POST') {
      const payload = route.request().postDataJSON()
      submittedPublicationStates.push(payload.is_published)
      storedPage = {
        id: 7,
        ...payload,
        created_at: timestamp,
        updated_at: timestamp,
      }
      return route.fulfill({ status: 201, json: { data: storedPage } })
    }
    if (path === '/admin/pages/7' && route.request().method() === 'PATCH') {
      const payload = route.request().postDataJSON()
      submittedPublicationStates.push(payload.is_published)
      storedPage = { ...storedPage!, ...payload }
      return route.fulfill({ json: { data: storedPage } })
    }
    if (path === '/admin/pages/7' && route.request().method() === 'DELETE') {
      storedPage = null
      return route.fulfill({ status: 204 })
    }
    return route.fulfill({
      status: 404,
      json: { error: { message: 'Unknown endpoint' } },
    })
  })

  await page.goto('/content')
  await expect(page.getByText('Страниц пока нет.')).toBeVisible()
  await page.getByRole('button', { name: 'Добавить страницу' }).click()
  await page.getByLabel('Заголовок').fill('О компании')
  await page.getByLabel('Адрес страницы (slug)').fill('about')
  await page.getByLabel('Текст страницы').fill('История компании.')
  await expect(
    page.getByRole('checkbox', { name: 'Опубликовать страницу' }),
  ).not.toBeChecked()
  await page.getByRole('button', { name: 'Сохранить' }).click()
  await expect(page.getByText('Черновик')).toBeVisible()

  await page
    .getByRole('button', { name: 'Редактировать страницу О компании' })
    .click()
  await page.getByText('Опубликовать страницу', { exact: true }).click()
  await expect(
    page.getByRole('checkbox', { name: 'Опубликовать страницу' }),
  ).toBeChecked()
  await page.getByRole('button', { name: 'Сохранить' }).click()
  await expect(page.getByText('Опубликована')).toBeVisible()
  expect(submittedPublicationStates).toEqual([false, true])

  await page
    .getByRole('button', { name: 'Удалить страницу О компании' })
    .click()
  await page
    .getByRole('dialog', { name: 'Удалить страницу?' })
    .getByRole('button', { name: 'Удалить' })
    .click()
  await expect(page.getByText('Страниц пока нет.')).toBeVisible()
})
