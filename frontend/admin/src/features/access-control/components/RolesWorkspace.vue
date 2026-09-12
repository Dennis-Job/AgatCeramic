<script setup lang="ts">
import { onMounted } from 'vue'
import { Pencil, Plus, ShieldCheck, Trash2 } from '@lucide/vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiBadge from '../../../components/ui/UiBadge.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiEmptyState from '../../../components/ui/UiEmptyState.vue'
import UiLoadingState from '../../../components/ui/UiLoadingState.vue'
import { useRolesWorkspace } from '../composables/useRolesWorkspace'
import RoleFormDialog from './RoleFormDialog.vue'

const workspace = useRolesWorkspace()
onMounted(workspace.load)
</script>

<template>
  <section class="mx-auto admin-page" :aria-busy="workspace.loading.value">
    <PageHeader class="mb-7" eyebrow="Управление доступом" title="Роли"><template #actions><UiButton v-if="workspace.canManage.value" :disabled="!workspace.canEdit.value" @click="workspace.open()"><Plus :size="18" aria-hidden="true" />Добавить роль</UiButton></template></PageHeader>
    <UiAlert v-if="workspace.error.value" class="mb-4">{{ workspace.error.value }}</UiAlert>
    <UiAlert v-if="!workspace.error.value && workspace.permissionsError.value" class="mb-4">{{ workspace.permissionsError.value }} Редактирование ролей временно недоступно.</UiAlert>
    <UiLoadingState v-if="workspace.loading.value" label="Загрузка ролей…" />
    <UiEmptyState v-else-if="!workspace.error.value && workspace.roles.value.length === 0" label="Роли не найдены." />
    <div v-else-if="!workspace.error.value" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
      <UiCard v-for="role in workspace.roles.value" :key="role.id">
        <div class="flex gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-lg bg-primary-50 text-primary-600"><ShieldCheck :size="20" aria-hidden="true" /></span><div class="min-w-0 flex-1"><div class="flex flex-wrap items-start justify-between gap-2"><div class="min-w-0"><h2 class="break-words font-semibold text-gray-700">{{ role.name }}</h2><p class="mt-0.5 break-all text-xs text-gray-400">{{ role.slug }}</p></div><UiBadge v-if="role.is_system" tone="neutral">Системная</UiBadge></div><p class="mt-3 min-h-10 break-words text-sm text-gray-500">{{ role.description || 'Без описания' }}</p></div></div>
        <p class="mt-4 text-sm font-medium text-gray-600">Прав: {{ role.permissions.length }}</p>
        <div v-if="workspace.canManage.value" class="mt-4 flex justify-end gap-1 border-t border-gray-100 pt-4"><UiButton variant="ghost" size="sm" :disabled="!workspace.canEdit.value" :aria-label="`Редактировать роль ${role.name}`" @click="workspace.open(role)"><Pencil :size="17" /></UiButton><UiButton v-if="!role.is_system" variant="danger-ghost" size="sm" :aria-label="`Удалить роль ${role.name}`" @click="workspace.deleting.value = role"><Trash2 :size="17" /></UiButton></div>
      </UiCard>
    </div>
    <RoleFormDialog v-model:form="workspace.form.value" :open="workspace.opened.value" :title="workspace.title.value" :editing="workspace.editing.value" :permissions="workspace.permissions.value" :busy="workspace.saving.value" :error="workspace.formError.value" @close="workspace.close" @submit="workspace.save" />
    <ConfirmDialog :open="Boolean(workspace.deleting.value)" title="Удалить роль?" :description="`Роль «${workspace.deleting.value?.name ?? ''}» будет удалена. Это действие нельзя отменить.`" :busy="workspace.isDeleting.value" :error="workspace.deleteError.value" @close="workspace.cancelDelete" @confirm="workspace.remove" />
  </section>
</template>
