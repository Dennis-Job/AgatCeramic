<script setup lang="ts">
import { X } from '@lucide/vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiNotification from '../../../components/ui/UiNotification.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiDialogFooter from '../../../components/ui/UiDialogFooter.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import type { Attribute } from '../../attributes/types/attribute.types'
import type { AttributeGroup } from '../../attribute-groups/types/attributeGroup.types'
import type { Category } from '../types/category.types'
import { attributeTypeLabel } from '../../../utils/attributeTypes'
defineProps<{
  open: boolean
  busy: boolean
  error: string
  category: Category | null
  groups: AttributeGroup[]
  groupedAttributes: Array<AttributeGroup & { attributes: Attribute[] }>
  ungroupedAttributes: Attribute[]
  selectedGroupIds: number[]
  selectedAttributeIds: number[]
  requiredAttributeIds: number[]
}>()
const emit = defineEmits<{
  close: []
  submit: []
  groupsChange: [value: Array<number | string>]
  attributesChange: [value: Array<number | string>]
  requiredChange: [value: Array<number | string>]
}>()
</script>
<template>
  <UiDialog
    :open="open && Boolean(category)"
    labelledby="category-attributes-title"
    describedby="category-attributes-description"
    :close-disabled="busy"
    panel-class="category-assignments-dialog w-full max-w-4xl"
    @close="emit('close')"
    ><form
      v-if="category"
      class="flex h-[calc(100dvh-2rem)] w-full flex-col rounded-xl bg-white p-6 shadow-xl"
      @submit.prevent="emit('submit')"
    >
      <div class="flex shrink-0 items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
          <h2
            id="category-attributes-title"
            class="break-words text-lg font-bold text-gray-500 [overflow-wrap:anywhere]"
          >
            Характеристики: {{ category.name }}
          </h2>
        </div>
        <UiButton
          type="button"
          variant="surface"
          size="sm"
          aria-label="Закрыть окно характеристик категории"
          :disabled="busy"
          @click="emit('close')"
          ><X :size="20"
        /></UiButton>
      </div>
      <UiNotification v-if="error">{{ error }}</UiNotification>
      <div
        class="mt-6 min-h-0 flex-1 space-y-4 overflow-y-auto overscroll-contain"
      >
        <UiCard
          :padded="false"
          aria-labelledby="category-assignment-groups-title"
        >
          <template #header>
            <h3
              id="category-assignment-groups-title"
              class="text-sm font-semibold text-gray-500"
            >
              Группы характеристик
            </h3>
          </template>
          <div class="grid gap-2 p-3 sm:grid-cols-2 sm:p-4">
            <UiCheckbox
              v-for="group in groups"
              :key="group.id"
              :model-value="selectedGroupIds"
              :value="group.id"
              class="h-auto min-h-[42px]"
              @update:model-value="emit('groupsChange', $event)"
              >{{ group.name }}</UiCheckbox
            >
          </div>
        </UiCard>
        <UiCard
          v-for="group in groupedAttributes"
          :key="group.id"
          :padded="false"
          :aria-labelledby="`category-assignment-group-${group.id}`"
        >
          <template #header>
            <h3
              :id="`category-assignment-group-${group.id}`"
              class="break-words text-sm font-semibold text-gray-500 [overflow-wrap:anywhere]"
            >
              {{ group.name }}
            </h3>
          </template>
          <div
            v-for="attribute in group.attributes"
            :key="attribute.id"
            class="flex min-w-0 flex-col gap-2 px-3 py-2 sm:flex-row sm:items-center"
          >
            <UiCheckbox
              :model-value="selectedAttributeIds"
              :value="attribute.id"
              class="h-auto min-h-[42px] min-w-0 flex-1 border-0"
              @update:model-value="emit('attributesChange', $event)"
              ><span class="block font-medium text-gray-500">{{
                attribute.name
              }}</span>
              <span class="mt-1 block text-xs text-gray-500"
                >/{{ attribute.slug }} ·
                {{ attributeTypeLabel(attribute.type) }}</span
              ></UiCheckbox
            ><UiCheckbox
              v-if="selectedAttributeIds.includes(attribute.id)"
              :model-value="requiredAttributeIds"
              :value="attribute.id"
              class="h-auto min-h-[42px] shrink-0 self-start sm:self-center"
              :accessible-name="`Обязательная характеристика: ${attribute.name}`"
              @update:model-value="emit('requiredChange', $event)"
              >Обязательная</UiCheckbox
            >
          </div>
          <p
            v-if="!group.attributes.length"
            class="px-4 py-3 text-sm text-gray-500"
          >
            В группе пока нет характеристик.
          </p>
        </UiCard>
        <UiCard
          v-if="ungroupedAttributes.length"
          :padded="false"
          aria-labelledby="category-assignment-ungrouped-title"
        >
          <template #header>
            <h3
              id="category-assignment-ungrouped-title"
              class="text-sm font-semibold text-gray-500"
            >
              Без группы
            </h3>
          </template>
          <div
            v-for="attribute in ungroupedAttributes"
            :key="attribute.id"
            class="flex min-w-0 flex-col gap-2 px-3 py-2 sm:flex-row sm:items-center"
          >
            <UiCheckbox
              :model-value="selectedAttributeIds"
              :value="attribute.id"
              class="h-auto min-h-[42px] min-w-0 flex-1 border-0"
              @update:model-value="emit('attributesChange', $event)"
              >{{ attribute.name }}</UiCheckbox
            ><UiCheckbox
              v-if="selectedAttributeIds.includes(attribute.id)"
              :model-value="requiredAttributeIds"
              :value="attribute.id"
              class="h-auto min-h-[42px] shrink-0 self-start sm:self-center"
              :accessible-name="`Обязательная характеристика: ${attribute.name}`"
              @update:model-value="emit('requiredChange', $event)"
              >Обязательная</UiCheckbox
            >
          </div>
        </UiCard>
      </div>
      <UiDialogFooter>
        <template #note
          ><span id="category-attributes-description"
            >Выберите характеристики, доступные для товаров этой
            категории.</span
          ></template
        >
        <div class="flex shrink-0 items-center gap-3">
          <UiButton
            type="button"
            variant="surface"
            :disabled="busy"
            @click="emit('close')"
            >Отмена</UiButton
          ><UiButton :loading="busy">Сохранить</UiButton>
        </div>
      </UiDialogFooter>
    </form></UiDialog
  >
</template>

<style scoped>
:deep(.category-assignments-dialog) {
  max-height: calc(100dvh - 2rem);
}
</style>
