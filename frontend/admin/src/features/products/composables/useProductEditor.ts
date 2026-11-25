import { onBeforeUnmount, ref, watch, type Ref } from 'vue'
import type { AttributeDraftValue, Product } from '../types/product.types'
import type { ProductEditorSession } from '../types/productEditor.types'
import { useProductAttributes } from './useProductAttributes'
import type { useProductCatalogOptions } from './useProductCatalogOptions'
import { useProductEditorSteps } from './useProductEditorSteps'
import { useProductForm } from './useProductForm'
import { useProductGroups } from './useProductGroups'
import { useProductImages } from './useProductImages'
import { useProductRelations } from './useProductRelations'

type ProductEditorOptions = {
  catalog: ReturnType<typeof useProductCatalogOptions>
  canManage: Readonly<Ref<boolean>>
  error: Ref<string>
  saving: Ref<boolean>
  refreshList: () => Promise<void>
  updateThumbnail: (productId: number, image: Product['primary_image']) => void
}

export function useProductEditor({
  catalog,
  canManage,
  error,
  saving,
  refreshList,
  updateThumbnail,
}: ProductEditorOptions) {
  const opened = ref(false)
  const photosOnly = ref(false)
  const editing = ref<Product | null>(null)
  const success = ref('')
  const { activeStep, reset: resetStep, steps } = useProductEditorSteps()
  const relationDetailsLoading = ref(false)
  let detailsRequest = 0

  const session: ProductEditorSession = { editing, saving, error, success }
  const main = useProductForm(editing, catalog)
  const groups = useProductGroups(session, main.copiedFromProduct, () => {
    main.clearCopySource()
    activeStep.value = 'review'
  })
  const attributes = useProductAttributes(
    session,
    () => groups.groupRolesPending.value,
    () => {
      activeStep.value = 'images'
    },
  )
  const images = useProductImages(session, photosOnly, canManage, syncThumbnail)
  const relations = useProductRelations(
    session,
    opened,
    photosOnly,
    relationDetailsLoading,
  )

  function invalidateDetails() {
    detailsRequest++
    relationDetailsLoading.value = false
  }

  watch([opened, () => editing.value?.id, photosOnly], invalidateDetails, {
    flush: 'sync',
  })
  onBeforeUnmount(invalidateDetails)

  function syncThumbnail(
    productId: number,
    primaryImage: Product['primary_image'],
  ) {
    if (editing.value?.id === productId)
      editing.value = { ...editing.value, primary_image: primaryImage }
    updateThumbnail(productId, primaryImage)
  }

  function enabled(step: string) {
    return step === 'main' || editing.value !== null
  }

  async function loadDetails(
    product: Product,
    copiedAttributes: Record<number, AttributeDraftValue> | null = null,
  ) {
    const request = ++detailsRequest
    relationDetailsLoading.value = true
    try {
      const [attributeDetails, photoList, relationList, groupList] =
        await Promise.all([
          attributes.load(product),
          images.load(product.id),
          relations.load(product.id),
          groups.load(),
        ])
      if (
        request !== detailsRequest ||
        !opened.value ||
        editing.value?.id !== product.id ||
        photosOnly.value
      )
        return
      attributes.apply(attributeDetails, copiedAttributes)
      images.apply(photoList)
      relations.apply(relationList)
      groups.apply(product, groupList)
    } finally {
      if (request === detailsRequest) relationDetailsLoading.value = false
    }
  }

  async function open(product: Product | null = null) {
    photosOnly.value = false
    error.value = ''
    success.value = ''
    editing.value = product
    resetStep()
    main.reset(product)
    opened.value = true
    attributes.reset()
    images.reset()
    relations.reset()
    groups.reset()
    if (!product) return
    const loading = loadDetails(product)
    const request = detailsRequest
    try {
      await loading
    } catch (reason) {
      if (request === detailsRequest)
        error.value =
          reason instanceof Error
            ? reason.message
            : 'Не удалось загрузить карточку.'
    }
  }

  async function openPhotos(product: Product) {
    if (!canManage.value || opened.value) return
    photosOnly.value = true
    editing.value = product
    main.setProduct(product)
    activeStep.value = 'images'
    images.reset()
    success.value = ''
    opened.value = true
    await images.loadPhotos()
  }

  async function cloneProduct(product: Product) {
    await open()
    main.copyProduct(product)
    const request = detailsRequest
    try {
      const details = await attributes.load(product)
      if (request !== detailsRequest) return
      attributes.apply(details)
      success.value =
        'Данные и характеристики скопированы без фотографий. Укажите отличающееся название — URL сформируется из названия, а SKU будет назначен автоматически.'
    } catch {
      if (request === detailsRequest)
        error.value =
          'Основные данные скопированы, но характеристики исходного товара загрузить не удалось.'
    }
  }

  function close() {
    if (saving.value) return
    opened.value = false
    images.resetPhotoRequest()
    editing.value = null
  }

  async function saveMain() {
    if (saving.value || !main.validateName()) return
    const copiedAttributes = main.copiedFromProduct.value
      ? attributes.copyAttributeDrafts()
      : null
    saving.value = true
    error.value = ''
    try {
      const saved = await main.save()
      editing.value = saved
      main.setProduct(saved)
      await refreshList()
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

  async function saveVisibility(isActive: boolean) {
    if (!editing.value || saving.value) return
    saving.value = true
    error.value = ''
    try {
      const saved = await main.saveVisibility(editing.value.id, isActive)
      editing.value = saved
      main.setProduct(saved)
      success.value = isActive
        ? 'Товар опубликован.'
        : 'Товар скрыт и перемещён в черновики.'
      await refreshList()
    } catch (reason) {
      error.value =
        reason instanceof Error
          ? reason.message
          : isActive
            ? 'Не удалось опубликовать товар.'
            : 'Не удалось скрыть товар.'
    } finally {
      saving.value = false
    }
  }

  return {
    opened,
    photosOnly,
    editing,
    saving,
    error,
    success,
    canManage,
    activeStep,
    steps,
    enabled,
    main,
    attributes,
    images,
    groups,
    relations,
    open,
    openPhotos,
    cloneProduct,
    close,
    saveMain,
    saveVisibility,
  }
}
