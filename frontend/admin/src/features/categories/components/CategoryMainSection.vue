<script setup lang="ts">
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import type { CategoryPayload } from '../types/category.types'
import { slugPattern } from '../../catalog/validation/slug'

defineProps<{ form: CategoryPayload; parentOptions: { label: string; value: string }[]; updateName: (value: string) => void; updateSlug: (value: string) => void }>()
</script>
<template>
  <div class="mt-6 grid gap-4">
    <div class="grid gap-4 sm:grid-cols-2"><label class="text-sm font-medium text-gray-700">Название<UiInput :model-value="form.name" class="mt-1.5" data-autofocus required @update:model-value="updateName" /></label><label class="text-sm font-medium text-gray-700">Технический код (slug)<UiInput :model-value="form.slug" class="mt-1.5" :pattern="slugPattern" required @update:model-value="updateSlug" /></label></div>
    <label class="text-sm font-medium text-gray-700">Описание<UiTextarea v-model="form.description" class="mt-1.5 min-h-24 font-normal" /></label>
    <label class="text-sm font-medium text-gray-700">Родительская категория<UiSelect :model-value="form.parent_id === null ? '' : String(form.parent_id)" class="mt-1.5 w-full font-normal" accessible-name="Родительская категория" :options="parentOptions" @update:model-value="form.parent_id = $event === '' ? null : Number($event)" /></label>
    <div class="grid gap-4 sm:grid-cols-2"><UiCheckbox class="mt-7" mode="boolean" :checked="form.is_parent" @update:checked="form.is_parent = $event">Родительская категория</UiCheckbox><UiCheckbox class="mt-7" mode="boolean" :checked="form.is_active" @update:checked="form.is_active = $event">Категория активна</UiCheckbox></div>
    <label class="text-sm font-medium text-gray-700">Порядок сортировки<UiInput :model-value="String(form.sort_order)" class="mt-1.5" type="number" min="0" required @update:model-value="form.sort_order = Number($event)" /></label>
  </div>
</template>
