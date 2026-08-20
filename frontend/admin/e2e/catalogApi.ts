import type { Page } from '@playwright/test'

const now = '2026-08-20T10:00:00.000Z'

export const category = {
  id: 1,
  parent_id: null,
  name: 'Керамогранит',
  slug: 'keramogranit',
  description: 'Напольная плитка',
  is_parent: true,
  is_active: true,
  sort_order: 10,
  children: [],
  created_at: now,
  updated_at: now,
}

export const brand = {
  id: 1,
  name: 'Kerama Marazzi',
  slug: 'kerama-marazzi',
  description: 'Российский бренд',
  country_code: 'RU',
  is_active: true,
  created_at: now,
  updated_at: now,
}

export const attributeGroup = {
  id: 1,
  name: 'Размеры',
  slug: 'dimensions',
  description: 'Габариты изделия',
  sort_order: 10,
  created_at: now,
  updated_at: now,
}

export const attribute = {
  id: 1,
  attribute_group_id: 1,
  name: 'Ширина',
  slug: 'width',
  type: 'decimal',
  unit: 'см',
  is_filterable: true,
  is_required: true,
  is_visible_on_product_page: true,
  sort_order: 10,
  options: [],
  created_at: now,
  updated_at: now,
}

export const product = {
  id: 1,
  category_id: 1,
  brand_id: 1,
  name: 'Монте Тиберио',
  slug: 'monte-tiberio',
  description: 'Матовый керамогранит',
  is_active: true,
  category,
  brand,
  created_at: now,
  updated_at: now,
}

type ApiOptions = {
  emptyPath?: string
  errorPath?: string
  delayPath?: string
  auth?: 'allowed' | 'unauthenticated' | 'forbidden'
}

function page(data: unknown[], currentPage = 1, lastPage = 1, total = data.length) {
  return {
    data,
    meta: {
      current_page: currentPage,
      last_page: lastPage,
      per_page: 15,
      total,
      from: data.length ? (currentPage - 1) * 15 + 1 : null,
      to: data.length ? (currentPage - 1) * 15 + data.length : null,
    },
  }
}

function paginatedFixture(url: URL, first: Record<string, unknown>, secondName: string) {
  const second = { ...first, id: 2, name: secondName, slug: `${String(first.slug)}-2` }
  if (url.searchParams.get('per_page') === '100') return page([first, second], 1, 1, 2)
  const currentPage = Number(url.searchParams.get('page') ?? '1')
  return page(currentPage === 2 ? [second] : [first], currentPage, 2, 16)
}

export async function mockCatalogApi(pageContext: Page, options: ApiOptions = {}): Promise<void> {
  await pageContext.route('**/api/v1/**', async (route) => {
    const url = new URL(route.request().url())
    const path = url.pathname.replace('/api/v1', '')

    if (options.delayPath === path) await new Promise((resolve) => setTimeout(resolve, 300))
    if (options.errorPath === path) {
      await route.fulfill({ status: 500, json: { error: { message: 'Тестовая ошибка каталога' } } })
      return
    }

    if (path === '/admin/auth/me') {
      if (options.auth === 'unauthenticated') {
        await route.fulfill({ status: 401, json: { error: { message: 'Unauthenticated' } } })
        return
      }
      await route.fulfill({ json: { data: { id: 1, name: 'Тестовый администратор', email: 'admin@example.test', status: 'active', last_login_at: now, permissions: options.auth === 'forbidden' ? [] : ['catalog.manage'] } } })
      return
    }

    if (path === '/admin/categories/tree') {
      await route.fulfill({ json: { data: options.emptyPath === path ? [] : [category] } })
      return
    }

    if (path === '/admin/brands') {
      await route.fulfill({ json: options.emptyPath === path ? page([]) : paginatedFixture(url, brand, 'Italon') })
      return
    }

    if (path === '/admin/attribute-groups') {
      await route.fulfill({ json: options.emptyPath === path ? page([]) : paginatedFixture(url, attributeGroup, 'Поверхность') })
      return
    }

    if (path === '/admin/attributes') {
      await route.fulfill({ json: options.emptyPath === path ? page([]) : paginatedFixture(url, attribute, 'Длина') })
      return
    }

    if (path === '/admin/products') {
      await route.fulfill({ json: options.emptyPath === path ? page([]) : paginatedFixture(url, product, 'Про Стоун') })
      return
    }

    if (path === '/admin/products/1/variants' || path === '/admin/products/1/images') {
      await route.fulfill({ json: page([]) })
      return
    }

    if (path === '/admin/categories/1/attributes') {
      await route.fulfill({ json: { data: [attribute] } })
      return
    }

    if (path === '/admin/categories/1/attribute-groups') {
      await route.fulfill({ json: { data: [attributeGroup] } })
      return
    }

    if (path === '/admin/products/1/attributes' || path === '/admin/products/1/relations') {
      await route.fulfill({ json: { data: [] } })
      return
    }

    await route.fulfill({ status: 404, json: { error: { message: `Unhandled test route: ${path}` } } })
  })
}
