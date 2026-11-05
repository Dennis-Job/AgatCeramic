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
): Promise<void> {
  const record = structuredClone(order)
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
          ],
        },
      })
    if (path === '/admin/orders')
      return route.fulfill({ json: { ...responsePage, data: [record] } })
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
      new URL(req.url()).searchParams.get('status') === 'processing',
  )
  await page.getByRole('tab', { name: 'В обработке', exact: true }).click()
  await filterRequest
  await expect(
    statistics.locator('[data-statistic-metric=total]'),
  ).toContainText('12')
  await page.setViewportSize({ width: 768, height: 900 })
  await expect(
    page.getByRole('tablist', { name: 'Статус заказа' }),
  ).toHaveCount(0)
  await expect(
    page.getByRole('button', { name: 'Статус заказа', exact: true }),
  ).toContainText('В обработке')
  await page.getByRole('button', { name: 'Статус заказа', exact: true }).click()
  await page.getByRole('button', { name: 'Все статусы', exact: true }).click()
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
})
