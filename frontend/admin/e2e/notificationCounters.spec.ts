import AxeBuilder from './approvedAccessibility'
import { expect, test, type Page } from './fixtures'
import { mockAdminBaseline } from './adminBaselineApi'
import { contactStatuses } from './contactStatusFixtures'

async function counters(page: Page, permissions?: string[]) {
  await mockAdminBaseline(page)
  const state = {
    contacts: 9857,
    orders: 1715,
    requests: { contacts: 0, orders: 0 },
  }
  await page.route('**/api/v1/admin/contact-request-counts', (route) =>
    route.fulfill({
      json: {
        data: { new: state.contacts, processing: 0, awaiting_client: 0 },
      },
    }),
  )
  if (permissions)
    await page.route('**/api/v1/admin/auth/me', (route) =>
      route.fulfill({
        json: {
          data: {
            id: 1,
            name: 'Сотрудник',
            email: 'admin@example.test',
            status: 'active',
            last_login_at: null,
            permissions,
          },
        },
      }),
    )
  for (const [kind, path] of [
    ['contacts', 'contact-requests'],
    ['orders', 'orders'],
  ] as const) {
    await page.route(`**/api/v1/admin/${path}?**`, async (route) => {
      const url = new URL(route.request().url())
      if (url.searchParams.get('per_page') !== '5') return route.fallback()
      state.requests[kind]++
      expect(url.searchParams.get('status')).toBe('new')
      expect(url.searchParams.has('type')).toBe(false)
      await route.fulfill({
        json: {
          data:
            kind === 'contacts' && state.contacts
              ? ['callback', 'email', 'partner'].map((type, i) => ({
                  id: i + 1,
                  type,
                  contact: {
                    name: 'Покупатель',
                    phone: '+79991234567',
                    email: null,
                  },
                  status: 'new',
                  assignee: null,
                  message: null,
                  source: 'site',
                  created_at: '2026-10-21T10:00:00Z',
                  assigned_at: null,
                  completed_at: null,
                }))
              : [],
          meta: {
            total: state[kind],
            current_page: 1,
            last_page: 1,
            per_page: 5,
          },
        },
      })
    })
  }
  return state
}

test('red counters share totals in desktop and compact navigation at all widths', async ({
  page,
}, testInfo) => {
  const state = await counters(page)
  await page.goto('/profile')
  const bell = page.getByRole('button', { name: 'Уведомления', exact: true })
  await expect(bell).toHaveAccessibleDescription('Новых уведомлений: 11572')
  // Navigation and bell consume one header scope rather than duplicate polling.
  expect(state.requests).toEqual({ contacts: 1, orders: 1 })
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    if (width < 1024)
      await page
        .getByRole('button', { name: 'Открыть меню', exact: true })
        .click()
    const nav = page.getByRole('navigation', {
      name: 'Основная навигация',
      exact: true,
    })
    await expect(
      nav.getByRole('link', { name: 'Обращения', exact: true }),
    ).toHaveAccessibleDescription('Новых обращений: 9857')
    await expect(
      nav.getByRole('link', { name: 'Заказы', exact: true }),
    ).toHaveAccessibleDescription('Новых заказов: 1715')
    for (const badge of await nav.locator('.admin-notification-count').all()) {
      await expect(badge).toHaveCSS('background-color', 'rgb(255, 107, 82)')
      await expect(badge).toHaveCSS('color', 'rgb(255, 255, 255)')
    }
    await page.screenshot({
      path: testInfo.outputPath(`notification-counters-${width}.png`),
    })
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    expect((await new AxeBuilder({ page }).analyze()).violations).toEqual([])
    if (width < 1024)
      await page
        .getByRole('button', { name: 'Закрыть меню', exact: true })
        .click()
    else {
      await page.evaluate(() => {
        document.body.style.minHeight = '1800px'
        window.scrollTo(0, 60)
      })
      await expect(page.getByRole('banner')).toHaveClass(/is-condensed/)
      const lastLink = await nav
        .getByRole('link', { name: 'Настройки', exact: true })
        .boundingBox()
      const actions = await page.locator('.admin-header-actions').boundingBox()
      expect(lastLink!.x + lastLink!.width).toBeLessThanOrEqual(actions!.x)
      expect(
        await page.evaluate(
          () => document.documentElement.scrollWidth <= innerWidth,
        ),
      ).toBe(true)
      await page.screenshot({
        path: testInfo.outputPath(
          `notification-counters-condensed-${width}.png`,
        ),
      })
      await page.evaluate(() => window.scrollTo(0, 0))
    }
  }
  await bell.click()
  const panel = page.getByRole('region', { name: 'Уведомления', exact: true })
  await expect(
    panel.getByRole('link', { name: /Обратный звонок #1/ }),
  ).toBeVisible()
  await expect(panel.getByRole('link', { name: /Email #2/ })).toBeVisible()
  await expect(
    panel.getByRole('link', { name: /Партнёрство #3/ }),
  ).toBeVisible()
  await expect(
    panel.getByRole('link', { name: 'Новые заказы · 1715' }),
  ).toHaveAttribute('href', '/orders')
  await expect(bell.locator('.admin-notification-count')).toHaveText('99+')
  await page.screenshot({
    path: testInfo.outputPath('notification-counters-panel.png'),
  })
  await page.keyboard.press('Escape')
  await expect(bell).toBeFocused()
  state.contacts = 0
  state.orders = 0
  await bell.click()
  await expect(bell.locator('.admin-notification-count')).toHaveCount(0)
  await expect(panel.getByRole('status')).toContainText('Новых уведомлений нет')
  await page.keyboard.press('Escape')
  await expect(
    page
      .getByRole('navigation', { name: 'Основная навигация' })
      .locator('.admin-notification-count'),
  ).toHaveCount(0)
})

for (const [permissions, expected, requests] of [
  [['contacts.view'], 9857, { contacts: 1, orders: 0 }],
  [['orders.view'], 1715, { contacts: 0, orders: 1 }],
  [[], 0, { contacts: 0, orders: 0 }],
] as const) {
  test(`header fetches only allowed domains: ${permissions.join(',') || 'none'}`, async ({
    page,
  }) => {
    const state = await counters(page, [...permissions])
    await page.goto('/profile')
    const bell = page.getByRole('button', { name: 'Уведомления', exact: true })
    if (expected)
      await expect(bell).toHaveAccessibleDescription(
        `Новых уведомлений: ${expected}`,
      )
    else await expect(bell.locator('.admin-notification-count')).toHaveCount(0)
    expect(state.requests).toEqual(requests)
  })
}

test('saving a contact status immediately updates the bell and navigation without a page reload', async ({
  page,
  browserIssueGuard,
}) => {
  const state = await counters(page)
  state.contacts = 1
  state.orders = 0
  let fail = true
  const contact = {
    id: 1,
    type: 'callback',
    contact: { name: 'Покупатель', phone: '+79991234567', email: null },
    status: 'new',
    assignee: null,
    message: null,
    source: 'site',
    created_at: '2026-10-21T10:00:00Z',
    assigned_at: null,
    completed_at: null,
  }
  await page.route('**/api/v1/admin/contact-workflow', (route) =>
    route.fulfill({
      json: {
        data: contactStatuses,
      },
    }),
  )
  await page.route('**/api/v1/admin/contact-requests/1', (route) =>
    route.fulfill({ json: { data: contact } }),
  )
  await page.route('**/api/v1/admin/contact-requests/1/status', (route) => {
    expect(route.request().method()).toBe('PATCH')
    expect(route.request().postDataJSON()).toEqual({ status: 'processing' })
    if (fail)
      return route.fulfill({
        status: 503,
        json: { error: { message: 'Не удалось сохранить статус.' } },
      })
    contact.status = 'processing'
    state.contacts = 0
    return route.fulfill({ json: { data: contact } })
  })
  browserIssueGuard.allowApiError(
    503,
    '/api/v1/admin/contact-requests/1/status',
  )
  let navigations = 0
  page.on('request', (request) => {
    if (
      request.resourceType() === 'document' &&
      request.frame() === page.mainFrame()
    )
      navigations++
  })
  await page.goto('/contacts?contact=1')
  const bell = page.getByRole('button', { name: 'Уведомления', exact: true })
  await expect(bell).toHaveAccessibleDescription('Новых уведомлений: 1')
  const requestsBeforeSave = state.requests.contacts
  await page
    .getByRole('button', { name: 'Начать обработку', exact: true })
    .click()
  await expect(page.getByRole('alert')).toContainText(
    'Не удалось сохранить статус.',
  )
  expect(state.requests.contacts).toBe(requestsBeforeSave)
  await expect(bell).toHaveAccessibleDescription('Новых уведомлений: 1')
  fail = false
  await page
    .getByRole('button', { name: 'Начать обработку', exact: true })
    .click()
  await expect(bell.locator('.admin-notification-count')).toHaveCount(0)
  await expect(
    page
      .getByRole('navigation', { name: 'Основная навигация' })
      .getByRole('link', { name: 'Обращения', exact: true })
      .locator('.admin-notification-count'),
  ).toHaveCount(0)
  expect(state.requests.contacts).toBe(requestsBeforeSave + 1)
  expect(navigations).toBe(1)
})
