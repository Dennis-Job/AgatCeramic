<script setup lang="ts">
// Shared destructive action flow.
import { X } from '@lucide/vue'
import UiDialog from '../ui/UiDialog.vue'
import UiNotification from '../ui/UiNotification.vue'
import UiButton from '../ui/UiButton.vue'
import UiDialogFooter from '../ui/UiDialogFooter.vue'

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
    <div class="flex items-start justify-between gap-4">
      <h2 id="confirm-dialog-title" class="text-lg font-bold text-gray-900">
        {{ title }}
      </h2>
      <UiButton
        type="button"
        variant="ghost"
        aria-label="Закрыть подтверждение"
        :disabled="busy"
        @click="emit('close')"
      >
        <X :size="20" />
      </UiButton>
    </div>
    <UiNotification v-if="error">{{ error }}</UiNotification>
    <UiDialogFooter>
      <template #note
        ><span id="confirm-dialog-description">{{
          description
        }}</span></template
      >
      <UiButton
        type="button"
        variant="ghost"
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
    </UiDialogFooter>
  </UiDialog>
</template>
