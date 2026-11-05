import { orderStatistics } from './orderStatisticsFixtures'
import AxeBuilder from './approvedAccessibility'
import { expect, test, type Page } from './fixtures'

const timestamp = '2026-09-11T10:00:00.000Z'
const order = {
  id: 1,
  order_number: 'AC-20260911-ABCDEF1234',
  customer: {
    name: 'Иван Петров',
    phone: '+79990000000',
    email: 'ivan@example.test',
  },
  delivery_address: 'Москва, ул. Пример, 1',
  customer_comment: 'Позвонить за час',
  assignee: null as { id: number; name: string } | null,
  assigned_at: null as string | null,
  status: 'new',
  payment_status: 'not_paid',
  payment_amount: null,
  payment_method: null,
  payment_reference: null,
  total_amount: '2500.00',
  items: [
    {
      product_id: 1,
      product_name: 'Керамогранит',
      product_sku: '1000001',
      unit_price: '1250.00',
      quantity: 2,
      line_total: '2500.00',
    },
  ],
  paid_at: null,
  completed_at: null,
  created_at: timestamp,
}
const responsePage = {
  data: [order],
  meta: {
    current_page: 1,
    last_page: 1,
    per_page: 25,
    total: 1,
    from: 1,
    to: 1,
  },
}

async function mockOrdersApi(
  page: Page,
  permissions = ['orders.view', 'orders.manage', 'payments.manage'],
) {
  const record = structuredClone(order)
  const state = {
    record,
    failCandidates: false,
    failAssignment: false,
    holdAssignment: null as Promise<void> | null,
    candidates: [
      { id: 2, name: 'Анна Менеджер' },
      { id: 3, name: 'Иван Менеджер' },
    ],
    assignments: [] as (number | null)[],
  }
  const comments: {
    id: number
    body: string
    author: null
    created_at: string
  }[] = []
  await page.route('**/sanctum/csrf-cookie', (route) =>
    route.fulfill({ status: 204 }),
  )
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname.replace('/api/v1', '')
    if (path === '/admin/order-assignees')
      return state.failCandidates
        ? route.fulfill({
            status: 503,
            json: { error: { message: 'Список сотрудников недоступен' } },
          })
        : route.fulfill({ json: { data: state.candidates } })
    if (path === '/admin/order-status-counts')
      return route.fulfill({
        json: { data: { new: 3, working: 0, shipped: 0 } },
      })
    if (path === '/admin/order-statistics')
      return route.fulfill({
        json: {
          data: orderStatistics(
            new URL(route.request().url()).searchParams.get('month') ??
              '2026-10',
          ),
        },
      })
    if (path === '/admin/auth/me')
      return route.fulfill({
        json: {
          data: {
            id: 1,
            name: 'Менеджер',
            email: 'manager@example.test',
            status: 'active',
            last_login_at: timestamp,
            permissions,
          },
        },
      })
    if (path === '/admin/order-statuses')
      return route.fulfill({
        json: {
          data: [
            { code: 'new', name: 'Новый', sort_order: 0, is_terminal: false },
            {
              code: 'processing',
              name: 'В обработке',
              sort_order: 1,
              is_terminal: false,
            },
            ...[
              ['awaiting_clarification', 'Ожидает уточнения'],
              ['confirmed', 'Подтверждён'],
              ['awaiting_payment', 'Ожидает оплаты'],
              ['paid', 'Оплачен'],
              ['picking', 'Комплектация'],
              ['shipped', 'Передан в доставку'],
              ['completed', 'Завершён'],
              ['cancelled', 'Отменён'],
            ].map(([code, name], index) => ({
              code,
              name,
              sort_order: index + 2,
              is_terminal: ['completed', 'cancelled'].includes(code!),
            })),
          ],
        },
      })
    if (path === '/admin/orders') {
      const query = new URL(route.request().url()).searchParams
      const matches =
        (!query.get('assignee_id') ||
          String(record.assignee?.id) === query.get('assignee_id')) &&
        (query.get('unassigned') !== '1' || !record.assignee)
      return route.fulfill({
        json: {
          ...responsePage,
          data: matches ? [record] : [],
          meta: { ...responsePage.meta, total: matches ? 1 : 0 },
        },
      })
    }
    if (path === '/admin/orders/1/assignee') {
      await state.holdAssignment
      if (state.failAssignment)
        return route.fulfill({
          status: 422,
          json: { error: { message: 'Назначение отклонено' } },
        })
      const id = route.request().postDataJSON().assignee_id as number | null
      state.assignments.push(id)
      record.assignee = state.candidates.find((item) => item.id === id) ?? null
      record.assigned_at = id === null ? null : timestamp
      return route.fulfill({ status: 204 })
    }
    if (path === '/admin/orders/1/status') {
      record.status = route.request().postDataJSON().status
      return route.fulfill({ json: { data: record } })
    }
    if (path === '/admin/orders/1/payment') {
      Object.assign(record, route.request().postDataJSON())
      return route.fulfill({ json: { data: record } })
    }
    if (path === '/admin/orders/1')
      return route.fulfill({ json: { data: record } })
    if (path === '/admin/orders/1/status-history')
      return route.fulfill({
        json: {
          ...responsePage,
          data: [
            {
              id: 1,
              from_status: 'new',
              to_status: 'processing',
              actor: { id: 1, name: 'Менеджер' },
              occurred_at: timestamp,
            },
          ],
        },
      })
    if (path === '/admin/orders/1/comments') {
      if (route.request().method() === 'POST') {
        const entry = {
          id: comments.length + 1,
          body: route.request().postDataJSON().body,
          author: null,
          created_at: timestamp,
        }
        comments.push(entry)
        return route.fulfill({ json: { data: entry } })
      }
      return route.fulfill({ json: { ...responsePage, data: comments } })
    }
    return route.fulfill({
      status: 404,
      json: { error: { message: `Unhandled ${path}` } },
    })
  })
  return state
}

test('orders workspace exposes protected snapshot, keyboard selection, and manager controls', async ({
  page,
}) => {
  await mockOrdersApi(page)
  await page.goto('/orders')
  await expect(
    page.getByRole('heading', { level: 1, name: 'Заказы' }),
  ).toBeVisible()
  const opener = page.getByRole('button', {
    name: `Открыть заказ ${order.order_number}`,
  })
  await opener.focus()
  await page.keyboard.press('Enter')
  await expect(
    page.getByRole('heading', { level: 3, name: 'Клиент и доставка' }),
  ).toBeVisible()
  await expect(page.getByText(order.delivery_address)).toBeVisible()
  await expect(
    page
      .getByText('+7 (999) 000-00-00', { exact: true })
      .filter({ visible: true })
      .first(),
  ).toBeVisible()
  await expect(opener).toHaveAttribute('aria-current', 'true')
  await expect(
    page.getByRole('heading', { name: 'Статус заказа' }),
  ).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Оплата' })).toBeVisible()
  await expect(page.getByLabel('Новый внутренний комментарий')).toBeVisible()
  const results = await new AxeBuilder({ page }).analyze()
  expect(
    results.violations.filter((violation) =>
      ['serious', 'critical'].includes(violation.impact ?? ''),
    ),
  ).toEqual([])
})

test('orders workspace keeps management controls hidden for a view-only employee', async ({
  page,
}) => {
  await mockOrdersApi(page, ['orders.view'])
  await page.goto('/orders')
  await page
    .getByRole('button', { name: `Открыть заказ ${order.order_number}` })
    .click()
  await expect(
    page.getByRole('heading', { name: 'Статус заказа' }),
  ).toHaveCount(0)
  await expect(page.getByRole('heading', { name: 'Оплата' })).toHaveCount(0)
  await expect(page.getByLabel('Новый внутренний комментарий')).toHaveCount(0)
})

for (const width of [320, 640, 768, 1024, 1280]) {
  test(`orders workspace remains usable at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 800 })
    await mockOrdersApi(page)
    await page.goto('/orders')
    await page
      .getByRole('button', { name: `Открыть заказ ${order.order_number}` })
      .click()
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= window.innerWidth,
      ),
    ).toBe(true)
    await expect(
      page.getByRole('heading', { level: 3, name: 'Внутренние комментарии' }),
    ).toBeVisible()
  })
}

test('payment input groups thousands and sends an unformatted decimal amount', async ({
  page,
}) => {
  await mockOrdersApi(page)
  await page.goto('/orders')
  await page
    .getByRole('button', { name: `Открыть заказ ${order.order_number}` })
    .click()
  const payment = page
    .locator('section')
    .filter({ has: page.getByRole('heading', { name: 'Оплата', exact: true }) })
    .last()
  await payment.getByRole('button', { name: 'Статус оплаты' }).click()
  await page
    .getByRole('button', { name: 'Частично оплачено', exact: true })
    .click()
  const amount = page.getByRole('textbox', { name: 'Сумма оплаты' })
  await amount.fill('12 345,67')
  await expect(amount).toHaveValue('12\u00a0345,67')
  await page.getByRole('textbox', { name: 'Способ оплаты' }).fill('Наличные')
  const request = page.waitForRequest(
    (req) =>
      req.method() === 'PATCH' && req.url().endsWith('/admin/orders/1/payment'),
  )
  await page.getByRole('button', { name: 'Сохранить оплату' }).click()
  expect((await request).postDataJSON()).toMatchObject({
    payment_amount: '12345.67',
    payment_method: 'Наличные',
  })
})

test('order drawer follows deep links, protects all drafts and returns focus', async ({
  page,
}) => {
  await mockOrdersApi(page)
  await page.goto('/orders?order=1')
  const drawer = page.getByRole('dialog', {
    name: order.order_number,
    exact: true,
  })
  await expect(drawer).toBeVisible()
  await expect(page.locator('.admin-side-panel-enter-active')).toHaveCount(0)
  await expect(
    drawer.getByRole('heading', { name: order.order_number }),
  ).toBeFocused()
  const comment = drawer.getByLabel('Новый внутренний комментарий')
  await comment.fill('Перезвонить завтра')
  await page.keyboard.press('Escape')
  const confirmation = page.getByRole('dialog', {
    name: 'Закрыть без сохранения изменений?',
  })
  await expect(confirmation).toBeVisible()
  await confirmation
    .getByRole('button', { name: 'Отмена', exact: true })
    .click()
  await expect(comment).toHaveValue('Перезвонить завтра')
  await drawer.getByRole('button', { name: 'Добавить', exact: true }).click()
  await expect(
    drawer.getByText('Перезвонить завтра', { exact: true }),
  ).toBeVisible()
  await expect(comment).toHaveValue('')
  await drawer.getByRole('button', { name: 'Новый статус заказа' }).click()
  await page.getByRole('button', { name: 'В обработке', exact: true }).click()
  await page.keyboard.press('Escape')
  await expect(confirmation).toBeVisible()
  await confirmation
    .getByRole('button', { name: 'Отмена', exact: true })
    .click()
  await drawer.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(
    drawer.getByRole('button', { name: 'Сохранить', exact: true }),
  ).toBeEnabled()
  await drawer
    .getByRole('button', { name: 'Статус оплаты', exact: true })
    .click()
  await page.getByRole('button', { name: 'Оплачено', exact: true }).click()
  await drawer.getByRole('button', { name: 'Закрыть заказ' }).click()
  await expect(confirmation).toBeVisible()
  await confirmation
    .getByRole('button', { name: 'Закрыть без сохранения', exact: true })
    .click()
  await expect(drawer).toHaveCount(0)
  await expect(page).toHaveURL(/\/orders$/)
  await expect(
    page.getByRole('region', { name: 'Очередь заказов', exact: true }),
  ).toBeFocused()
  const opener = page.getByRole('button', {
    name: `Открыть заказ ${order.order_number}`,
  })
  await opener.click()
  await expect(drawer).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(drawer).toHaveCount(0)
  await expect(opener).toBeFocused()
})

test('a deep-linked drawer focuses immediately while details are pending and ignores responses after close', async ({
  page,
}) => {
  await mockOrdersApi(page)
  let release!: () => void
  const pending = new Promise<void>((resolve) => {
    release = resolve
  })
  await page.route('**/api/v1/admin/orders/1', async (route) => {
    await pending
    await route.fulfill({ json: { data: order } })
  })
  await page.goto('/orders?order=1')
  const drawer = page.getByRole('dialog', { name: 'Заказ #1', exact: true })
  await expect(drawer.getByRole('heading', { name: 'Заказ #1' })).toBeFocused()
  await expect(drawer.getByRole('status')).toContainText(
    'Загрузка деталей заказа',
  )
  await page.keyboard.press('Escape')
  await expect(drawer).toHaveCount(0)
  release()
  await expect(page).toHaveURL(/\/orders$/)
  await expect(page.getByRole('dialog')).toHaveCount(0)
  expect(await page.evaluate(() => document.body.style.overflow)).not.toBe(
    'hidden',
  )
})

test('order filters use tabs and a compact select while month statistics stay independent', async ({
  page,
}, testInfo) => {
  await mockOrdersApi(page)
  await page.clock.setFixedTime(new Date('2026-10-22T09:00:00Z'))
  await page.goto('/orders')
  const statistics = page.getByRole('region', {
    name: 'Статистика заказов',
    exact: true,
  })
  await expect(statistics).toContainText('12')
  await expect(statistics).toContainText('Нет базы')
  const filterRequest = page.waitForRequest(
    (req) =>
      req.url().includes('/admin/orders?') &&
      new URL(req.url()).searchParams
        .getAll('statuses[]')
        .includes('processing'),
  )
  await page.getByRole('tab', { name: /^В работе/ }).click()
  const grouped = new URL((await filterRequest).url()).searchParams
  expect(grouped.get('status')).toBeNull()
  expect(grouped.getAll('statuses[]')).toEqual([
    'processing',
    'awaiting_clarification',
    'confirmed',
    'awaiting_payment',
    'paid',
    'picking',
  ])
  for (const width of [1024, 1102, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    const tabs = page
      .getByRole('tablist', { name: 'Статус заказа' })
      .getByRole('tab')
    await expect(tabs).toHaveText([
      'Все',
      /Новые\s*3/,
      /В работе\s*0/,
      /В доставке\s*0/,
      'Завершённые',
      'Отменённые',
    ])
    const tops = await tabs.evaluateAll((items) =>
      items.map((item) => item.getBoundingClientRect().top),
    )
    expect(new Set(tops).size).toBe(1)
    await page.screenshot({
      path: testInfo.outputPath(`orders-tabs-${width}.png`),
    })
  }
  await expect(
    statistics.locator('[data-statistic-metric=total]'),
  ).toContainText('12')
  await page.setViewportSize({ width: 768, height: 900 })
  await expect(
    page.getByRole('tablist', { name: 'Статус заказа' }),
  ).toHaveCount(0)
  await expect(
    page.getByRole('button', { name: 'Статус заказа', exact: true }),
  ).toContainText('В работе')
  await page.getByRole('button', { name: 'Статус заказа', exact: true }).click()
  await page.getByRole('button', { name: 'Все', exact: true }).click()
  await page
    .getByRole('button', { name: 'Месяц статистики', exact: true })
    .click()
  const picker = page.getByRole('region', {
    name: 'Месяц статистики: выбор месяца',
  })
  await picker.getByLabel('Год', { exact: true }).fill('2025')
  const monthRequest = page.waitForRequest((req) =>
    req.url().includes('/admin/order-statistics?month=2025-02'),
  )
  await picker.getByRole('button', { name: 'Февраль', exact: true }).click()
  await monthRequest
  await expect(statistics).toHaveAttribute('aria-busy', 'false')
  await page.setViewportSize({ width: 1280, height: 900 })
  await page.screenshot({
    path: testInfo.outputPath('orders-desktop.png'),
    fullPage: true,
  })
  await page
    .getByRole('button', { name: `Открыть заказ ${order.order_number}` })
    .click()
  await expect(page.locator('.admin-side-panel-enter-active')).toHaveCount(0)
  for (const width of [320, 640, 768, 1024, 1280]) {
    await page.setViewportSize({ width, height: 900 })
    const drawer = page.getByRole('dialog', {
      name: order.order_number,
      exact: true,
    })
    await expect(drawer).toBeVisible()
    const box = await drawer.boundingBox()
    expect(box!.width).toBeCloseTo(Math.min(544, width))
    expect(box!.x + box!.width).toBeCloseTo(width)
    expect(
      await page.evaluate(
        () => document.documentElement.scrollWidth <= innerWidth,
      ),
    ).toBe(true)
    await page.screenshot({
      path: testInfo.outputPath(`orders-drawer-${width}.png`),
    })
  }
  await page.getByRole('button', { name: 'Новый статус заказа' }).click()
  await expect(
    page.getByRole('button', { name: 'Ожидает оплаты', exact: true }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Оплачен', exact: true }),
  ).toBeVisible()
  await expect(
    page.getByRole('button', { name: 'Комплектация', exact: true }),
  ).toBeVisible()
})

test('manager assignment, reassignment and removal preserve drafts and update the list filters', async ({
  page,
}, testInfo) => {
  const state = await mockOrdersApi(page)
  await page.goto('/orders?order=1')
  const drawer = page.getByRole('dialog', {
    name: order.order_number,
    exact: true,
  })
  const assignee = drawer.getByRole('button', {
    name: 'Ответственный за заказ',
    exact: true,
  })
  await expect(assignee).toBeEnabled()
  await assignee.click()
  await expect(
    page.getByRole('group', {
      name: 'Ответственный за заказ: варианты',
      exact: true,
    }),
  ).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(drawer).toBeVisible()
  await expect(assignee).toBeFocused()
  await expect(
    page.getByRole('group', {
      name: 'Ответственный за заказ: варианты',
      exact: true,
    }),
  ).toHaveCount(0)
  await drawer.getByRole('button', { name: 'Новый статус заказа' }).click()
  await page.getByRole('button', { name: 'В обработке', exact: true }).click()
  await drawer
    .getByRole('button', { name: 'Статус оплаты', exact: true })
    .click()
  await page
    .getByRole('button', { name: 'Частично оплачено', exact: true })
    .click()
  await drawer.getByRole('textbox', { name: 'Сумма оплаты' }).fill('100')
  await drawer.getByRole('textbox', { name: 'Способ оплаты' }).fill('Наличные')
  await drawer
    .getByLabel('Новый внутренний комментарий')
    .fill('Черновик менеджера')
  let release!: () => void
  state.holdAssignment = new Promise<void>((resolve) => {
    release = resolve
  })
  await assignee.click()
  await page.getByRole('button', { name: 'Анна Менеджер', exact: true }).click()
  await expect(
    drawer.getByRole('button', { name: 'Закрыть заказ' }),
  ).toBeDisabled()
  await page.keyboard.press('Escape')
  await expect(drawer).toBeVisible()
  release()
  await expect(assignee).toContainText('Анна Менеджер')
  await expect(
    drawer.getByRole('button', { name: 'Новый статус заказа' }),
  ).toContainText('В обработке')
  await expect(
    drawer.getByRole('textbox', { name: 'Способ оплаты' }),
  ).toHaveValue('Наличные')
  await expect(drawer.getByLabel('Новый внутренний комментарий')).toHaveValue(
    'Черновик менеджера',
  )
  await assignee.click()
  await page.getByRole('button', { name: 'Иван Менеджер', exact: true }).click()
  await expect(assignee).toContainText('Иван Менеджер')
  await page.screenshot({ path: testInfo.outputPath('assigned-drafts.png') })
  await drawer.getByRole('button', { name: 'Закрыть заказ' }).click()
  await page
    .getByRole('dialog', { name: 'Закрыть без сохранения изменений?' })
    .getByRole('button', { name: 'Закрыть без сохранения', exact: true })
    .click()
  await expect(
    page.getByRole('cell', { name: 'Иван Менеджер', exact: true }),
  ).toBeVisible()
  const filter = page.getByRole('button', {
    name: 'Ответственный в списке заказов',
    exact: true,
  })
  await filter.click()
  await page.getByRole('button', { name: 'Анна Менеджер', exact: true }).click()
  await expect(
    page.getByText('Заказы не найдены.', { exact: true }),
  ).toBeVisible()
  await filter.click()
  await page.getByRole('button', { name: 'Иван Менеджер', exact: true }).click()
  await page
    .getByRole('button', { name: `Открыть заказ ${order.order_number}` })
    .click()
  await assignee.click()
  await page.getByRole('button', { name: 'Не назначен', exact: true }).click()
  await expect(assignee).toContainText('Не назначен')
  await drawer.getByRole('button', { name: 'Закрыть заказ' }).click()
  await expect(
    page.getByText('Заказы не найдены.', { exact: true }),
  ).toBeVisible()
  const request = page.waitForRequest(
    (req) =>
      req.url().includes('/admin/orders?') &&
      new URL(req.url()).searchParams.get('unassigned') === '1',
  )
  await filter.click()
  await page.getByRole('button', { name: 'Не назначен', exact: true }).click()
  await request
  await expect(
    page.getByRole('button', { name: `Открыть заказ ${order.order_number}` }),
  ).toBeVisible()
  expect(state.assignments).toEqual([2, 3, null])
})

test('candidate and mutation errors are recoverable without granting view-only assignment access', async ({
  page,
  browserIssueGuard,
}) => {
  const state = await mockOrdersApi(page)
  state.failCandidates = true
  browserIssueGuard.allowApiError(503, '/api/v1/admin/order-assignees')
  browserIssueGuard.allowApiError(422, '/api/v1/admin/orders/1/assignee')
  await page.goto('/orders?order=1')
  const assignee = page.getByRole('button', {
    name: 'Ответственный за заказ',
    exact: true,
  })
  await expect(assignee).toBeDisabled()
  state.failCandidates = false
  await page
    .getByRole('button', { name: 'Повторить загрузку сотрудников' })
    .click()
  await expect(assignee).toBeEnabled()
  state.failAssignment = true
  await assignee.click()
  await page.getByRole('button', { name: 'Анна Менеджер', exact: true }).click()
  await expect(page.getByRole('alert')).toContainText('Назначение отклонено')
  await expect(assignee).toContainText('Не назначен')
  await expect(assignee).toBeEnabled()
  await page.getByRole('button', { name: 'Закрыть заказ' }).click()
  await mockOrdersApi(page, ['orders.view']).then((viewer) => {
    viewer.record.assignee = { id: 2, name: 'Анна Менеджер' }
  })
  await page.reload()
  await page
    .getByRole('button', { name: `Открыть заказ ${order.order_number}` })
    .click()
  await expect(
    page.getByRole('button', { name: 'Ответственный за заказ', exact: true }),
  ).toHaveCount(0)
  await expect(
    page
      .getByRole('dialog', { name: order.order_number })
      .getByText('Анна Менеджер', { exact: true }),
  ).toBeVisible()
})

test('active queue counters update after a saved status and appear in compact selects', async ({
  page,
}, testInfo) => {
  const state = await mockOrdersApi(page)
  let requests = 0
  await page.route('**/api/v1/admin/order-status-counts', (route) => {
    requests++
    return route.fulfill({
      json: {
        data: {
          new: state.record.status === 'new' ? 3 : 2,
          working: state.record.status === 'new' ? 0 : 1,
          shipped: 0,
        },
      },
    })
  })
  await page.goto('/orders')
  await expect(page.locator('[data-order-count=new]')).toHaveText('3')
  await expect(page.locator('[data-order-count=working]')).toHaveText('0')
  await expect(page.locator('[data-order-count=shipped]')).toHaveText('0')
  await page
    .getByRole('button', { name: `Открыть заказ ${order.order_number}` })
    .click()
  const drawer = page.getByRole('dialog', {
    name: order.order_number,
    exact: true,
  })
  await drawer.getByRole('button', { name: 'Новый статус заказа' }).click()
  await page.getByRole('button', { name: 'В обработке', exact: true }).click()
  await drawer.getByRole('button', { name: 'Сохранить', exact: true }).click()
  await expect(page.locator('[data-order-count=new]')).toHaveText('2')
  await expect(page.locator('[data-order-count=working]')).toHaveText('1')
  await expect(
    drawer.getByRole('button', { name: 'Закрыть заказ' }),
  ).toBeEnabled()
  await drawer.getByRole('button', { name: 'Закрыть заказ' }).click()
  await expect(drawer).toHaveCount(0)
  const calls = requests
  await page.getByRole('button', { name: 'Статус оплаты', exact: true }).click()
  await page.getByRole('button', { name: 'Оплачено', exact: true }).click()
  await page.getByRole('button', { name: 'Найти', exact: true }).click()
  expect(requests).toBe(calls)
  await page.screenshot({
    path: testInfo.outputPath('queue-counts-desktop.png'),
  })
  for (const width of [320, 640, 768]) {
    await page.setViewportSize({ width, height: 900 })
    const select = page.getByRole('button', {
      name: 'Статус заказа',
      exact: true,
    })
    await select.click()
    await expect(
      page.getByRole('button', { name: 'Новые (2)', exact: true }),
    ).toBeVisible()
    await expect(
      page.getByRole('button', { name: 'В работе (1)', exact: true }),
    ).toBeVisible()
    await expect(
      page.getByRole('button', { name: 'В доставке (0)', exact: true }),
    ).toBeVisible()
    await page.screenshot({
      path: testInfo.outputPath(`queue-counts-${width}.png`),
    })
    await page.keyboard.press('Escape')
  }
})

test('failed queue counters stay unknown and retry shows real zeroes without blocking orders', async ({
  page,
  browserIssueGuard,
}) => {
  const state = await mockOrdersApi(page)
  state.record.status = 'completed'
  let fail = true
  browserIssueGuard.allowApiError(503, '/api/v1/admin/order-status-counts')
  await page.route('**/api/v1/admin/order-status-counts', (route) =>
    fail
      ? route.fulfill({
          status: 503,
          json: { error: { message: 'Счётчики заказов недоступны' } },
        })
      : route.fulfill({ json: { data: { new: 0, working: 0, shipped: 0 } } }),
  )
  await page.goto('/orders')
  await expect(
    page.getByRole('button', { name: `Открыть заказ ${order.order_number}` }),
  ).toBeVisible()
  await expect(page.locator('[data-order-count]')).toHaveCount(0)
  await expect(page.getByRole('alert')).toContainText(
    'Счётчики заказов недоступны',
  )
  fail = false
  await page
    .getByRole('button', { name: 'Повторить загрузку счётчиков заказов' })
    .click()
  for (const code of ['new', 'working', 'shipped'])
    await expect(page.locator(`[data-order-count=${code}]`)).toHaveText('0')
  await expect(page.getByRole('alert')).toHaveCount(0)
})
