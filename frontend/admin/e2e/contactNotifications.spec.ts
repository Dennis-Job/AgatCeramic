import AxeBuilder from './approvedAccessibility'
import { expect, test, type Page } from './fixtures'
import { contactStatuses } from './contactStatusFixtures'
import { contactStatistics } from './contactStatisticsFixtures'

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
  assignee: null,
  assigned_at: null,
  completed_at: null,
  created_at: '2026-10-21T10:00:00Z',
}

const order = {
  id: 58,
  order_number: 'AC-20261026-58',
  customer: {
    name: 'Покупатель с длинным именем для проверки переноса',
    phone: '+79991234567',
    email: null,
  },
  delivery_address: 'Москва',
  customer_comment: null,
  status: 'new',
  payment_status: 'not_paid',
  payment_amount: null,
  payment_method: null,
  payment_reference: null,
  total_amount: '1990.00',
  items: [],
  paid_at: null,
  completed_at: null,
  created_at: '2026-10-26T10:00:00Z',
}

async function mockApi(page: Page, permissions = ['contacts.view']) {
  const state = {
    fail: false,
    empty: false,
    notifications: 0,
    pending: null as Promise<void> | null,
    contactPages: [] as number[],
    mutations: 0,
    failOrders: false,
  }
  await page.route('**/api/v1/**', async (route) => {
    const url = new URL(route.request().url())
    const path = url.pathname.replace('/api/v1', '')
    if (!['GET', 'HEAD'].includes(route.request().method())) state.mutations++
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
      const currentPage = Number(url.searchParams.get('page') || 1)
      if (isNotification) {
        state.notifications++
        state.contactPages.push(currentPage)
        await state.pending
        expect(url.searchParams.has('type')).toBe(false)
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
          : [{ ...contact, id: currentPage === 1 ? 42 : 47 }]
        : [{ ...contact, id: 43 }]
      return route.fulfill({
        json: {
          data,
          meta: {
            current_page: currentPage,
            last_page: isNotification && !state.empty ? 2 : 1,
            per_page: isNotification ? 5 : 25,
            total: data.length ? 8 : 0,
          },
        },
      })
    }
    if (path === '/admin/orders') {
      if (state.failOrders)
        return route.fulfill({
          status: 503,
          json: { error: { message: 'Заказы недоступны' } },
        })
      return route.fulfill({
        json: {
          data: [order],
          meta: { current_page: 1, last_page: 1, per_page: 5, total: 1 },
        },
      })
    }
    if (path === '/admin/orders/58')
      return route.fulfill({ json: { data: order } })
    if (path === '/admin/order-statuses')
      return route.fulfill({
        json: {
          data: [
            { code: 'new', name: 'Новый', sort_order: 1, is_terminal: false },
          ],
        },
      })
    if (
      path === '/admin/contact-requests/42' ||
      path === '/admin/contact-requests/43'
    )
      return route.fulfill({
        json: { data: { ...contact, id: Number(path.split('/').at(-1)) } },
      })
    if (path === '/admin/contact-workflow')
      return route.fulfill({ json: { data: contactStatuses } })
    if (path === '/admin/contact-request-counts')
      return route.fulfill({
        json: { data: { new: 8, processing: 0, awaiting_client: 0 } },
      })
    if (path === '/admin/contact-statistics')
      return route.fulfill({
        json: {
          data: contactStatistics(url.searchParams.get('month') ?? '2026-10'),
        },
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
    const trigger = page.getByRole('link', {
      name: 'Уведомления',
      exact: true,
    })
    await trigger.click()
    await expect(page).toHaveURL('/notifications')
    await expect(trigger).toHaveAccessibleDescription('Новых уведомлений: 8')
    const panel = page.getByRole('region', { name: 'Уведомления', exact: true })
    const sections = await page
      .getByRole('navigation', { name: 'Разделы уведомлений' })
      .boundingBox()
    const list = await panel.boundingBox()
    if (width >= 1024)
      expect(sections!.x + sections!.width).toBeLessThanOrEqual(list!.x)
    else expect(sections!.y + sections!.height).toBeLessThanOrEqual(list!.y)
    await expect(
      panel.getByRole('link', { name: /Обратный звонок #42/ }),
    ).toBeVisible()
    await expect(panel).toContainText('+7 (999) 123-45-67')
    await expect(page.getByRole('main')).toContainText('до смены статуса')
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    expect(
      (await new AxeBuilder({ page }).include('#admin-main').analyze())
        .violations,
    ).toEqual([])
    await page.screenshot({
      path: testInfo.outputPath(`notifications-${width}.png`),
    })
  }
  await page.getByRole('link', { name: /Обратный звонок #42/ }).click()
  await expect(page).toHaveURL(/\/contacts\?contact=42$/)
  await expect(
    page.getByRole('heading', { name: 'Обращение #42', exact: true }),
  ).toBeFocused()
  await expect(
    page.getByRole('dialog', { name: 'Обращение #42', exact: true }),
  ).toBeVisible()
  await expect(
    page.getByRole('region', { name: 'Детали выбранной записи' }),
  ).toContainText('client@example.test')
  await expect(
    page.getByRole('region', { name: 'Уведомления', exact: true }),
  ).toHaveCount(0)
  await page.getByRole('button', { name: 'Закрыть обращение' }).click()
  await expect(page).toHaveURL(/\/contacts$/)
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
  ).toBeFocused()
  await page.keyboard.press('Escape')
  await expect(
    page
      .getByRole('button', { name: 'Открыть обращение 43', exact: true })
      .filter({ visible: true }),
  ).toBeFocused()
  await page.getByRole('link', { name: 'Уведомления', exact: true }).click()
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
  await page.goto('/notifications')
  const panel = page.getByRole('region', { name: 'Уведомления', exact: true })
  await expect(panel.getByRole('alert')).toContainText(
    'Не удалось загрузить обращения.',
  )
  await page.screenshot({
    path: testInfo.outputPath('notifications-error.png'),
  })
  state.fail = false
  state.empty = true
  await panel.getByRole('button', { name: 'Повторить' }).click()
  await expect(panel.getByRole('status')).toContainText('Новых уведомлений нет')
  await expect(panel).toBeFocused()
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
  await page.goto('/notifications')
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
  await page.getByRole('link', { name: 'Уведомления', exact: true }).click()
  await expect(
    page.getByRole('region', { name: 'Уведомления', exact: true }),
  ).toContainText('Нет доступа к уведомлениям о заказах и обращениях')
  expect(state.notifications).toBe(0)
})

test('orders and contacts have separate lists, pagination reaches older new contacts, and viewing never changes status', async ({
  page,
}, testInfo) => {
  const state = await mockApi(page, ['contacts.view', 'orders.view'])
  await page.goto('/notifications')
  const panel = page.getByRole('region', { name: 'Уведомления', exact: true })
  const sections = page.getByRole('navigation', { name: 'Разделы уведомлений' })
  await expect(
    sections.getByRole('link', { name: 'Заказы', exact: true }),
  ).toHaveAttribute('aria-current', 'page')
  const orderLink = panel.getByRole('link', { name: /Заказ AC-20261026-58/ })
  await expect(orderLink).toHaveAttribute('href', '/orders?order=58')
  await expect(
    panel.getByRole('link', { name: /Обратный звонок #42/ }),
  ).toHaveCount(0)
  await sections.getByRole('link', { name: 'Обращения', exact: true }).focus()
  await page.keyboard.press('Enter')
  await expect(page).toHaveURL('/notifications?section=contacts')
  await expect(
    panel.getByRole('link', { name: /Обратный звонок #42/ }),
  ).toBeVisible()
  await panel
    .getByRole('button', { name: 'Следующая страница', exact: true })
    .click()
  await expect(
    panel.getByRole('link', { name: /Обратный звонок #47/ }),
  ).toBeVisible()
  expect(state.contactPages).toContain(2)
  await expect(
    panel.getByRole('link', { name: /Обратный звонок #42/ }),
  ).toHaveCount(0)
  await sections.getByRole('link', { name: 'Заказы', exact: true }).click()
  await expect(orderLink).toBeVisible()
  await page.screenshot({
    path: testInfo.outputPath('notifications-orders.png'),
  })
  await orderLink.click()
  await expect(page).toHaveURL('/orders?order=58')
  await expect(
    page.getByRole('heading', { name: order.order_number, exact: true }),
  ).toBeVisible()
  expect(state.mutations).toBe(0)
})

test('one failed section keeps the other available', async ({
  page,
  browserIssueGuard,
}) => {
  const state = await mockApi(page, ['contacts.view', 'orders.view'])
  state.failOrders = true
  browserIssueGuard.allowApiError(503, '/api/v1/admin/orders')
  await page.goto('/notifications')
  const panel = page.getByRole('region', { name: 'Уведомления', exact: true })
  await expect(panel.getByRole('alert')).toContainText(
    'Не удалось загрузить заказы.',
  )
  await page
    .getByRole('navigation', { name: 'Разделы уведомлений' })
    .getByRole('link', { name: 'Обращения', exact: true })
    .click()
  await expect(
    panel.getByRole('link', { name: /Обратный звонок #42/ }),
  ).toBeVisible()
  await expect(
    panel.getByRole('status').filter({ hasText: 'Новых уведомлений нет' }),
  ).toHaveCount(0)
})

test('only permitted sections appear even when a different section is requested in the URL', async ({
  page,
}) => {
  await mockApi(page, ['contacts.view'])
  await page.goto('/notifications?section=orders')
  const sections = page.getByRole('navigation', { name: 'Разделы уведомлений' })
  await expect(
    sections.getByRole('link', { name: 'Заказы', exact: true }),
  ).toHaveCount(0)
  await expect(
    sections.getByRole('link', { name: 'Обращения', exact: true }),
  ).toHaveAttribute('aria-current', 'page')
  await expect(
    page.getByRole('link', { name: /Обратный звонок #42/ }),
  ).toBeVisible()
})
