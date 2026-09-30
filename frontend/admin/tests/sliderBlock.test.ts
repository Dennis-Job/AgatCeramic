import { beforeEach, describe, expect, it, vi } from 'vitest'
import { ref } from 'vue'
import { useSliderBlock } from '../src/features/pages/composables/useSliderBlock'
import {
  getSliders,
  saveSlider,
} from '../src/features/sliders/services/sliders'
import { saveBanner } from '../src/features/banners/services/banners'
import type { Banner } from '../src/features/banners/types/banner.types'
import type { Slider } from '../src/features/sliders/types/slider.types'

vi.mock('../src/features/sliders/services/sliders', () => ({
  getSliders: vi.fn(),
  saveSlider: vi.fn(),
  getBannerOptions: vi.fn().mockResolvedValue([]),
  deleteSlider: vi.fn(),
}))
vi.mock('../src/features/banners/services/banners', () => ({
  saveBanner: vi.fn(),
  getBanners: vi.fn(),
  deleteBanner: vi.fn(),
}))

const banner: Banner = {
  id: 5,
  title: 'Общий баннер',
  eyebrow: null,
  description: null,
  image_url: '/media/5.webp',
  legacy_image_url: null,
  image_media_id: 5,
  image_alt: 'Керамическая плитка',
  link_label: null,
  link_url: null,
  is_published: true,
  created_at: '',
  updated_at: '',
}
const slider: Slider = {
  id: 2,
  name: 'Главный',
  slug: 'home',
  is_published: true,
  banners: [banner],
  created_at: '',
  updated_at: '',
}

describe('contextual slider editor', () => {
  beforeEach(() => vi.clearAllMocks())

  it('loads later pages so an existing reference remains editable', async () => {
    vi.mocked(getSliders)
      .mockResolvedValueOnce({
        data: [],
        meta: { current_page: 1, last_page: 2, per_page: 25, total: 26 },
      })
      .mockResolvedValueOnce({
        data: [slider],
        meta: { current_page: 2, last_page: 2, per_page: 25, total: 26 },
      })
    const state = useSliderBlock(() => 2, vi.fn())
    await state.load()
    expect(getSliders).toHaveBeenCalledTimes(2)
    expect(state.selected.value).toEqual(slider)
  })

  it('requires explicit discard of dirty dialog changes', () => {
    const state = useSliderBlock(() => 2, vi.fn())
    state.openSlider(slider)
    expect(state.dirty.value).toBe(false)
    state.sliders.form.value.name = 'Несохранённый заголовок'
    state.requestClose('slider')
    expect(state.sliders.editorOpen.value).toBe(true)
    expect(state.discarding.value).toBe('slider')
    expect(state.dirty.value).toBe(true)
    state.discard()
    expect(state.sliders.editorOpen.value).toBe(false)
    expect(state.dirty.value).toBe(false)
    expect(saveSlider).not.toHaveBeenCalled()
  })

  it('guards selected upload files even before the banner payload changes', async () => {
    const state = useSliderBlock(() => 2, vi.fn())
    state.openBanner(banner)
    state.mediaPending.value = true
    expect(state.dirty.value).toBe(true)
    state.requestClose('banner')
    expect(state.discarding.value).toBe('banner')
    expect(state.banners.editorOpen.value).toBe(true)
    await state.submitBanner()
    expect(saveBanner).not.toHaveBeenCalled()
    expect(state.banners.formError.value).toContain('Загрузите выбранный файл')
    state.discard()
    expect(state.dirty.value).toBe(false)
  })

  it('refuses close and save while a media upload is running', async () => {
    const state = useSliderBlock(() => 2, vi.fn())
    state.openBanner(banner)
    state.mediaUploading.value = true
    expect(state.busy.value).toBe(true)
    expect(state.dirty.value).toBe(true)
    state.requestClose('banner')
    expect(state.discarding.value).toBeNull()
    expect(state.banners.editorOpen.value).toBe(true)
    await state.submitBanner()
    expect(saveBanner).not.toHaveBeenCalled()
  })

  it('keeps failed saves editable and reports in-flight writes as dirty', async () => {
    let reject!: (reason: Error) => void
    vi.mocked(saveSlider).mockReturnValueOnce(
      new Promise((_resolve, fail) => {
        reject = fail
      }),
    )
    const select = vi.fn()
    const state = useSliderBlock(() => 2, select)
    state.openSlider(slider)
    state.sliders.form.value.name = 'Новый заголовок'
    const saving = state.submitSlider()
    expect(state.dirty.value).toBe(true)
    state.requestClose('slider')
    expect(state.discarding.value).toBeNull()
    reject(new Error('Ошибка сохранения'))
    await saving
    expect(state.sliders.editorOpen.value).toBe(true)
    expect(state.sliders.formError.value).toBe('Ошибка сохранения')
    expect(state.sliders.form.value.name).toBe('Новый заголовок')
    expect(select).not.toHaveBeenCalled()
  })

  it('updates a reused banner in every cached slider without changing the page selection', async () => {
    const updated = { ...banner, title: 'Исправленный общий баннер' }
    vi.mocked(saveBanner).mockResolvedValueOnce(updated)
    const select = vi.fn()
    const state = useSliderBlock(() => 2, select)
    state.items.value = [slider, { ...slider, id: 3 }]
    state.openBanner(banner)
    state.banners.form.value.title = updated.title
    await state.submitBanner()
    expect(
      state.items.value.every(
        (item) => item.banners[0]?.title === updated.title,
      ),
    ).toBe(true)
    expect(state.banners.editorOpen.value).toBe(false)
    expect(select).not.toHaveBeenCalled()
  })

  it('creates a reusable banner but attaches it only after explicit slider save', async () => {
    const created = { ...banner, id: 6, title: 'Новый баннер' }
    vi.mocked(saveBanner).mockResolvedValueOnce(created)
    vi.mocked(saveSlider).mockResolvedValueOnce({
      ...slider,
      banners: [banner, created],
    })
    const id = ref<number | null>(2)
    const select = vi.fn((value: number) => {
      id.value = value
    })
    const state = useSliderBlock(() => id.value, select)
    state.items.value = [slider]
    state.openBanner(null)
    state.banners.form.value.title = created.title
    await state.submitBanner()
    expect(saveSlider).not.toHaveBeenCalled()
    expect(state.sliders.form.value.banner_ids).toEqual([5, 6])
    expect(state.dirty.value).toBe(true)
    await state.submitSlider()
    expect(saveSlider).toHaveBeenCalledWith(
      2,
      expect.objectContaining({ banner_ids: [5, 6] }),
    )
    expect(state.dirty.value).toBe(false)
    expect(state.selected.value?.banners).toHaveLength(2)
  })
})
