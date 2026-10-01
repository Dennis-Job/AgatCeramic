import { fetchDraftPreview } from '../services/preview'
import type { DraftPreview } from '../types/preview'

export function useDraftPreview(slug: Ref<string>) {
  const { publicBase } = usePublicApiConfig()
  // Drafts stay in component memory: never SSR payload, shared state or storage.
  const data = shallowRef<DraftPreview | null>(null)
  const loading = ref(true)
  const error = ref('')
  let controller: AbortController | undefined
  let timer: ReturnType<typeof setInterval> | undefined
  let generation = 0

  function clear() {
    generation++
    controller?.abort()
    data.value = null
  }

  async function refresh() {
    if (document.visibilityState === 'hidden') return
    controller?.abort()
    controller = new AbortController()
    const request = ++generation
    loading.value = !data.value
    error.value = ''
    try {
      const result = await fetchDraftPreview(
        slug.value,
        publicBase,
        controller.signal,
      )
      if (request !== generation) return
      // Preserve slider position and animation when the saved draft is unchanged.
      if (JSON.stringify(result) !== JSON.stringify(data.value))
        data.value = result
    } catch (reason) {
      if (request !== generation) return
      data.value = null
      const status = (reason as { statusCode?: number }).statusCode
      error.value =
        status === 401 || status === 403
          ? 'Войдите в административную панель с правом управления контентом. Доступ к черновику закрыт.'
          : status === 404
            ? 'Сохранённая страница не найдена.'
            : 'Не удалось проверить доступ и загрузить черновик. Повторите загрузку.'
    } finally {
      if (request === generation) loading.value = false
    }
  }

  function visibilityChanged() {
    if (document.visibilityState === 'hidden') clear()
    else void refresh()
  }

  onMounted(() => {
    void refresh()
    timer = setInterval(() => void refresh(), 15_000)
    document.addEventListener('visibilitychange', visibilityChanged)
    window.addEventListener('pagehide', clear)
    window.addEventListener('pageshow', visibilityChanged)
  })
  watch(slug, () => {
    clear()
    if (import.meta.client) void refresh()
  })
  onBeforeUnmount(() => {
    clear()
    clearInterval(timer)
    document.removeEventListener('visibilitychange', visibilityChanged)
    window.removeEventListener('pagehide', clear)
    window.removeEventListener('pageshow', visibilityChanged)
  })
  return { data, loading, error, refresh }
}
