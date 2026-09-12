<script setup lang="ts">
import { X } from '@lucide/vue'
import UiAlert from '../../../components/ui/UiAlert.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiDialog from '../../../components/ui/UiDialog.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import UiTextarea from '../../../components/ui/UiTextarea.vue'
import type { AttributeGroupPayload } from '../types/attributeGroup.types'
import { slugPattern } from '../../catalog/validation/slug'
defineProps<{ open: boolean; title: string; busy: boolean; error: string; form: AttributeGroupPayload }>()
const emit = defineEmits<{ close: []; submit: []; updateName: [value: string]; updateSlug: [value: string] }>()
</script>
<template><UiDialog :open="open" labelledby="attribute-group-dialog-title" describedby="attribute-group-dialog-description" :close-disabled="busy" panel-class="w-full max-w-xl" @close="emit('close')"><form class="max-h-[90vh] w-full overflow-y-auto rounded-xl bg-white p-6 shadow-xl" @submit.prevent="emit('submit')"><div class="flex items-start justify-between gap-4"><div><h2 id="attribute-group-dialog-title" class="text-lg font-bold text-gray-900">{{ title }}</h2><p id="attribute-group-dialog-description" class="mt-1 text-sm text-gray-500">Сгруппируйте характеристики для карточек товаров.</p></div><UiButton type="button" variant="ghost" size="sm" aria-label="Закрыть окно группы характеристик" :disabled="busy" @click="emit('close')"><X :size="20" /></UiButton></div><UiAlert v-if="error" class="mt-4">{{ error }}</UiAlert><div class="mt-6 grid gap-4"><label class="text-sm font-medium text-gray-700">Название<UiInput :model-value="form.name" class="mt-1.5" data-autofocus required @update:model-value="emit('updateName', $event)" /></label><label class="text-sm font-medium text-gray-700">Технический код (slug)<UiInput :model-value="form.slug" class="mt-1.5" required :pattern="slugPattern" @update:model-value="emit('updateSlug', $event)" /></label><label class="text-sm font-medium text-gray-700">Описание<UiTextarea v-model="form.description" class="mt-1.5 min-h-24 font-normal" /></label><label class="text-sm font-medium text-gray-700">Порядок сортировки<UiInput :model-value="String(form.sort_order)" class="mt-1.5" type="number" min="0" required @update:model-value="form.sort_order = Number($event)" /></label></div><div class="mt-6 flex flex-wrap justify-end gap-3"><UiButton type="button" variant="ghost" :disabled="busy" @click="emit('close')">Отмена</UiButton><UiButton :loading="busy">Сохранить</UiButton></div></form></UiDialog></template>
