<script setup lang="ts">
import { onMounted } from 'vue'
import { Pencil, Plus, Trash2 } from '@lucide/vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import AdminWorkspace from '../../../components/shared/AdminWorkspace.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiTable from '../../../components/ui/UiTable.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import { useRolesWorkspace } from '../composables/useRolesWorkspace'
import RoleFormDialog from './RoleFormDialog.vue'

const workspace = useRolesWorkspace()
onMounted(workspace.load)
</script>

<template>
  <AdminWorkspace mode="list" :aria-busy="workspace.loading.value">
    <template #intro>
      <PageHeader class="mb-7" eyebrow="Управление доступом" title="Роли"
        ><template #actions
          ><UiButton
            v-if="workspace.canManage.value"
            :disabled="!workspace.canEdit.value"
            @click="workspace.open()"
            ><Plus :size="18" aria-hidden="true" />Добавить роль</UiButton
          ></template
        ></PageHeader
      >
    </template>

    <UiAlert v-if="workspace.error.value" class="mb-4">{{
      workspace.error.value
    }}</UiAlert>
    <UiAlert
      v-if="!workspace.error.value && workspace.permissionsError.value"
      class="mb-4"
      >{{ workspace.permissionsError.value }} Редактирование ролей временно
      недоступно.</UiAlert
    >
    <UiLoadingState v-if="workspace.loading.value" label="Загрузка ролей…" />
    <UiEmptyState
      v-else-if="!workspace.error.value && workspace.roles.value.length === 0"
      label="Роли не найдены."
    />
    <UiTable
      v-else-if="!workspace.error.value"
      label="Список ролей"
      min-width="min-w-[960px]"
      table-class="seller-table"
      sticky-header
      :sticky-edges="workspace.canManage.value"
    >
      <thead>
        <tr>
          <th scope="col" class="w-72">Роль</th>
          <th scope="col">Описание</th>
          <th scope="col" class="w-28 text-right">Прав</th>
          <th scope="col" class="w-40">Тип</th>
          <th v-if="workspace.canManage.value" scope="col" class="w-40">
            <span class="sr-only">Действия</span>
          </th>
        </tr>
      </thead>
      <tbody>
        <tr v-for="role in workspace.roles.value" :key="role.id">
          <td>
            <h2 class="font-semibold text-gray-700">{{ role.name }}</h2>
            <p class="mt-1 text-xs text-gray-500">{{ role.slug }}</p>
          </td>
          <td>{{ role.description || 'Без описания' }}</td>
          <td class="text-right">{{ role.permissions.length }}</td>
          <td>
            <UiBadge tone="neutral">{{
              role.is_system ? 'Системная' : 'Пользовательская'
            }}</UiBadge>
          </td>
          <td v-if="workspace.canManage.value">
            <div class="flex justify-end gap-1">
              <UiButton
                variant="ghost"
                size="sm"
                :disabled="!workspace.canEdit.value"
                :aria-label="`Редактировать роль ${role.name}`"
                @click="workspace.open(role)"
                ><Pencil :size="17" /></UiButton
              ><UiButton
                v-if="!role.is_system"
                variant="danger-ghost"
                size="sm"
                :aria-label="`Удалить роль ${role.name}`"
                @click="workspace.deleting.value = role"
                ><Trash2 :size="17"
              /></UiButton>
            </div>
          </td>
        </tr></tbody
    ></UiTable>
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
