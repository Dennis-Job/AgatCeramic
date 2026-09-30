import { computed, ref } from 'vue'
import { loadAllPages } from '../../../services/pagination'
import { useBanners } from '../../banners/composables/useBanners'
import { saveBanner } from '../../banners/services/banners'
import type { Banner } from '../../banners/types/banner.types'
import { useSliders } from '../../sliders/composables/useSliders'
import { getSliders, saveSlider } from '../../sliders/services/sliders'
import type { Slider } from '../../sliders/types/slider.types'

export function useSliderBlock(
  selectedId: () => number | null,
  select: (id: number) => void,
) {
  const items = ref<Slider[]>([])
  const loading = ref(false)
  const error = ref('')
  const success = ref('')
  const sliders = useSliders()
  const banners = useBanners()
  const sliderBaseline = ref('')
  const bannerBaseline = ref('')
  const mediaPending = ref(false)
  const mediaUploading = ref(false)
  const discarding = ref<'slider' | 'banner' | null>(null)
  const selected = computed(
    () => items.value.find((item) => item.id === selectedId()) ?? null,
  )
  const busy = computed(
    () => sliders.busy.value || banners.busy.value || mediaUploading.value,
  )
  const sliderDirty = computed(
    () =>
      sliders.editorOpen.value &&
      JSON.stringify(sliders.form.value) !== sliderBaseline.value,
  )
  const bannerDirty = computed(
    () =>
      banners.editorOpen.value &&
      (mediaPending.value ||
        JSON.stringify(banners.form.value) !== bannerBaseline.value),
  )
  const dirty = computed(
    () => busy.value || sliderDirty.value || bannerDirty.value,
  )

  async function load(): Promise<void> {
    if (loading.value) return
    loading.value = true
    error.value = ''
    try {
      items.value = await loadAllPages(getSliders)
    } catch (reason) {
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось загрузить слайдеры.'
    } finally {
      loading.value = false
    }
  }

  function openSlider(slider: Slider | null): void {
    if (busy.value) return
    success.value = ''
    sliders.openEditor(slider)
    sliderBaseline.value = JSON.stringify(sliders.form.value)
  }

  function openBanner(banner: Banner | null): void {
    if (busy.value) return
    success.value = ''
    banners.openEditor(banner)
    mediaPending.value = false
    mediaUploading.value = false
    bannerBaseline.value = JSON.stringify(banners.form.value)
  }

  function requestClose(kind: 'slider' | 'banner'): void {
    if (busy.value) return
    if (kind === 'slider' ? sliderDirty.value : bannerDirty.value) {
      discarding.value = kind
      return
    }
    if (kind === 'slider') sliders.closeEditor()
    else banners.closeEditor()
  }

  function discard(): void {
    if (busy.value) return
    if (discarding.value === 'slider') sliders.closeEditor()
    if (discarding.value === 'banner') banners.closeEditor()
    discarding.value = null
  }

  async function submitSlider(): Promise<void> {
    if (busy.value) return
    sliders.busy.value = true
    sliders.formError.value = ''
    try {
      const saved = await saveSlider(sliders.editing.value?.id ?? null, {
        ...sliders.form.value,
        banner_ids: [...sliders.form.value.banner_ids],
      })
      items.value = [
        saved,
        ...items.value.filter((item) => item.id !== saved.id),
      ]
      select(saved.id)
      sliders.editorOpen.value = false
      success.value =
        'Слайдер сохранён. Выбор слайдера сохраните в черновике страницы.'
    } catch (reason) {
      sliders.formError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось сохранить слайдер.'
    } finally {
      sliders.busy.value = false
    }
  }

  async function submitBanner(): Promise<void> {
    if (busy.value) return
    if (mediaPending.value) {
      banners.formError.value =
        'Загрузите выбранный файл или отмените его выбор перед сохранением баннера.'
      return
    }
    banners.busy.value = true
    banners.formError.value = ''
    const creating = banners.editing.value === null
    try {
      const saved = await saveBanner(banners.editing.value?.id ?? null, {
        ...banners.form.value,
      })
      items.value = items.value.map((slider) => ({
        ...slider,
        banners: slider.banners.map((banner) =>
          banner.id === saved.id ? saved : banner,
        ),
      }))
      banners.editorOpen.value = false
      if (creating && selected.value) {
        sliders.openEditor(selected.value)
        sliderBaseline.value = JSON.stringify(sliders.form.value)
        sliders.addBanner(saved)
        success.value =
          'Баннер создан. Сохраните слайдер, чтобы добавить баннер в его состав.'
      } else {
        success.value = 'Баннер сохранён во всех слайдерах, где используется.'
      }
    } catch (reason) {
      banners.formError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось сохранить баннер.'
    } finally {
      banners.busy.value = false
    }
  }

  return {
    items,
    selected,
    loading,
    error,
    success,
    busy,
    dirty,
    discarding,
    mediaPending,
    mediaUploading,
    sliders,
    banners,
    load,
    openSlider,
    openBanner,
    requestClose,
    discard,
    submitSlider,
    submitBanner,
  }
}
