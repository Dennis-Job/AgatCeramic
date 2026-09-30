import { ref, toValue, watch, type MaybeRefOrGetter } from 'vue'
import { getAllMedia } from '../services/media'
import type { Media } from '../types/media.types'

export function useMediaOptions(
  kind: Media['kind'],
  enabled: MaybeRefOrGetter<boolean> = true,
) {
  const options = ref<Media[]>([])
  const loading = ref(true)
  const error = ref('')

  async function load() {
    if (!toValue(enabled)) return
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

  watch(
    () => toValue(enabled),
    (value) => {
      if (value) void load()
      else {
        options.value = []
        loading.value = false
        error.value = ''
      }
    },
    { immediate: true },
  )
  return { options, loading, error, load }
}
