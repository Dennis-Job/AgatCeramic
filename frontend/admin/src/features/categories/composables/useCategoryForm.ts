import { computed, ref } from 'vue'
import type { Category, CategoryPayload } from '../types/category.types'
import { emptyCategory, validateCategory } from '../validation/category.schema'
import { slugify } from '../../catalog/validation/slug'

export function useCategoryForm(saveCategory: (id: number | null, payload: CategoryPayload) => Promise<Category>, reload: () => Promise<void>) {
  const open = ref(false)
  const editing = ref<Category | null>(null)
  const busy = ref(false)
  const error = ref('')
  const form = ref<CategoryPayload>(emptyCategory())
  const manuallyEditedSlug = ref(false)
  const title = computed(() => editing.value ? `Категория: ${editing.value.name}` : 'Новая категория')

  function show(category: Category | null = null): void {
    editing.value = category
    manuallyEditedSlug.value = category !== null
    error.value = ''
    form.value = category ? { parent_id: category.parent_id ?? null, name: category.name, slug: category.slug, description: category.description ?? '', is_parent: category.is_parent, is_active: category.is_active, sort_order: category.sort_order } : emptyCategory()
    open.value = true
  }
  function updateName(name: string): void { form.value.name = name; if (!manuallyEditedSlug.value) form.value.slug = slugify(name) }
  function updateSlug(slug: string): void { form.value.slug = slug; manuallyEditedSlug.value = true }
  async function submit(): Promise<void> {
    error.value = validateCategory(form.value)
    if (error.value) return
    busy.value = true
    try { await saveCategory(editing.value?.id ?? null, form.value); open.value = false; await reload() }
    catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось сохранить категорию.' }
    finally { busy.value = false }
  }
  return { open, editing, busy, error, form, title, show, close: () => { open.value = false }, updateName, updateSlug, submit }
}
