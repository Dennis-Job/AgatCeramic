<script setup lang="ts">
import { X } from '@lucide/vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import { roleDisplayName } from '../services/employees'
import type { Employee, EmployeePayload, EmployeeRole } from '../types/employee.types'

defineProps<{ open: boolean; title: string; editing: Employee | null; roles: EmployeeRole[]; busy: boolean; error: string }>()
const form = defineModel<EmployeePayload>('form', { required: true })
const emit = defineEmits<{ close: []; submit: [] }>()
const statusOptions = [{ label: 'Активен', value: 'active' }, { label: 'Заблокирован', value: 'blocked' }]
</script>

<template>
  <UiDialog :open="open" labelledby="employee-dialog-title" describedby="employee-dialog-description" :close-disabled="busy" panel-class="w-full max-w-xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" @close="emit('close')">
    <form @submit.prevent="emit('submit')">
      <div class="flex items-start justify-between gap-3">
        <div><h2 id="employee-dialog-title" class="text-lg font-bold text-gray-900">{{ title }}</h2><p id="employee-dialog-description" class="mt-1 text-sm text-gray-500">Укажите учётные данные, статус и роли.</p></div>
        <UiButton type="button" variant="ghost" size="sm" aria-label="Закрыть окно сотрудника" :disabled="busy" @click="emit('close')"><X :size="20" /></UiButton>
      </div>
      <UiAlert v-if="error" class="mt-4">{{ error }}</UiAlert>
      <div class="mt-6 grid gap-4">
        <UiField label="Имя" required><UiInput v-model="form.name" class="mt-1.5 w-full font-normal" required :disabled="busy" /></UiField>
        <UiField label="Email" required><UiInput v-model="form.email" class="mt-1.5 w-full font-normal" required type="email" :disabled="busy" /></UiField>
        <div class="grid gap-4 sm:grid-cols-2">
          <UiField :label="editing ? 'Новый пароль (необязательно)' : 'Пароль'" :required="!editing"><UiInput v-model="form.password" class="mt-1.5 w-full font-normal" :required="!editing" minlength="12" type="password" autocomplete="new-password" :disabled="busy" /></UiField>
          <UiField label="Подтверждение пароля" :required="!editing || Boolean(form.password)"><UiInput v-model="form.password_confirmation" class="mt-1.5 w-full font-normal" :required="!editing || Boolean(form.password)" minlength="12" type="password" autocomplete="new-password" :disabled="busy" /></UiField>
        </div>
        <UiField label="Статус" required><UiSelect v-model="form.status" class="mt-1.5 w-full font-normal" accessible-name="Статус сотрудника" :options="statusOptions" :disabled="busy" /></UiField>
        <fieldset :disabled="busy"><legend class="text-sm font-medium text-gray-700">Роли</legend><div class="mt-2 grid gap-2 sm:grid-cols-2"><UiCheckbox v-for="role in roles" :key="role.id" v-model="form.role_ids" :value="role.id">{{ roleDisplayName(role) }}</UiCheckbox></div></fieldset>
      </div>
      <div class="mt-6 flex flex-wrap justify-end gap-3"><UiButton type="button" variant="ghost" :disabled="busy" @click="emit('close')">Отмена</UiButton><UiButton type="submit" :loading="busy" :disabled="busy">{{ busy ? 'Сохранение…' : 'Сохранить' }}</UiButton></div>
    </form>
  </UiDialog>
</template>
