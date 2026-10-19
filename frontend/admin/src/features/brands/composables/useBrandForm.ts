import { computed, ref } from 'vue'
import type { Brand, BrandPayload } from '../types/brand.types'
import { emptyBrand, validateBrand } from '../validation/brand.schema'
import { slugify } from '../../catalog/validation/slug'
export function useBrandForm(
  save: (id: number | null, payload: BrandPayload) => Promise<Brand>,
  reload: () => Promise<void>,
) {
  const open = ref(false)
  const editing = ref<Brand | null>(null)
  const busy = ref(false)
  const mediaPending = ref(false)
  const mediaUploading = ref(false)
  const error = ref('')
  const form = ref<BrandPayload>(emptyBrand())
  const manual = ref(false)
  const title = computed(() =>
    editing.value ? 'Редактирование бренда' : 'Новый бренд',
  )
  function show(brand: Brand | null = null) {
    mediaPending.value = false
    mediaUploading.value = false
    editing.value = brand
    manual.value = brand !== null
    error.value = ''
    form.value = brand
      ? {
          name: brand.name,
          slug: brand.slug,
          description: brand.description ?? '',
          country_code: brand.country_code,
          logo_id: brand.logo_id,
          document_ids: brand.documents.map((item) => item.id),
          is_active: brand.is_active,
        }
      : emptyBrand()
    open.value = true
  }
  function updateName(value: string) {
    form.value.name = value
    if (!manual.value) form.value.slug = slugify(value)
  }
  async function submit() {
    if (busy.value || mediaPending.value || mediaUploading.value) return
    error.value = validateBrand(form.value)
    if (error.value) return
    busy.value = true
    try {
      await save(editing.value?.id ?? null, form.value)
      open.value = false
      await reload()
    } catch (reason) {
      error.value =
        reason instanceof Error ? reason.message : 'Не удалось сохранить бренд.'
    } finally {
      busy.value = false
    }
  }
  return {
    open,
    busy,
    mediaPending,
    mediaUploading,
    error,
    form,
    title,
    show,
    close: () => {
      if (busy.value || mediaUploading.value) return
      open.value = false
    },
    updateName,
    updateSlug: (value: string) => {
      form.value.slug = value
      manual.value = true
    },
    submit,
  }
}
