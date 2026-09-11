import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'

const timestamp = '2026-09-11T10:00:00.000Z'
const order = {
  id: 1, order_number: 'AC-20260911-ABCDEF1234', customer: { name: 'Иван Петров', phone: '+79990000000', email: 'ivan@example.test' },
  delivery_address: 'Москва, ул. Пример, 1', customer_comment: 'Позвонить за час', status: 'new', payment_status: 'not_paid', payment_amount: null, payment_method: null, payment_reference: null,
  total_amount: '2500.00', items: [{ product_id: 1, product_name: 'Керамогранит', product_sku: '1000001', unit_price: '1250.00', quantity: 2, line_total: '2500.00' }], paid_at: null, completed_at: null, created_at: timestamp,
}
const responsePage = { data: [order], meta: { current_page: 1, last_page: 1, per_page: 25, total: 1, from: 1, to: 1 } }

async function mockOrdersApi(page: Page, permissions = ['orders.view', 'orders.manage', 'payments.manage']): Promise<void> {
  await page.route('**/sanctum/csrf-cookie', route => route.fulfill({ status: 204 }))
  await page.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname.replace('/api/v1', '')
    if (path === '/admin/auth/me') return route.fulfill({ json: { data: { id: 1, name: 'Менеджер', email: 'manager@example.test', status: 'active', last_login_at: timestamp, permissions } } })
    if (path === '/admin/order-statuses') return route.fulfill({ json: { data: [{ code: 'new', name: 'Новый', sort_order: 0, is_terminal: false }, { code: 'processing', name: 'В обработке', sort_order: 1, is_terminal: false }] } })
    if (path === '/admin/orders') return route.fulfill({ json: responsePage })
    if (path === '/admin/orders/1') return route.fulfill({ json: { data: order } })
    if (path === '/admin/orders/1/status-history') return route.fulfill({ json: { ...responsePage, data: [{ id: 1, from_status: 'new', to_status: 'processing', actor: { id: 1, name: 'Менеджер' }, occurred_at: timestamp }] } })
    if (path === '/admin/orders/1/comments') return route.fulfill({ json: { ...responsePage, data: [] } })
    return route.fulfill({ status: 404, json: { error: { message: `Unhandled ${path}` } } })
  })
}

test('orders workspace exposes protected snapshot, keyboard selection, and manager controls', async ({ page }) => {
  await mockOrdersApi(page)
  await page.goto('/orders')
  await expect(page.getByRole('heading', { level: 1, name: 'Заказы' })).toBeVisible()
  const opener = page.getByRole('button', { name: `Открыть заказ ${order.order_number}` })
  await opener.focus()
  await page.keyboard.press('Enter')
  await expect(page.getByRole('heading', { level: 3, name: 'Клиент и доставка' })).toBeVisible()
  await expect(page.getByText(order.delivery_address)).toBeVisible()
  await expect(opener).toHaveAttribute('aria-current', 'true')
  await expect(page.getByRole('heading', { name: 'Статус заказа' })).toBeVisible()
  await expect(page.getByRole('heading', { name: 'Оплата' })).toBeVisible()
  await expect(page.getByLabel('Новый внутренний комментарий')).toBeVisible()
  const results = await new AxeBuilder({ page }).disableRules(['color-contrast']).analyze()
  expect(results.violations.filter(violation => ['serious', 'critical'].includes(violation.impact ?? ''))).toEqual([])
})

test('orders workspace keeps management controls hidden for a view-only employee', async ({ page }) => {
  await mockOrdersApi(page, ['orders.view'])
  await page.goto('/orders')
  await page.getByRole('button', { name: `Открыть заказ ${order.order_number}` }).click()
  await expect(page.getByRole('heading', { name: 'Статус заказа' })).toHaveCount(0)
  await expect(page.getByRole('heading', { name: 'Оплата' })).toHaveCount(0)
  await expect(page.getByLabel('Новый внутренний комментарий')).toHaveCount(0)
})

for (const width of [320, 640, 768, 1024, 1280]) {
  test(`orders workspace remains usable at ${width}px`, async ({ page }) => {
    await page.setViewportSize({ width, height: 800 })
    await mockOrdersApi(page)
    await page.goto('/orders')
    await page.getByRole('button', { name: `Открыть заказ ${order.order_number}` }).click()
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    await expect(page.getByRole('heading', { level: 3, name: 'Внутренние комментарии' })).toBeVisible()
  })
}
