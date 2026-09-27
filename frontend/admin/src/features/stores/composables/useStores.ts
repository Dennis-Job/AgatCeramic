import { ref } from 'vue'
import { usePaginatedCollection } from '../../../composables/usePaginatedCollection'
import {
  deleteStore,
  getStores,
  saveStore,
  saveWorkingHours,
} from '../services/stores'
import type { Store, StorePayload, WorkingHour } from '../types/store.types'

const blankForm = (): StorePayload => ({
  name: '',
  address: '',
  phone: null,
  is_published: false,
})

export function useStores() {
  const list = usePaginatedCollection<Store>('Не удалось загрузить магазины.')
  const editing = ref<Store | null>(null)
  const hoursStore = ref<Store | null>(null)
  const form = ref<StorePayload>(blankForm())
  const hours = ref<WorkingHour[]>([])
  const editorOpen = ref(false)
  const busy = ref(false)
  const formError = ref('')
  const hoursError = ref('')
  const deleteError = ref('')
  const success = ref('')

  async function load(
    page = list.pagination.value?.current_page ?? 1,
  ): Promise<void> {
    await list.load(page, (requestedPage) => getStores({ page: requestedPage }))
  }

  function openEditor(store: Store | null = null): void {
    editing.value = store
    form.value = store
      ? {
          name: store.name,
          address: store.address,
          phone: store.phone,
          is_published: store.is_published,
        }
      : blankForm()
    formError.value = ''
    editorOpen.value = true
  }

  function openHours(store: Store): void {
    hoursStore.value = store
    hours.value = store.working_hours.map((day) => ({ ...day }))
    hoursError.value = ''
  }

  async function submit(): Promise<void> {
    busy.value = true
    formError.value = ''
    success.value = ''
    try {
      await saveStore(editing.value?.id ?? null, form.value)
      editorOpen.value = false
      success.value = 'Магазин сохранён.'
      await load()
    } catch (reason) {
      formError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось сохранить магазин.'
    } finally {
      busy.value = false
    }
  }

  async function submitHours(): Promise<void> {
    if (!hoursStore.value) return
    busy.value = true
    hoursError.value = ''
    success.value = ''
    try {
      await saveWorkingHours(hoursStore.value.id, hours.value)
      hoursStore.value = null
      success.value = 'Часы работы сохранены.'
      await load()
    } catch (reason) {
      hoursError.value =
        reason instanceof Error
          ? reason.message
          : 'Не удалось сохранить часы работы.'
    } finally {
      busy.value = false
    }
  }

  async function remove(store: Store): Promise<boolean> {
    busy.value = true
    deleteError.value = ''
    success.value = ''
    try {
      await deleteStore(store.id)
      success.value = 'Магазин удалён.'
      await list.reloadAfterDeletion((page) => getStores({ page }))
      return true
    } catch (reason) {
      deleteError.value =
        reason instanceof Error ? reason.message : 'Не удалось удалить магазин.'
      return false
    } finally {
      busy.value = false
    }
  }

  return {
    ...list,
    load,
    editing,
    hoursStore,
    form,
    hours,
    editorOpen,
    busy,
    formError,
    hoursError,
    deleteError,
    success,
    openEditor,
    openHours,
    closeEditor: () => {
      if (!busy.value) editorOpen.value = false
    },
    closeHours: () => {
      if (!busy.value) hoursStore.value = null
    },
    submit,
    submitHours,
    remove,
  }
}
