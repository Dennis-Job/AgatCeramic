<script setup lang="ts">
import UiNotification from '../../../components/ui/UiNotification.vue'
import { X } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiDialogFooter from '../../../components/ui/UiDialogFooter.vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import type { StorePayload } from '../types/store.types'

defineProps<{
  open: boolean
  editing: boolean
  busy: boolean
  error: string
  form: StorePayload
}>()
const emit = defineEmits<{ close: []; submit: [] }>()
</script>

<template>
  <UiDialog
    :open="open"
    labelledby="store-dialog-title"
    :close-disabled="busy"
    panel-class="w-full max-w-xl"
    @close="emit('close')"
  >
    <form
      class="max-h-[90vh] w-full overflow-y-auto rounded-xl bg-white p-6 shadow-xl"
      @submit.prevent="emit('submit')"
    >
      <div class="flex items-start justify-between gap-3">
        <h2 id="store-dialog-title" class="text-lg font-bold">
          {{ editing ? 'Редактировать магазин' : 'Новый магазин' }}
        </h2>
        <UiButton
          type="button"
          variant="ghost"
          size="sm"
          aria-label="Закрыть окно магазина"
          :disabled="busy"
          @click="emit('close')"
          ><X :size="20"
        /></UiButton>
      </div>
      <UiNotification v-if="error">
        {{ error }}
      </UiNotification>
      <div class="mt-6 grid gap-4">
        <UiField label="Название" required>
          <UiInput
            v-model="form.name"
            required
            maxlength="255"
            :disabled="busy"
            data-autofocus
          />
        </UiField>
        <UiField label="Адрес" required>
          <UiInput
            v-model="form.address"
            required
            maxlength="255"
            :disabled="busy"
          />
        </UiField>
        <UiField label="Телефон">
          <UiInput
            :model-value="form.phone ?? ''"
            type="tel"
            maxlength="50"
            :disabled="busy"
            @update:model-value="form.phone = $event || null"
          />
        </UiField>
        <UiCheckbox
          mode="boolean"
          :checked="form.is_published"
          :disabled="busy"
          @update:checked="form.is_published = $event"
          >Опубликовать магазин</UiCheckbox
        >
      </div>
      <UiDialogFooter>
        <template #note
          >Часы работы можно настроить после сохранения магазина.</template
        >
        <UiButton
          type="button"
          variant="secondary"
          :disabled="busy"
          @click="emit('close')"
          >Отмена</UiButton
        >
        <UiButton :loading="busy">Сохранить</UiButton>
      </UiDialogFooter>
    </form>
  </UiDialog>
</template>
