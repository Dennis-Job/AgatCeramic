import { onMounted, ref } from 'vue'
import { usePaginatedCollection } from '../../../composables/usePaginatedCollection'
import {
  deleteMedia,
  getMedia,
  updateMedia,
  uploadMedia,
} from '../services/media'
import type { Media } from '../types/media.types'

export function useMediaWorkspace() {
  const list = usePaginatedCollection<Media>('Не удалось загрузить медиатеку.')
  const kind = ref<Media['kind']>('image')
  const title = ref('')
  const alt = ref('')
  const file = ref<File | null>(null)
  const fileInput = ref<HTMLInputElement | null>(null)
  const editing = ref<Media | null>(null)
  const deleting = ref<Media | null>(null)
  const busy = ref(false)
  const formError = ref('')
  const deleteError = ref('')
  const success = ref('')

  async function load(page = list.pagination.value?.current_page ?? 1) {
    await list.load(page, (requestedPage) =>
      getMedia(undefined, { page: requestedPage }),
    )
  }

  function chooseFile(event: Event) {
    fileInput.value = event.target as HTMLInputElement
    file.value = fileInput.value.files?.[0] ?? null
    if (file.value && !title.value.trim())
      title.value = file.value.name.replace(/\.[^.]+$/, '')
  }

  async function upload() {
    success.value = ''
    if (!file.value || !title.value.trim()) {
      formError.value = 'Выберите файл и укажите название.'
      return
    }
    busy.value = true
    formError.value = ''
    try {
      await uploadMedia(
        file.value,
        kind.value,
        title.value.trim(),
        alt.value.trim(),
      )
      file.value = null
      title.value = ''
      alt.value = ''
      if (fileInput.value) fileInput.value.value = ''
      success.value = 'Файл загружен.'
      await load(1)
    } catch (reason) {
      formError.value =
        reason instanceof Error ? reason.message : 'Не удалось загрузить файл.'
    } finally {
      busy.value = false
    }
  }

  function startEdit(item: Media) {
    success.value = ''
    editing.value = item
    title.value = item.title
    alt.value = item.alt ?? ''
    formError.value = ''
  }

  function cancelEdit() {
    editing.value = null
    title.value = ''
    alt.value = ''
  }

  function confirmDelete(item: Media) {
    success.value = ''
    deleteError.value = ''
    deleting.value = item
  }

  async function saveEdit() {
    success.value = ''
    if (!editing.value || !title.value.trim()) return
    busy.value = true
    formError.value = ''
    try {
      await updateMedia(editing.value.id, {
        title: title.value.trim(),
        alt: editing.value.kind === 'image' ? alt.value.trim() : null,
      })
      editing.value = null
      title.value = ''
      alt.value = ''
      success.value = 'Метаданные сохранены.'
      await load()
    } catch (reason) {
      formError.value =
        reason instanceof Error ? reason.message : 'Не удалось сохранить файл.'
    } finally {
      busy.value = false
    }
  }

  async function remove() {
    success.value = ''
    if (!deleting.value) return
    busy.value = true
    deleteError.value = ''
    try {
      await deleteMedia(deleting.value.id)
      deleting.value = null
      success.value = 'Файл удалён.'
      await list.reloadAfterDeletion((page) => getMedia(undefined, { page }))
    } catch (reason) {
      deleteError.value =
        reason instanceof Error ? reason.message : 'Не удалось удалить файл.'
    } finally {
      busy.value = false
    }
  }

  onMounted(load)

  return {
    list,
    kind,
    title,
    alt,
    editing,
    deleting,
    busy,
    formError,
    deleteError,
    success,
    load,
    chooseFile,
    upload,
    startEdit,
    cancelEdit,
    confirmDelete,
    saveEdit,
    remove,
  }
}
