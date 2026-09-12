import { ref } from 'vue'
import type { Attribute } from '../../attributes/types/attribute.types'
import type { AttributeGroup } from '../../attribute-groups/types/attributeGroup.types'
import type { Category } from '../types/category.types'
import { useCategoryCatalog } from './useCategoryCatalog'

function flattenTree(items: Category[]): Category[] { return items.flatMap(item => [item, ...flattenTree(item.children ?? [])]) }

export function useCategoriesWorkspace() {
  const catalog = useCategoryCatalog()
  const categories = ref<Category[]>([]); const error = ref(''); const loading = ref(false)
  const deleting = ref<Category | null>(null); const deletingBusy = ref(false)
  const details = ref<Category | null>(null); const detailsLoading = ref(false); const detailsAttributes = ref<Attribute[]>([]); const detailsGroups = ref<AttributeGroup[]>([])
  function descendants(category: Category): number[] { return (category.children ?? []).flatMap(child => [child.id, ...descendants(child)]) }
  function parentOptionsFor(editing: Category | null): { label: string; value: string }[] { const excluded = new Set(editing ? [editing.id, ...descendants(editing)] : []); return [{ label: 'Без родителя', value: '' }, ...categories.value.filter(category => category.is_parent && !excluded.has(category.id)).map(category => ({ label: category.name, value: String(category.id) }))] }
  function categoryName(id: number | null | undefined): string | null { return id === null || id === undefined ? null : categories.value.find(category => category.id === id)?.name ?? null }
  async function load(): Promise<void> { loading.value = true; error.value = ''; try { categories.value = flattenTree(await catalog.getCategories()) } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить категории.' } finally { loading.value = false } }
  async function showDetails(category: Category): Promise<void> { details.value = category; detailsLoading.value = true; detailsAttributes.value = []; detailsGroups.value = []; try { const [attributes, groups] = await Promise.all([catalog.getCategoryAttributes(category.id), catalog.getCategoryAttributeGroups(category.id)]); detailsAttributes.value = attributes; detailsGroups.value = groups } catch (reason) { details.value = null; error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить информацию о категории.' } finally { detailsLoading.value = false } }
  async function confirmDelete(): Promise<void> { if (!deleting.value) return; deletingBusy.value = true; try { await catalog.deleteCategory(deleting.value.id); deleting.value = null; await load() } catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось удалить категорию.' } finally { deletingBusy.value = false } }
  return { catalog, categories, error, loading, deleting, deletingBusy, details, detailsLoading, detailsAttributes, detailsGroups, parentOptionsFor, categoryName, load, showDetails, confirmDelete }
}
