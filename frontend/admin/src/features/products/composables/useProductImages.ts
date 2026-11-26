import { onBeforeUnmount, ref, type Ref } from 'vue'
import {
  deleteProductImage,
  getAllProductImages,
  updateProductImage,
  uploadProductImage,
} from '../services/productImages'
import { validateProductImageFiles } from '../validation/productImages'
import type { Product, ProductImage } from '../types/product.types'
import type { ProductEditorSession } from '../types/productEditor.types'

export function useProductImages(
  { editing, saving, error, success }: ProductEditorSession,
  photosOnly: Ref<boolean>,
  canManage: Readonly<Ref<boolean>>,
  updateThumbnail: (productId: number, image: Product['primary_image']) => void,
) {
  const confirmError = ref('')
  const photosLoading = ref(false)
  const photosLoadFailed = ref(false)
  let photoRequest = 0
  const images = ref<ProductImage[]>([])
  const draggedImageId = ref<number | null>(null)
  const draggedOverImageId = ref<number | null>(null)
  const selectedFile = ref<File | null>(null)
  const imageInput = ref<HTMLInputElement | null>(null)
  const imageDeleting = ref<ProductImage | null>(null)
  const imageStatus = ref('')

  function resetPhotoRequest() {
    photoRequest++
    photosLoading.value = false
    selectedFile.value = null
  }

  function reset() {
    resetPhotoRequest()
    photosLoadFailed.value = false
    closeImageDeletion()
    imageStatus.value = ''
    images.value = []
  }

  function apply(photoList: ProductImage[]) {
    images.value = photoList
  }

  function requestImageDeletion(image: ProductImage) {
    confirmError.value = ''
    imageDeleting.value = image
  }

  function closeImageDeletion() {
    confirmError.value = ''
    imageDeleting.value = null
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
  function syncProductThumbnail() {
    if (!editing.value) return
    const primary =
      images.value.find((image) => image.is_primary) ?? images.value[0] ?? null
    const primary_image = primary
      ? { id: primary.id, url: primary.url, alt: primary.alt }
      : null
    updateThumbnail(editing.value.id, primary_image)
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

  onBeforeUnmount(resetPhotoRequest)

  return {
    images,
    draggedImageId,
    draggedOverImageId,
    selectedFile,
    imageInput,
    imageDeleting,
    imageStatus,
    confirmError,
    photosLoading,
    photosLoadFailed,
    resetPhotoRequest,
    reset,
    load: getAllProductImages,
    apply,
    loadPhotos,
    selectFile,
    selectImageFiles,
    chooseFile,
    upload,
    reorderImage,
    startImageDrag,
    markImageDropTarget,
    endImageDrag,
    dropImage,
    requestImageDeletion,
    closeImageDeletion,
    removeImage,
  }
}
