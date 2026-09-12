import { computed, ref } from 'vue'
import type { Attribute, AttributePayload, AttributeType } from '../types/attribute.types'
import { emptyAttribute, validateAttribute } from '../validation/attribute.schema'
import { slugify } from '../../catalog/validation/slug'

export function useAttributeForm(saveAttribute: (id: number | null, payload: AttributePayload) => Promise<Attribute>, onSaved: () => Promise<void>) {
  const open = ref(false)
  const editing = ref<Attribute | null>(null)
  const busy = ref(false)
  const error = ref('')
  const manuallyEditedSlug = ref(false)
  const form = ref<AttributePayload>(emptyAttribute())
  const title = computed(() => editing.value ? `Характеристика: ${editing.value.name}` : 'Новая характеристика')
  function show(attribute: Attribute | null = null): void {
    editing.value = attribute; error.value = ''; manuallyEditedSlug.value = attribute !== null
    form.value = attribute ? { attribute_group_id: attribute.attribute_group_id, name: attribute.name, slug: attribute.slug, type: attribute.type, unit: attribute.unit, is_filterable: attribute.is_filterable, is_visible_on_product_page: attribute.is_visible_on_product_page, sort_order: attribute.sort_order, options: attribute.options.map(option => ({ ...option })) } : emptyAttribute()
    open.value = true
  }
  function updateName(value: string): void { form.value.name = value; if (!manuallyEditedSlug.value) form.value.slug = slugify(value) }
  function updateSlug(value: string): void { form.value.slug = value; manuallyEditedSlug.value = true }
  function updateType(value: AttributeType): void { form.value.type = value; if (['select', 'multiselect'].includes(value) && form.value.options.length === 0) form.value.options.push({ value: '', label: '', sort_order: 0 }) }
  async function submit(): Promise<void> { error.value = validateAttribute(form.value); if (error.value) return; busy.value = true; try { await saveAttribute(editing.value?.id ?? null, form.value); open.value = false; await onSaved() } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось сохранить характеристику.' } finally { busy.value = false } }
  return { open, editing, busy, error, form, title, manuallyEditedSlug, show, close: () => { open.value = false }, updateName, updateSlug, updateType, submit }
}
