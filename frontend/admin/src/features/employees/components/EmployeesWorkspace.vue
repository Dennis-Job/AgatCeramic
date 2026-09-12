<script setup lang="ts">
import { onMounted } from 'vue'
import { Plus } from '@lucide/vue'
import ConfirmDialog from '../../../components/shared/ConfirmDialog.vue'
import PageHeader from '../../../components/shared/PageHeader.vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import { useEmployeesWorkspace } from '../composables/useEmployeesWorkspace'
import EmployeeFormDialog from './EmployeeFormDialog.vue'
import EmployeesList from './EmployeesList.vue'

const workspace = useEmployeesWorkspace()
const statusFilterOptions = [{ label: 'Все статусы', value: '' }, { label: 'Активные', value: 'active' }, { label: 'Заблокированные', value: 'blocked' }]
onMounted(workspace.initialize)
</script>

<template>
  <section class="mx-auto admin-page">
    <PageHeader class="mb-7" eyebrow="Управление доступом" title="Сотрудники"><template #actions><UiButton v-if="workspace.canManage.value" :disabled="!workspace.canEdit.value" @click="workspace.openForm()"><Plus :size="18" aria-hidden="true" />Добавить сотрудника</UiButton></template></PageHeader>
    <UiAlert v-if="workspace.error.value" class="mb-4">{{ workspace.error.value }}</UiAlert>
    <UiAlert v-if="!workspace.error.value && workspace.rolesError.value" class="mb-4">{{ workspace.rolesError.value }} Редактирование сотрудников временно недоступно.</UiAlert>
    <UiCard :padded="false" class="overflow-hidden">
      <form role="search" class="grid grid-cols-2 gap-3 border-b border-gray-100 p-5 admin-employees-filter-grid" @submit.prevent="workspace.load()">
        <UiInput v-model="workspace.search.value" class="col-span-2 sm:col-auto" searchable placeholder="Имя или email" aria-label="Поиск сотрудников" />
        <UiSelect v-model="workspace.status.value" accessible-name="Фильтр по статусу" :options="statusFilterOptions" @change="workspace.load()" />
        <UiButton type="submit" variant="secondary" class="w-full sm:w-auto">Найти</UiButton>
      </form>
      <EmployeesList v-if="!workspace.error.value" :employees="workspace.employees.value" :loading="workspace.loading.value" :can-manage="workspace.canManage.value" :can-edit="workspace.canEdit.value" @edit="workspace.openForm" @remove="workspace.deleting.value = $event" />
    </UiCard>
    <EmployeeFormDialog v-model:form="workspace.form.value" :open="workspace.isFormOpen.value" :title="workspace.title.value" :editing="workspace.editing.value" :roles="workspace.roles.value" :busy="workspace.saving.value" :error="workspace.formError.value" @close="workspace.closeForm" @submit="workspace.submit" />
    <ConfirmDialog :open="Boolean(workspace.deleting.value)" title="Удалить сотрудника?" :description="`Учётная запись «${workspace.deleting.value?.name ?? ''}» будет удалена. Это действие нельзя отменить.`" :busy="workspace.isDeleting.value" :error="workspace.deleteError.value" @close="workspace.cancelDelete" @confirm="workspace.remove" />
  </section>
</template>
