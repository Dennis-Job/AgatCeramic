import { flushPromises, mount } from '@vue/test-utils'
import { defineComponent, ref } from 'vue'
import { afterEach, beforeEach, expect, test, vi } from 'vitest'
import ProductGroupAttributeRoles from '../src/features/products/components/ProductGroupAttributeRoles.vue'
import { useProductCatalogOptions } from '../src/features/products/composables/useProductCatalogOptions'
import { useProductEditor } from '../src/features/products/composables/useProductEditor'
import type { Attribute } from '../src/features/attributes/types/attribute.types'
import type {
  Product,
  ProductGroup,
} from '../src/features/products/types/product.types'

const api = vi.hoisted(() => ({
  getCategoryAttributes: vi.fn(),
  saveProductAttributeValues: vi.fn(),
  getProductGroup: vi.fn(),
  saveProductGroup: vi.fn(),
}))
vi.mock('../src/features/products/services/productAttributes', () => ({
  saveProductAttributeValues: api.saveProductAttributeValues,
  getCategoryAttributes: api.getCategoryAttributes,
  getProductAttributeValues: vi.fn(async () => []),
}))
vi.mock('../src/features/categories/services/categories', () => ({
  getCategoryAttributeGroups: vi.fn(async () => []),
}))
vi.mock('../src/features/products/services/productImages', () => ({
  getAllProductImages: vi.fn(async () => []),
}))
vi.mock('../src/features/products/services/productRelations', () => ({
  getProductRelations: vi.fn(async () => []),
}))
vi.mock('../src/features/products/services/productGroups', () => ({
  getProductGroup: api.getProductGroup,
  saveProductGroup: api.saveProductGroup,
}))
const wrappers: Array<ReturnType<typeof mount>> = []
const attributes = [
  { id: 1, name: 'Цвет', slug: 'color', type: 'select' },
  { id: 2, name: 'м² в упаковке', slug: 'package-area', type: 'decimal' },
  { id: 3, name: 'Описание упаковки', slug: 'pack-note', type: 'text' },
] as Attribute[]
const product = {
  id: 7,
  category_id: 1,
  group: { id: 5, name: 'Аллея', code: 'alleya' },
} as Product
const savedGroup = (products = [product]): ProductGroup => ({
  id: 5,
  name: 'Аллея',
  code: 'alleya',
  axes: [attributes[0]!],
  individual_attributes: [attributes[1]!],
  products,
  created_at: '',
  updated_at: '',
})

beforeEach(() => {
  vi.resetAllMocks()
  api.getCategoryAttributes.mockResolvedValue(attributes)
  api.getProductGroup.mockResolvedValue(savedGroup())
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

async function editor() {
  let state!: ReturnType<typeof useProductEditor>
  wrappers.push(
    mount(
      defineComponent({
        setup() {
          state = useProductEditor({
            catalog: useProductCatalogOptions(),
            canManage: ref(true),
            error: ref(''),
            saving: ref(false),
            refreshList: async () => {},
            updateThumbnail: vi.fn(),
          })
          return () => null
        },
      }),
    ),
  )
  state.open(product)
  state.activeStep.value = 'group'
  await flushPromises()
  return state
}

test('loads individual roles and describes the saved membership rather than a draft selection', async () => {
  const { groups } = await editor()
  expect(groups.groupForm.value.individual_attribute_ids).toEqual([2])
  expect(groups.attributeScope(2)).toBe('индивидуальная')
  await groups.selectGroup('')
  expect(groups.attributeScope(2)).toBe('индивидуальная')
  expect(groups.attributeScope(3)).toBe('общая для группы')
})

test('prevents attribute save under unsaved role changes that would overwrite other products', async () => {
  const state = await editor()
  state.groups.groupForm.value.individual_attribute_ids = [2, 3]
  await state.attributes.saveAttributes()
  expect(api.saveProductAttributeValues).not.toHaveBeenCalled()
  expect(state.error.value).toContain('Сначала сохраните роли характеристик')
})

test('saves the group with individual roles and keeps them after reopening', async () => {
  const state = await editor()
  state.groups.groupForm.value.product_ids.push(8)
  const updatedGroup = savedGroup([product, { ...product, id: 8 }])
  api.saveProductGroup.mockResolvedValue(updatedGroup)
  api.getProductGroup.mockResolvedValue(updatedGroup)
  await state.groups.saveGroup()
  expect(api.saveProductGroup).toHaveBeenCalledWith(
    5,
    expect.objectContaining({
      axis_attribute_ids: [1],
      individual_attribute_ids: [2],
      product_ids: [7, 8],
    }),
  )
  state.close()
  state.open(product)
  state.activeStep.value = 'group'
  await flushPromises()
  expect(state.groups.groupForm.value.individual_attribute_ids).toEqual([2])
})
