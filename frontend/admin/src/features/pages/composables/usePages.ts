import { ref } from 'vue'
import { usePaginatedCollection } from '../../../composables/usePaginatedCollection'
import { deletePage, getPages, savePage } from '../services/pages'
import type { ContentPage, PagePayload } from '../types/page.types'

const blankForm = (): PagePayload => ({
  title: '',
  slug: '',
  body: '',
  is_published: false,
})

export function usePages() {
  const list = usePaginatedCollection<ContentPage>(
    'Не удалось загрузить страницы.',
  )
  const editing = ref<ContentPage | null>(null)
  const form = ref<PagePayload>(blankForm())
  const editorOpen = ref(false)
  const busy = ref(false)
  const formError = ref('')
  const deleteError = ref('')
  const success = ref('')

  async function load(
    page = list.pagination.value?.current_page ?? 1,
  ): Promise<void> {
    await list.load(page, (requestedPage) => getPages({ page: requestedPage }))
  }

  function openEditor(page: ContentPage | null = null): void {
    editing.value = page
    form.value = page
      ? {
          title: page.title,
          slug: page.slug,
          body: page.body,
          is_published: page.is_published,
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
      await savePage(editing.value?.id ?? null, form.value)
      editorOpen.value = false
      success.value = 'Страница сохранена.'
      await load()
    } catch (reason) {
      formError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось сохранить страницу.'
    } finally {
      busy.value = false
    }
  }

  async function remove(page: ContentPage): Promise<boolean> {
    busy.value = true
    deleteError.value = ''
    try {
      await deletePage(page.id)
      success.value = 'Страница удалена.'
      await list.reloadAfterDeletion((requestedPage) =>
        getPages({ page: requestedPage }),
      )
      return true
    } catch (reason) {
      deleteError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось удалить страницу.'
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
