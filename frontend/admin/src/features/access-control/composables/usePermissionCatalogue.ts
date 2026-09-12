import { computed, ref } from 'vue'
import { getPermissionCatalogue } from '../services/permissions'
import type { CataloguePermission } from '../types/access.types'

const moduleNames: Record<string, string> = {
  'admin-users': 'Сотрудники', roles: 'Роли', permissions: 'Права', catalog: 'Каталог', imports: 'Импорт и экспорт',
  orders: 'Заказы', payments: 'Оплаты', contacts: 'Обращения', content: 'Контент', media: 'Медиа', seo: 'SEO',
  analytics: 'Аналитика', settings: 'Настройки', 'audit-log': 'Журнал аудита',
}

export function usePermissionCatalogue() {
  const permissions = ref<CataloguePermission[]>([])
  const selectedModule = ref('')
  const search = ref('')
  const error = ref('')
  const loading = ref(false)
  const modules = computed(() => [...new Set(permissions.value.map(permission => permission.code.split('.')[0]))])
  const moduleOptions = computed(() => [{ label: 'Все разделы', value: '' }, ...modules.value.map(module => ({ label: moduleNames[module] ?? module, value: module }))])
  const filteredPermissions = computed(() => {
    const needle = search.value.trim().toLocaleLowerCase()
    return permissions.value.filter(permission => {
      const matchesModule = !selectedModule.value || permission.code.startsWith(`${selectedModule.value}.`)
      const matchesSearch = !needle || [permission.name, permission.code, permission.description ?? ''].some(value => value.toLocaleLowerCase().includes(needle))
      return matchesModule && matchesSearch
    })
  })

  async function load(): Promise<void> {
    loading.value = true
    error.value = ''
    try { permissions.value = await getPermissionCatalogue() }
    catch (reason) { error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить каталог прав.' }
    finally { loading.value = false }
  }

  return { permissions, selectedModule, search, error, loading, moduleOptions, filteredPermissions, load }
}
