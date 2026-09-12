import { computed, ref } from 'vue'
import type { AttributeGroup, AttributeGroupPayload } from '../types/attributeGroup.types'
import { emptyAttributeGroup, validateAttributeGroup } from '../validation/attributeGroup.schema'
import { slugify } from '../../catalog/validation/slug'

export function useAttributeGroupForm(saveGroup: (id: number | null, payload: AttributeGroupPayload) => Promise<AttributeGroup>, reload: () => Promise<void>) {
  const open = ref(false); const editing = ref<AttributeGroup | null>(null); const busy = ref(false); const error = ref('')
  const form = ref<AttributeGroupPayload>(emptyAttributeGroup()); const manuallyEditedSlug = ref(false)
  const title = computed(() => editing.value ? `Группа: ${editing.value.name}` : 'Новая группа характеристик')
  function show(group: AttributeGroup | null = null): void { editing.value = group; error.value = ''; manuallyEditedSlug.value = group !== null; form.value = group ? { name: group.name, slug: group.slug, description: group.description ?? '', sort_order: group.sort_order } : emptyAttributeGroup(); open.value = true }
  function updateName(value: string): void { form.value.name = value; if (!manuallyEditedSlug.value) form.value.slug = slugify(value) }
  function updateSlug(value: string): void { form.value.slug = value; manuallyEditedSlug.value = true }
  async function submit(): Promise<void> { error.value = validateAttributeGroup(form.value); if (error.value) return; busy.value = true; try { await saveGroup(editing.value?.id ?? null, form.value); open.value = false; await reload() } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось сохранить группу.' } finally { busy.value = false } }
  return { open, busy, error, form, title, show, close: () => { open.value = false }, updateName, updateSlug, submit }
}
