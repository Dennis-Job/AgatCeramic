import { beforeEach, expect, test, vi } from 'vitest'
import { useAttributesWorkspace } from '../src/features/attributes/composables/useAttributesWorkspace'
import type { Attribute } from '../src/features/attributes/types/attribute.types'
import {
  adminNavigation,
  isNavigationLinkActive,
} from '../src/layouts/navigation'

const api = vi.hoisted(() => ({
  getAllAttributes: vi.fn(),
  getAllAttributeGroups: vi.fn(),
  patchAttributeDisplay: vi.fn(),
  saveAttribute: vi.fn(),
  deleteAttribute: vi.fn(),
  saveAttributeGroup: vi.fn(),
  deleteAttributeGroup: vi.fn(),
}))
vi.mock('../src/features/attributes/services/attributes', () => api)
vi.mock('../src/features/attribute-groups/services/attributeGroups', () => api)
const group = {
  id: 1,
  name: 'Размеры',
  slug: 'dimensions',
  description: null,
  sort_order: 0,
  created_at: '',
  updated_at: '',
}
const attribute: Attribute = {
  id: 1,
  name: 'Ширина',
  slug: 'width',
  attribute_group_id: 1,
  type: 'decimal',
  unit: 'см',
  sort_order: 0,
  options: [],
  is_required: false,
  is_filterable: true,
  is_visible_on_product_page: true,
  created_at: '',
  updated_at: '',
}
function deferred<T>() {
  let resolve!: (value: T) => void
  let reject!: (reason: Error) => void
  const promise = new Promise<T>((yes, no) => {
    resolve = yes
    reject = no
  })
  return { promise, resolve, reject }
}
beforeEach(() => {
  vi.resetAllMocks()
  api.getAllAttributes.mockResolvedValue([
    { ...attribute },
    {
      ...attribute,
      id: 2,
      name: 'Цвет',
      slug: 'color',
      attribute_group_id: null,
    },
  ])
  api.getAllAttributeGroups.mockResolvedValue([group])
})
test('counts the complete catalog, filters ungrouped and searches within selection', async () => {
  const workspace = useAttributesWorkspace()
  await workspace.load()
  expect(workspace.groupItems.value.map((item) => item.count)).toEqual([
    2, 1, 1,
  ])
  workspace.selectedGroup.value = 'ungrouped'
  expect(workspace.filteredAttributes.value.map((item) => item.name)).toEqual([
    'Цвет',
  ])
  workspace.search.value = 'width'
  expect(workspace.filteredAttributes.value).toEqual([])
  workspace.selectedGroup.value = '1'
  expect(workspace.filteredAttributes.value.map((item) => item.name)).toEqual([
    'Ширина',
  ])
})
test('optimistic switch blocks duplicate edits and rolls back on failure', async () => {
  const workspace = useAttributesWorkspace()
  await workspace.load()
  const request = deferred<Attribute>()
  api.patchAttributeDisplay.mockReturnValue(request.promise)
  const pending = workspace.toggleDisplay(
    workspace.attributes.value[0]!,
    'is_filterable',
    false,
  )
  expect(workspace.attributes.value[0]!.is_filterable).toBe(false)
  expect(workspace.pendingIds.value.has(1)).toBe(true)
  await workspace.toggleDisplay(
    workspace.attributes.value[0]!,
    'is_visible_on_product_page',
    false,
  )
  expect(api.patchAttributeDisplay).toHaveBeenCalledTimes(1)
  expect(api.patchAttributeDisplay).toHaveBeenCalledWith(1, {
    is_filterable: false,
  })
  request.reject(new Error('Нет доступа'))
  await pending
  expect(workspace.attributes.value[0]!.is_filterable).toBe(true)
  expect(workspace.pendingIds.value.size).toBe(0)
  expect(workspace.error.value).toContain('Нет доступа')
})
test('late switch result cannot replace a refreshed attribute definition', async () => {
  const workspace = useAttributesWorkspace()
  await workspace.load()
  const request = deferred<Attribute>()
  api.patchAttributeDisplay.mockReturnValue(request.promise)
  const pending = workspace.toggleDisplay(
    workspace.attributes.value[0]!,
    'is_filterable',
    false,
  )
  api.getAllAttributes.mockResolvedValue([
    { ...attribute, name: 'Новое название', is_filterable: true },
  ])
  await workspace.load()
  request.resolve({ ...attribute, is_filterable: false })
  await pending
  expect(workspace.attributes.value[0]!.name).toBe('Новое название')
  expect(workspace.attributes.value[0]!.is_filterable).toBe(false)
})
test('failed switch rolls back even when group deletion replaced its row', async () => {
  const workspace = useAttributesWorkspace()
  await workspace.load()
  const request = deferred<Attribute>()
  api.patchAttributeDisplay.mockReturnValue(request.promise)
  const pending = workspace.toggleDisplay(
    workspace.attributes.value[0]!,
    'is_filterable',
    false,
  )
  workspace.deletingGroup.value = group
  api.deleteAttributeGroup.mockResolvedValue(undefined)
  await workspace.confirmDelete()
  expect(workspace.attributes.value[0]!.attribute_group_id).toBeNull()
  request.reject(new Error('Не сохранено'))
  await pending
  expect(workspace.attributes.value[0]!.is_filterable).toBe(true)
  expect(workspace.attributes.value[0]!.attribute_group_id).toBeNull()
})
test('creating a group inside attribute editor preserves draft and selects new group', async () => {
  const workspace = useAttributesWorkspace()
  await workspace.load()
  workspace.selectedGroup.value = '1'
  workspace.showAttribute()
  workspace.attributeEditor.updateName('Толщина')
  workspace.showGroupFromAttribute()
  workspace.groupEditor.updateName('Технические')
  api.saveAttributeGroup.mockResolvedValue({
    ...group,
    id: 3,
    name: 'Технические',
  })
  await workspace.groupEditor.submit()
  expect(workspace.attributeEditor.open.value).toBe(true)
  expect(workspace.attributeEditor.form.value.name).toBe('Толщина')
  expect(workspace.attributeEditor.form.value.attribute_group_id).toBe(3)
  expect(workspace.groups.value.some((item) => item.id === 3)).toBe(true)
})
test('a refresh started during saving cannot undo the completed switch', async () => {
  const workspace = useAttributesWorkspace()
  await workspace.load()
  const patch = deferred<Attribute>()
  const refresh = deferred<Attribute[]>()
  api.patchAttributeDisplay.mockReturnValue(patch.promise)
  const pending = workspace.toggleDisplay(
    workspace.attributes.value[0]!,
    'is_filterable',
    false,
  )
  api.getAllAttributes.mockReturnValue(refresh.promise)
  const loading = workspace.load()
  patch.resolve({ ...attribute, is_filterable: false })
  await pending
  refresh.resolve([
    { ...attribute, name: 'Свежее название', is_filterable: true },
  ])
  await loading
  expect(workspace.attributes.value[0]!.name).toBe('Свежее название')
  expect(workspace.attributes.value[0]!.is_filterable).toBe(false)
})
test('local pagination retains every record and clamps after deletion or search', async () => {
  api.getAllAttributes.mockResolvedValue(
    Array.from({ length: 26 }, (_, index) => ({
      ...attribute,
      id: index + 1,
      name: `Размер ${index + 1}`,
      slug: `size-${index + 1}`,
    })),
  )
  const workspace = useAttributesWorkspace()
  await workspace.load()
  expect(workspace.pagination.value.total).toBe(26)
  expect(workspace.visibleAttributes.value).toHaveLength(25)
  workspace.page.value = 2
  expect(workspace.visibleAttributes.value[0]!.id).toBe(26)
  workspace.deletingAttribute.value = workspace.visibleAttributes.value[0]!
  api.deleteAttribute.mockResolvedValue(undefined)
  await workspace.confirmDelete()
  expect(workspace.page.value).toBe(1)
  expect(workspace.pagination.value.total).toBe(25)
  workspace.search.value = 'size-1'
  expect(workspace.pagination.value.total).toBe(11)
})
test('failed initial load exposes retry state rather than an empty catalog', async () => {
  const workspace = useAttributesWorkspace()
  api.getAllAttributes.mockRejectedValueOnce(new Error('Сбой загрузки'))
  await workspace.load()
  expect(workspace.loadFailed.value).toBe(true)
  expect(workspace.error.value).toBe('Сбой загрузки')
  await workspace.load()
  expect(workspace.loadFailed.value).toBe(false)
  expect(workspace.attributes.value).toHaveLength(2)
})
test('legacy groups URL keeps the single characteristics navigation entry active', () => {
  const links = adminNavigation.flatMap(
    (section) => section.groups?.flatMap((group) => group.links) ?? [],
  )
  const link = links.find((item) => item.path === '/attributes')!
  expect(
    links.filter((item) => item.path === '/attribute-groups'),
  ).toHaveLength(0)
  expect(
    isNavigationLinkActive(link, { path: '/attribute-groups', query: {} }),
  ).toBe(true)
})
