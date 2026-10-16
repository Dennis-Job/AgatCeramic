import type { Permission } from '../types/access.types'

export const permissionModuleNames: Record<string, string> = {
  'admin-users': 'Сотрудники',
  roles: 'Роли',
  permissions: 'Права',
  catalog: 'Каталог',
  imports: 'Импорт и экспорт',
  orders: 'Заказы',
  payments: 'Оплаты',
  contacts: 'Обращения',
  content: 'Контент',
  media: 'Медиа',
  seo: 'SEO',
  analytics: 'Аналитика',
  settings: 'Настройки',
  'audit-log': 'Журнал аудита',
}

export type PermissionGroup<T extends Permission = Permission> = {
  module: string
  name: string
  permissions: T[]
}

export function groupPermissions<T extends Permission>(
  permissions: T[],
): PermissionGroup<T>[] {
  const groups = new Map<string, PermissionGroup<T>>()
  for (const permission of permissions) {
    const module = permission.code.split('.')[0] ?? permission.code
    if (!groups.has(module))
      groups.set(module, {
        module,
        name: permissionModuleNames[module] ?? module,
        permissions: [],
      })
    groups.get(module)!.permissions.push(permission)
  }
  return [...groups.values()]
}
