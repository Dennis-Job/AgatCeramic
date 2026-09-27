<script setup lang="ts">
import { computed } from 'vue'
import UiSelect from '../../../components/ui/UiSelect.vue'
import UiCheckbox from '../../../components/ui/UiCheckbox.vue'
import UiButton from '../../../components/ui/UiButton.vue'
import { useMediaOptions } from '../composables/useMediaOptions'
import type { Media } from '../types/media.types'
import MediaImagePreview from './MediaImagePreview.vue'

const props = defineProps<{
  kind: Media['kind']
  label: string
  modelValue: number | null | number[]
  disabled?: boolean
}>()
const emit = defineEmits<{
  'update:modelValue': [value: number | null | number[]]
}>()
const { options, loading, error, load } = useMediaOptions(props.kind)
const selected = computed(() =>
  Array.isArray(props.modelValue) ? props.modelValue : [],
)

function toggle(id: number, checked: boolean) {
  emit(
    'update:modelValue',
    checked
      ? [...selected.value, id]
      : selected.value.filter((value) => value !== id),
  )
}
</script>

<template>
  <div class="min-w-0">
    <p class="text-sm font-medium text-gray-700">{{ label }}</p>
    <p v-if="loading" class="mt-1 text-sm text-gray-500" role="status">
      Загрузка файлов…
    </p>
    <div v-else-if="error" class="mt-1">
      <p class="text-sm text-error-600" role="alert">{{ error }}</p>
      <UiButton
        type="button"
        variant="secondary"
        size="sm"
        class="mt-2"
        @click="load"
        >Повторить загрузку</UiButton
      >
    </div>
    <template v-else-if="kind === 'image'">
      <UiSelect
        :model-value="modelValue === null ? '' : String(modelValue)"
        :options="[
          { label: 'Без изображения', value: '' },
          ...options.map((item) => ({
            label: `${item.title} (#${item.id})`,
            value: String(item.id),
          })),
        ]"
        :accessible-name="label"
        searchable
        teleport-menu
        :disabled="disabled"
        @update:model-value="
          emit('update:modelValue', $event ? Number($event) : null)
        "
      />
      <MediaImagePreview
        v-if="options.find((item) => item.id === modelValue)"
        :url="
          options.find((item) => item.id === modelValue)?.thumbnail_url ||
          options.find((item) => item.id === modelValue)?.url ||
          null
        "
        :alt="options.find((item) => item.id === modelValue)?.alt || label"
        class="mt-2 h-20 w-20 max-w-full"
      />
    </template>
    <div
      v-else
      class="mt-2 max-h-44 space-y-2 overflow-y-auto rounded-lg border border-gray-200 p-3"
    >
      <p v-if="!options.length" class="text-sm text-gray-500" role="status">
        Документов пока нет.
      </p>
      <div
        v-for="item in options"
        :key="item.id"
        class="flex flex-wrap items-center gap-2"
      >
        <UiCheckbox
          mode="boolean"
          :checked="selected.includes(item.id)"
          :disabled="disabled"
          :accessible-name="`${item.title}, файл ${item.id}`"
          class="min-w-0 flex-1"
          @update:checked="toggle(item.id, $event)"
          >{{ item.title }} · #{{ item.id }}</UiCheckbox
        >
        <a
          :href="item.url"
          target="_blank"
          rel="noopener noreferrer"
          :aria-label="`Открыть документ ${item.title}, файл ${item.id}`"
          class="text-sm text-primary-700 underline focus-visible:outline-2 focus-visible:outline-primary-500"
          >Открыть</a
        >
      </div>
    </div>
    <p class="mt-1 text-xs text-gray-500">
      Загрузка новых файлов доступна в разделе «Медиатека».
    </p>
  </div>
</template>
