import { beforeEach, expect, test, vi } from 'vitest'
import { useRolesWorkspace } from '../src/features/access-control/composables/useRolesWorkspace'

const api = vi.hoisted(() => ({
  getRoles: vi.fn(),
  getPermissions: vi.fn(),
  saveRole: vi.fn(),
  deleteRole: vi.fn(),
}))
vi.mock('../src/features/access-control/services/roles', () => api)
vi.mock('../src/stores/auth', () => ({
  useAuthStore: () => ({ hasPermission: () => true }),
}))
const role = {
  id: 7,
  name: 'Системный сотрудник',
  slug: 'system',
  description: 'Исходное описание',
  is_system: true,
  permissions: [
    { id: 3, name: 'Просмотр заказов', code: 'orders.view', description: null },
  ],
}
beforeEach(() => {
  vi.resetAllMocks()
  api.getRoles.mockResolvedValue([role])
  api.getPermissions.mockResolvedValue(role.permissions)
  api.saveRole.mockResolvedValue(role)
})

test('updates system role permissions without submitting protected identity fields', async () => {
  const workspace = useRolesWorkspace()
  await workspace.load()
  workspace.open(role)
  workspace.form.value.description = 'Новое описание'
  await workspace.save()
  expect(api.saveRole).toHaveBeenCalledWith(7, {
    description: 'Новое описание',
    permission_ids: [3],
  })
})

test('keeps a selected role after reload and searches backend name, slug and description', async () => {
  const custom = {
    ...role,
    id: 8,
    name: 'Работа с каталогом',
    slug: 'catalog',
    description: 'Товары',
    is_system: false,
  }
  api.getRoles.mockResolvedValue([role, custom])
  const workspace = useRolesWorkspace()
  await workspace.load()
  workspace.selectedRoleId.value = 8
  await workspace.load()
  expect(workspace.selectedRole.value?.id).toBe(8)
  for (const query of ['каталогом', 'catalog', 'Товары']) {
    workspace.search.value = query
    expect(workspace.filteredRoles.value.map((item) => item.id)).toEqual([8])
  }
  workspace.search.value = 'Нет совпадений'
  expect(workspace.selectedRole.value).toBeNull()
  workspace.search.value = ''
  expect(workspace.selectedRole.value?.id).toBe(8)
})
