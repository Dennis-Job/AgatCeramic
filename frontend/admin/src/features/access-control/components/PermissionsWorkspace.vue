<script setup lang="ts">
import { onMounted } from 'vue'
import { KeyRound } from '@lucide/vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import { usePermissionCatalogue } from '../composables/usePermissionCatalogue'

const catalogue = usePermissionCatalogue()
onMounted(catalogue.load)
</script>

<template>
  <section class="mx-auto admin-page" :aria-busy="catalogue.loading.value">
    <PageHeader class="mb-7" eyebrow="Управление доступом" title="Права" description="Каталог системных прав и ролей, которым они назначены." />
    <UiAlert v-if="catalogue.error.value" class="mb-4">{{ catalogue.error.value }}</UiAlert>
    <UiCard :padded="false" class="overflow-hidden">
      <form role="search" class="grid gap-3 border-b border-gray-100 p-4 admin-permissions-filter-grid" @submit.prevent>
        <UiInput v-model="catalogue.search.value" class="min-w-0" searchable placeholder="Поиск по названию или коду" aria-label="Поиск прав" />
        <UiSelect v-model="catalogue.selectedModule.value" :options="catalogue.moduleOptions.value" accessible-name="Модуль прав" />
      </form>
      <UiLoadingState v-if="catalogue.loading.value" label="Загрузка каталога прав…" />
      <UiEmptyState v-else-if="!catalogue.error.value && catalogue.permissions.value.length === 0" label="Каталог прав пока пуст." />
      <div v-else-if="!catalogue.error.value" class="divide-y divide-gray-100">
        <article v-for="permission in catalogue.filteredPermissions.value" :key="permission.id" class="flex gap-3 p-4 sm:p-5"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-primary-50 text-primary-600"><KeyRound :size="19" aria-hidden="true" /></span><div class="min-w-0 flex-1"><div class="flex flex-wrap items-start justify-between gap-2"><div class="min-w-0"><h2 class="break-words font-semibold text-gray-700">{{ permission.name }}</h2><code class="mt-1 inline-block max-w-full break-all rounded bg-gray-25 px-1.5 py-0.5 text-xs text-gray-500">{{ permission.code }}</code></div><UiBadge tone="neutral">Ролей: {{ permission.roles.length }}</UiBadge></div><p v-if="permission.description" class="mt-3 break-words text-sm text-gray-500">{{ permission.description }}</p><div class="mt-3 flex flex-wrap gap-2"><UiBadge v-for="role in permission.roles" :key="role.id" tone="primary">{{ role.name }}</UiBadge><span v-if="!permission.roles.length" class="text-sm text-gray-400">Не назначено ни одной роли</span></div></div></article>
        <UiEmptyState v-if="!catalogue.filteredPermissions.value.length" label="По выбранным условиям права не найдены." />
      </div>
    </UiCard>
  </section>
</template>
