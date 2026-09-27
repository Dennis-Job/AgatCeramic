import { onMounted, ref } from 'vue'
import { getAllMedia } from '../services/media'
import type { Media } from '../types/media.types'

export function useMediaOptions(kind: Media['kind']) {
  const options = ref<Media[]>([])
  const loading = ref(true)
  const error = ref('')

  async function load() {
    loading.value = true
    error.value = ''
    try {
      options.value = await getAllMedia(kind)
    } catch (reason) {
      error.value =
        reason instanceof Error ? reason.message : 'Не удалось загрузить файлы.'
    } finally {
      loading.value = false
    }
  }

  onMounted(load)
  return { options, loading, error, load }
}
