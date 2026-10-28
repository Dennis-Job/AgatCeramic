import { computed, onMounted, ref } from 'vue'
import {
  getAppearance,
  publishAppearance,
  saveAppearanceSection,
} from '../services/appearance'
import type {
  AppearanceSection,
  NavigationEditorDraft,
  SiteAppearance,
} from '../types/appearance.types'
import { navigationError } from '../validation/navigation'
import { footerError } from '../validation/footer'

const sections: AppearanceSection[] = ['header', 'footer']
export function useAppearance() {
  const content = ref<SiteAppearance | null>(null)
  const saved = ref<SiteAppearance | null>(null)
  const navigationDraft = ref<NavigationEditorDraft>({ rows: [] })
  const loading = ref(false)
  const saving = ref(false)
  const publishing = ref(false)
  const error = ref('')
  const success = ref('')
  const busy = computed(() => saving.value || publishing.value || loading.value)
  function isDirty(section: AppearanceSection): boolean {
    return Boolean(
      content.value &&
      saved.value &&
      JSON.stringify(content.value[section]) !==
        JSON.stringify(saved.value[section]),
    )
  }
  const dirty = computed(() => sections.some(isDirty))

  async function load(): Promise<void> {
    loading.value = true
    error.value = ''
    try {
      const result = await getAppearance()
      navigationDraft.value.rows = []
      content.value = structuredClone(result)
      saved.value = result
    } catch (reason) {
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось загрузить общее оформление.'
    } finally {
      loading.value = false
    }
  }
  async function save(section: AppearanceSection): Promise<void> {
    if (!content.value || busy.value) return
    if (section === 'footer') {
      const invalid = footerError(content.value.footer)
      if (invalid) {
        error.value = invalid
        success.value = ''
        return
      }
    }
    if (section === 'header') {
      const invalid = navigationError(content.value.header.navigation)
      if (invalid) {
        error.value = invalid
        success.value = ''
        return
      }
    }
    saving.value = true
    error.value = ''
    success.value = ''
    try {
      const submitted = JSON.parse(
        JSON.stringify(content.value[section]),
      ) as SiteAppearance[AppearanceSection]
      const footerBeforeSave = JSON.stringify(content.value.footer)
      const footerWasDirty = isDirty('footer')
      const result = await saveAppearanceSection(section, submitted)
      const editedDuringSave =
        JSON.stringify(content.value[section]) !== JSON.stringify(submitted)
      content.value = {
        ...content.value,
        [section]: editedDuringSave
          ? content.value[section]
          : structuredClone(result[section]),
        has_unpublished_changes: result.has_unpublished_changes,
      }
      if (
        section === 'header' &&
        !footerWasDirty &&
        JSON.stringify(content.value.footer) === footerBeforeSave
      ) {
        content.value.footer = structuredClone(result.footer)
      }
      content.value.footer.bottom_left = result.footer.bottom_left
      saved.value = result
      success.value =
        'Черновик раздела сохранён. Опубликуйте оформление, чтобы обновить сайт.'
    } catch (reason) {
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось сохранить оформление.'
    } finally {
      saving.value = false
    }
  }
  async function publish(): Promise<void> {
    if (
      !content.value ||
      busy.value ||
      dirty.value ||
      !content.value.has_unpublished_changes
    )
      return
    publishing.value = true
    error.value = ''
    success.value = ''
    try {
      const result = await publishAppearance()
      content.value = structuredClone(result)
      saved.value = result
      success.value = 'Общее оформление опубликовано.'
    } catch (reason) {
      error.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось опубликовать оформление.'
    } finally {
      publishing.value = false
    }
  }
  onMounted(load)
  return {
    content,
    navigationDraft,
    loading,
    saving,
    publishing,
    busy,
    error,
    success,
    dirty,
    isDirty,
    load,
    save,
    publish,
  }
}
