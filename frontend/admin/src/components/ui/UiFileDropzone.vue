<script setup lang="ts">
import { computed, ref, useId, watch } from 'vue'
import { File } from '@lucide/vue'
import UiButton from './UiButton.vue'

const props = withDefaults(
  defineProps<{
    label: string
    description: string
    formatLabel: string
    dropLabel?: string
    iconTone?: 'blue' | 'green' | 'yellow'
    descriptionId?: string
    disabled?: boolean
  }>(),
  {
    dropLabel: 'Отпустите файл для добавления',
    iconTone: 'blue',
    descriptionId: '',
    disabled: false,
  },
)
const emit = defineEmits<{ choose: []; files: [files: FileList] }>()
const dragDepth = ref(0)
const labelId = useId()
const helpId = useId()
const describedBy = computed(() =>
  [helpId, props.descriptionId].filter(Boolean).join(' '),
)

function dragEnter(event: DragEvent) {
  if (!event.dataTransfer?.types.includes('Files')) return
  event.preventDefault()
  if (!props.disabled) dragDepth.value++
}
function dragOver(event: DragEvent) {
  if (!event.dataTransfer?.types.includes('Files')) return
  event.preventDefault()
  event.dataTransfer.dropEffect = props.disabled ? 'none' : 'copy'
}
function dropFiles(event: DragEvent) {
  dragDepth.value = 0
  if (!event.dataTransfer?.types.includes('Files')) return
  event.preventDefault()
  if (!props.disabled && event.dataTransfer.files.length)
    emit('files', event.dataTransfer.files)
}
watch(
  () => props.disabled,
  (disabled) => {
    if (disabled) dragDepth.value = 0
  },
)
</script>

<template>
  <UiButton
    type="button"
    variant="surface"
    class="ui-file-dropzone"
    :class="{ 'ui-file-dropzone-active': dragDepth > 0 && !disabled }"
    :disabled="disabled"
    :aria-labelledby="labelId"
    :aria-describedby="describedBy"
    @click="!disabled && emit('choose')"
    @dragenter="dragEnter"
    @dragover="dragOver"
    @dragleave="dragDepth = Math.max(0, dragDepth - 1)"
    @drop="dropFiles"
  >
    <span
      class="ui-file-dropzone-icon"
      :class="{
        'ui-file-dropzone-icon-green': iconTone === 'green',
        'ui-file-dropzone-icon-yellow': iconTone === 'yellow',
      }"
      aria-hidden="true"
    >
      <slot name="icon"><File :size="44" :stroke-width="1.4" /></slot>
      <span
        class="ui-file-dropzone-badge"
        :class="{ 'ui-file-dropzone-badge-yellow': iconTone === 'yellow' }"
        >{{ formatLabel }}</span
      >
    </span>
    <span class="min-w-0 flex-1 text-left font-normal">
      <span
        :id="labelId"
        class="block text-sm font-semibold text-gray-500 sm:text-base"
        >{{ dragDepth > 0 && !disabled ? dropLabel : label }}</span
      >
      <span :id="helpId" class="mt-1 block text-xs text-gray-500 sm:text-sm">{{
        description
      }}</span>
    </span>
  </UiButton>
</template>

<style scoped>
.ui-file-dropzone {
  width: 100%;
  display: flex;
  justify-content: flex-start;
  gap: var(--admin-spacing-4);
  padding: var(--admin-spacing-5);
  border: var(--admin-spacing-1) solid var(--color-gray-50);
  border-radius: var(--admin-radius-2xl);
  background: var(--color-white);
}
.ui-file-dropzone:hover:not(:disabled),
.ui-file-dropzone:focus-visible,
.ui-file-dropzone-active {
  border-color: var(--color-primary-200);
  background: var(--color-primary-25);
}
.ui-file-dropzone:disabled {
  background: var(--color-gray-50);
}
.ui-file-dropzone-icon {
  position: relative;
  display: grid;
  place-items: center;
  flex-shrink: 0;
  width: calc(var(--admin-spacing-4) * 3);
  height: calc(var(--admin-spacing-4) * 3);
  color: var(--color-gray-500);
}
.ui-file-dropzone-icon :deep(svg) {
  fill: var(--color-gray-100);
  transform: rotate(-6deg);
}
.ui-file-dropzone-badge {
  position: absolute;
  left: 0;
  bottom: var(--admin-spacing-1);
  padding: var(--admin-spacing-1) var(--admin-spacing-2);
  border-radius: var(--admin-radius-lg);
  background: var(--color-primary-100);
  color: var(--color-primary-700);
  box-shadow: var(--admin-shadow-sm);
  font-size: var(--text-xs);
  font-weight: 700;
}
.ui-file-dropzone-icon-green {
  color: var(--color-green-500);
}
.ui-file-dropzone-icon-green :deep(svg) {
  fill: var(--color-success-50);
}
.ui-file-dropzone-icon-green .ui-file-dropzone-badge {
  background: var(--color-green-500);
  color: var(--color-white);
}
.ui-file-dropzone-icon-yellow {
  color: var(--color-warning-500);
}
.ui-file-dropzone-icon-yellow :deep(svg) {
  fill: var(--color-warning-100);
}
.ui-file-dropzone-badge-yellow {
  background: var(--color-warning-300);
  color: var(--color-warning-900);
}
@media (max-width: 639px) {
  .ui-file-dropzone {
    gap: var(--admin-spacing-3);
    padding: var(--admin-spacing-3);
  }
}
</style>
