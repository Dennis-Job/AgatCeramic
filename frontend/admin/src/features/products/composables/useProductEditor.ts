import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { usePaginatedCollection } from '../../../composables/usePaginatedCollection'
import type { Attribute } from '../../attributes/types/attribute.types'
import type { AttributeGroup } from '../../attribute-groups/types/attributeGroup.types'
import { getAllBrands } from '../../brands/services/brands'
import type { Brand } from '../../brands/types/brand.types'
import {
  getCategories,
  getCategoryAttributeGroups,
} from '../../categories/services/categories'
import type { Category } from '../../categories/types/category.types'
import {
  getCategoryAttributes,
  getProductAttributeValues,
  saveProductAttributeValues,
} from '../services/productAttributes'
import {
  deleteProductGroup,
  getAllProductGroups,
  ProductGroupRequestError,
  saveProductGroup,
} from '../services/productGroups'
import {
  deleteProductImage,
  getAllProductImages,
  updateProductImage,
  uploadProductImage,
} from '../services/productImages'
import {
  getProductRelations,
  getRelationCandidates,
  ProductRelationRequestError,
  saveProductRelations,
} from '../services/productRelations'
import { deleteProduct, getProducts, saveProduct } from '../services/products'
import { useAuthStore } from '../../../stores/auth'
import { compareAlphabetically, sortByLabel } from '../../../utils/alphabetical'
import { useProductFilterCounts } from './useProductFilterCounts'
import { useProductEditorSteps } from './useProductEditorSteps'
import {
  normalizedProductName,
  productSlug,
} from '../validation/product.schema'
import { validateProductImageFiles } from '../validation/productImages'
import type {
  AttributeDraftValue,
  Product,
  ProductFilters,
  ProductGroup,
  ProductGroupPayload,
  ProductImage,
  ProductPayload,
  ProductRelationDraft,
  ProductRelationType,
  ProductSort,
  ProductListFilters,
  ProductUnit,
  SortDirection,
} from '../types/product.types'

export function useProductEditor() {
  const auth = useAuthStore()
  const list = usePaginatedCollection<Product>('Не удалось загрузить товары.')
  const { items: products, pagination, error, loading } = list
  const categories = ref<Category[]>([])
  const brands = ref<Brand[]>([])
  let categoriesRequest: Promise<Category[]> | null = null
  let brandsRequest: Promise<Brand[]> | null = null
  const candidates = ref<Product[]>([])
  const groupProducts = ref<ProductGroup['products']>([])
  const opened = ref(false)
  const photosOnly = ref(false)
  const photosLoading = ref(false)
  const photosLoadFailed = ref(false)
  let photoRequest = 0
  const saving = ref(false)
  const editing = ref<Product | null>(null)
  const deleting = ref<Product | null>(null)
  const { activeStep, reset: resetStep, steps } = useProductEditorSteps()
  const success = ref('')
  const confirmError = ref('')
  const filterResultStatus = ref('')
  const productCountUnavailable = ref(false)
  const filters = ref<ProductListFilters>({
    search: '',
    category_id: '',
    brand_id: '',
    is_active: '',
    is_on_sale: '',
  })
  const {
    counts: filterCounts,
    loading: filterCountsLoading,
    error: filterCountsError,
    refresh: refreshFilterCounts,
  } = useProductFilterCounts(() => ({
    search: filters.value.search.trim() || undefined,
    category_id: filters.value.category_id
      ? Number(filters.value.category_id)
      : undefined,
    brand_id: filters.value.brand_id
      ? Number(filters.value.brand_id)
      : undefined,
    is_active:
      filters.value.is_active === ''
        ? undefined
        : filters.value.is_active === '1',
    is_on_sale:
      filters.value.is_on_sale === ''
        ? undefined
        : filters.value.is_on_sale === '1',
  }))
  const sort = ref<ProductSort>('created_at')
  const direction = ref<SortDirection>('desc')
  const form = ref<ProductPayload>(emptyProduct())
  const manuallyEditedSlug = ref(false)
  const copiedFromProduct = ref<Product | null>(null)
  const copiedNameError = ref('')
  const attributes = ref<Attribute[]>([])
  const attributeGroups = ref<AttributeGroup[]>([])
  const attributeValues = ref<Record<number, AttributeDraftValue>>({})
  const requiredIds = ref<number[]>([])
  const images = ref<ProductImage[]>([])
  const draggedImageId = ref<number | null>(null)
  const draggedOverImageId = ref<number | null>(null)
  const selectedFile = ref<File | null>(null)
  const imageInput = ref<HTMLInputElement | null>(null)
  const imageDeleting = ref<ProductImage | null>(null)
  const imageStatus = ref('')
  const groups = ref<ProductGroup[]>([])
  const selectedGroupId = ref('')
  const groupErrors = ref<Record<string, string[]>>({})
  const groupForm = ref<ProductGroupPayload>({
    name: '',
    code: '',
    axis_attribute_ids: [],
    individual_attribute_ids: [],
    product_ids: [],
  })
  const groupSearch = ref('')
  const groupDeleting = ref(false)
  const relations = ref<ProductRelationDraft[]>([])
  const relationErrors = ref<Record<number, string>>({})
  const relationSearch = ref('')
  const relationSearchLoading = ref(false)
  const relationDetailsLoading = ref(false)
  const relationSearchError = ref('')
  const relationLookupResult = ref<Product | null>(null)
  const relationSearchCompleted = ref(false)
  const relationActionStatus = ref('')
  const relationKnownProductIds = new Set<number>()
  let relationSearchRequest = 0
  let relationEditorSession = 0
  let relationSearchTimer: ReturnType<typeof setTimeout> | null = null
  const canManage = computed(() => auth.hasPermission('catalog.manage'))
  const canManageImports = computed(() => auth.hasPermission('imports.manage'))
  const sortLabels: Record<ProductSort, string> = {
    sku: 'SKU',
    name: 'наименованию',
    created_at: 'дате создания',
    updated_at: 'дате изменения',
  }
  const sortStatus = computed(
    () =>
      `Сортировка по ${sortLabels[sort.value]}, ${direction.value === 'asc' ? 'по возрастанию' : 'по убыванию'}.`,
  )
  const productDateFormatter = new Intl.DateTimeFormat('ru-RU', {
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
  })
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
  const relationTypes = sortByLabel([
    { value: 'related', label: 'Сопутствующий товар' },
    { value: 'recommended', label: 'Рекомендуемый товар' },
  ])
  const activityOptions = computed(() => [
    { value: '', label: 'Все' },
    { value: '1', label: 'Активные', count: filterCounts.value.active },
    { value: '0', label: 'Скрытые', count: filterCounts.value.hidden },
  ])
  const saleOptions = computed(() => [
    { value: '', label: 'Все' },
    { value: '1', label: 'Распродажа', count: filterCounts.value.sale },
    { value: '0', label: 'Не распродажа', count: filterCounts.value.regular },
  ])
  function flatten(nodes: Category[]): Category[] {
    return [...nodes]
      .sort((left, right) => compareAlphabetically(left.name, right.name))
      .flatMap((item) => [item, ...flatten(item.children ?? [])])
  }
  const categoryOptions = computed(() =>
    flatten(categories.value)
      .filter((item) => item.is_active)
      .map((item) => ({
        value: String(item.id),
        label: item.name,
      })),
  )
  const brandOptions = computed(() => [
    { value: '', label: 'Без бренда' },
    ...sortByLabel(
      brands.value.map((item) => ({
        value: String(item.id),
        label: item.name,
      })),
    ),
  ])
  const filterCategoryOptions = computed(() => [
    { value: '', label: 'Все категории' },
    ...categoryOptions.value,
  ])
  const filterBrandOptions = computed(() => [
    { value: '', label: 'Все бренды' },
    ...brandOptions.value.slice(1),
  ])
  const groupedAttributeSections = computed(() =>
    attributeGroups.value
      .map((group) => ({
        ...group,
        attributes: attributes.value.filter(
          (attribute) => attribute.attribute_group_id === group.id,
        ),
      }))
      .filter((group) => group.attributes.length),
  )
  const groupedAttributeIds = computed(
    () =>
      new Set(
        groupedAttributeSections.value.flatMap((group) =>
          group.attributes.map((attribute) => attribute.id),
        ),
      ),
  )
  const ungroupedAttributes = computed(() =>
    attributes.value.filter(
      (attribute) => !groupedAttributeIds.value.has(attribute.id),
    ),
  )
  const attributeSections = computed(() => [
    ...groupedAttributeSections.value,
    ...(ungroupedAttributes.value.length
      ? [
          {
            id: 'ungrouped',
            name: 'Без группы',
            attributes: ungroupedAttributes.value,
          },
        ]
      : []),
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
  const groupOptions = computed(() => [
    { value: '', label: 'Новая группа' },
    ...sortByLabel(
      groups.value.map((group) => ({
        value: String(group.id),
        label: `${group.name} · ${group.code}`,
      })),
    ),
  ])
  const currentGroup = computed(
    () =>
      groups.value.find((group) =>
        group.products.some((product) => product.id === editing.value?.id),
      ) ?? null,
  )
  const groupRolesPending = computed(() => {
    const group = currentGroup.value
    if (!group || selectedGroupId.value !== String(group.id)) return false
    const sameIds = (draft: number[], saved: number[]) =>
      draft.length === saved.length && draft.every((id) => saved.includes(id))
    return (
      !sameIds(
        groupForm.value.axis_attribute_ids,
        group.axes.map((a) => a.id),
      ) ||
      !sameIds(
        groupForm.value.individual_attribute_ids,
        (group.individual_attributes ?? []).map((a) => a.id),
      )
    )
  })
  function attributeScope(id: number): string {
    const group = currentGroup.value
    if (!group) return ''
    if (group.axes.some((a) => a.id === id)) return 'для выбора варианта'
    if (group.individual_attributes?.some((a) => a.id === id))
      return 'индивидуальная'
    return 'общая для группы'
  }
  const normalizeSku = (sku: string) => sku.trim().toLocaleLowerCase('ru')
  const relationSkuExclusion = computed(() => {
    const sku = normalizeSku(relationSearch.value)
    if (!sku) return ''
    if (sku === normalizeSku(editing.value?.sku ?? ''))
      return 'Это SKU редактируемого товара. Выберите другой товар.'
    if (
      candidates.value.some(
        (product) =>
          normalizeSku(product.sku) === sku &&
          relations.value.some(
            (row) => Number(row.related_product_id) === product.id,
          ),
      )
    )
      return 'Этот товар уже добавлен. Тип связи можно изменить в списке ниже.'
    return ''
  })
  const relationSearchResult = computed(() =>
    relationSearchLoading.value ||
    relationDetailsLoading.value ||
    relationSearchError.value ||
    relationSkuExclusion.value
      ? null
      : relationLookupResult.value,
  )
  const relationSearchMessage = computed(
    () =>
      relationSkuExclusion.value ||
      (relationSearchCompleted.value &&
      !relationLookupResult.value &&
      relationSearch.value.trim()
        ? 'Товар с таким SKU не найден среди доступных для добавления.'
        : ''),
  )
  const hasActiveFilters = computed(() =>
    Object.values(filters.value).some(Boolean),
  )
  function slugify(value: string) {
    return productSlug(value)
  }
  function normalizedName(value: string) {
    return normalizedProductName(value)
  }
  const productCountLabel = computed(() => {
    if (loading.value) return 'Обновляем количество товаров…'
    if (productCountUnavailable.value) return 'Количество товаров недоступно'
    const total = pagination.value?.total
    if (total === undefined) return 'Обновляем количество товаров…'
    return hasActiveFilters.value
      ? `Найдено товаров: ${total}`
      : `Всего товаров в каталоге: ${total}`
  })
  function relationProduct(id: string) {
    return candidates.value.find((product) => String(product.id) === id)
  }
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
  function inputValue(v: unknown): AttributeDraftValue {
    if (Array.isArray(v)) return v.map(String)
    if (typeof v === 'boolean') return v ? 'true' : 'false'
    return String(v ?? '')
  }
  function attributeLabel(attribute: Attribute): string {
    return `${attribute.name}${attribute.unit ? ` (${attribute.unit})` : ''}`
  }
  function displayAttributeValue(
    attribute: Attribute | undefined,
    value: unknown,
  ): string {
    if (attribute?.type === 'select')
      return (
        attribute.options.find((option) => option.value === String(value))
          ?.label ?? String(value ?? '')
      )
    if (typeof value === 'boolean') return value ? 'Да' : 'Нет'
    if (Array.isArray(value))
      return value.map((item) => String(item)).join(', ')
    return String(value ?? '')
  }
  function hasValue(v: AttributeDraftValue | undefined) {
    return Array.isArray(v) ? v.length > 0 : String(v ?? '').trim() !== ''
  }
  function typed(
    a: Attribute,
    v: AttributeDraftValue,
  ): string | number | boolean | string[] {
    if (a.type === 'integer') return Number.parseInt(String(v), 10)
    if (a.type === 'decimal') return Number(v)
    if (a.type === 'boolean') return v === 'true'
    if (a.type === 'multiselect') return Array.isArray(v) ? v : []
    return String(v)
  }
  function enabled(step: string) {
    return step === 'main' || editing.value !== null
  }
  function filtersPayload(): ProductFilters {
    return {
      search: filters.value.search.trim() || undefined,
      category_id: filters.value.category_id
        ? Number(filters.value.category_id)
        : undefined,
      brand_id: filters.value.brand_id
        ? Number(filters.value.brand_id)
        : undefined,
      is_active:
        filters.value.is_active === ''
          ? undefined
          : filters.value.is_active === '1',
      is_on_sale:
        filters.value.is_on_sale === ''
          ? undefined
          : filters.value.is_on_sale === '1',
      sort: sort.value,
      direction: direction.value,
    }
  }
  function loadCatalogOptions() {
    categoriesRequest ??= getCategories().catch((reason: unknown) => {
      categoriesRequest = null
      throw reason
    })
    brandsRequest ??= getAllBrands().catch((reason: unknown) => {
      brandsRequest = null
      throw reason
    })
    return Promise.all([categoriesRequest, brandsRequest]).then(
      ([categoryList, brandList]) => ({ categoryList, brandList }),
    )
  }
  async function fetchPage(page: number) {
    const [productPage, catalogOptions] = await Promise.all([
      getProducts({ ...filtersPayload(), page, perPage: 50 }),
      loadCatalogOptions(),
    ])
    return { ...productPage, ...catalogOptions }
  }
  let listLoadVersion = 0
  async function load(
    page = pagination.value?.current_page ?? 1,
  ): Promise<boolean> {
    ++listLoadVersion
    filterResultStatus.value = ''
    productCountUnavailable.value = false
    const response = await list.load(page, fetchPage)
    if (!response) {
      if (error.value) productCountUnavailable.value = true
      return false
    }
    categories.value = response.categoryList
    brands.value = response.brandList
    const from = response.meta.from ?? 0
    const to = response.meta.to ?? 0
    filterResultStatus.value =
      response.meta.total === 0
        ? 'Товары не найдены.'
        : `${hasActiveFilters.value ? 'Найдено товаров' : 'Всего товаров в каталоге'}: ${response.meta.total}. Показано ${from}–${to}.`
    return true
  }
  async function setSort(field: ProductSort, order: SortDirection) {
    if (loading.value) return
    if (sort.value === field && direction.value === order) return
    const previousSort = sort.value
    const previousDirection = direction.value
    sort.value = field
    direction.value = order
    const requestVersion = listLoadVersion + 1
    // A newer filter request already uses the selected sort; a stale response
    // must not roll the controls back behind the displayed results.
    if (!(await load(1)) && requestVersion === listLoadVersion) {
      sort.value = previousSort
      direction.value = previousDirection
    }
  }
  function changeSort(field: ProductSort) {
    const order =
      sort.value === field
        ? direction.value === 'asc'
          ? 'desc'
          : 'asc'
        : field === 'created_at' || field === 'updated_at'
          ? 'desc'
          : 'asc'
    return setSort(field, order)
  }
  function ariaSort(field: ProductSort): 'none' | 'ascending' | 'descending' {
    return sort.value === field
      ? direction.value === 'asc'
        ? 'ascending'
        : 'descending'
      : 'none'
  }
  function formatDate(value: string): string {
    return productDateFormatter.format(new Date(value))
  }
  function resetFilters() {
    filters.value = {
      search: '',
      category_id: '',
      brand_id: '',
      is_active: '',
      is_on_sale: '',
    }
  }
  function fillGroup(group: ProductGroup | null, id: number) {
    selectedGroupId.value = group ? String(group.id) : ''
    groupForm.value = group
      ? {
          name: group.name,
          code: group.code,
          axis_attribute_ids: group.axes.map((a) => a.id),
          individual_attribute_ids: (group.individual_attributes ?? []).map(
            (a) => a.id,
          ),
          product_ids: group.products.map((p) => p.id),
        }
      : {
          name: '',
          code: '',
          axis_attribute_ids: [],
          individual_attribute_ids: [],
          product_ids: [id],
        }
  }
  function prepareGroupDraft(product: Product, groupList: ProductGroup[]) {
    const ownGroup =
      groupList.find((group) =>
        group.products.some((item) => item.id === product.id),
      ) ?? null
    if (ownGroup) {
      groupProducts.value = ownGroup.products
      fillGroup(ownGroup, product.id)
      return
    }
    const source = copiedFromProduct.value
    const compatible =
      source &&
      source.category_id === product.category_id &&
      source.brand_id === product.brand_id
    if (!source || !compatible) {
      groupProducts.value = [product]
      fillGroup(null, product.id)
      return
    }
    const sourceGroup =
      groupList.find((group) =>
        group.products.some((item) => item.id === source.id),
      ) ?? null
    if (sourceGroup) {
      groupProducts.value = [...sourceGroup.products, product]
      fillGroup(sourceGroup, product.id)
      groupForm.value.product_ids = [...groupForm.value.product_ids, product.id]
      return
    }
    groupProducts.value = [source, product]
    fillGroup(null, product.id)
    groupForm.value.product_ids = [source.id, product.id]
  }
  function copyAttributeDrafts(values: Record<number, AttributeDraftValue>) {
    return Object.fromEntries(
      Object.entries(values).map(([id, value]) => [
        id,
        Array.isArray(value) ? [...value] : value,
      ]),
    )
  }
  async function loadDetails(
    product: Product,
    copiedAttributes: Record<number, AttributeDraftValue> | null = null,
  ) {
    const session = relationEditorSession
    relationDetailsLoading.value = true
    try {
      const [
        categoryAttrs,
        categoryAttributeGroups,
        savedAttrs,
        photoList,
        relationList,
        groupList,
      ] = await Promise.all([
        getCategoryAttributes(product.category_id),
        getCategoryAttributeGroups(product.category_id),
        getProductAttributeValues(product.id),
        getAllProductImages(product.id),
        getProductRelations(product.id),
        getAllProductGroups(),
      ])
      if (
        session !== relationEditorSession ||
        !opened.value ||
        editing.value?.id !== product.id ||
        photosOnly.value
      )
        return
      attributes.value = categoryAttrs
      attributeGroups.value = categoryAttributeGroups
      requiredIds.value = categoryAttrs
        .filter((a) => a.is_required)
        .map((a) => a.id)
      const saved = new Map(savedAttrs.map((v) => [v.attribute_id, v.value]))
      attributeValues.value = Object.fromEntries(
        categoryAttrs.map((a) => [
          a.id,
          copiedAttributes && a.id in copiedAttributes
            ? copiedAttributes[a.id]
            : inputValue(saved.get(a.id)),
        ]),
      )
      images.value = photoList
      relations.value = relationList.map((r) => ({
        related_product_id: String(r.related_product_id),
        type: r.type,
        sort_order: String(r.sort_order),
      }))
      groups.value = groupList
      prepareGroupDraft(product, groupList)
      candidates.value = relationList.map(
        (relation) => relation.related_product,
      )
      candidates.value.forEach((product) =>
        relationKnownProductIds.add(product.id),
      )
    } finally {
      if (session === relationEditorSession)
        relationDetailsLoading.value = false
    }
  }
  async function open(product: Product | null = null) {
    photoRequest++
    photosOnly.value = false
    photosLoading.value = false
    selectedFile.value = null
    imageStatus.value = ''
    error.value = ''
    success.value = ''
    copiedFromProduct.value = null
    copiedNameError.value = ''
    editing.value = product
    resetStep()
    form.value = product ? toPayload(product) : emptyProduct()
    manuallyEditedSlug.value = Boolean(product)
    opened.value = true
    attributes.value = []
    attributeGroups.value = []
    attributeValues.value = {}
    images.value = []
    relations.value = []
    groups.value = []
    groupProducts.value = []
    selectedGroupId.value = ''
    groupForm.value = {
      name: '',
      code: '',
      axis_attribute_ids: [],
      individual_attribute_ids: [],
      product_ids: [],
    }
    groupErrors.value = {}
    groupSearch.value = ''
    if (product)
      try {
        await loadDetails(product)
      } catch (reason) {
        error.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось загрузить карточку.'
      }
  }
  async function loadPhotos() {
    if (!editing.value || !photosOnly.value) return
    const productId = editing.value.id
    const request = ++photoRequest
    photosLoading.value = true
    photosLoadFailed.value = false
    error.value = ''
    try {
      const photoList = await getAllProductImages(productId)
      if (request !== photoRequest) return
      images.value = photoList
      syncProductThumbnail()
    } catch (reason) {
      if (request !== photoRequest) return
      photosLoadFailed.value = true
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось загрузить фотографии.'
    } finally {
      if (request === photoRequest) photosLoading.value = false
    }
  }
  async function openPhotos(product: Product) {
    if (!canManage.value || opened.value) return
    photosOnly.value = true
    editing.value = product
    form.value = toPayload(product)
    activeStep.value = 'images'
    images.value = []
    selectedFile.value = null
    imageStatus.value = ''
    success.value = ''
    opened.value = true
    await loadPhotos()
  }
  async function cloneProduct(product: Product) {
    await open(null)
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
    try {
      const [attrs, categoryAttributeGroups, values] = await Promise.all([
        getCategoryAttributes(product.category_id),
        getCategoryAttributeGroups(product.category_id),
        getProductAttributeValues(product.id),
      ])
      attributes.value = attrs
      attributeGroups.value = categoryAttributeGroups
      const saved = new Map(values.map((v) => [v.attribute_id, v.value]))
      attributeValues.value = Object.fromEntries(
        attrs.map((a) => [a.id, inputValue(saved.get(a.id))]),
      )
      success.value =
        'Данные и характеристики скопированы без фотографий. Укажите отличающееся название — URL сформируется из названия, а SKU будет назначен автоматически.'
    } catch {
      error.value =
        'Основные данные скопированы, но характеристики исходного товара загрузить не удалось.'
    }
  }
  function close() {
    if (!saving.value) {
      opened.value = false
      photoRequest++
      photosLoading.value = false
      selectedFile.value = null
      editing.value = null
    }
  }
  function syncProductThumbnail() {
    if (!editing.value) return
    const primary =
      images.value.find((image) => image.is_primary) ?? images.value[0] ?? null
    const primary_image = primary
      ? { id: primary.id, url: primary.url, alt: primary.alt }
      : null
    const productId = editing.value.id
    editing.value = { ...editing.value, primary_image }
    products.value = products.value.map((product) =>
      product.id === productId ? { ...product, primary_image } : product,
    )
  }
  async function saveMain() {
    if (saving.value) return
    copiedNameError.value = ''
    if (
      copiedFromProduct.value &&
      normalizedProductName(form.value.name) ===
        normalizedProductName(copiedFromProduct.value.name)
    ) {
      copiedNameError.value = `Название должно отличаться от исходного товара «${copiedFromProduct.value.name}».`
      return
    }
    const copiedAttributes = copiedFromProduct.value
      ? copyAttributeDrafts(attributeValues.value)
      : null
    saving.value = true
    error.value = ''
    try {
      const saved = await saveProduct(editing.value?.id ?? null, {
        ...form.value,
        is_active: editing.value ? form.value.is_active : false,
        article_number: form.value.article_number || null,
        barcode: form.value.barcode || null,
        old_price: form.value.old_price || null,
      })
      editing.value = saved
      form.value = toPayload(saved)
      await load()
      void refreshFilterCounts()
      await loadDetails(saved, copiedAttributes)
      success.value = 'Основные и коммерческие данные сохранены.'
      activeStep.value = 'attributes'
    } catch (reason) {
      error.value =
        reason instanceof Error ? reason.message : 'Не удалось сохранить товар.'
    } finally {
      saving.value = false
    }
  }
  async function saveAttributes() {
    if (!editing.value || saving.value) return
    if (groupRolesPending.value) {
      error.value =
        'Сначала сохраните роли характеристик на шаге «Варианты модели».'
      return
    }
    error.value = ''
    saving.value = true
    try {
      await saveProductAttributeValues(editing.value.id, {
        attributes: attributes.value
          .filter((a) => hasValue(attributeValues.value[a.id]))
          .map((a) => ({
            attribute_id: a.id,
            value: typed(a, attributeValues.value[a.id]),
          })),
      })
      success.value = 'Характеристики сохранены.'
      activeStep.value = 'images'
    } catch (reason) {
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось сохранить характеристики.'
    } finally {
      saving.value = false
    }
  }
  function selectFile(event: Event) {
    selectImageFiles((event.target as HTMLInputElement).files)
  }
  function selectImageFiles(files: FileList | null) {
    if (saving.value || !canManage.value || !files?.length) return
    const validationError = validateProductImageFiles(files)
    error.value = validationError
    success.value = ''
    selectedFile.value = validationError ? null : (files[0] ?? null)
    if (imageInput.value) imageInput.value.value = ''
  }
  function chooseFile() {
    imageInput.value?.click()
  }
  async function upload() {
    if (!editing.value || !selectedFile.value || saving.value) return
    saving.value = true
    error.value = ''
    try {
      const image = await uploadProductImage(
        editing.value.id,
        selectedFile.value,
        { sort_order: images.value.length },
      )
      images.value.push(image)
      syncProductThumbnail()
      selectedFile.value = null
      if (imageInput.value) imageInput.value.value = ''
      success.value = `Фотография ${image.alt} загружена.`
    } catch (reason) {
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось загрузить фотографию.'
    } finally {
      saving.value = false
    }
  }
  async function saveImageOrder(reordered: ProductImage[], status: string) {
    if (!editing.value || saving.value) return
    saving.value = true
    try {
      for (const [position, image] of reordered.entries())
        await updateProductImage(editing.value.id, image.id, {
          sort_order: position,
          is_primary: position === 0,
        })
      images.value = await getAllProductImages(editing.value.id)
      syncProductThumbnail()
      imageStatus.value = status
    } catch (reason) {
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось изменить порядок.'
    } finally {
      draggedImageId.value = null
      draggedOverImageId.value = null
      saving.value = false
    }
  }
  async function reorderImage(index: number, direction: -1 | 1) {
    const target = index + direction
    if (target < 0 || target >= images.value.length) return
    const reordered = [...images.value]
    ;[reordered[index], reordered[target]] = [
      reordered[target],
      reordered[index],
    ]
    await saveImageOrder(
      reordered,
      `Порядок сохранён. Изображение на позиции ${target + 1}.`,
    )
  }
  function startImageDrag(image: ProductImage, event: DragEvent) {
    if (!canManage.value || saving.value) return
    draggedImageId.value = image.id
    event.dataTransfer?.setData('text/plain', String(image.id))
    if (event.dataTransfer) event.dataTransfer.effectAllowed = 'move'
  }
  function markImageDropTarget(image: ProductImage) {
    if (draggedImageId.value !== null && draggedImageId.value !== image.id)
      draggedOverImageId.value = image.id
  }
  function endImageDrag() {
    draggedImageId.value = null
    draggedOverImageId.value = null
  }
  async function dropImage(targetIndex: number) {
    const sourceIndex = images.value.findIndex(
      (image) => image.id === draggedImageId.value,
    )
    if (sourceIndex < 0 || sourceIndex === targetIndex) {
      endImageDrag()
      return
    }
    const reordered = [...images.value]
    const [draggedImage] = reordered.splice(sourceIndex, 1)
    reordered.splice(targetIndex, 0, draggedImage)
    await saveImageOrder(
      reordered,
      `Порядок сохранён. Изображение на позиции ${targetIndex + 1}.`,
    )
  }
  async function removeImage() {
    if (!editing.value || !imageDeleting.value || saving.value) return
    saving.value = true
    confirmError.value = ''
    try {
      await deleteProductImage(editing.value.id, imageDeleting.value.id)
      imageDeleting.value = null
      images.value = await getAllProductImages(editing.value.id)
      syncProductThumbnail()
    } catch (reason) {
      confirmError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось удалить фотографию.'
    } finally {
      saving.value = false
    }
  }
  function selectGroup(id: string) {
    fillGroup(
      groups.value.find((g) => String(g.id) === id) ?? null,
      editing.value!.id,
    )
  }
  async function searchGroupProducts() {
    if (!editing.value || saving.value) return
    saving.value = true
    error.value = ''
    try {
      const found = (
        await getProducts({
          search: groupSearch.value || undefined,
          category_id: editing.value.category_id,
          brand_id: editing.value.brand_id ?? undefined,
          page: 1,
          perPage: 25,
        })
      ).data
      const selected = groupProducts.value.filter((product) =>
        groupForm.value.product_ids.includes(product.id),
      )
      groupProducts.value = [
        ...selected,
        ...found.filter(
          (product) => !selected.some((item) => item.id === product.id),
        ),
      ]
    } catch (reason) {
      error.value =
        reason instanceof Error ? reason.message : 'Не удалось найти товары.'
    } finally {
      saving.value = false
    }
  }
  async function saveGroup() {
    if (!editing.value || saving.value) return
    groupErrors.value = {}
    if (!groupForm.value.axis_attribute_ids.length) {
      groupErrors.value.axis_attribute_ids = [
        'Выберите хотя бы одну характеристику.',
      ]
      return
    }
    if (groupForm.value.product_ids.length < 2) {
      groupErrors.value.product_ids = ['Добавьте минимум два товара.']
      return
    }
    saving.value = true
    try {
      const saved = await saveProductGroup(
        selectedGroupId.value ? Number(selectedGroupId.value) : null,
        groupForm.value,
      )
      groups.value = await getAllProductGroups()
      fillGroup(saved, editing.value.id)
      copiedFromProduct.value = null
      success.value = 'Группа вариантов сохранена.'
      activeStep.value = 'review'
    } catch (reason) {
      if (reason instanceof ProductGroupRequestError)
        groupErrors.value = reason.details
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось сохранить группу.'
    } finally {
      saving.value = false
    }
  }
  function addRelation(type: ProductRelationType): boolean {
    const product = relationSearchResult.value
    if (
      product &&
      editing.value &&
      opened.value &&
      product.id !== editing.value.id &&
      !saving.value &&
      !relationDetailsLoading.value &&
      !relationSearchLoading.value &&
      !relations.value.some(
        (row) => Number(row.related_product_id) === product.id,
      )
    ) {
      relations.value.push({
        related_product_id: String(product.id),
        type,
        sort_order: String(
          Math.max(
            -1,
            ...relations.value.map((row) => Number(row.sort_order) || 0),
          ) + 1,
        ),
      })
      relationKnownProductIds.add(product.id)
      relationErrors.value = {}
      relationSearch.value = ''
      success.value = ''
      relationActionStatus.value =
        type === 'related'
          ? 'Товар добавлен в сопутствующие.'
          : 'Товар добавлен в рекомендуемые.'
      return true
    }
    return false
  }
  function removeRelation(index: number) {
    if (saving.value || relationDetailsLoading.value) return
    relations.value.splice(index, 1)
    relationErrors.value = {}
    success.value = ''
    relationActionStatus.value = 'Товар удалён из списка.'
    if (relationSearch.value.trim()) void searchRelations()
  }
  async function searchRelations() {
    if (relationSearchTimer) clearTimeout(relationSearchTimer)
    relationSearchTimer = null
    if (
      !editing.value ||
      !opened.value ||
      photosOnly.value ||
      relationDetailsLoading.value
    )
      return
    const productId = editing.value.id
    const query = relationSearch.value.trim()
    const request = ++relationSearchRequest
    relationLookupResult.value = null
    relationSearchCompleted.value = false
    relationSearchError.value = ''
    relationSearchLoading.value = false
    if (!query || relationSkuExclusion.value) return
    relationSearchLoading.value = true
    try {
      const found = await getRelationCandidates(productId, query, 20)
      if (request !== relationSearchRequest) return
      const exact =
        found.find(
          (product) => normalizeSku(product.sku) === normalizeSku(query),
        ) ??
        candidates.value.find(
          (product) =>
            relationKnownProductIds.has(product.id) &&
            normalizeSku(product.sku) === normalizeSku(query),
        )
      const available =
        exact &&
        exact.id !== productId &&
        !relations.value.some(
          (row) => Number(row.related_product_id) === exact.id,
        )
      relationLookupResult.value = available ? exact : null
      candidates.value = candidates.value.filter((product) =>
        relationKnownProductIds.has(product.id),
      )
      if (
        available &&
        !candidates.value.some((product) => product.id === exact.id)
      )
        candidates.value.push(exact)
      relationSearchCompleted.value = true
    } catch (reason) {
      if (request !== relationSearchRequest) return
      relationSearchError.value =
        reason instanceof Error ? reason.message : 'Не удалось найти товары.'
    } finally {
      if (request === relationSearchRequest) relationSearchLoading.value = false
    }
  }
  function resetRelationSearch() {
    relationSearch.value = ''
    if (relationSearchTimer) clearTimeout(relationSearchTimer)
    relationSearchTimer = null
    relationSearchRequest++
    relationEditorSession++
    relationDetailsLoading.value = false
    relationSearchLoading.value = false
    relationSearchError.value = ''
    relationLookupResult.value = null
    relationSearchCompleted.value = false
    relationActionStatus.value = ''
    relationKnownProductIds.clear()
    candidates.value = []
  }
  watch([opened, () => editing.value?.id, photosOnly], resetRelationSearch, {
    flush: 'sync',
  })
  watch(
    relationSearch,
    () => {
      if (relationSearchTimer) clearTimeout(relationSearchTimer)
      relationSearchRequest++
      relationLookupResult.value = null
      relationSearchCompleted.value = false
      relationActionStatus.value = ''
      relationSearchError.value = ''
      relationSearchLoading.value = false
      if (
        !editing.value ||
        !opened.value ||
        photosOnly.value ||
        relationDetailsLoading.value
      )
        return
      if (!relationSearch.value.trim() || relationSkuExclusion.value) return
      relationSearchLoading.value = true
      relationSearchTimer = setTimeout(() => void searchRelations(), 350)
    },
    { flush: 'sync' },
  )
  async function saveRelations() {
    if (!editing.value || saving.value || relationDetailsLoading.value) return
    relationErrors.value = {}
    error.value = ''
    success.value = ''
    saving.value = true
    try {
      const saved = await saveProductRelations(editing.value.id, {
        relations: relations.value.map((r) => ({
          related_product_id: Number(r.related_product_id),
          type: r.type,
          sort_order: Number(r.sort_order),
        })),
      })
      relations.value = saved.map((r) => ({
        related_product_id: String(r.related_product_id),
        type: r.type,
        sort_order: String(r.sort_order),
      }))
      success.value = 'Связи товаров сохранены.'
    } catch (reason) {
      if (reason instanceof ProductRelationRequestError)
        Object.entries(reason.details).forEach(([key, messages]) => {
          const m = key.match(/^relations\.(\d+)/)
          if (m) relationErrors.value[Number(m[1])] = messages[0]
        })
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось сохранить рекомендации.'
    } finally {
      saving.value = false
    }
  }
  async function removeProduct() {
    if (!deleting.value || saving.value) return
    saving.value = true
    confirmError.value = ''
    try {
      await deleteProduct(deleting.value.id)
      deleting.value = null
      await load()
      void refreshFilterCounts()
    } catch (reason) {
      confirmError.value =
        reason instanceof Error ? reason.message : 'Не удалось удалить товар.'
    } finally {
      saving.value = false
    }
  }
  async function ungroup() {
    if (!selectedGroupId.value || saving.value) return
    saving.value = true
    confirmError.value = ''
    try {
      await deleteProductGroup(Number(selectedGroupId.value))
      groups.value = await getAllProductGroups()
      fillGroup(null, editing.value!.id)
      groupDeleting.value = false
      success.value = 'Группа удалена.'
    } catch (reason) {
      confirmError.value =
        reason instanceof Error ? reason.message : 'Не удалось удалить группу.'
    } finally {
      saving.value = false
    }
  }
  async function publish() {
    if (!editing.value) return
    saving.value = true
    error.value = ''
    try {
      const saved = await saveProduct(editing.value.id, {
        ...form.value,
        is_active: true,
      })
      editing.value = saved
      form.value = toPayload(saved)
      success.value = 'Товар опубликован.'
      await load()
      void refreshFilterCounts()
    } catch (reason) {
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось опубликовать товар.'
    } finally {
      saving.value = false
    }
  }
  async function hideProduct() {
    if (!editing.value) return
    saving.value = true
    error.value = ''
    try {
      const saved = await saveProduct(editing.value.id, {
        ...form.value,
        is_active: false,
      })
      editing.value = saved
      form.value = toPayload(saved)
      success.value = 'Товар скрыт и перемещён в черновики.'
      await load()
      void refreshFilterCounts()
    } catch (reason) {
      error.value =
        reason instanceof Error ? reason.message : 'Не удалось скрыть товар.'
    } finally {
      saving.value = false
    }
  }
  let filterTimer: ReturnType<typeof setTimeout> | null = null
  let previousFilterSearch = filters.value.search
  watch(
    filters,
    (next) => {
      if (filterTimer) clearTimeout(filterTimer)
      const delay = next.search !== previousFilterSearch ? 350 : 250
      previousFilterSearch = next.search
      filterTimer = setTimeout(() => {
        filterTimer = null
        void load(1)
      }, delay)
    },
    { deep: true },
  )
  onBeforeUnmount(() => {
    if (filterTimer) clearTimeout(filterTimer)
    if (relationSearchTimer) clearTimeout(relationSearchTimer)
    relationSearchRequest++
    relationEditorSession++
  })
  onMounted(load)
  return {
    products,
    pagination,
    error,
    loading,
    categories,
    brands,
    candidates,
    groupProducts,
    opened,
    photosOnly,
    photosLoading,
    photosLoadFailed,
    saving,
    editing,
    deleting,
    activeStep,
    steps,
    success,
    confirmError,
    filterResultStatus,
    filterCountsLoading,
    filterCountsError,
    productCountUnavailable,
    filters,
    sort,
    direction,
    form,
    manuallyEditedSlug,
    copiedFromProduct,
    copiedNameError,
    attributes,
    attributeGroups,
    attributeValues,
    requiredIds,
    images,
    draggedImageId,
    draggedOverImageId,
    selectedFile,
    imageInput,
    imageDeleting,
    imageStatus,
    groups,
    selectedGroupId,
    groupErrors,
    groupForm,
    groupSearch,
    groupDeleting,
    relations,
    relationErrors,
    relationSearch,
    relationSearchLoading,
    relationDetailsLoading,
    relationSearchError,
    relationSearchResult,
    relationSearchMessage,
    relationActionStatus,
    canManage,
    canManageImports,
    sortLabels,
    sortStatus,
    copiedFromName,
    units,
    relationTypes,
    activityOptions,
    saleOptions,
    categoryOptions,
    brandOptions,
    filterCategoryOptions,
    filterBrandOptions,
    attributeSections,
    selectedCategory,
    selectedBrand,
    groupOptions,
    currentGroup,
    groupRolesPending,
    attributeScope,
    hasActiveFilters,
    productCountLabel,
    relationProduct,
    ariaSort,
    formatDate,
    slugify,
    normalizedName,
    emptyProduct,
    toPayload,
    inputValue,
    attributeLabel,
    displayAttributeValue,
    hasValue,
    typed,
    load,
    resetFilters,
    changeSort,
    setSort,
    enabled,
    fillGroup,
    prepareGroupDraft,
    copyAttributeDrafts,
    loadDetails,
    open,
    openPhotos,
    loadPhotos,
    cloneProduct,
    close,
    syncProductThumbnail,
    saveMain,
    saveAttributes,
    selectFile,
    selectImageFiles,
    chooseFile,
    upload,
    reorderImage,
    startImageDrag,
    markImageDropTarget,
    endImageDrag,
    dropImage,
    removeImage,
    selectGroup,
    searchGroupProducts,
    saveGroup,
    addRelation,
    removeRelation,
    searchRelations,
    saveRelations,
    removeProduct,
    ungroup,
    publish,
    hideProduct,
  }
}
