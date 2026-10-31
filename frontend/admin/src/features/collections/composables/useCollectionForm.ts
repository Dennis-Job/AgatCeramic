import { computed, ref } from 'vue'
import type {
  ProductCollection,
  CollectionPayload,
} from '../types/collection.types'
import {
  emptyCollection,
  validateCollection,
} from '../validation/collection.schema'
import { slugify } from '../../catalog/validation/slug'
export function useCollectionForm(
  save: (
    id: number | null,
    payload: CollectionPayload,
  ) => Promise<ProductCollection>,
  reload: () => Promise<void>,
) {
  const open = ref(false)
  const editing = ref<ProductCollection | null>(null)
  const busy = ref(false)
  const mediaPending = ref(false)
  const mediaUploading = ref(false)
  const error = ref('')
  const form = ref(emptyCollection())
  const manual = ref(false)
  const title = computed(() =>
    editing.value ? 'Редактирование коллекции' : 'Новая коллекция',
  )
  function show(collection: ProductCollection | null = null) {
    editing.value = collection
    manual.value = Boolean(collection)
    mediaPending.value = false
    mediaUploading.value = false
    error.value = ''
    form.value = collection
      ? {
          name: collection.name,
          slug: collection.slug,
          brand_id: collection.brand_id,
          headline: collection.headline ?? '',
          description: collection.description ?? '',
          image_id: collection.image_id,
          is_active: collection.is_active,
        }
      : emptyCollection()
    open.value = true
  }
  function updateName(value: string) {
    form.value.name = value
    if (!manual.value) form.value.slug = slugify(value)
  }
  async function submit() {
    if (busy.value || mediaPending.value || mediaUploading.value) return
    error.value = validateCollection(form.value)
    if (error.value) return
    busy.value = true
    try {
      await save(editing.value?.id ?? null, form.value)
      open.value = false
      await reload()
    } catch (reason) {
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось сохранить коллекцию.'
    } finally {
      busy.value = false
    }
  }
  return {
    open,
    editing,
    busy,
    mediaPending,
    mediaUploading,
    error,
    form,
    title,
    show,
    submit,
    updateName,
    updateSlug: (value: string) => {
      form.value.slug = value
      manual.value = true
    },
    close: () => {
      if (!busy.value && !mediaUploading.value) open.value = false
    },
  }
}
