import type { Page } from './fixtures'
import type { DeferredApiRequests } from './deferredApi'

const timestamp = '2026-09-11T10:00:00.000Z'
const permissions = [
  'catalog.manage',
  'media.manage',
  'imports.manage',
  'admin-users.view',
  'admin-users.manage',
  'roles.view',
  'roles.manage',
  'permissions.view',
  'audit-log.view',
  'orders.view',
  'orders.manage',
  'payments.manage',
  'contacts.view',
  'contacts.manage',
  'content.manage',
  'settings.manage',
  'settings.approve',
]
const page = <T>(data: T[]) => ({
  data,
  meta: {
    current_page: 1,
    last_page: 1,
    per_page: 25,
    total: data.length,
    from: data.length ? 1 : null,
    to: data.length || null,
  },
})

type BaselineState = 'default' | 'loading' | 'empty' | 'error' | 'forbidden'

export async function mockAdminBaseline(
  pageContext: Page,
  guest = false,
  state: BaselineState = 'default',
  deferredRequests?: DeferredApiRequests,
  fixtures: Record<string, unknown> = {},
): Promise<void> {
  await pageContext.route('**/sanctum/csrf-cookie', (route) =>
    route.fulfill({ status: 204 }),
  )
  await pageContext.route('**/api/v1/**', async (route) => {
    const path = new URL(route.request().url()).pathname.replace('/api/v1', '')
    const user = {
      id: 1,
      name: 'Тестовый администратор',
      email: 'admin@example.test',
      status: 'active',
      last_login_at: timestamp,
      permissions,
    }
    const category = {
      id: 1,
      parent_id: null,
      name: 'Керамогранит',
      slug: 'keramogranit',
      description: null,
      sku_prefix: '1',
      is_parent: true,
      is_active: true,
      sort_order: 1,
      children: [],
      created_at: timestamp,
      updated_at: timestamp,
    }
    const brand = {
      id: 1,
      name: 'Kerama Marazzi',
      slug: 'kerama-marazzi',
      description: null,
      country_code: 'RU',
      is_active: true,
      created_at: timestamp,
      updated_at: timestamp,
    }
    const attributeGroup = {
      id: 1,
      name: 'Размеры',
      slug: 'dimensions',
      description: null,
      sort_order: 1,
      created_at: timestamp,
      updated_at: timestamp,
    }
    const attribute = {
      id: 1,
      attribute_group_id: 1,
      name: 'Ширина',
      slug: 'width',
      type: 'decimal',
      unit: 'см',
      is_filterable: true,
      is_required: false,
      is_visible_on_product_page: true,
      sort_order: 1,
      options: [],
      created_at: timestamp,
      updated_at: timestamp,
    }
    const product = {
      id: 1,
      category_id: 1,
      brand_id: 1,
      name: 'Монте Тиберио',
      slug: 'monte-tiberio',
      sku: 'MONTE-1',
      description: null,
      article_number: null,
      barcode: null,
      unit: 'piece',
      price: '1990.00',
      old_price: null,
      stock_quantity: 4,
      is_active: true,
      is_on_sale: false,
      category,
      brand,
      primary_image: null,
      created_at: timestamp,
      updated_at: timestamp,
    }
    const role = {
      id: 1,
      name: 'Администратор',
      slug: 'administrator',
      description: null,
      is_system: false,
      permissions: [
        {
          id: 1,
          name: 'Просмотр каталога',
          code: 'catalog.manage',
          description: null,
          roles: [],
        },
      ],
    }
    const employee = {
      id: 1,
      name: 'Тестовый администратор',
      email: 'admin@example.test',
      status: 'active',
      last_login_at: timestamp,
      roles: [{ id: 1, name: 'Администратор', slug: 'administrator' }],
    }
    const order = {
      id: 1,
      order_number: 'AC-20260911-BASELINE',
      customer: {
        name: 'Иван Петров',
        phone: '+79990000000',
        email: 'ivan@example.test',
      },
      delivery_address: 'Москва, ул. Пример, 1',
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
      created_at: timestamp,
    }
    const contact = {
      id: 1,
      type: 'callback',
      contact: { name: 'Иван Петров', phone: '+79990000000', email: null },
      message: 'Перезвоните по наличию.',
      source: 'site',
      status: 'new',
      assignee: null,
      assigned_at: null,
      completed_at: null,
      created_at: timestamp,
    }
    if (path === '/admin/auth/me')
      return guest
        ? route.fulfill({
            status: 401,
            json: { error: { message: 'Unauthenticated' } },
          })
        : route.fulfill({ json: { data: user } })
    await deferredRequests?.wait(path)
    if (state === 'error')
      return route.fulfill({
        status: 500,
        json: { error: { message: 'Baseline API error' } },
      })
    if (state === 'forbidden' && path === '/admin/products')
      return route.fulfill({
        status: 403,
        json: {
          error: { message: 'Недостаточно прав для просмотра ресурса.' },
        },
      })
    if (fixtures[path]) return route.fulfill({ json: fixtures[path] })
    const collection = <T>(items: T[]) => page(state === 'empty' ? [] : items)
    const data = <T>(items: T[]) => ({ data: state === 'empty' ? [] : items })
    if (path === '/admin/categories/overview')
      return route.fulfill({
        json: data([
          {
            ...category,
            attributes: [attribute],
            attribute_groups: [attributeGroup],
          },
        ]),
      })
    if (path === '/admin/categories/tree')
      return route.fulfill({ json: data([category]) })
    if (path === '/admin/brands')
      return route.fulfill({ json: collection([brand]) })
    if (path === '/admin/attribute-groups')
      return route.fulfill({ json: collection([attributeGroup]) })
    if (path === '/admin/attributes')
      return route.fulfill({ json: collection([attribute]) })
    if (path === '/admin/products')
      return route.fulfill({ json: collection([product]) })
    if (path === '/admin/media')
      return route.fulfill({
        json: collection([
          {
            id: 1,
            kind: 'document',
            url: '/catalog.pdf',
            thumbnail_url: null,
            mime_type: 'application/pdf',
            size: 2048,
            title: 'Каталог коллекций',
            alt: null,
            width: null,
            height: null,
            created_at: timestamp,
            updated_at: timestamp,
          },
        ]),
      })
    if (path === '/admin/product-groups')
      return route.fulfill({ json: collection([]) })
    if (path === '/admin/users')
      return route.fulfill({ json: collection([employee]) })
    if (path === '/admin/users/roles')
      return route.fulfill({ json: data(employee.roles) })
    if (path === '/admin/roles') return route.fulfill({ json: data([role]) })
    if (path === '/admin/roles/permissions' || path === '/admin/permissions')
      return route.fulfill({ json: data(role.permissions) })
    if (path === '/admin/audit-logs')
      return route.fulfill({
        json: collection([
          {
            id: 1,
            action: 'created',
            actor: { id: 1, name: user.name },
            entity: { type: 'product', id: 1, name: product.name },
            metadata: null,
            details: [],
            occurred_at: timestamp,
          },
        ]),
      })
    if (path === '/admin/order-statuses')
      return route.fulfill({
        json: data([
          { code: 'new', name: 'Новый', sort_order: 1, is_terminal: false },
        ]),
      })
    if (path === '/admin/orders')
      return route.fulfill({ json: collection([order]) })
    if (path === '/admin/contact-statuses')
      return route.fulfill({
        json: data([{ code: 'new', name: 'Новое', is_terminal: false }]),
      })
    if (path === '/admin/contact-assignees')
      return route.fulfill({ json: data([{ id: 1, name: user.name }]) })
    if (path === '/admin/contact-requests')
      return route.fulfill({ json: collection([contact]) })
    if (path === '/admin/pages')
      return route.fulfill({
        json: collection([
          {
            id: 1,
            title: 'О компании',
            slug: 'about',
            body: 'Информация о компании.',
            is_published: true,
            created_at: timestamp,
            updated_at: timestamp,
          },
        ]),
      })
    if (path === '/admin/site-settings')
      return route.fulfill({
        json: {
          data: {
            operator_type: 'individual_entrepreneur',
            seller_name: 'ИП Тестовый продавец',
            entrepreneur_name: 'Тестовый Продавец',
            inn: '123456789012',
            ogrnip: '123456789012345',
            address: 'Москва, ул. Примерная, 1',
            phones: ['+79990000000'],
            email: 'seller@example.test',
            bank_name: null,
            bank_bik: null,
            bank_account: null,
            bank_correspondent_account: null,
            bank_details: null,
            publish_bank_details: false,
            updated_at: timestamp,
          },
        },
      })
    if (
      path === '/admin/legal-documents' ||
      path === '/admin/compliance-approvals'
    )
      return route.fulfill({ json: { data: [] } })
    return route.fulfill({ json: { data: [] } })
  })
}
