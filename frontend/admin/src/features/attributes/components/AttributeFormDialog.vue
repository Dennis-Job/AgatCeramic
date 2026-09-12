<script setup lang="ts">
import { computed } from 'vue'
import { Trash2, X } from '@lucide/vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import type { AttributePayload, AttributeType } from '../types/attribute.types'
import type { AttributeGroup } from '../../attribute-groups/types/attributeGroup.types'
import { slugPattern } from '../../catalog/validation/slug'

const props = defineProps<{ open: boolean; title: string; busy: boolean; error: string; form: AttributePayload; groups: AttributeGroup[]; types: { label: string; value: string }[]; manuallyEditedSlug: boolean }>()
const emit = defineEmits<{ close: []; submit: []; updateName: [value: string]; updateSlug: [value: string]; updateType: [value: AttributeType] }>()
const groupOptions = computed(() => [{ label: 'Без группы', value: '' }, ...props.groups.map(group => ({ label: group.name, value: String(group.id) }))])
const hasOptions = computed(() => ['select', 'multiselect'].includes(props.form.type))
function addOption(): void { props.form.options.push({ value: '', label: '', sort_order: props.form.options.length }) }
function removeOption(index: number): void { props.form.options.splice(index, 1) }
</script>
<template>
  <UiDialog :open="open" labelledby="attribute-dialog-title" describedby="attribute-dialog-description" :close-disabled="busy" panel-class="w-full max-w-xl" @close="emit('close')">
    <form class="max-h-[90vh] w-full overflow-y-auto rounded-xl bg-white p-6 shadow-xl" @submit.prevent="emit('submit')">
      <div class="flex items-start justify-between"><div><h2 id="attribute-dialog-title" class="text-lg font-bold text-gray-900">{{ title }}</h2><p id="attribute-dialog-description" class="mt-1 text-sm text-gray-500">Укажите тип значения и варианты выбора, если они нужны.</p></div><UiButton type="button" variant="ghost" size="sm" aria-label="Закрыть окно характеристики" :disabled="busy" @click="emit('close')"><X :size="20" /></UiButton></div>
      <p v-if="error" class="mt-4 rounded-lg border border-error-200 bg-error-50 px-4 py-3 text-sm text-error-500" role="alert">{{ error }}</p>
      <div class="mt-6 grid gap-4"><label class="text-sm font-medium text-gray-700">Название<UiInput :model-value="form.name" class="mt-1.5 w-full font-normal" data-autofocus required @update:model-value="emit('updateName', $event)" /></label><label class="text-sm font-medium text-gray-700">Технический код (slug)<UiInput :model-value="form.slug" class="mt-1.5 w-full font-normal" required :pattern="slugPattern" @update:model-value="emit('updateSlug', $event)" /></label><div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium text-gray-700">Группа<UiSelect :model-value="form.attribute_group_id === null ? '' : String(form.attribute_group_id)" class="mt-1.5 w-full font-normal" accessible-name="Группа характеристики" :options="groupOptions" @update:model-value="form.attribute_group_id = $event === '' ? null : Number($event)" /></label><label class="text-sm font-medium text-gray-700">Тип<UiSelect :model-value="form.type" class="mt-1.5 w-full font-normal" accessible-name="Тип характеристики" :options="types" @update:model-value="emit('updateType', $event as AttributeType)" /></label></div><label class="text-sm font-medium text-gray-700">Единица измерения<UiInput :model-value="form.unit ?? ''" class="mt-1.5 w-full font-normal" placeholder="мм, м², кг" @update:model-value="form.unit = $event || null" /></label><div class="grid gap-2 sm:grid-cols-2"><UiCheckbox mode="boolean" :checked="form.is_filterable" @update:checked="form.is_filterable = $event">Использовать в фильтре</UiCheckbox><UiCheckbox mode="boolean" :checked="form.is_visible_on_product_page" @update:checked="form.is_visible_on_product_page = $event">Показывать на странице товара</UiCheckbox></div><section v-if="hasOptions" class="rounded-xl border border-gray-200 bg-gray-25 p-4"><div class="flex items-center justify-between"><div><h3 class="font-semibold text-gray-800">Варианты</h3><p class="mt-0.5 text-xs text-gray-500">Код должен быть уникален в пределах характеристики.</p></div><UiButton type="button" variant="ghost" size="sm" @click="addOption">Добавить</UiButton></div><div v-for="(option, index) in form.options" :key="index" class="mt-3 grid gap-2 sm:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_auto]"><UiInput v-model="option.label" required placeholder="Название" :aria-label="`Название варианта ${index + 1}`" /><UiInput v-model="option.value" required placeholder="Код" :aria-label="`Код варианта ${index + 1}`" /><UiButton type="button" variant="danger" size="sm" :disabled="form.options.length === 1" :aria-label="`Удалить вариант ${index + 1}`" @click="removeOption(index)"><Trash2 :size="17" /></UiButton></div></section><label class="text-sm font-medium text-gray-700">Порядок сортировки<UiInput :model-value="String(form.sort_order)" class="mt-1.5" type="number" min="0" required @update:model-value="form.sort_order = Number($event)" /></label></div>
      <div class="mt-6 flex justify-end gap-3"><UiButton type="button" variant="secondary" :disabled="busy" @click="emit('close')">Отмена</UiButton><UiButton :loading="busy">Сохранить</UiButton></div>
    </form>
  </UiDialog>
</template>
