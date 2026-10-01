import { expect, test } from './fixtures'

test('legacy banner URL leads to page context without standalone resource UI', async ({
  page,
}) => {
  let resourceRequests = 0
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
          data: [],
          meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
        },
      })
    resourceRequests++
    return route.fulfill({ status: 404 })
  })
  await page.goto('/content?section=banners')
  await expect(page).toHaveURL(/section=pages/)
  await expect(
    page.getByRole('heading', { name: 'Страницы', exact: true }),
  ).toBeVisible()
  await expect(
    page.getByRole('heading', { name: 'Баннеры', exact: true }),
  ).toHaveCount(0)
  expect(resourceRequests).toBe(0)
})
