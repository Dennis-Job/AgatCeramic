import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent } from 'vue'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import ProductGroupAttributeRoles from '../src/features/products/components/ProductGroupAttributeRoles.vue'
import { useProductEditor } from '../src/features/products/composables/useProductEditor'
import type { Attribute } from '../src/features/attributes/types/attribute.types'
import type {
  Product,
  ProductGroup,
} from '../src/features/products/types/product.types'

const api = vi.hoisted(() => ({
  getProducts: vi.fn(),
  getProductFilterCounts: vi.fn(),
  saveProductAttributeValues: vi.fn(),
  getAllProductGroups: vi.fn(),
  saveProductGroup: vi.fn(),
}))
vi.mock('../src/stores/auth', () => ({
  useAuthStore: () => ({ hasPermission: () => true }),
}))
vi.mock('../src/features/products/services/products', () => ({
  getProducts: api.getProducts,
  saveProduct: vi.fn(),
  deleteProduct: vi.fn(),
}))
vi.mock('../src/features/products/services/productFilterCounts', () => ({
  getProductFilterCounts: api.getProductFilterCounts,
}))
vi.mock('../src/features/products/services/productAttributes', () => ({
  saveProductAttributeValues: api.saveProductAttributeValues,
  getCategoryAttributes: vi.fn(),
  getProductAttributeValues: vi.fn(),
}))
vi.mock('../src/features/products/services/productGroups', () => ({
  getAllProductGroups: api.getAllProductGroups,
  saveProductGroup: api.saveProductGroup,
  deleteProductGroup: vi.fn(),
  ProductGroupRequestError: class extends Error {},
}))
const wrappers: Array<ReturnType<typeof mount>> = []
const attributes = [
  { id: 1, name: 'Цвет', slug: 'color', type: 'select' },
  { id: 2, name: 'м² в упаковке', slug: 'package-area', type: 'decimal' },
  { id: 3, name: 'Описание упаковки', slug: 'pack-note', type: 'text' },
] as Attribute[]
const product = { id: 7 } as Product
const savedGroup = (): ProductGroup => ({
  id: 5,
  name: 'Аллея',
  code: 'alleya',
  axes: [attributes[0]!],
  individual_attributes: [attributes[1]!],
  products: [product],
  created_at: '',
  updated_at: '',
})

beforeEach(() => {
  vi.resetAllMocks()
  api.getProducts.mockResolvedValue({
    data: [],
    meta: { current_page: 1, last_page: 1, per_page: 25, total: 0 },
  })
  api.getProductFilterCounts.mockResolvedValue({
    active: 0,
    hidden: 0,
    sale: 0,
    regular: 0,
  })
})
afterEach(() => wrappers.splice(0).forEach((wrapper) => wrapper.unmount()))

test('assigns one exclusive role per characteristic and supports individual text', async () => {
  const wrapper = mount(ProductGroupAttributeRoles, {
    global: { stubs: { teleport: true } },
    props: {
      attributes,
      axisAttributeIds: [1],
      individualAttributeIds: [2],
      errors: {},
      disabled: false,
    },
  })
  wrappers.push(wrapper)
  const color = wrapper.find('[aria-label="Роль: Цвет"]')
  await color.trigger('click')
  await wrapper
    .findAll('[data-select-option]')
    .find((option) => option.text() === 'Индивидуальная')!
    .trigger('click')
  expect(wrapper.emitted('update:axisAttributeIds')?.at(-1)).toEqual([[]])
  expect(wrapper.emitted('update:individualAttributeIds')?.at(-1)).toEqual([
    [2, 1],
  ])

  await wrapper.find('[aria-label="Роль: Описание упаковки"]').trigger('click')
  expect(
    wrapper.findAll('[data-select-option]').map((option) => option.text()),
  ).toEqual(['Общая', 'Индивидуальная'])
  await wrapper
    .findAll('[data-select-option]')
    .find((option) => option.text() === 'Индивидуальная')!
    .trigger('click')
  expect(wrapper.emitted('update:individualAttributeIds')?.at(-1)).toEqual([
    [2, 3],
  ])
})

function editor() {
  let state!: ReturnType<typeof useProductEditor>
  wrappers.push(
    mount(
      defineComponent({
        setup() {
          state = useProductEditor()
          return () => null
        },
      }),
    ),
  )
  state.editing.value = product
  state.groups.value = [savedGroup()]
  state.fillGroup(savedGroup(), product.id)
  return state
}

test('loads individual roles and describes the saved membership rather than a draft selection', async () => {
  const state = editor()
  await flushPromises()
  expect(state.groupForm.value.individual_attribute_ids).toEqual([2])
  expect(state.attributeScope(2)).toBe('индивидуальная')
  state.fillGroup(null, product.id)
  expect(state.attributeScope(2)).toBe('индивидуальная')
  expect(state.attributeScope(3)).toBe('общая для группы')
})

test('prevents attribute save under unsaved role changes that would overwrite other products', async () => {
  const state = editor()
  await flushPromises()
  state.groupForm.value.individual_attribute_ids = [2, 3]
  await state.saveAttributes()
  expect(api.saveProductAttributeValues).not.toHaveBeenCalled()
  expect(state.error.value).toContain('Сначала сохраните роли характеристик')
})

test('saves the group with individual roles and keeps them after reopening', async () => {
  const state = editor()
  await flushPromises()
  state.groupForm.value.product_ids.push(8)
  api.saveProductGroup.mockResolvedValue(savedGroup())
  api.getAllProductGroups.mockResolvedValue([savedGroup()])
  await state.saveGroup()
  expect(api.saveProductGroup).toHaveBeenCalledWith(
    5,
    expect.objectContaining({
      axis_attribute_ids: [1],
      individual_attribute_ids: [2],
      product_ids: [7, 8],
    }),
  )
  state.fillGroup(state.groups.value[0]!, product.id)
  expect(state.groupForm.value.individual_attribute_ids).toEqual([2])
})
