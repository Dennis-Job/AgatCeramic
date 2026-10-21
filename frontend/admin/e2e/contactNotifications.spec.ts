import AxeBuilder from './approvedAccessibility'
import { expect, test, type Page } from './fixtures'

const contact = {
  id: 42,
  type: 'callback',
  contact: {
    name: 'Иван Петров с длинным именем для проверки переноса',
    phone: '+79991234567',
    email: 'client@example.test',
  },
  message: null,
  source: 'site',
  status: 'new',
  assigned_at: null,
  completed_at: null,
  created_at: '2026-10-21T10:00:00Z',
}

async function mockApi(page: Page, permissions = ['contacts.view']) {
  const state = {
    fail: false,
    empty: false,
    notifications: 0,
    pending: null as Promise<void> | null,
  }
  await page.route('**/api/v1/**', async (route) => {
    const url = new URL(route.request().url())
    const path = url.pathname.replace('/api/v1', '')
    if (path === '/admin/auth/me')
      return route.fulfill({
        json: {
          data: {
            id: 1,
            name: 'Сотрудник',
            email: 'admin@example.test',
            status: 'active',
            permissions,
            last_login_at: null,
          },
        },
      })
    if (path === '/admin/contact-requests') {
      const isNotification = url.searchParams.get('per_page') === '5'
      if (isNotification) {
        state.notifications++
        await state.pending
        expect(url.searchParams.get('type')).toBe('callback')
        expect(url.searchParams.get('status')).toBe('new')
        if (state.fail)
          return route.fulfill({
            status: 503,
            json: { error: { message: 'Уведомления временно недоступны' } },
          })
      }
      const data = isNotification
        ? state.empty
          ? []
          : [contact]
        : [{ ...contact, id: 43 }]
      return route.fulfill({
        json: {
          data,
          meta: {
            current_page: 1,
            last_page: 1,
            per_page: isNotification ? 5 : 25,
            total: data.length ? 8 : 0,
          },
        },
      })
    }
    if (
      path === '/admin/contact-requests/42' ||
      path === '/admin/contact-requests/43'
    )
      return route.fulfill({
        json: { data: { ...contact, id: Number(path.split('/').at(-1)) } },
      })
    return route.fulfill({ json: { data: [] } })
  })
  return state
}

test('notification list opens contact details, remains keyboard usable and fits all widths', async ({
  page,
}, testInfo) => {
  await mockApi(page)
  await page.goto('/contacts')
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 800 })
    const trigger = page.getByRole('button', {
      name: 'Уведомления',
      exact: true,
    })
    await trigger.click()
    await expect(trigger).toHaveAccessibleDescription(
      'Новых заявок на звонок: 8',
    )
    const panel = page.getByRole('region', { name: 'Уведомления', exact: true })
    await expect(
      panel.getByRole('link', { name: /Обратный звонок #42/ }),
    ).toBeVisible()
    await expect(panel).toContainText('+7 (999) 123-45-67')
    await expect(panel).toContainText('до смены статуса')
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    expect(
      (
        await new AxeBuilder({ page })
          .include('#admin-notifications-panel')
          .analyze()
      ).violations,
    ).toEqual([])
    await page.screenshot({
      path: testInfo.outputPath(`notifications-${width}.png`),
    })
    await page.keyboard.press('Escape')
    await expect(trigger).toBeFocused()
  }
  await page.getByRole('button', { name: 'Уведомления', exact: true }).click()
  await page.getByRole('link', { name: /Обратный звонок #42/ }).click()
  await expect(page).toHaveURL(/\/contacts\?contact=42$/)
  await expect(
    page.getByRole('heading', { name: 'Обращение #42', exact: true }),
  ).toBeVisible()
  await expect(
    page.getByRole('region', { name: 'Детали выбранной записи' }),
  ).toContainText('client@example.test')
  await expect(
    page.getByRole('region', { name: 'Уведомления', exact: true }),
  ).toHaveCount(0)
  await page.getByRole('button', { name: 'Вернуться к списку' }).click()
  await expect(
    page.getByRole('region', { name: 'Очередь обращений' }),
  ).toBeFocused()
  await page
    .getByRole('button', { name: 'Открыть обращение 43', exact: true })
    .filter({ visible: true })
    .click()
  await expect(page).toHaveURL(/\/contacts\?contact=43$/)
  await expect(
    page.getByRole('heading', { name: 'Обращение #43', exact: true }),
  ).toBeVisible()
  await page.getByRole('button', { name: 'Уведомления', exact: true }).click()
  await page.getByRole('link', { name: /Обратный звонок #42/ }).click()
  await expect(
    page.getByRole('heading', { name: 'Обращение #42', exact: true }),
  ).toBeVisible()
})

test('notification error supports retry and a processed queue becomes empty', async ({
  page,
  browserIssueGuard,
}, testInfo) => {
  const state = await mockApi(page)
  state.fail = true
  browserIssueGuard.allowApiError(503, '/api/v1/admin/contact-requests')
  await page.goto('/contacts')
  await page.getByRole('button', { name: 'Уведомления', exact: true }).click()
  const panel = page.getByRole('region', { name: 'Уведомления', exact: true })
  await expect(panel.getByRole('alert')).toContainText(
    'Уведомления временно недоступны',
  )
  await page.screenshot({
    path: testInfo.outputPath('notifications-error.png'),
  })
  state.fail = false
  state.empty = true
  await panel.getByRole('button', { name: 'Повторить' }).click()
  await expect(panel.getByRole('status')).toContainText(
    'Новых заявок на звонок нет',
  )
  await page.screenshot({
    path: testInfo.outputPath('notifications-empty.png'),
  })
})

test('notification initial loading is announced before the result appears', async ({
  page,
}, testInfo) => {
  const state = await mockApi(page)
  let finish!: () => void
  state.pending = new Promise<void>((resolve) => {
    finish = resolve
  })
  await page.goto('/contacts')
  await page.getByRole('button', { name: 'Уведомления', exact: true }).click()
  const panel = page.getByRole('region', { name: 'Уведомления', exact: true })
  await expect(panel.getByRole('status')).toContainText('Загрузка уведомлений')
  await page.screenshot({
    path: testInfo.outputPath('notifications-loading.png'),
  })
  finish()
  await expect(
    panel.getByRole('link', { name: /Обратный звонок #42/ }),
  ).toBeVisible()
})

test('employees without contacts permission do not fetch callback notifications', async ({
  page,
}) => {
  const state = await mockApi(page, [])
  await page.goto('/profile')
  await page.getByRole('button', { name: 'Уведомления', exact: true }).click()
  await expect(
    page.getByRole('region', { name: 'Уведомления', exact: true }),
  ).toContainText('Новых уведомлений нет')
  expect(state.notifications).toBe(0)
})
