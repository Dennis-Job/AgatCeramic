<script setup lang="ts">
import { computed, nextTick, ref, useId, watch } from 'vue'
import { CalendarDays, ChevronLeft, ChevronRight } from '@lucide/vue'
import UiPopover from './UiPopover.vue'
import UiButton from './UiButton.vue'
import UiInput from './UiInput.vue'
import { currentMoscowMonth, formatMonth } from '../../utils/month'

const props = withDefaults(
  defineProps<{
    modelValue: string
    accessibleName?: string
    disabled?: boolean
  }>(),
  {
    accessibleName: 'Месяц',
    disabled: false,
  },
)
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const open = ref(false)
const triggerButton = ref<InstanceType<typeof UiButton> | null>(null)
const year = ref(props.modelValue.slice(0, 4))
const grid = ref<HTMLElement | null>(null)
const yearHelpId = `month-year-help-${useId()}`
const validYear = computed(
  () =>
    /^\d{4}$/.test(year.value) &&
    Number(year.value) >= 1900 &&
    Number(year.value) <= 9998,
)
const months = Array.from({ length: 12 }, (_, index) => {
  const name = new Intl.DateTimeFormat('ru-RU', {
    month: 'long',
    timeZone: 'UTC',
  }).format(new Date(Date.UTC(2020, index, 1)))
  return {
    number: String(index + 1).padStart(2, '0'),
    label: name[0]!.toUpperCase() + name.slice(1),
  }
})
watch(open, async (value) => {
  if (value) year.value = props.modelValue.slice(0, 4)
  else {
    await nextTick()
    if (!props.disabled && document.activeElement === document.body)
      (triggerButton.value?.$el as HTMLButtonElement | undefined)?.focus({
        preventScroll: true,
      })
  }
})
watch(
  () => props.disabled,
  (value) => {
    if (value) open.value = false
  },
)
function changeYear(offset: number) {
  if (!validYear.value) return
  year.value = String(
    Math.min(9998, Math.max(1900, Number(year.value) + offset)),
  )
}
function setOpen(value: boolean) {
  if (!props.disabled) open.value = value
}
function choose(month: string, close: () => void) {
  if (!validYear.value || props.disabled) return
  emit('update:modelValue', `${year.value.padStart(4, '0')}-${month}`)
  close()
}
function chooseCurrent(close: () => void) {
  if (props.disabled) return
  emit('update:modelValue', currentMoscowMonth())
  close()
}
function inputKeydown(event: KeyboardEvent) {
  if (
    ['ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown', 'Home', 'End'].includes(
      event.key,
    )
  )
    event.stopPropagation()
}
async function navigate(event: KeyboardEvent) {
  const keys: Record<string, number> = {
    ArrowLeft: -1,
    ArrowRight: 1,
    ArrowUp: -3,
    ArrowDown: 3,
  }
  if (
    !(event.key in keys) &&
    !['Home', 'End', 'PageUp', 'PageDown'].includes(event.key)
  )
    return
  event.preventDefault()
  event.stopPropagation()
  const buttons = Array.from(
    grid.value?.querySelectorAll<HTMLButtonElement>('button') ?? [],
  )
  const index = buttons.indexOf(event.target as HTMLButtonElement)
  if (index < 0) return
  if (event.key === 'PageUp' || event.key === 'PageDown') {
    changeYear(event.key === 'PageUp' ? -1 : 1)
    await nextTick()
    buttons[index]?.focus()
    return
  }
  const next =
    event.key === 'Home'
      ? 0
      : event.key === 'End'
        ? 11
        : (index + keys[event.key]! + 12) % 12
  buttons[next]?.focus()
}
</script>

<template>
  <UiPopover
    :open="open"
    :label="`${accessibleName}: выбор месяца`"
    align="end"
    teleport
    @update:open="setOpen"
  >
    <template #trigger="{ trigger }">
      <UiButton
        ref="triggerButton"
        v-bind="trigger"
        variant="secondary"
        :disabled="disabled"
        :aria-label="accessibleName"
      >
        <CalendarDays :size="18" aria-hidden="true" /><span
          class="capitalize"
          >{{ formatMonth(modelValue) }}</span
        >
      </UiButton>
    </template>
    <template #default="{ close }">
      <div class="mb-4 flex items-center gap-3">
        <UiButton
          variant="neutral-ghost"
          size="sm"
          aria-label="Предыдущий год"
          :disabled="!validYear || Number(year) <= 1900"
          @click="changeYear(-1)"
          ><ChevronLeft :size="18" aria-hidden="true"
        /></UiButton>
        <UiInput
          v-model="year"
          type="number"
          min="1900"
          max="9998"
          step="1"
          aria-label="Год"
          :aria-invalid="!validYear"
          :aria-describedby="validYear ? undefined : yearHelpId"
          @keydown="inputKeydown"
        />
        <UiButton
          variant="neutral-ghost"
          size="sm"
          aria-label="Следующий год"
          :disabled="!validYear || Number(year) >= 9998"
          @click="changeYear(1)"
          ><ChevronRight :size="18" aria-hidden="true"
        /></UiButton>
      </div>
      <p
        v-if="!validYear"
        :id="yearHelpId"
        role="alert"
        class="mb-3 text-xs text-error-500"
      >
        Введите год от 1900 до 9998.
      </p>
      <div ref="grid" class="grid grid-cols-3 gap-1" @keydown="navigate">
        <UiButton
          v-for="month in months"
          :key="month.number"
          size="sm"
          :variant="
            modelValue === `${year.padStart(4, '0')}-${month.number}`
              ? 'primary'
              : 'neutral-ghost'
          "
          :aria-pressed="
            modelValue === `${year.padStart(4, '0')}-${month.number}`
          "
          :disabled="!validYear || disabled"
          @click="choose(month.number, close)"
          >{{ month.label }}</UiButton
        >
      </div>
      <UiButton
        variant="primary-ghost"
        size="sm"
        class="mt-3"
        @click="chooseCurrent(close)"
        >Текущий месяц</UiButton
      >
    </template>
  </UiPopover>
</template>
