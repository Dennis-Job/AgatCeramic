import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'

const timestamp = '2026-09-11T10:00:00.000Z'
const permissions = ['admin-users.view', 'admin-users.manage', 'roles.view', 'roles.manage', 'permissions.view', 'audit-log.view', 'orders.view', 'orders.manage', 'payments.manage', 'contacts.view', 'contacts.manage']
const employee = { id: 1, name: 'Тестовый администратор', email: 'admin@example.test', status: 'active', last_login_at: timestamp, roles: [{ id: 1, name: 'Администратор', slug: 'administrator' }] }
const role = { id: 1, name: 'Администратор', slug: 'administrator', description: 'Полный доступ', is_system: false, permissions: [{ id: 1, name: 'Просмотр каталога', code: 'catalog.manage', description: null }] }
const contact = { id: 1, type: 'callback', contact: { name: 'Иван Петров', phone: '+79990000000', email: 'ivan@example.test' }, message: 'Перезвоните по наличию.', source: 'site', status: 'new', assignee: null, assigned_at: null, completed_at: null, created_at: timestamp }
const order = { id: 1, order_number: 'AC-20260911-A029', customer: { name: 'Иван Петров', phone: '+79990000000', email: 'ivan@example.test' }, delivery_address: 'Москва', customer_comment: null, status: 'new', payment_status: 'not_paid', payment_amount: null, payment_method: null, payment_reference: null, total_amount: '1990.00', items: [], paid_at: null, completed_at: null, created_at: timestamp }
const pageOf = <T>(items: T[]) => ({ data: items, meta: { current_page: 1, last_page: 1, per_page: 25, total: items.length, from: items.length ? 1 : null, to: items.length || null } })

type MockOptions = { grantedPermissions?: string[]; failingPaths?: string[]; delayedPaths?: string[] }

async function mockApi(page: Page, options: MockOptions = {}): Promise<void> {
  const grantedPermissions = options.grantedPermissions ?? permissions
  const failingPaths = new Set(options.failingPaths ?? [])
  const delayedPaths = new Set(options.delayedPaths ?? [])
  await page.route('**/sanctum/csrf-cookie', route => route.fulfill({ status: 204 }))
  await page.route('**/api/v1/**', async route => {
    const path = new URL(route.request().url()).pathname.replace('/api/v1', '')
    if (delayedPaths.has(path)) await new Promise(resolve => setTimeout(resolve, 800))
    if (failingPaths.has(path)) return route.fulfill({ status: 500, json: { error: { message: `Недоступен ресурс ${path}` } } })
    if (path === '/admin/auth/me') return route.fulfill({ json: { data: { ...employee, permissions: grantedPermissions } } })
    if (path === '/admin/users') return route.fulfill({ json: pageOf([employee]) })
    if (path === '/admin/users/roles') return route.fulfill({ json: { data: employee.roles } })
    if (path === '/admin/roles') return route.fulfill({ json: { data: [role] } })
    if (path === '/admin/roles/permissions' || path === '/admin/permissions') return route.fulfill({ json: { data: role.permissions.map(permission => ({ ...permission, roles: [employee.roles[0]] })) } })
    if (path === '/admin/audit-logs') return route.fulfill({ json: pageOf([{ id: 1, action: 'auth.login', actor: { id: 1, name: employee.name }, entity: null, metadata: null, details: [], occurred_at: timestamp }]) })
    if (path === '/admin/order-statuses') return route.fulfill({ json: { data: [{ code: 'new', name: 'Новый', sort_order: 1, is_terminal: false }] } })
    if (path === '/admin/orders') return route.fulfill({ json: pageOf([order]) })
    if (path === '/admin/orders/1') return route.fulfill({ json: { data: order } })
    if (path === '/admin/orders/1/status-history') return route.fulfill({ json: { data: [] } })
    if (path === '/admin/orders/1/comments') return route.fulfill({ json: { data: [] } })
    if (path === '/admin/contact-statuses') return route.fulfill({ json: { data: [{ code: 'new', name: 'Новое', is_terminal: false }] } })
    if (path === '/admin/contact-assignees') return route.fulfill({ json: { data: [{ id: 1, name: employee.name }] } })
    if (path === '/admin/contact-requests') return route.fulfill({ json: pageOf([contact]) })
    if (path === '/admin/contact-requests/1') return route.fulfill({ json: { data: contact } })
    if (path === '/admin/contact-requests/1/status-history') return route.fulfill({ json: pageOf([{ id: 1, from_status: 'new', to_status: 'new', actor: { id: 1, name: employee.name }, occurred_at: timestamp }]) })
    if (path === '/admin/contact-requests/1/comments') return route.fulfill({ json: pageOf([{ id: 1, body: 'Внутренний комментарий', author: { id: 1, name: employee.name }, created_at: timestamp }]) })
    return route.fulfill({ status: 404, json: { error: { message: `Unhandled ${path}` } } })
  })
}

test('TASK-A029 routes render without serious accessibility violations', async ({ page }) => {
  await mockApi(page)
  const routes = [['/profile', 'Мой профиль'], ['/employees', 'Сотрудники'], ['/roles', 'Роли'], ['/permissions', 'Права'], ['/audit-log', 'Журнал аудита'], ['/orders', 'Заказы'], ['/contacts', 'Обращения']] as const
  for (const [path, heading] of routes) {
    await page.goto(path)
    await expect(page.getByRole('heading', { level: 1, name: heading })).toBeVisible()
    const results = await new AxeBuilder({ page }).disableRules(['color-contrast']).analyze()
    expect(results.violations.filter(violation => ['serious', 'critical'].includes(violation.impact ?? ''))).toEqual([])
  }
})

test('employee editor keeps keyboard close and opener focus', async ({ page }) => {
  await mockApi(page)
  await page.goto('/employees')
  const opener = page.getByRole('button', { name: 'Добавить сотрудника' })
  await opener.click()
  await expect(page.getByRole('dialog', { name: 'Новый сотрудник' })).toBeVisible()
  await expect(page.getByText('Администратор', { exact: true }).last()).toBeVisible()
  await page.keyboard.press('Escape')
  await expect(page.getByRole('dialog')).toHaveCount(0)
  await expect(opener).toBeFocused()
})

test('contact detail exposes protected workflow and activity data without overflow', async ({ page }) => {
  await page.setViewportSize({ width: 320, height: 800 })
  await mockApi(page)
  await page.goto('/contacts')
  await page.getByRole('button', { name: 'Открыть обращение 1' }).click()
  await page.keyboard.press('End')
  await expect(page.getByRole('heading', { name: 'Обработка' })).toBeVisible()
  await expect(page.getByText('Внутренний комментарий', { exact: true })).toBeVisible()
  expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
})

test('employee mutations require the manage permission', async ({ page }) => {
  await mockApi(page, { grantedPermissions: ['admin-users.view'] })
  await page.goto('/employees')
  await expect(page.getByRole('heading', { name: 'Сотрудники' })).toBeVisible()
  await expect(page.getByRole('button', { name: 'Добавить сотрудника' })).toHaveCount(0)
  await expect(page.getByRole('button', { name: /(?:Редактировать|Удалить) сотрудника/ })).toHaveCount(0)
})

test('dependent mutations are blocked when reference data is unavailable', async ({ page }) => {
  await mockApi(page, { failingPaths: ['/admin/users/roles', '/admin/contact-statuses'] })
  await page.goto('/employees')
  await expect(page.getByRole('alert')).toContainText('Редактирование сотрудников временно недоступно')
  await expect(page.getByRole('button', { name: 'Добавить сотрудника' })).toBeDisabled()

  await page.goto('/contacts')
  await expect(page.getByRole('alert')).toContainText('Смена статуса обращения временно недоступна')
  await page.getByRole('button', { name: 'Открыть обращение 1' }).click()
  await expect(page.getByRole('button', { name: 'Сохранить статус' })).toHaveCount(0)
  await expect(page.getByLabel('Ответственный')).toBeVisible()
})

test('dependent controls wait for their reference data', async ({ page }) => {
  await mockApi(page, { delayedPaths: ['/admin/users/roles', '/admin/roles/permissions', '/admin/order-statuses', '/admin/contact-statuses', '/admin/contact-assignees'] })
  await page.goto('/employees')
  const employeeButton = page.getByRole('button', { name: 'Добавить сотрудника' })
  await expect(employeeButton).toBeDisabled()
  await expect(employeeButton).toBeEnabled()

  await page.goto('/roles')
  const roleButton = page.getByRole('button', { name: 'Добавить роль' })
  await expect(roleButton).toBeDisabled()
  await expect(roleButton).toBeEnabled()

  await page.goto('/orders')
  const orderStatusFilter = page.getByLabel('Статус заказа', { exact: true })
  await expect(orderStatusFilter).toBeDisabled()
  await page.getByRole('button', { name: 'Найти' }).click()
  await page.getByRole('button', { name: `Открыть заказ ${order.order_number}` }).click()
  await expect(page.getByRole('button', { name: 'Сохранить', exact: true })).toHaveCount(0)
  await expect(orderStatusFilter).toBeEnabled()
  await expect(page.getByRole('button', { name: 'Сохранить', exact: true })).toBeVisible()

  await page.goto('/contacts')
  const contactStatusFilter = page.getByLabel('Статус обращения', { exact: true })
  await expect(contactStatusFilter).toBeDisabled()
  await page.getByRole('button', { name: 'Найти' }).click()
  await page.getByRole('button', { name: 'Открыть обращение 1' }).click()
  await expect(page.getByRole('heading', { name: 'Обработка' })).toHaveCount(0)
  await expect(contactStatusFilter).toBeEnabled()
  await expect(page.getByLabel('Ответственный')).toBeVisible()
})

test('initial detail failures remain visible', async ({ page }) => {
  await mockApi(page, { failingPaths: ['/admin/orders/1', '/admin/contact-requests/1'] })
  await page.goto('/orders')
  await page.getByRole('button', { name: `Открыть заказ ${order.order_number}` }).click()
  await expect(page.getByRole('alert')).toContainText('/admin/orders/1')

  await page.goto('/contacts')
  await page.getByRole('button', { name: 'Открыть обращение 1' }).click()
  await expect(page.getByRole('alert')).toContainText('/admin/contact-requests/1')
})

test('audit date picker stays inside narrow viewports and restores focus', async ({ page }) => {
  await mockApi(page)
  for (const width of [320, 640]) {
    await page.setViewportSize({ width, height: 800 })
    await page.goto('/audit-log')
    const input = page.getByLabel('Дата с', { exact: true })
    await input.focus()
    const dialog = page.getByRole('dialog', { name: 'Дата с: выбор даты' })
    await expect(dialog).toBeVisible()
    const box = await dialog.boundingBox()
    expect(box).not.toBeNull()
    expect(box!.x).toBeGreaterThanOrEqual(0)
    expect(box!.x + box!.width).toBeLessThanOrEqual(width)
    expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    await page.keyboard.press('Escape')
    await expect(dialog).toHaveCount(0)
    await expect(input).toBeFocused()
  }
})
