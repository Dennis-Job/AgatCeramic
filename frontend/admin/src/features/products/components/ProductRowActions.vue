<script setup lang="ts">
import { ref } from 'vue'
import { Copy, EllipsisVertical, Pencil, Trash2 } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiPopover from '../../../components/ui/UiPopover.vue'

defineProps<{ name: string }>()
const emit = defineEmits<{ edit: []; clone: []; delete: [] }>()
const open = ref(false)
async function select(action: 'clone' | 'delete', close: () => Promise<void>) {
  await close()
  if (action === 'clone') emit('clone')
  else emit('delete')
}
</script>

<template>
  <div class="flex justify-end">
    <UiButton
      type="button"
      variant="primary-ghost"
      size="sm"
      tooltip="Редактировать товар"
      :aria-label="`Редактировать товар ${name}`"
      @click="emit('edit')"
      ><Pencil :size="17" aria-hidden="true"
    /></UiButton>
    <UiPopover
      v-model:open="open"
      align="end"
      teleport
      :label="`Действия товара ${name}`"
      panel-class="product-row-menu p-2! w-64!"
    >
      <template #trigger="{ trigger }">
        <UiButton
          v-bind="trigger"
          type="button"
          variant="neutral-ghost"
          size="sm"
          tooltip="Другие действия"
          :aria-label="`Действия товара ${name}`"
          ><EllipsisVertical :size="17" aria-hidden="true"
        /></UiButton>
      </template>
      <template #default="{ close }">
        <UiButton
          type="button"
          variant="surface"
          class="w-full justify-start text-left whitespace-normal"
          :aria-label="`Создать похожий товар ${name}`"
          @click="select('clone', close)"
          ><Copy :size="17" class="shrink-0" aria-hidden="true" />Создать
          похожий</UiButton
        >
        <UiButton
          type="button"
          variant="danger-ghost"
          class="w-full justify-start text-left whitespace-normal"
          :aria-label="`Удалить товар ${name}`"
          @click="select('delete', close)"
          ><Trash2 :size="17" class="shrink-0" aria-hidden="true" />Удалить
          товар</UiButton
        >
      </template>
    </UiPopover>
  </div>
</template>
