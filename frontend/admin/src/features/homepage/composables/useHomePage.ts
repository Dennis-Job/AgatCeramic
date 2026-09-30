import { onMounted, ref } from 'vue'
import {
  getHomePage,
  publishHomePage,
  saveHomePageSection,
} from '../services/homepage'
import type { EditableSection, HomePageContent } from '../types/homepage.types'

export function useHomePage() {
  const content = ref<HomePageContent | null>(null)
  const saved = ref<HomePageContent | null>(null)
  const loading = ref(false)
  const saving = ref(false)
  const publishing = ref(false)
  const error = ref('')
  const success = ref('')

  async function load(): Promise<void> {
    loading.value = true
    error.value = ''
    try {
      const result = await getHomePage()
      content.value = structuredClone(result)
      saved.value = result
    } catch (reason) {
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось загрузить главную страницу.'
    } finally {
      loading.value = false
    }
  }

  async function save(section: EditableSection): Promise<void> {
    if (!content.value || saving.value || publishing.value) return
    saving.value = true
    error.value = ''
    success.value = ''
    try {
      const submitted = JSON.parse(
        JSON.stringify(content.value[section]),
      ) as HomePageContent[EditableSection]
      // The endpoint replaces only the requested section, preserving other edits.
      const result = await saveHomePageSection(section, submitted)
      const editedDuringSave =
        JSON.stringify(content.value[section]) !== JSON.stringify(submitted)
      content.value = {
        ...content.value,
        [section]: editedDuringSave ? content.value[section] : result[section],
        hero_slides: result.hero_slides,
        is_published: result.is_published,
        has_unpublished_changes: result.has_unpublished_changes,
        published_at: result.published_at,
      }
      saved.value = result
      success.value =
        'Черновик раздела сохранён. Для обновления сайта опубликуйте страницу.'
    } catch (reason) {
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось сохранить раздел.'
    } finally {
      saving.value = false
    }
  }

  async function publish(): Promise<void> {
    if (!content.value || saving.value || publishing.value) return
    publishing.value = true
    error.value = ''
    success.value = ''
    try {
      const result = await publishHomePage()
      content.value = {
        ...content.value,
        hero_slides: result.hero_slides,
        is_published: result.is_published,
        has_unpublished_changes: result.has_unpublished_changes,
        published_at: result.published_at,
      }
      saved.value = result
      success.value = 'Сохранённый черновик главной страницы опубликован.'
    } catch (reason) {
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось опубликовать главную страницу.'
    } finally {
      publishing.value = false
    }
  }

  onMounted(load)
  function isDirty(section: EditableSection): boolean {
    return Boolean(
      content.value &&
      saved.value &&
      JSON.stringify(content.value[section]) !==
        JSON.stringify(saved.value[section]),
    )
  }

  return {
    content,
    loading,
    saving,
    publishing,
    error,
    success,
    load,
    save,
    publish,
    isDirty,
  }
}
