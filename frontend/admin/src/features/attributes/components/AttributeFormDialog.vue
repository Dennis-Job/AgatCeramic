<script setup lang="ts">
import UiNotification from '../../../components/ui/UiNotification.vue'
import { computed } from 'vue'
import { Plus, Trash2, X } from '@lucide/vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiDialogFooter from '../../../components/ui/UiDialogFooter.vue'
import type { AttributePayload, AttributeType } from '../types/attribute.types'
import type { AttributeGroup } from '../../attribute-groups/types/attributeGroup.types'
import { slugPattern } from '../../catalog/validation/slug'

const props = defineProps<{
  open: boolean
  title: string
  busy: boolean
  error: string
  form: AttributePayload
  groups: AttributeGroup[]
  types: { label: string; value: string }[]
  manuallyEditedSlug: boolean
  canCreateGroup?: boolean
  suspended?: boolean
}>()
const emit = defineEmits<{
  close: []
  submit: []
  updateName: [value: string]
  updateSlug: [value: string]
  updateType: [value: AttributeType]
  createGroup: []
}>()
const groupOptions = computed(() => [
  { label: 'Без группы', value: '' },
  ...props.groups.map((group) => ({
    label: group.name,
    value: String(group.id),
  })),
])
const hasOptions = computed(() =>
  ['select', 'multiselect'].includes(props.form.type),
)
function addOption(): void {
  props.form.options.push({
    value: '',
    label: '',
    sort_order: props.form.options.length,
  })
}
function removeOption(index: number): void {
  props.form.options.splice(index, 1)
}
</script>
<template>
  <UiDialog
    :open="open"
    :suspended="suspended"
    labelledby="attribute-dialog-title"
    describedby="attribute-dialog-description"
    :close-disabled="busy"
    panel-class="w-full max-w-3xl"
    @close="emit('close')"
  >
    <form
      class="flex max-h-[90dvh] w-full flex-col rounded-xl bg-white p-6 shadow-xl"
      @submit.prevent="emit('submit')"
    >
      <div class="flex shrink-0 items-start justify-between gap-4">
        <div class="min-w-0 flex-1">
          <h2
            id="attribute-dialog-title"
            class="break-words text-lg font-bold text-gray-500 [overflow-wrap:anywhere]"
          >
            {{ title }}
          </h2>
        </div>
        <UiButton
          type="button"
          variant="surface"
          size="sm"
          aria-label="Закрыть окно характеристики"
          :disabled="busy"
          @click="emit('close')"
          ><X :size="20"
        /></UiButton>
      </div>
      <div
        class="-m-[var(--admin-focus-halo-width)] min-h-0 overflow-y-auto overscroll-contain p-[var(--admin-focus-halo-width)]"
      >
        <UiNotification v-if="error">
          {{ error }}
        </UiNotification>
        <div class="mt-6 grid gap-4">
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm font-medium text-gray-500"
              >Название<UiInput
                :model-value="form.name"
                class="mt-1.5 w-full font-normal"
                data-autofocus
                required
                @update:model-value="emit('updateName', $event)" /></label
            ><label class="text-sm font-medium text-gray-500"
              >Технический код (slug)<UiInput
                :model-value="form.slug"
                class="mt-1.5 w-full font-normal"
                required
                :pattern="slugPattern"
                @update:model-value="emit('updateSlug', $event)"
            /></label>
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <div class="min-w-0">
              <label class="text-sm font-medium text-gray-500"
                >Группа<UiSelect
                  :model-value="
                    form.attribute_group_id === null
                      ? ''
                      : String(form.attribute_group_id)
                  "
                  class="mt-1.5 w-full font-normal"
                  accessible-name="Группа характеристики"
                  teleport-menu
                  :options="groupOptions"
                  @update:model-value="
                    form.attribute_group_id =
                      $event === '' ? null : Number($event)
                  "
              /></label>
              <UiButton
                v-if="canCreateGroup"
                type="button"
                variant="primary-ghost"
                size="sm"
                class="mt-1.5"
                :disabled="busy"
                @click="emit('createGroup')"
                ><Plus :size="15" />Создать группу</UiButton
              >
            </div>
            <label class="text-sm font-medium text-gray-500"
              >Тип<UiSelect
                :model-value="form.type"
                class="mt-1.5 w-full font-normal"
                accessible-name="Тип характеристики"
                teleport-menu
                :options="types"
                @update:model-value="
                  emit('updateType', $event as AttributeType)
                "
            /></label>
          </div>
          <div class="grid gap-4 sm:grid-cols-2">
            <label class="text-sm font-medium text-gray-500"
              >Единица измерения<UiInput
                :model-value="form.unit ?? ''"
                class="mt-1.5 w-full font-normal"
                placeholder="мм, м², кг"
                @update:model-value="form.unit = $event || null"
            /></label>
            <label class="text-sm font-medium text-gray-500"
              >Порядок сортировки<UiInput
                :model-value="String(form.sort_order)"
                class="mt-1.5"
                type="number"
                min="0"
                required
                @update:model-value="form.sort_order = Number($event)"
            /></label>
          </div>
          <div class="grid gap-2 sm:grid-cols-2">
            <UiCheckbox
              mode="boolean"
              :checked="form.is_filterable"
              @update:checked="form.is_filterable = $event"
              >Использовать в фильтре</UiCheckbox
            ><UiCheckbox
              mode="boolean"
              :checked="form.is_visible_on_product_page"
              @update:checked="form.is_visible_on_product_page = $event"
              >Показывать на странице товара</UiCheckbox
            >
          </div>
          <section v-if="hasOptions" class="admin-panel--inset p-4">
            <div class="flex items-center justify-between">
              <div>
                <h3 class="font-semibold text-gray-500">Варианты</h3>
              </div>
              <UiButton
                type="button"
                variant="surface"
                size="sm"
                @click="addOption"
                >Добавить</UiButton
              >
            </div>
            <div
              v-for="(option, index) in form.options"
              :key="index"
              class="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"
            >
              <UiInput
                v-model="option.label"
                required
                placeholder="Название"
                :aria-label="`Название варианта ${index + 1}`"
              /><UiInput
                v-model="option.value"
                required
                placeholder="Код"
                :aria-label="`Код варианта ${index + 1}`"
              /><UiButton
                type="button"
                variant="danger"
                size="sm"
                :disabled="form.options.length === 1"
                :aria-label="`Удалить вариант ${index + 1}`"
                @click="removeOption(index)"
                ><Trash2 :size="17"
              /></UiButton>
            </div>
          </section>
        </div>
      </div>
      <UiDialogFooter>
        <template #note>
          <span id="attribute-dialog-description" class="block"
            >Укажите тип значения и варианты выбора, если они нужны.</span
          >
          <span class="block"
            >Код варианта должен быть уникален в пределах характеристики.</span
          >
        </template>
        <UiButton
          type="button"
          variant="surface"
          :disabled="busy"
          @click="emit('close')"
          >Отмена</UiButton
        ><UiButton :loading="busy">Сохранить</UiButton>
      </UiDialogFooter>
    </form>
  </UiDialog>
</template>
