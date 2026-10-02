<script setup lang="ts">
import UiNotification from '../../../components/ui/UiNotification.vue'
import { X } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import type { WorkingHour } from '../types/store.types'

defineProps<{
  open: boolean
  storeName: string
  busy: boolean
  error: string
  hours: WorkingHour[]
}>()
const emit = defineEmits<{ close: []; submit: [] }>()
const dayNames = [
  'Понедельник',
  'Вторник',
  'Среда',
  'Четверг',
  'Пятница',
  'Суббота',
  'Воскресенье',
]

function setClosed(day: WorkingHour, closed: boolean): void {
  day.is_closed = closed
  day.opens_at = closed ? null : '09:00'
  day.closes_at = closed ? null : '18:00'
}
</script>

<template>
  <UiDialog
    :open="open"
    labelledby="hours-dialog-title"
    :close-disabled="busy"
    panel-class="w-full max-w-2xl"
    @close="emit('close')"
  >
    <form
      class="max-h-[90vh] w-full overflow-y-auto rounded-xl bg-white p-6 shadow-xl"
      @submit.prevent="emit('submit')"
    >
      <div class="flex items-start justify-between gap-3">
        <div class="min-w-0">
          <h2 id="hours-dialog-title" class="text-lg font-bold">Часы работы</h2>
          <p class="break-words text-sm text-gray-500">{{ storeName }}</p>
        </div>
        <UiButton
          type="button"
          variant="ghost"
          size="sm"
          aria-label="Закрыть часы работы"
          :disabled="busy"
          @click="emit('close')"
          ><X :size="20"
        /></UiButton>
      </div>
      <UiNotification v-if="error">
        {{ error }}
      </UiNotification>
      <div class="mt-6 space-y-3">
        <div
          v-for="day in hours"
          :key="day.weekday"
          class="grid gap-3 rounded-lg border border-gray-100 p-3 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center"
        >
          <div class="min-w-0">
            <p class="font-medium">{{ dayNames[day.weekday - 1] }}</p>
            <UiCheckbox
              class="mt-2"
              mode="boolean"
              :checked="day.is_closed"
              :disabled="busy"
              :accessible-name="`${dayNames[day.weekday - 1]} — выходной`"
              @update:checked="setClosed(day, $event)"
              >Выходной</UiCheckbox
            >
          </div>
          <div v-if="!day.is_closed" class="grid grid-cols-2 gap-2">
            <UiField label="Открытие" required>
              <UiInput
                :model-value="day.opens_at ?? ''"
                type="time"
                required
                :disabled="busy"
                :aria-label="`${dayNames[day.weekday - 1]} — открытие`"
                @update:model-value="day.opens_at = $event"
              />
            </UiField>
            <UiField label="Закрытие" required>
              <UiInput
                :model-value="day.closes_at ?? ''"
                type="time"
                required
                :disabled="busy"
                :aria-label="`${dayNames[day.weekday - 1]} — закрытие`"
                @update:model-value="day.closes_at = $event"
              />
            </UiField>
          </div>
        </div>
      </div>
      <div class="mt-6 flex flex-wrap justify-end gap-3">
        <UiButton
          type="button"
          variant="secondary"
          :disabled="busy"
          @click="emit('close')"
          >Отмена</UiButton
        >
        <UiButton :loading="busy">Сохранить часы работы</UiButton>
      </div>
    </form>
  </UiDialog>
</template>
