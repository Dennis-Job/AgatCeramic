<script setup lang="ts">
import { ArrowDown, ArrowUp, Menu, Plus, Trash2 } from '@lucide/vue'
import UiButton from '../../../components/ui/UiButton.vue'
import UiCard from '../../../components/ui/UiCard.vue'
import UiField from '../../../components/ui/UiField.vue'
import UiInput from '../../../components/ui/UiInput.vue'
import type { SiteLink } from '../types/appearance.types'

const props = defineProps<{
  modelValue: SiteLink[]
  title: string
  id: string
  max: number
  description?: string
  disabled?: boolean
}>()
const emit = defineEmits<{ 'update:modelValue': [value: SiteLink[]] }>()
function move(index: number, offset: number): void {
  const items = [...props.modelValue]
  const next = index + offset
  if (props.disabled || next < 0 || next >= items.length) return
  ;[items[index], items[next]] = [items[next]!, items[index]!]
  emit('update:modelValue', items)
}
function add(): void {
  if (props.disabled || props.modelValue.length >= props.max) return
  emit('update:modelValue', [...props.modelValue, { label: '', to: '' }])
}
</script>

<template>
  <UiCard :aria-labelledby="id">
    <template #header>
      <h3 :id="id" class="flex items-center gap-3 font-semibold text-gray-500">
        <Menu :size="20" class="shrink-0" aria-hidden="true" />{{ title }}
      </h3>
    </template>
    <p v-if="description" class="mb-3 text-xs text-gray-500">
      {{ description }}
    </p>
    <p v-if="!modelValue.length" class="text-sm text-gray-500" role="status">
      Ссылок пока нет. Пустая колонка на сайте не отображается.
    </p>
    <ol class="divide-y divide-gray-100">
      <li
        v-for="(link, index) in modelValue"
        :key="index"
        class="space-y-2 py-3 first:pt-0"
        :aria-label="`Ссылка ${index + 1}`"
      >
        <div class="flex flex-wrap items-center justify-between gap-2">
          <span class="text-xs text-gray-500">Ссылка {{ index + 1 }}</span>
          <div class="flex gap-1">
            <UiButton
              variant="surface"
              size="sm"
              :disabled="disabled || index === 0"
              :aria-label="`Поднять ссылку ${index + 1}`"
              @click="move(index, -1)"
              ><ArrowUp :size="16" aria-hidden="true"
            /></UiButton>
            <UiButton
              variant="surface"
              size="sm"
              :disabled="disabled || index === modelValue.length - 1"
              :aria-label="`Опустить ссылку ${index + 1}`"
              @click="move(index, 1)"
              ><ArrowDown :size="16" aria-hidden="true"
            /></UiButton>
            <UiButton
              variant="danger-ghost"
              size="sm"
              :disabled="disabled"
              :aria-label="`Удалить ссылку ${index + 1}`"
              @click="
                emit(
                  'update:modelValue',
                  modelValue.filter((_, position) => position !== index),
                )
              "
              ><Trash2 :size="16" aria-hidden="true"
            /></UiButton>
          </div>
        </div>
        <div class="grid min-w-0 gap-3 sm:grid-cols-2">
          <UiField label="Название"
            ><UiInput
              v-model="link.label"
              :disabled="disabled"
              :maxlength="255"
          /></UiField>
          <UiField label="Ссылка" help="Внутренний путь /... или HTTPS-адрес."
            ><UiInput v-model="link.to" :disabled="disabled" :maxlength="2048"
          /></UiField>
        </div>
      </li>
    </ol>
    <UiButton
      variant="primary-ghost"
      size="sm"
      class="mt-3"
      :disabled="disabled || modelValue.length >= max"
      @click="add"
      ><Plus :size="16" aria-hidden="true" />Добавить ссылку</UiButton
    >
    <p class="mt-2 text-xs text-gray-500">
      {{ modelValue.length }} из {{ max }} ссылок
    </p>
  </UiCard>
</template>
