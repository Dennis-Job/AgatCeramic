import { expect, test } from './fixtures'

test('legacy slider URL restores page selection and uses contextual editor', async ({
  page,
}) => {
  const content = {
    id: 7,
    title: 'Материалы',
    slug: 'materials',
    body: '',
    blocks: [
      { id: 'hero', type: 'hero', enabled: true, data: { slider_id: null } },
    ],
    is_published: false,
    has_unpublished_changes: false,
    published_at: null,
    created_at: '',
    updated_at: '',
  }
  await page.route('**/api/v1/**', (route) => {
    const path = new URL(route.request().url()).pathname
    if (path.endsWith('/admin/auth/me'))
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
    if (path.endsWith('/admin/pages'))
      return route.fulfill({
        json: {
          data: [content],
          meta: { current_page: 1, last_page: 1, per_page: 25, total: 1 },
        },
      })
    if (path.endsWith('/admin/sliders'))
      return route.fulfill({
        json: {
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
        },
      })
    return route.fulfill({ status: 404 })
  })
  await page.goto('/content?section=sliders&page=7&block=hero')
  await expect(page).toHaveURL(/section=pages&page=7&block=hero/)
  await expect(
    page.getByRole('region', { name: 'Настройки: Слайдер' }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Создать слайдер', exact: true }),
  ).toBeVisible()
})
