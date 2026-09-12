import AxeBuilder from '@axe-core/playwright'
import { expect, test, type Page } from '@playwright/test'

const timestamp = '2026-09-11T10:00:00.000Z'
const permissions = ['catalog.manage', 'imports.manage', 'admin-users.view', 'admin-users.manage', 'roles.view', 'roles.manage', 'permissions.view', 'audit-log.view', 'orders.view', 'orders.manage', 'payments.manage', 'contacts.view', 'contacts.manage']
const page = <T>(data: T[]) => ({ data, meta: { current_page: 1, last_page: 1, per_page: 25, total: data.length, from: data.length ? 1 : null, to: data.length || null } })

type BaselineState = 'default' | 'loading' | 'empty' | 'error'

async function mockAdminBaseline(pageContext: Page, guest = false, state: BaselineState = 'default'): Promise<void> {
  await pageContext.route('**/sanctum/csrf-cookie', route => route.fulfill({ status: 204 }))
  await pageContext.route('**/api/v1/**', async route => {
    const path = new URL(route.request().url()).pathname.replace('/api/v1', '')
    const user = { id: 1, name: 'Тестовый администратор', email: 'admin@example.test', status: 'active', last_login_at: timestamp, permissions }
    const category = { id: 1, parent_id: null, name: 'Керамогранит', slug: 'keramogranit', description: null, sku_prefix: '1', is_parent: true, is_active: true, sort_order: 1, children: [], created_at: timestamp, updated_at: timestamp }
    const brand = { id: 1, name: 'Kerama Marazzi', slug: 'kerama-marazzi', description: null, country_code: 'RU', is_active: true, created_at: timestamp, updated_at: timestamp }
    const attributeGroup = { id: 1, name: 'Размеры', slug: 'dimensions', description: null, sort_order: 1, created_at: timestamp, updated_at: timestamp }
    const attribute = { id: 1, attribute_group_id: 1, name: 'Ширина', slug: 'width', type: 'decimal', unit: 'см', is_filterable: true, is_required: false, is_visible_on_product_page: true, sort_order: 1, options: [], created_at: timestamp, updated_at: timestamp }
    const product = { id: 1, category_id: 1, brand_id: 1, name: 'Монте Тиберио', slug: 'monte-tiberio', sku: 'MONTE-1', description: null, article_number: null, barcode: null, unit: 'piece', price: '1990.00', old_price: null, stock_quantity: 4, is_active: true, is_on_sale: false, category, brand, primary_image: null, created_at: timestamp, updated_at: timestamp }
    const role = { id: 1, name: 'Администратор', slug: 'administrator', description: null, is_system: false, permissions: [{ id: 1, name: 'Просмотр каталога', code: 'catalog.manage', description: null, roles: [] }] }
    const employee = { id: 1, name: 'Тестовый администратор', email: 'admin@example.test', status: 'active', last_login_at: timestamp, roles: [{ id: 1, name: 'Администратор', slug: 'administrator' }] }
    const order = { id: 1, order_number: 'AC-20260911-BASELINE', customer: { name: 'Иван Петров', phone: '+79990000000', email: 'ivan@example.test' }, delivery_address: 'Москва, ул. Пример, 1', customer_comment: null, status: 'new', payment_status: 'not_paid', payment_amount: null, payment_method: null, payment_reference: null, total_amount: '1990.00', items: [], paid_at: null, completed_at: null, created_at: timestamp }
    const contact = { id: 1, type: 'callback', contact: { name: 'Иван Петров', phone: '+79990000000', email: null }, message: 'Перезвоните по наличию.', source: 'site', status: 'new', assignee: null, assigned_at: null, completed_at: null, created_at: timestamp }
    if (path === '/admin/auth/me') return guest
      ? route.fulfill({ status: 401, json: { error: { message: 'Unauthenticated' } } })
      : route.fulfill({ json: { data: user } })
    if (state === 'loading' && ['/admin/products', '/admin/categories/tree', '/admin/brands', '/admin/attribute-groups', '/admin/attributes', '/admin/users', '/admin/roles', '/admin/permissions', '/admin/audit-logs', '/admin/orders', '/admin/contact-requests'].includes(path)) {
      await new Promise(resolve => setTimeout(resolve, 1000))
    }
    if (state === 'error') return route.fulfill({ status: 500, json: { error: { message: 'Baseline API error' } } })
    const collection = <T>(items: T[]) => page(state === 'empty' ? [] : items)
    const data = <T>(items: T[]) => ({ data: state === 'empty' ? [] : items })
    if (path === '/admin/categories/tree') return route.fulfill({ json: data([category]) })
    if (path === '/admin/brands') return route.fulfill({ json: collection([brand]) })
    if (path === '/admin/attribute-groups') return route.fulfill({ json: collection([attributeGroup]) })
    if (path === '/admin/attributes') return route.fulfill({ json: collection([attribute]) })
    if (path === '/admin/products') return route.fulfill({ json: collection([product]) })
    if (path === '/admin/product-groups') return route.fulfill({ json: collection([]) })
    if (path === '/admin/users') return route.fulfill({ json: collection([employee]) })
    if (path === '/admin/users/roles') return route.fulfill({ json: data(employee.roles) })
    if (path === '/admin/roles') return route.fulfill({ json: data([role]) })
    if (path === '/admin/roles/permissions' || path === '/admin/permissions') return route.fulfill({ json: data(role.permissions) })
    if (path === '/admin/audit-logs') return route.fulfill({ json: collection([{ id: 1, action: 'created', actor: { id: 1, name: user.name }, entity: { type: 'product', id: 1, name: product.name }, metadata: null, details: [], occurred_at: timestamp }]) })
    if (path === '/admin/order-statuses') return route.fulfill({ json: data([{ code: 'new', name: 'Новый', sort_order: 1, is_terminal: false }]) })
    if (path === '/admin/orders') return route.fulfill({ json: collection([order]) })
    if (path === '/admin/contact-statuses') return route.fulfill({ json: data([{ code: 'new', name: 'Новое', is_terminal: false }]) })
    if (path === '/admin/contact-assignees') return route.fulfill({ json: data([{ id: 1, name: user.name }]) })
    if (path === '/admin/contact-requests') return route.fulfill({ json: collection([contact]) })
    return route.fulfill({ json: { data: [] } })
  })
}

const stateRoutes = ['/products', '/categories', '/brands', '/attribute-groups', '/attributes', '/employees', '/roles', '/permissions', '/audit-log', '/orders', '/contacts'] as const

for (const path of stateRoutes) {
  test(`Admin baseline: ${path} loading state`, async ({ page }) => {
    await mockAdminBaseline(page, false, 'loading')
    await page.goto(path, { waitUntil: 'commit' })
    await expect(page.getByRole('status').filter({ hasText: 'Загрузка' })).toBeVisible()
    await expect(page).toHaveScreenshot(`route-${path.slice(1)}-loading.png`, { fullPage: true, animations: 'disabled' })
  })
}

test('Admin baseline: roles destructive confirmation dialog', async ({ page }) => {
  await mockAdminBaseline(page)
  await page.goto('/roles')
  await page.getByRole('button', { name: 'Удалить роль Администратор' }).click()
  const dialog = page.getByRole('dialog', { name: 'Удалить роль?' })
  await expect(dialog).toBeVisible()
  await expect(dialog).toHaveScreenshot('roles-destructive-dialog.png', { animations: 'disabled' })
})

for (const path of stateRoutes) {
  for (const state of ['empty', 'error'] as const) {
    test(`Admin baseline: ${path} ${state} state`, async ({ page }) => {
      await mockAdminBaseline(page, false, state)
      await page.goto(path, { waitUntil: 'networkidle' })
      if (state === 'error') await expect(page.getByRole('alert')).toBeVisible({ timeout: 10_000 })
      await expect(page).toHaveScreenshot(`route-${path.slice(1)}-${state}.png`, { fullPage: true, animations: 'disabled' })
    })
  }
}

const authenticatedRoutes = [
  ['/', 'Обзор магазина'], ['/profile', 'Мой профиль'], ['/products', 'Товары'], ['/categories', 'Категории'], ['/brands', 'Бренды'],
  ['/attribute-groups', 'Группы характеристик'], ['/attributes', 'Характеристики'], ['/employees', 'Сотрудники'], ['/roles', 'Роли'],
  ['/permissions', 'Права'], ['/audit-log', 'Журнал аудита'], ['/orders', 'Заказы'], ['/contacts', 'Обращения'], ['/content', 'Контент'], ['/settings', 'Настройки'],
] as const

for (const [path, heading] of authenticatedRoutes) {
  test(`Admin baseline: ${path}`, async ({ page }) => {
    await mockAdminBaseline(page)
    await page.goto(path)
    await expect(page.getByRole('heading', { level: 1, name: heading })).toBeVisible()
    await expect(page).toHaveScreenshot(`route-${path === '/' ? 'dashboard' : path.slice(1)}.png`, { fullPage: true, animations: 'disabled' })
    const accessibility = await new AxeBuilder({ page }).disableRules(['color-contrast']).analyze()
    const serious = accessibility.violations.filter(item => ['serious', 'critical'].includes(item.impact ?? ''))
    expect(serious).toEqual([])
  })
}

for (const [path, heading] of [['/login', 'Вход'], ['/forgot-password', 'Восстановление пароля'], ['/reset-password', 'Задайте новый пароль']] as const) {
  test(`Admin baseline: guest ${path}`, async ({ page }) => {
    await mockAdminBaseline(page, true)
    await page.goto(path)
    await expect(page.getByRole('heading', { level: 1, name: heading })).toBeVisible()
    await expect(page).toHaveScreenshot(`route-${path.slice(1)}.png`, { fullPage: true, animations: 'disabled' })
    const accessibility = await new AxeBuilder({ page }).disableRules(['color-contrast']).analyze()
    expect(accessibility.violations.filter(item => ['serious', 'critical'].includes(item.impact ?? ''))).toEqual([])
  })
}

for (const path of [...authenticatedRoutes.map(([route]) => route), '/login', '/forgot-password', '/reset-password']) {
  test(`Admin baseline: ${path} remains usable at all supported widths`, async ({ page }) => {
    await mockAdminBaseline(page, path.startsWith('/login') || path.startsWith('/forgot') || path.startsWith('/reset'))
    for (const width of [320, 640, 768, 1024, 1280]) {
      await page.setViewportSize({ width, height: 800 })
      await page.goto(path)
      expect(await page.evaluate(() => document.documentElement.scrollWidth <= window.innerWidth)).toBe(true)
    }
  })
}
