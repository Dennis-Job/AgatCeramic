<script setup lang="ts">
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { BookOpen, Pencil, Plus, Trash2 } from '@lucide/vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import CatalogReferenceLayout from '../../../components/shared/CatalogReferenceLayout.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import { useAuthStore } from '../../../stores/auth'
import { useRolesWorkspace } from '../composables/useRolesWorkspace'
import RoleFormDialog from './RoleFormDialog.vue'
import RolesNavigation from './RolesNavigation.vue'
import PermissionGroups from './PermissionGroups.vue'

const auth = useAuthStore()
const workspace = useRolesWorkspace()
const roleOptions = computed(() =>
  workspace.filteredRoles.value.map((role) => ({
    label: role.name,
    value: String(role.id),
  })),
)
const selectedId = computed(() => workspace.selectedRole.value?.id ?? null)
onMounted(workspace.load)
</script>

<template>
  <AdminWorkspace mode="list" :aria-busy="workspace.loading.value">
    <template #intro>
      <PageHeader eyebrow="Управление" title="Команда и доступ">
        <template #actions>
          <UiButton
            v-if="workspace.canManage.value"
            :disabled="!workspace.canEdit.value"
            @click="workspace.open()"
            ><Plus :size="18" aria-hidden="true" />Добавить роль</UiButton
          >
        </template>
      </PageHeader>
      <slot name="navigation" />
    </template>

    <UiNotification v-if="workspace.error.value">{{
      workspace.error.value
    }}</UiNotification>
    <UiNotification
      v-if="!workspace.error.value && workspace.permissionsError.value"
      >{{ workspace.permissionsError.value }} Редактирование ролей временно
      недоступно.</UiNotification
    >
    <UiLoadingState
      v-if="workspace.loading.value"
      class="admin-container"
      label="Загрузка ролей…"
    />
    <div v-else-if="workspace.error.value" class="admin-container py-5">
      <UiButton variant="secondary" @click="workspace.load()"
        >Повторить загрузку ролей</UiButton
      >
    </div>
    <div v-else role="region" aria-label="Роли и права">
      <CatalogReferenceLayout>
        <template #sidebar>
          <RolesNavigation
            v-model:search="workspace.search.value"
            :roles="workspace.filteredRoles.value"
            :selected-id="selectedId"
            @select="workspace.selectedRoleId.value = $event"
          />
          <RouterLink
            v-if="auth.hasPermission('permissions.view')"
            to="/permissions"
            class="admin-focus mt-6 flex items-center gap-2 rounded-lg border-t border-gray-100 px-3 py-4 text-sm text-primary-500"
            ><BookOpen :size="18" aria-hidden="true" />Справочник
            прав</RouterLink
          >
        </template>
        <template #mobile>
          <h2 class="mb-3 text-xl font-bold text-gray-500">Роли</h2>
          <UiInput
            v-model="workspace.search.value"
            searchable
            type="search"
            aria-label="Поиск ролей"
            placeholder="Найти роль"
            class="mb-3"
          />
          <UiSelect
            :model-value="selectedId === null ? '' : String(selectedId)"
            :options="roleOptions"
            accessible-name="Выбор роли"
            :disabled="!roleOptions.length"
            @update:model-value="
              workspace.selectedRoleId.value = Number($event)
            "
          />
          <RouterLink
            v-if="auth.hasPermission('permissions.view')"
            to="/permissions"
            class="admin-focus mt-4 inline-flex items-center gap-2 rounded-lg py-2 text-sm text-primary-500"
            ><BookOpen :size="18" aria-hidden="true" />Справочник
            прав</RouterLink
          >
        </template>

        <article
          v-if="workspace.selectedRole.value"
          :aria-labelledby="`role-title-${workspace.selectedRole.value.id}`"
        >
          <header
            class="mb-6 flex flex-col flex-wrap items-start justify-between gap-4 border-b border-gray-100 pb-5 sm:flex-row"
          >
            <div class="w-full min-w-0 flex-1 sm:w-auto">
              <h2
                :id="`role-title-${workspace.selectedRole.value.id}`"
                class="break-words text-2xl font-bold text-gray-500 [overflow-wrap:anywhere]"
              >
                {{ workspace.selectedRole.value.name }}
              </h2>
              <p class="mt-1 break-all text-sm text-gray-500">
                {{ workspace.selectedRole.value.slug }}
              </p>
              <UiBadge class="mt-3" tone="neutral">{{
                workspace.selectedRole.value.is_system
                  ? 'Системная'
                  : 'Пользовательская роль'
              }}</UiBadge>
              <p
                class="mt-4 whitespace-pre-wrap break-words text-sm text-gray-500 [overflow-wrap:anywhere]"
              >
                {{
                  workspace.selectedRole.value.description ||
                  'Описание не добавлено.'
                }}
              </p>
            </div>
            <div
              v-if="workspace.canManage.value"
              class="flex max-w-full flex-wrap gap-1"
            >
              <UiButton
                variant="primary-ghost"
                size="sm"
                :disabled="!workspace.canEdit.value"
                :aria-label="`Редактировать роль ${workspace.selectedRole.value.name}`"
                @click="workspace.open(workspace.selectedRole.value)"
                ><Pencil :size="17" aria-hidden="true" />Редактировать
                роль</UiButton
              >
              <UiButton
                v-if="!workspace.selectedRole.value.is_system"
                variant="danger-ghost"
                size="sm"
                :aria-label="`Удалить роль ${workspace.selectedRole.value.name}`"
                tooltip="Удалить роль"
                @click="workspace.deleting.value = workspace.selectedRole.value"
                ><Trash2 :size="17" aria-hidden="true"
              /></UiButton>
            </div>
          </header>
          <h3 class="mb-4 text-lg font-bold text-gray-500">
            Назначенные права
          </h3>
          <PermissionGroups
            v-if="workspace.permissionGroups.value.length"
            :groups="workspace.permissionGroups.value"
            assigned
          />
          <UiEmptyState v-else label="Этой роли пока не назначены права." />
          <p class="mt-5 border-t border-gray-100 pt-4 text-xs text-gray-500">
            Набор прав применяется ко всем сотрудникам с этой ролью.
          </p>
        </article>
        <UiEmptyState
          v-else
          :label="
            workspace.search.value.trim()
              ? 'Роли не найдены. Попробуйте другой запрос.'
              : 'Роли не найдены.'
          "
        />
      </CatalogReferenceLayout>
    </div>
    <RoleFormDialog
      v-model:form="workspace.form.value"
      :open="workspace.opened.value"
      :title="workspace.title.value"
      :editing="workspace.editing.value"
      :permissions="workspace.permissions.value"
      :busy="workspace.saving.value"
      :error="workspace.formError.value"
      @close="workspace.close"
      @submit="workspace.save"
    />
    <ConfirmDialog
      :open="Boolean(workspace.deleting.value)"
      title="Удалить роль?"
      :description="`Роль «${workspace.deleting.value?.name ?? ''}» будет удалена. Это действие нельзя отменить.`"
      :busy="workspace.isDeleting.value"
      :error="workspace.deleteError.value"
      @close="workspace.cancelDelete"
      @confirm="workspace.remove"
    />
  </AdminWorkspace>
</template>
