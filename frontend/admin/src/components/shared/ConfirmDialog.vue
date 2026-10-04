<script setup lang="ts">
// Shared destructive action flow.
import UiDialog from '../ui/UiDialog.vue'
import UiNotification from '../ui/UiNotification.vue'
import UiButton from '../ui/UiButton.vue'

withDefaults(
  defineProps<{
    open: boolean
    title: string
    description: string
    confirmLabel?: string
    busy?: boolean
    error?: string
  }>(),
  {
    confirmLabel: 'Удалить',
    busy: false,
    error: '',
  },
)

const emit = defineEmits<{ close: []; confirm: [] }>()
</script>

<template>
  <UiDialog
    :open="open"
    labelledby="confirm-dialog-title"
    describedby="confirm-dialog-description"
    :close-disabled="busy"
    panel-class="w-full max-w-md rounded-xl bg-white p-6 shadow-xl"
    @close="emit('close')"
  >
    <h2 id="confirm-dialog-title" class="text-lg font-bold text-gray-500">
      {{ title }}
    </h2>
    <p
      id="confirm-dialog-description"
      class="mt-3 text-sm leading-6 text-gray-500"
    >
      {{ description }}
    </p>
    <UiNotification v-if="error">{{ error }}</UiNotification>
    <div class="mt-6 flex flex-wrap justify-end gap-3">
      <UiButton
        type="button"
        variant="surface"
        :disabled="busy"
        @click="emit('close')"
        >Отмена</UiButton
      >
      <UiButton
        type="button"
        variant="danger"
        :loading="busy"
        :disabled="busy"
        @click="emit('confirm')"
        >{{ busy ? 'Удаление…' : confirmLabel }}</UiButton
      >
    </div>
  </UiDialog>
</template>
