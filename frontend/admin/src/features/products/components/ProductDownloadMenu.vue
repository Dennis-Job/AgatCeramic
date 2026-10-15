<script setup lang="ts">
import { ref, useId } from 'vue'
import { ChevronDown, Download, X } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiPopover from '../../../components/ui/UiPopover.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiDialogFooter from '../../../components/ui/UiDialogFooter.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import { useProductExport } from '../composables/useProductExport'

const {
  exporting,
  dialogOpen,
  skuInput,
  skuError,
  error,
  success,
  openDialog,
  closeDialog,
  download,
  downloadSkus,
} = useProductExport()
const menuOpen = ref(false)
const id = useId()

async function selectSkus(closeMenu: () => Promise<void>) {
  await closeMenu()
  openDialog()
}
async function downloadAll(closeMenu: () => Promise<void>) {
  await closeMenu()
  await download()
}
</script>

<template>
  <UiPopover
    :open="menuOpen && !exporting"
    align="end"
    label="Скачать товары"
    panel-class="product-download-menu"
    @update:open="menuOpen = !exporting && $event"
  >
    <template #trigger="{ trigger }">
      <UiButton
        v-bind="trigger"
        type="button"
        variant="secondary"
        :loading="exporting"
        :disabled="exporting"
        :aria-busy="exporting"
      >
        <Download :size="18" aria-hidden="true" />{{
          exporting ? 'Экспорт…' : 'Скачать'
        }}<ChevronDown :size="16" aria-hidden="true" />
      </UiButton>
    </template>
    <template #default="{ close }">
      <div class="grid gap-1">
        <UiButton
          type="button"
          variant="surface"
          class="w-full justify-start text-left whitespace-normal"
          @click="downloadAll(close)"
          >Все товары в Excel</UiButton
        >
        <UiButton
          type="button"
          variant="surface"
          class="w-full justify-start text-left whitespace-normal"
          @click="selectSkus(close)"
          >Товары по SKU</UiButton
        >
      </div>
    </template>
  </UiPopover>
  <UiNotification v-if="error">{{ error }}</UiNotification>
  <UiNotification v-if="success" tone="success" live="polite">{{
    success
  }}</UiNotification>
  <Teleport to="body">
    <UiDialog
      :open="dialogOpen"
      :labelledby="`${id}-title`"
      :describedby="`${id}-description`"
      :close-disabled="exporting"
      panel-class="w-full max-w-xl overflow-y-auto"
      @close="closeDialog"
    >
      <form :aria-busy="exporting" @submit.prevent="downloadSkus">
        <div class="flex items-start justify-between gap-4">
          <h2 :id="`${id}-title`" class="text-lg font-bold text-gray-500">
            Скачать товары по SKU
          </h2>
          <UiButton
            type="button"
            variant="surface"
            class="shrink-0"
            aria-label="Закрыть окно"
            :disabled="exporting"
            @click="closeDialog"
            ><X :size="20" aria-hidden="true"
          /></UiButton>
        </div>
        <p :id="`${id}-description`" class="mt-3 text-sm text-gray-500">
          Введите SKU товаров через запятую. Файл Excel будет содержать только
          указанные товары независимо от фильтров страницы.
        </p>
        <UiField
          class="mt-5 [overflow-wrap:anywhere]"
          label="SKU товаров"
          :error="skuError"
          required
        >
          <UiTextarea
            v-model="skuInput"
            class="mt-2"
            data-autofocus
            placeholder="00123, 00456, ABC-789"
            :disabled="exporting"
            :aria-invalid="!!skuError"
            :aria-describedby="`${id}-help${skuError ? ` ${id}-error` : ''}`"
            @update:model-value="skuError = ''"
          />
        </UiField>
        <p :id="`${id}-help`" class="mt-2 text-xs text-gray-500">
          До 1000 SKU за один раз. Пробелы по краям и повторы будут убраны.
        </p>
        <span v-if="skuError" :id="`${id}-error`" class="sr-only">{{
          skuError
        }}</span>
        <UiDialogFooter>
          <UiButton
            type="button"
            variant="surface"
            :disabled="exporting"
            @click="closeDialog"
            >Отмена</UiButton
          >
          <UiButton type="submit" :loading="exporting" :disabled="exporting"
            ><Download :size="18" aria-hidden="true" />{{
              exporting ? 'Экспорт…' : 'Скачать Excel'
            }}</UiButton
          >
        </UiDialogFooter>
      </form>
    </UiDialog>
  </Teleport>
</template>

<style scoped>
:deep(.product-download-menu) {
  --ui-popover-width: max-content;
  padding: var(--admin-spacing-2);
}
</style>
