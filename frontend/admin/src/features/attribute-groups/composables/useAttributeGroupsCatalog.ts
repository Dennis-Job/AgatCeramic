import { usePaginatedCollection } from '../../../composables/usePaginatedCollection'
import { deleteAttributeGroup, getAttributeGroups, saveAttributeGroup } from '../services/attributeGroups'
import type { AttributeGroup, AttributeGroupPayload } from '../types/attributeGroup.types'
export function useAttributeGroupsCatalog() {
  const list = usePaginatedCollection<AttributeGroup>('Не удалось загрузить группы.')
  async function load(page = list.pagination.value?.current_page ?? 1): Promise<void> { await list.load(page, requestedPage => getAttributeGroups({ page: requestedPage })) }
  async function remove(id: number): Promise<void> { await deleteAttributeGroup(id); await list.reloadAfterDeletion(page => getAttributeGroups({ page })) }
  return { groups: list.items, pagination: list.pagination, error: list.error, loading: list.loading, load, remove, save: (id: number | null, payload: AttributeGroupPayload) => saveAttributeGroup(id, payload) }
}
