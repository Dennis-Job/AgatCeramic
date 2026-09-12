import { ref } from 'vue'
import { usePaginatedCollection } from '../../../composables/usePaginatedCollection'
import { getAllAttributeGroups } from '../../attribute-groups/services/attributeGroups'
import { deleteAttribute, getAttributes, saveAttribute } from '../services/attributes'
import type { Attribute } from '../types/attribute.types'
import type { AttributeGroup } from '../../attribute-groups/types/attributeGroup.types'

export function useAttributesCatalog() {
  const list = usePaginatedCollection<Attribute>('Не удалось загрузить характеристики.')
  const groups = ref<AttributeGroup[]>([])
  async function load(page = list.pagination.value?.current_page ?? 1): Promise<void> {
    const response = await list.load(page, async requestedPage => {
      const [attributePage, attributeGroups] = await Promise.all([getAttributes({ page: requestedPage }), getAllAttributeGroups()])
      return { ...attributePage, attributeGroups }
    })
    if (response) groups.value = response.attributeGroups
  }
  async function remove(id: number): Promise<void> {
    await deleteAttribute(id)
    const response = await list.reloadAfterDeletion(async page => {
      const [attributePage, attributeGroups] = await Promise.all([getAttributes({ page }), getAllAttributeGroups()])
      return { ...attributePage, attributeGroups }
    })
    if (response) groups.value = response.attributeGroups
  }
  return { attributes: list.items, pagination: list.pagination, error: list.error, loading: list.loading, groups, load, remove, save: saveAttribute }
}
