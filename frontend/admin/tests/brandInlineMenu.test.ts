import { mount } from '@vue/test-utils'
import { defineComponent, h } from 'vue'
import { beforeEach, expect, test, vi } from 'vitest'
import { useBrandCatalog } from '../src/features/brands/composables/useBrandCatalog'
import BrandsList from '../src/features/brands/components/BrandsList.vue'
import type { Brand } from '../src/features/brands/types/brand.types'

const api = vi.hoisted(() => ({
  getBrands: vi.fn(),
  saveBrand: vi.fn(),
  deleteBrand: vi.fn(),
  updateBrandCatalogMenu: vi.fn(),
}))
vi.mock('../src/features/brands/services/brands', () => api)

const brand: Brand = {
  id: 1,
  name: 'Hansgrohe',
  slug: 'hansgrohe',
  description: null,
  country_code: 'DE',
  logo_id: null,
  logo: null,
  documents: [],
  is_active: true,
  show_in_catalog_menu: false,
  created_at: '',
  updated_at: '',
}
const response = (items = [brand]) => ({
  data: items.map((item) => ({ ...item })),
  meta: { current_page: 2, last_page: 2, per_page: 25, total: 26 },
})
function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (reason: Error) => void
  const promise = new Promise<T>((yes, no) => {
    resolve = yes
    reject = no
  })
  return { promise, resolve, reject }
}
function setup() {
  let catalog!: ReturnType<typeof useBrandCatalog>
  const wrapper = mount(
    defineComponent({
      setup() {
        catalog = useBrandCatalog()
        return () => h('div')
      },
    }),
  )
  return { catalog, wrapper }
}
beforeEach(() => {
  vi.resetAllMocks()
  api.getBrands.mockResolvedValue(response())
})

test('inline change locks its row and updates only the confirmed flag without reloading the page', async () => {
  const { catalog, wrapper } = setup()
  await catalog.load(2)
  const pending = deferred<Brand>()
  api.updateBrandCatalogMenu.mockReturnValue(pending.promise)
  const save = catalog.setCatalogMenu(brand, true)
  expect(catalog.menuPendingIds.value.has(1)).toBe(true)
  expect(catalog.brands.value[0]?.show_in_catalog_menu).toBe(false)
  await catalog.setCatalogMenu(brand, true)
  expect(api.updateBrandCatalogMenu).toHaveBeenCalledTimes(1)
  pending.resolve({
    ...brand,
    name: 'Stale response name',
    show_in_catalog_menu: true,
  })
  await save
  expect(catalog.brands.value[0]).toMatchObject({
    name: 'Hansgrohe',
    show_in_catalog_menu: true,
  })
  expect(catalog.menuPendingIds.value.has(1)).toBe(false)
  expect(catalog.pagination.value?.current_page).toBe(2)
  expect(api.getBrands).toHaveBeenCalledTimes(1)
  wrapper.unmount()
})

test('a failed inline save preserves the value and list, reports the brand and allows retry', async () => {
  const { catalog, wrapper } = setup()
  await catalog.load()
  api.updateBrandCatalogMenu.mockRejectedValueOnce(
    new Error('Доступ запрещён.'),
  )
  await catalog.setCatalogMenu(brand, true)
  expect(catalog.brands.value[0]?.show_in_catalog_menu).toBe(false)
  expect(catalog.error.value).toBe('')
  expect(catalog.menuError.value).toContain('Hansgrohe')
  expect(catalog.menuError.value).toContain('Доступ запрещён.')
  expect(catalog.menuPendingIds.value.size).toBe(0)
  api.updateBrandCatalogMenu.mockResolvedValueOnce({
    ...brand,
    show_in_catalog_menu: true,
  })
  await catalog.setCatalogMenu(brand, true)
  expect(catalog.menuError.value).toBe('')
  expect(catalog.brands.value[0]?.show_in_catalog_menu).toBe(true)
  wrapper.unmount()
})

test.each(['load', 'loadAfterDeletion'] as const)(
  'an old %s request cannot overwrite the saved flag, but a fresh request is authoritative',
  async (method) => {
    const { catalog, wrapper } = setup()
    await catalog.load()
    const oldLoad = deferred<ReturnType<typeof response>>()
    api.getBrands.mockReturnValueOnce(oldLoad.promise)
    const loading = catalog[method]()
    api.updateBrandCatalogMenu.mockResolvedValueOnce({
      ...brand,
      show_in_catalog_menu: true,
    })
    await catalog.setCatalogMenu(brand, true)
    oldLoad.resolve(response())
    await loading
    expect(catalog.brands.value[0]?.show_in_catalog_menu).toBe(true)
    await catalog.load()
    expect(catalog.brands.value[0]?.show_in_catalog_menu).toBe(false)
    wrapper.unmount()
  },
)

test('list switch emits the chosen brand, reflects saved state and respects permissions and pending actions', async () => {
  const wrapper = mount(BrandsList, {
    props: {
      brands: [brand],
      loading: false,
      canManage: true,
      menuPendingIds: new Set<number>(),
    },
  })
  const toggle = wrapper.get('[role="switch"]')
  expect(toggle.attributes('aria-label')).toContain('Hansgrohe')
  expect(toggle.attributes('aria-checked')).toBe('false')
  await toggle.trigger('click')
  expect(wrapper.emitted('catalogMenu')).toEqual([[brand, true]])
  await wrapper.setProps({
    brands: [{ ...brand, show_in_catalog_menu: true }],
    menuPendingIds: new Set([1]),
  })
  expect(toggle.attributes('aria-checked')).toBe('true')
  expect(toggle.attributes('disabled')).toBeDefined()
  expect(
    wrapper
      .get('[aria-label="Редактировать бренд Hansgrohe"]')
      .attributes('disabled'),
  ).toBeDefined()
  expect(
    wrapper
      .get('[aria-label="Удалить бренд Hansgrohe"]')
      .attributes('disabled'),
  ).toBeDefined()
  await wrapper.setProps({ canManage: false, menuPendingIds: new Set() })
  expect(toggle.attributes('disabled')).toBeDefined()
  wrapper.unmount()
})
