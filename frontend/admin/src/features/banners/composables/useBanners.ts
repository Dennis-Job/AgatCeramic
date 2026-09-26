import { ref } from 'vue'
import { usePaginatedCollection } from '../../../composables/usePaginatedCollection'
import { deleteBanner, getBanners, saveBanner } from '../services/banners'
import type { Banner, BannerPayload } from '../types/banner.types'

const blankForm = (): BannerPayload => ({
  title: '',
  description: '',
  image_url: '',
  link_label: '',
  link_url: '',
  is_published: false,
})

export function useBanners() {
  const list = usePaginatedCollection<Banner>('Не удалось загрузить баннеры.')
  const editing = ref<Banner | null>(null)
  const form = ref<BannerPayload>(blankForm())
  const editorOpen = ref(false)
  const busy = ref(false)
  const formError = ref('')
  const deleteError = ref('')
  const success = ref('')

  async function load(
    page = list.pagination.value?.current_page ?? 1,
  ): Promise<void> {
    await list.load(page, (requestedPage) =>
      getBanners({ page: requestedPage }),
    )
  }

  function openEditor(banner: Banner | null = null): void {
    editing.value = banner
    form.value = banner
      ? {
          title: banner.title,
          description: banner.description ?? '',
          image_url: banner.image_url ?? '',
          link_label: banner.link_label ?? '',
          link_url: banner.link_url ?? '',
          is_published: banner.is_published,
        }
      : blankForm()
    formError.value = ''
    editorOpen.value = true
  }

  function closeEditor(): void {
    if (!busy.value) editorOpen.value = false
  }

  async function submit(): Promise<void> {
    busy.value = true
    formError.value = ''
    try {
      await saveBanner(editing.value?.id ?? null, form.value)
      editorOpen.value = false
      success.value = 'Баннер сохранён.'
      await load()
    } catch (reason) {
      formError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось сохранить баннер.'
    } finally {
      busy.value = false
    }
  }

  async function remove(banner: Banner): Promise<boolean> {
    busy.value = true
    deleteError.value = ''
    try {
      await deleteBanner(banner.id)
      success.value = 'Баннер удалён.'
      await list.reloadAfterDeletion((requestedPage) =>
        getBanners({ page: requestedPage }),
      )
      return true
    } catch (reason) {
      deleteError.value =
        reason instanceof Error ? reason.message : 'Не удалось удалить баннер.'
      return false
    } finally {
      busy.value = false
    }
  }

  return {
    ...list,
    load,
    editing,
    form,
    editorOpen,
    busy,
    formError,
    deleteError,
    success,
    openEditor,
    closeEditor,
    submit,
    remove,
  }
}
