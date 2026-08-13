<script setup lang="ts">
import { computed, onMounted, ref } from 'vue'
import { KeyRound } from '@lucide/vue'
import BaseInput from '../components/BaseInput.vue'
import BaseSelect from '../components/BaseSelect.vue'
import { getPermissionCatalogue, type CataloguePermission } from '../services/permissions'

const permissions = ref<CataloguePermission[]>([])
const selectedModule = ref('')
const search = ref('')
const error = ref('')

const moduleNames: Record<string, string> = {
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

const modules = computed(() => [...new Set(permissions.value.map((permission) => permission.code.split('.')[0]))])
const moduleOptions = computed(() => [
  { label: 'Все разделы', value: '' },
  ...modules.value.map((module) => ({ label: moduleNames[module] ?? module, value: module })),
])
const filteredPermissions = computed(() => {
  const needle = search.value.trim().toLocaleLowerCase()

  return permissions.value.filter((permission) => {
    const matchesModule = !selectedModule.value || permission.code.startsWith(`${selectedModule.value}.`)
    const matchesSearch = !needle || [permission.name, permission.code, permission.description ?? '']
      .some((value) => value.toLocaleLowerCase().includes(needle))

    return matchesModule && matchesSearch
  })
})

async function load(): Promise<void> {
  error.value = ''
  try {
    permissions.value = await getPermissionCatalogue()
  } catch (reason) {
    error.value = reason instanceof Error ? reason.message : 'Не удалось загрузить каталог прав.'
  }
}

onMounted(load)
</script>

<template>
  <section class="mx-auto max-w-[1440px]">
    <div class="mb-7">
      <p class="text-sm font-medium text-gray-500">Управление доступом</p>
      <h1 class="mt-1 text-2xl font-bold tracking-tight text-gray-900 sm:text-3xl">Права</h1>
      <p class="mt-2 text-sm text-gray-500">Каталог системных прав и ролей, которым они назначены.</p>
    </div>

    <p v-if="error" class="mb-4 rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-500">{{ error }}</p>

    <div class="rounded-xl border border-gray-200 bg-white shadow-card">
      <div class="grid gap-3 border-b border-gray-100 p-4 sm:grid-cols-[minmax(0,1fr)_240px]">
        <BaseInput v-model="search" class="min-w-0" searchable placeholder="Поиск по названию или коду" aria-label="Поиск прав" />
        <BaseSelect v-model="selectedModule" :options="moduleOptions" accessible-name="Модуль прав" />
      </div>

      <div class="divide-y divide-gray-100">
        <article v-for="permission in filteredPermissions" :key="permission.id" class="flex gap-3 p-4 sm:p-5">
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-primary-50 text-primary-600"><KeyRound :size="19" /></span>
          <div class="min-w-0 flex-1">
            <div class="flex flex-wrap items-start justify-between gap-2">
              <div>
                <h2 class="font-semibold text-gray-700">{{ permission.name }}</h2>
                <code class="mt-1 inline-block rounded bg-gray-25 px-1.5 py-0.5 text-xs text-gray-500">{{ permission.code }}</code>
              </div>
              <span class="admin-badge rounded-full bg-gray-50 px-2.5 py-1 text-xs font-medium text-gray-500">Ролей: {{ permission.roles.length }}</span>
            </div>
            <p v-if="permission.description" class="mt-3 text-sm text-gray-500">{{ permission.description }}</p>
            <div class="mt-3 flex flex-wrap gap-2">
              <span v-for="role in permission.roles" :key="role.id" class="admin-badge rounded-full bg-primary-50 px-2.5 py-1 text-xs font-medium text-primary-600">{{ role.name }}</span>
              <span v-if="!permission.roles.length" class="text-sm text-gray-400">Не назначено ни одной роли</span>
            </div>
          </div>
        </article>
        <p v-if="!filteredPermissions.length" class="p-8 text-center text-sm text-gray-500">Права не найдены.</p>
      </div>
    </div>
  </section>
</template>
