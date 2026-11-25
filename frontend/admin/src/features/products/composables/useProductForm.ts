import { computed, ref, type Ref } from 'vue'
import { sortByLabel } from '../../../utils/alphabetical'
import { saveProduct } from '../services/products'
import {
  normalizedProductName,
  productSlug,
} from '../validation/product.schema'
import type {
  Product,
  ProductPayload,
  ProductUnit,
} from '../types/product.types'
import type { useProductCatalogOptions } from './useProductCatalogOptions'

function emptyProduct(): ProductPayload {
  return {
    category_id: 0,
    brand_id: null,
    collection_id: null,
    name: '',
    slug: '',
    description: '',
    article_number: null,
    barcode: null,
    unit: 'piece',
    price: '0.00',
    old_price: null,
    stock_quantity: 0,
    is_active: false,
    is_on_sale: false,
  }
}
function toPayload(p: Product): ProductPayload {
  return {
    category_id: p.category_id,
    brand_id: p.brand_id,
    collection_id: p.collection_id ?? null,
    name: p.name,
    slug: p.slug,
    description: p.description ?? '',
    article_number: p.article_number,
    barcode: p.barcode,
    unit: p.unit,
    price: p.price,
    old_price: p.old_price,
    stock_quantity: p.stock_quantity,
    is_active: p.is_active,
    is_on_sale: p.is_on_sale,
  }
}

export function useProductForm(
  editing: Readonly<Ref<Product | null>>,
  {
    categories,
    categoryOptions,
    brandOptions,
  }: ReturnType<typeof useProductCatalogOptions>,
) {
  const form = ref<ProductPayload>(emptyProduct())
  const manuallyEditedSlug = ref(false)
  const copiedFromProduct = ref<Product | null>(null)
  const copiedNameError = ref('')
  const copiedFromName = computed(() => copiedFromProduct.value?.name ?? null)
  const units: Array<{ value: ProductUnit; label: string }> = sortByLabel([
    { value: 'piece', label: 'Штука' },
    { value: 'square_meter', label: 'Квадратный метр' },
    { value: 'linear_meter', label: 'Погонный метр' },
    { value: 'package', label: 'Упаковка' },
    { value: 'kilogram', label: 'Килограмм' },
    { value: 'liter', label: 'Литр' },
    { value: 'set', label: 'Комплект' },
  ])
  const selectedCategory = computed({
    get: () => (form.value.category_id ? String(form.value.category_id) : ''),
    set: (value) => (form.value.category_id = Number(value)),
  })
  const selectedBrand = computed({
    get: () =>
      form.value.brand_id === null ? '' : String(form.value.brand_id),
    set: (value) => (form.value.brand_id = value ? Number(value) : null),
  })

  function reset(product: Product | null) {
    clearCopySource()
    copiedNameError.value = ''
    setProduct(product)
    manuallyEditedSlug.value = Boolean(product)
  }

  function clearCopySource() {
    copiedFromProduct.value = null
  }

  function setProduct(product: Product | null) {
    form.value = product ? toPayload(product) : emptyProduct()
  }

  function copyProduct(product: Product) {
    copiedFromProduct.value = product
    form.value = {
      ...toPayload(product),
      collection_id:
        product.category.uses_collections &&
        product.collection?.brand_id === product.brand_id &&
        product.collection?.is_active
          ? product.collection_id
          : null,
      name: '',
      slug: '',
      article_number: null,
      barcode: null,
      is_active: false,
    }
    manuallyEditedSlug.value = false
  }

  function validateName() {
    copiedNameError.value = ''
    const source = copiedFromProduct.value
    if (
      !source ||
      normalizedProductName(form.value.name) !==
        normalizedProductName(source.name)
    )
      return true
    copiedNameError.value = `Название должно отличаться от исходного товара «${source.name}».`
    return false
  }

  function save() {
    return saveProduct(editing.value?.id ?? null, {
      ...form.value,
      is_active: editing.value ? form.value.is_active : false,
      article_number: form.value.article_number || null,
      barcode: form.value.barcode || null,
      old_price: form.value.old_price || null,
    })
  }

  function saveVisibility(id: number, isActive: boolean) {
    return saveProduct(id, { ...form.value, is_active: isActive })
  }

  return {
    categories,
    categoryOptions,
    brandOptions,
    copiedFromProduct,
    copiedFromName,
    copiedNameError,
    form,
    manuallyEditedSlug,
    slugify: productSlug,
    selectedCategory,
    selectedBrand,
    units,
    reset,
    clearCopySource,
    setProduct,
    copyProduct,
    validateName,
    save,
    saveVisibility,
  }
}
