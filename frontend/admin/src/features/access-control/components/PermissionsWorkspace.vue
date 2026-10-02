<script setup lang="ts">
import { onMounted } from 'vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiTable from '../../../components/ui/UiTable.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import { usePermissionCatalogue } from '../composables/usePermissionCatalogue'

const catalogue = usePermissionCatalogue()
onMounted(catalogue.load)
</script>

<template>
  <AdminWorkspace mode="list" :aria-busy="catalogue.loading.value">
    <template #intro>
      <PageHeader
        class="mb-7"
        eyebrow="Управление доступом"
        title="Права"
        description="Каталог системных прав и ролей, которым они назначены."
      />
    </template>

    <UiAlert v-if="catalogue.error.value" class="admin-container mb-4">{{
      catalogue.error.value
    }}</UiAlert>
    <div class="min-w-0">
      <form
        role="search"
        class="admin-container grid gap-3 border-b border-gray-100 p-4 admin-permissions-filter-grid"
        @submit.prevent
      >
        <UiInput
          v-model="catalogue.search.value"
          class="min-w-0"
          searchable
          placeholder="Поиск по названию или коду"
          aria-label="Поиск прав"
        />
        <UiSelect
          v-model="catalogue.selectedModule.value"
          :options="catalogue.moduleOptions.value"
          accessible-name="Модуль прав"
        />
      </form>
      <UiLoadingState
        v-if="catalogue.loading.value"
        class="admin-container"
        label="Загрузка каталога прав…"
      />
      <UiEmptyState
        v-else-if="
          !catalogue.error.value && catalogue.permissions.value.length === 0
        "
        class="admin-container"
        label="Каталог прав пока пуст."
      />
      <UiTable
        v-else-if="
          !catalogue.error.value && catalogue.filteredPermissions.value.length
        "
        label="Каталог прав"
        min-width="min-w-[960px]"
        table-class="seller-table"
        sticky-header
      >
        <thead>
          <tr>
            <th scope="col" class="w-80">Право</th>
            <th scope="col">Описание</th>
            <th scope="col" class="w-80">Назначенные роли</th>
          </tr>
        </thead>
        <tbody>
          <tr
            v-for="permission in catalogue.filteredPermissions.value"
            :key="permission.id"
          >
            <td>
              <h2 class="font-semibold text-gray-700">{{ permission.name }}</h2>
              <code class="mt-1 block text-xs text-gray-500">{{
                permission.code
              }}</code>
            </td>
            <td>{{ permission.description || 'Без описания' }}</td>
            <td>
              <p class="mb-2 text-xs text-gray-500">
                Ролей: {{ permission.roles.length }}
              </p>
              <div class="flex flex-wrap gap-2">
                <UiBadge
                  v-for="role in permission.roles"
                  :key="role.id"
                  tone="primary"
                  >{{ role.name }}</UiBadge
                ><span
                  v-if="!permission.roles.length"
                  class="text-sm text-gray-500"
                  >Не назначено ни одной роли</span
                >
              </div>
            </td>
          </tr>
        </tbody></UiTable
      ><UiEmptyState
        v-else-if="!catalogue.error.value"
        class="admin-container"
        label="По выбранным условиям права не найдены."
      />
    </div>
  </AdminWorkspace>
</template>
