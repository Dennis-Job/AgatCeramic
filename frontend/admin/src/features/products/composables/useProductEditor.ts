import { computed, onBeforeUnmount, ref, watch, type Ref } from 'vue'
import type { AttributeDraftValue, Product } from '../types/product.types'
import type { ProductEditorSession } from '../types/productEditor.types'
import { useProductAttributes } from './useProductAttributes'
import type { useProductCatalogOptions } from './useProductCatalogOptions'
import { useProductEditorSteps } from './useProductEditorSteps'
import { useProductForm } from './useProductForm'
import { useProductGroups } from './useProductGroups'
import { useProductImages } from './useProductImages'
import { useProductRelations } from './useProductRelations'
import { useProductSectionLoad } from './useProductSectionLoad'

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
  const copyingAttributes = ref(false)
  let editorSession = 0
  let copiedAttributes: Record<number, AttributeDraftValue> | null = null

  const session: ProductEditorSession = { editing, saving, error, success }
  const main = useProductForm(editing, catalog)
  const groups = useProductGroups(
    session,
    main.copiedFromProduct,
    () => {
      main.clearCopySource()
      activeStep.value = 'review'
    },
    async (group) => {
      if (editing.value)
        editing.value = {
          ...editing.value,
          group: group
            ? { id: group.id, name: group.name, code: group.code }
            : null,
        }
      await refreshList()
    },
  )
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
    computed<boolean>(() => !relationLoad.state.ready),
  )

  const attributeLoad = useProductSectionLoad(
    attributes.load,
    (data) => {
      attributes.apply(data, copiedAttributes)
      copiedAttributes = null
    },
    'Не удалось загрузить характеристики.',
  )
  const photoLoad = useProductSectionLoad(
    (product) => images.load(product.id),
    images.apply,
    'Не удалось загрузить фотографии.',
  )
  const relationLoad = useProductSectionLoad(
    (product) => relations.load(product.id),
    relations.apply,
    'Не удалось загрузить связи товаров.',
  )
  const membershipLoad = useProductSectionLoad(
    groups.loadMembership,
    groups.applyMembership,
    'Не удалось загрузить текущую группу.',
  )
  const groupLoad = useProductSectionLoad(
    groups.loadDraft,
    groups.prepareDraft,
    'Не удалось подготовить варианты модели.',
  )
  const loaders = [
    attributeLoad,
    photoLoad,
    relationLoad,
    membershipLoad,
    groupLoad,
  ]
  const stepLoads = {
    main: [],
    attributes: [attributeLoad, membershipLoad],
    images: [photoLoad],
    group: [attributeLoad, membershipLoad, groupLoad],
    review: [attributeLoad, photoLoad, relationLoad],
  }
  const sectionError = computed(
    () =>
      stepLoads[activeStep.value].find((loader) => loader.state.error)?.state
        .error ?? '',
  )
  const sectionReady = computed(() =>
    stepLoads[activeStep.value].every((loader) => loader.state.ready),
  )

  function resetSections() {
    editorSession++
    copyingAttributes.value = false
    loaders.forEach((loader) => loader.reset())
    attributes.reset()
    images.reset()
    relations.reset()
    groups.reset()
  }

  async function loadStep(retry = false) {
    const product = editing.value
    if (!opened.value || !product || photosOnly.value) return
    const step = activeStep.value
    const session = editorSession
    await Promise.all(
      stepLoads[step]
        .filter((loader) => loader !== groupLoad)
        .map((loader) => loader.ensure(product, retry)),
    )
    if (
      step === 'group' &&
      session === editorSession &&
      membershipLoad.state.ready
    )
      await groupLoad.ensure(product, retry)
  }

  watch([opened, activeStep, () => editing.value?.id], () => {
    void loadStep()
  })
  onBeforeUnmount(resetSections)

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

  function open(product: Product | null = null) {
    resetSections()
    copiedAttributes = null
    photosOnly.value = false
    error.value = ''
    success.value = ''
    editing.value = product
    resetStep()
    main.reset(product)
    opened.value = true
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
    open()
    main.copyProduct(product)
    const session = editorSession
    copyingAttributes.value = true
    try {
      const details = await attributes.load(product)
      if (session !== editorSession) return
      attributes.apply(details)
      success.value =
        'Данные и характеристики скопированы без фотографий. Укажите отличающееся название — URL сформируется из названия, а SKU будет назначен автоматически.'
    } catch {
      if (session === editorSession)
        error.value =
          'Основные данные скопированы, но характеристики исходного товара загрузить не удалось.'
    } finally {
      if (session === editorSession) copyingAttributes.value = false
    }
  }

  function close() {
    if (saving.value) return
    opened.value = false
    editing.value = null
    resetSections()
    copiedAttributes = null
  }

  async function saveMain() {
    if (saving.value || copyingAttributes.value || !main.validateName()) return
    const copy = main.copiedFromProduct.value
      ? (copiedAttributes ?? attributes.copyAttributeDrafts())
      : null
    saving.value = true
    error.value = ''
    try {
      const saved = await main.save()
      resetSections()
      copiedAttributes = copy
      editing.value = saved
      main.setProduct(saved)
      success.value = 'Основные и коммерческие данные сохранены.'
      activeStep.value = 'attributes'
      void loadStep()
      await refreshList()
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
    copyingAttributes,
    activeStep,
    sectionReady,
    sectionError,
    retrySection: () => loadStep(true),
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
