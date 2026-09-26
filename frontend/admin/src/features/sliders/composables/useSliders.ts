import { ref } from 'vue'
import { usePaginatedCollection } from '../../../composables/usePaginatedCollection'
import type { Banner } from '../../banners/types/banner.types'
import {
  deleteSlider,
  getBannerOptions,
  getSliders,
  saveSlider,
} from '../services/sliders'
import type { Slider, SliderPayload } from '../types/slider.types'

const blankForm = (): SliderPayload => ({
  name: '',
  slug: '',
  is_published: false,
  banner_ids: [],
})

export function useSliders() {
  const list = usePaginatedCollection<Slider>('Не удалось загрузить слайдеры.')
  const editing = ref<Slider | null>(null)
  const form = ref<SliderPayload>(blankForm())
  const selectedBanners = ref<Banner[]>([])
  const options = ref<Banner[]>([])
  const optionsLoading = ref(false)
  const optionsError = ref('')
  const editorOpen = ref(false)
  const busy = ref(false)
  const formError = ref('')
  const deleteError = ref('')
  const success = ref('')

  async function load(page = list.pagination.value?.current_page ?? 1) {
    await list.load(page, (requestedPage) =>
      getSliders({ page: requestedPage }),
    )
  }

  async function searchBanners(search = ''): Promise<void> {
    optionsLoading.value = true
    optionsError.value = ''
    try {
      options.value = await getBannerOptions(search)
    } catch (reason) {
      optionsError.value =
        reason instanceof Error ? reason.message : 'Не удалось найти баннеры.'
    } finally {
      optionsLoading.value = false
    }
  }

  function openEditor(slider: Slider | null = null): void {
    editing.value = slider
    selectedBanners.value = [...(slider?.banners ?? [])]
    form.value = slider
      ? {
          name: slider.name,
          slug: slider.slug,
          is_published: slider.is_published,
          banner_ids: slider.banners.map((banner) => banner.id),
        }
      : blankForm()
    formError.value = ''
    editorOpen.value = true
    void searchBanners()
  }

  function closeEditor(): void {
    if (!busy.value) editorOpen.value = false
  }

  function addBanner(banner: Banner): void {
    if (selectedBanners.value.some((item) => item.id === banner.id)) return
    if (selectedBanners.value.length >= 50) {
      formError.value = 'В слайдер можно добавить не более 50 баннеров.'
      return
    }
    selectedBanners.value.push(banner)
    form.value.banner_ids = selectedBanners.value.map((item) => item.id)
  }

  function moveBanner(index: number, offset: number): void {
    const next = index + offset
    if (next < 0 || next >= selectedBanners.value.length) return
    const items = [...selectedBanners.value]
    ;[items[index], items[next]] = [items[next]!, items[index]!]
    selectedBanners.value = items
    form.value.banner_ids = items.map((item) => item.id)
  }

  function removeBanner(id: number): void {
    selectedBanners.value = selectedBanners.value.filter(
      (item) => item.id !== id,
    )
    form.value.banner_ids = selectedBanners.value.map((item) => item.id)
  }

  async function submit(): Promise<void> {
    busy.value = true
    formError.value = ''
    try {
      await saveSlider(editing.value?.id ?? null, form.value)
      editorOpen.value = false
      success.value = 'Слайдер сохранён.'
      await load()
    } catch (reason) {
      formError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось сохранить слайдер.'
    } finally {
      busy.value = false
    }
  }

  async function remove(slider: Slider): Promise<boolean> {
    busy.value = true
    deleteError.value = ''
    try {
      await deleteSlider(slider.id)
      success.value = 'Слайдер удалён.'
      await list.reloadAfterDeletion((page) => getSliders({ page }))
      return true
    } catch (reason) {
      deleteError.value =
        reason instanceof Error ? reason.message : 'Не удалось удалить слайдер.'
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
    selectedBanners,
    options,
    optionsLoading,
    optionsError,
    editorOpen,
    busy,
    formError,
    deleteError,
    success,
    searchBanners,
    openEditor,
    closeEditor,
    addBanner,
    moveBanner,
    removeBanner,
    submit,
    remove,
  }
}
