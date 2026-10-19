<script setup lang="ts">
import { onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { ArrowLeft } from '@lucide/vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import { useAuthStore } from '../../../stores/auth'
import { usePermissionCatalogue } from '../composables/usePermissionCatalogue'
import PermissionGroups from './PermissionGroups.vue'

const auth = useAuthStore()
const catalogue = usePermissionCatalogue()
onMounted(catalogue.load)
</script>

<template>
  <AdminWorkspace mode="list" :aria-busy="catalogue.loading.value">
    <template #intro>
      <PageHeader eyebrow="Управление" title="Команда и доступ" />
      <slot name="navigation" />
    </template>
    <UiNotification v-if="catalogue.error.value">{{
      catalogue.error.value
    }}</UiNotification>
    <section
      class="admin-container border-t border-gray-200 py-5"
      aria-labelledby="permission-catalogue-title"
    >
      <RouterLink
        v-if="auth.hasPermission('roles.view')"
        to="/roles"
        class="admin-focus mb-4 inline-flex items-center gap-2 rounded-lg py-1 text-sm text-primary-500"
        ><ArrowLeft :size="16" aria-hidden="true" />К ролям</RouterLink
      >
      <h2
        id="permission-catalogue-title"
        class="text-xl font-bold text-gray-500"
      >
        Справочник прав
      </h2>
      <p class="mt-1 text-sm text-gray-500">
        Системные права и роли, которым они назначены. Настройка доступа
        выполняется в роли.
      </p>
      <form
        role="search"
        class="my-5 grid gap-3 admin-permissions-filter-grid"
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
        label="Загрузка каталога прав…"
      />
      <UiButton
        v-else-if="catalogue.error.value"
        variant="secondary"
        @click="catalogue.load()"
        >Повторить загрузку прав</UiButton
      >
      <div
        v-else-if="catalogue.permissionGroups.value.length"
        role="region"
        aria-label="Каталог прав"
      >
        <PermissionGroups :groups="catalogue.permissionGroups.value">
          <template #details="{ permission }">
            <p class="mb-2 mt-3 text-xs text-gray-500">
              Ролей: {{ permission.roles.length }}
            </p>
            <div v-if="permission.roles.length" class="flex flex-wrap gap-2">
              <UiBadge
                v-for="role in permission.roles"
                :key="role.id"
                tone="primary"
                >{{ role.name }}</UiBadge
              >
            </div>
            <p v-else class="text-sm text-gray-500">
              Не назначено ни одной роли
            </p>
          </template>
        </PermissionGroups>
      </div>
      <UiEmptyState
        v-else
        :label="
          catalogue.permissions.value.length
            ? 'По выбранным условиям права не найдены.'
            : 'Каталог прав пока пуст.'
        "
      />
    </section>
  </AdminWorkspace>
</template>
