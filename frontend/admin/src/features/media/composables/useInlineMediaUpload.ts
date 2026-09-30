import { ref } from 'vue'
import { uploadMedia } from '../services/media'
import type { Media } from '../types/media.types'

export function useInlineMediaUpload(
  kind: Media['kind'],
  onUploaded: (media: Media) => void,
) {
  const file = ref<File | null>(null)
  const title = ref('')
  const alt = ref('')
  const uploading = ref(false)
  const error = ref('')
  const success = ref('')
  const inputKey = ref(0)
  function choose(event: Event): void {
    file.value = (event.target as HTMLInputElement).files?.[0] ?? null
    title.value = file.value?.name.replace(/\.[^.]+$/, '') ?? ''
    error.value = ''
    success.value = ''
  }
  async function upload(): Promise<void> {
    if (!file.value || uploading.value) return
    if (!title.value.trim() || (kind === 'image' && !alt.value.trim())) {
      error.value = 'Укажите название и описание изображения.'
      return
    }
    uploading.value = true
    error.value = ''
    success.value = ''
    try {
      const media = await uploadMedia(
        file.value,
        kind,
        title.value.trim(),
        alt.value.trim(),
      )
      onUploaded(media)
      file.value = null
      title.value = ''
      alt.value = ''
      inputKey.value++
      success.value =
        'Файл загружен и выбран. Сохраните черновик, чтобы закрепить выбор.'
    } catch (reason) {
      error.value =
        reason instanceof Error ? reason.message : 'Не удалось загрузить файл.'
    } finally {
      uploading.value = false
    }
  }
  return {
    cancel: () => {
      if (!uploading.value) {
        file.value = null
        inputKey.value++
        error.value = ''
      }
    },
    file,
    title,
    alt,
    uploading,
    error,
    success,
    inputKey,
    choose,
    upload,
  }
}
