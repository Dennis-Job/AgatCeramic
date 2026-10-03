<script setup lang="ts">
import { X } from '@lucide/vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiDialogFooter from '../../../components/ui/UiDialogFooter.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import type { CategoryPayload } from '../types/category.types'
import CategoryMainSection from './CategoryMainSection.vue'
defineProps<{
  open: boolean
  title: string
  busy: boolean
  error: string
  form: CategoryPayload
  parentOptions: { label: string; value: string }[]
  updateName: (value: string) => void
  updateSlug: (value: string) => void
}>()
const emit = defineEmits<{ close: []; submit: [] }>()
</script>
<template>
  <UiDialog
    :open="open"
    labelledby="category-dialog-title"
    describedby="category-dialog-description"
    :close-disabled="busy"
    panel-class="w-full max-w-2xl"
    @close="emit('close')"
    ><form
      class="max-h-[90vh] w-full overflow-y-auto rounded-xl bg-white p-6 shadow-xl"
      @submit.prevent="emit('submit')"
    >
      <div class="flex items-start justify-between gap-4">
        <div>
          <h2
            id="category-dialog-title"
            class="text-lg font-bold text-gray-900"
          >
            {{ title }}
          </h2>
        </div>
        <UiButton
          type="button"
          variant="ghost"
          size="sm"
          aria-label="Закрыть окно категории"
          :disabled="busy"
          @click="emit('close')"
          ><X :size="20"
        /></UiButton>
      </div>
      <UiNotification v-if="error">{{ error }}</UiNotification
      ><CategoryMainSection
        :form="form"
        :parent-options="parentOptions"
        :update-name="updateName"
        :update-slug="updateSlug"
      />
      <UiDialogFooter>
        <template #note
          ><span id="category-dialog-description"
            >Настройте отображаемое название и адрес страницы категории.</span
          ></template
        >
        <UiButton
          type="button"
          variant="ghost"
          :disabled="busy"
          @click="emit('close')"
          >Отмена</UiButton
        ><UiButton :loading="busy">Сохранить</UiButton>
      </UiDialogFooter>
    </form></UiDialog
  >
</template>
