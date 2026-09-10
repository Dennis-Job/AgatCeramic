<script setup lang="ts">
import BaseDialog from './BaseDialog.vue'
import BaseAlert from './BaseAlert.vue'

withDefaults(defineProps<{
  open: boolean
  title: string
  description: string
  confirmLabel?: string
  busy?: boolean
  error?: string
}>(), {
  confirmLabel: 'Удалить',
  busy: false,
  error: '',
})

const emit = defineEmits<{ close: []; confirm: [] }>()
</script>

<template>
  <BaseDialog
    :open="open"
    labelledby="confirm-dialog-title"
    describedby="confirm-dialog-description"
    :close-disabled="busy"
    panel-class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl"
    @close="emit('close')"
  >
    <h2 id="confirm-dialog-title" class="text-lg font-bold text-gray-900">{{ title }}</h2>
    <p id="confirm-dialog-description" class="mt-3 text-sm leading-6 text-gray-500">{{ description }}</p>
    <BaseAlert v-if="error" class="mt-4">{{ error }}</BaseAlert>
    <div class="mt-6 flex flex-wrap justify-end gap-3">
      <button type="button" class="rounded-lg px-4 py-2.5 text-sm font-semibold text-gray-600 disabled:cursor-not-allowed disabled:opacity-60" :disabled="busy" @click="emit('close')">Отмена</button>
      <button type="button" class="rounded-lg bg-error-500 px-4 py-2.5 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-60" :disabled="busy" @click="emit('confirm')">{{ busy ? 'Удаление…' : confirmLabel }}</button>
    </div>
  </BaseDialog>
</template>
