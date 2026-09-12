<script setup lang="ts">
import { X } from '@lucide/vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import type { AccessRole, Permission, RolePayload } from '../types/access.types'

defineProps<{ open: boolean; title: string; editing: AccessRole | null; permissions: Permission[]; busy: boolean; error: string }>()
const form = defineModel<RolePayload>('form', { required: true })
const emit = defineEmits<{ close: []; submit: [] }>()
</script>

<template>
  <UiDialog :open="open" labelledby="role-dialog-title" describedby="role-dialog-description" :close-disabled="busy" panel-class="w-full max-w-2xl overflow-y-auto rounded-xl bg-white p-6 shadow-xl" @close="emit('close')">
    <form @submit.prevent="emit('submit')">
      <div class="flex items-start justify-between gap-3"><div><h2 id="role-dialog-title" class="text-lg font-bold text-gray-900">{{ title }}</h2><p id="role-dialog-description" class="mt-1 text-sm text-gray-500">Настройте роль и доступные ей права.</p></div><UiButton type="button" variant="ghost" size="sm" aria-label="Закрыть окно роли" :disabled="busy" @click="emit('close')"><X :size="20" /></UiButton></div>
      <UiAlert v-if="error" class="mt-4">{{ error }}</UiAlert>
      <div class="mt-6 grid gap-4">
        <div class="grid gap-4 sm:grid-cols-2"><UiField label="Название" required><UiInput v-model="form.name" class="mt-1.5" :disabled="editing?.is_system || busy" required /></UiField><UiField label="Технический код" required><UiInput v-model="form.slug" class="mt-1.5" :disabled="editing?.is_system || busy" required /></UiField></div>
        <UiField label="Описание"><UiTextarea v-model="form.description" class="mt-1.5 min-h-20 font-normal" :disabled="busy" /></UiField>
        <fieldset :disabled="busy"><legend class="text-sm font-medium text-gray-700">Права</legend><div class="mt-2 grid gap-2 sm:grid-cols-2"><UiCheckbox v-for="permission in permissions" :key="permission.id" v-model="form.permission_ids" :value="permission.id">{{ permission.name }} <span class="text-xs text-gray-400">({{ permission.code }})</span></UiCheckbox></div></fieldset>
      </div>
      <div class="mt-6 flex flex-wrap justify-end gap-3"><UiButton type="button" variant="ghost" :disabled="busy" @click="emit('close')">Отмена</UiButton><UiButton type="submit" :loading="busy" :disabled="busy">{{ busy ? 'Сохранение…' : 'Сохранить' }}</UiButton></div>
    </form>
  </UiDialog>
</template>
