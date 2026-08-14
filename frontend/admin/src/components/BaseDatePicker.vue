<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { CalendarDays, ChevronLeft, ChevronRight, X } from '@lucide/vue'

const props = withDefaults(defineProps<{
  modelValue: string
  placeholder?: string
  accessibleName?: string
}>(), {
  placeholder: 'дд.мм.гггг',
  accessibleName: 'Дата',
})

const emit = defineEmits<{ 'update:modelValue': [value: string] }>()

const root = ref<HTMLElement | null>(null)
const isOpen = ref(false)
const currentMonth = ref(dateFromIso(props.modelValue) ?? new Date())
const typedValue = ref(formatDate(dateFromIso(props.modelValue)))
const weekdays = ['Пн', 'Вт', 'Ср', 'Чт', 'Пт', 'Сб', 'Вс']

const monthLabel = computed(() => new Intl.DateTimeFormat('ru-RU', { month: 'long', year: 'numeric' }).format(currentMonth.value))
const calendarDays = computed(() => {
  const start = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth(), 1)
  const offset = (start.getDay() + 6) % 7
  const daysInMonth = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() + 1, 0).getDate()

  return Array.from({ length: 42 }, (_, index) => {
    const day = index - offset + 1
    return day > 0 && day <= daysInMonth ? new Date(start.getFullYear(), start.getMonth(), day) : null
  })
})

function dateFromIso(value: string): Date | null {
  if (!/^\d{4}-\d{2}-\d{2}$/.test(value)) return null
  const [year, month, day] = value.split('-').map(Number)
  const date = new Date(year, month - 1, day)
  return date.getFullYear() === year && date.getMonth() === month - 1 && date.getDate() === day ? date : null
}

function toIso(date: Date): string {
  return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

function formatDate(date: Date | null): string {
  return date ? new Intl.DateTimeFormat('ru-RU').format(date) : ''
}

function isoFromDisplay(value: string): string | null {
  const match = value.trim().match(/^(\d{2})\.(\d{2})\.(\d{4})$/)
  if (!match) return null

  const [, day, month, year] = match
  const date = new Date(Number(year), Number(month) - 1, Number(day))

  return date.getFullYear() === Number(year) && date.getMonth() === Number(month) - 1 && date.getDate() === Number(day)
    ? toIso(date)
    : null
}

function isSameDate(left: Date | null, right: Date): boolean {
  return left?.getFullYear() === right.getFullYear() && left.getMonth() === right.getMonth() && left.getDate() === right.getDate()
}

function open(): void {
  const selected = dateFromIso(props.modelValue)
  if (selected) currentMonth.value = selected
  isOpen.value = true
}

function select(date: Date): void {
  emit('update:modelValue', toIso(date))
  typedValue.value = formatDate(date)
  isOpen.value = false
}

function clear(): void {
  emit('update:modelValue', '')
  typedValue.value = ''
  isOpen.value = false
}

function formatTypedDate(value: string): string {
  const digits = value.replace(/\D/g, '').slice(0, 8)
  const parts = [digits.slice(0, 2), digits.slice(2, 4), digits.slice(4, 8)].filter(Boolean)

  return parts.join('.')
}

function updateTypedValue(event: Event): void {
  typedValue.value = formatTypedDate((event.target as HTMLInputElement).value)

  if (typedValue.value === '') {
    emit('update:modelValue', '')
    return
  }

  const isoValue = isoFromDisplay(typedValue.value)
  if (isoValue) emit('update:modelValue', isoValue)
}

function confirmTypedValue(): void {
  const isoValue = isoFromDisplay(typedValue.value)

  if (!isoValue) {
    normalizeTypedValue()
    return
  }

  emit('update:modelValue', isoValue)
  typedValue.value = formatDate(dateFromIso(isoValue))
  isOpen.value = false
}

function normalizeTypedValue(): void {
  const isoValue = isoFromDisplay(typedValue.value)
  typedValue.value = isoValue ? formatDate(dateFromIso(isoValue)) : formatDate(dateFromIso(props.modelValue))
}

function changeMonth(offset: number): void {
  currentMonth.value = new Date(currentMonth.value.getFullYear(), currentMonth.value.getMonth() + offset, 1)
}

function handleOutsideClick(event: MouseEvent): void {
  if (root.value && !root.value.contains(event.target as Node)) isOpen.value = false
}

function handleKeydown(event: KeyboardEvent): void {
  if (event.key === 'Escape') isOpen.value = false
}

watch(() => props.modelValue, (value) => {
  const selected = dateFromIso(value)
  if (selected) currentMonth.value = selected
  typedValue.value = formatDate(selected)
})

onMounted(() => {
  document.addEventListener('mousedown', handleOutsideClick)
  document.addEventListener('keydown', handleKeydown)
})

onBeforeUnmount(() => {
  document.removeEventListener('mousedown', handleOutsideClick)
  document.removeEventListener('keydown', handleKeydown)
})
</script>

<template>
  <div ref="root" class="relative">
    <div class="flex h-11 items-center rounded-lg border border-gray-300 bg-white px-3 shadow-theme-xs transition focus-within:border-primary-500 focus-within:ring-3 focus-within:ring-primary-500/10">
      <CalendarDays :size="18" class="shrink-0 text-gray-400" aria-hidden="true" />
      <input :value="typedValue" type="text" inputmode="numeric" maxlength="10" class="min-w-0 flex-1 bg-transparent px-2 text-sm text-gray-700 outline-none placeholder:text-gray-400" :placeholder="placeholder" :aria-label="accessibleName" :aria-expanded="isOpen" aria-haspopup="dialog" @focus="open" @input="updateTypedValue" @keydown.enter.prevent="confirmTypedValue" @blur="normalizeTypedValue" />
      <button v-if="modelValue" type="button" class="rounded p-1 text-gray-400 transition hover:bg-gray-100 hover:text-gray-600" aria-label="Очистить дату" @click="clear">
        <X :size="16" aria-hidden="true" />
      </button>
    </div>

    <div v-if="isOpen" class="absolute z-30 mt-2 w-80 rounded-xl border border-gray-200 bg-white p-3 shadow-dropdown" role="dialog" :aria-label="`${accessibleName}: выбор даты`">
      <div class="mb-3 flex items-center justify-between px-1">
        <button type="button" class="rounded-lg p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700" aria-label="Предыдущий месяц" @click="changeMonth(-1)"><ChevronLeft :size="18" aria-hidden="true" /></button>
        <p class="capitalize text-sm font-semibold text-gray-800">{{ monthLabel }}</p>
        <button type="button" class="rounded-lg p-2 text-gray-500 transition hover:bg-gray-100 hover:text-gray-700" aria-label="Следующий месяц" @click="changeMonth(1)"><ChevronRight :size="18" aria-hidden="true" /></button>
      </div>

      <div class="grid grid-cols-7 gap-1 text-center">
        <span v-for="weekday in weekdays" :key="weekday" class="py-1 text-xs font-medium text-gray-400">{{ weekday }}</span>
        <span v-for="(date, index) in calendarDays" :key="index" class="grid h-9 place-items-center">
          <button v-if="date" type="button" class="grid h-8 w-8 place-items-center rounded-lg text-sm font-medium transition hover:bg-primary-50 hover:text-primary-700" :class="[isSameDate(dateFromIso(modelValue), date) ? 'bg-primary-600 text-white hover:bg-primary-700 hover:text-white' : 'text-gray-700', isSameDate(new Date(), date) && !isSameDate(dateFromIso(modelValue), date) ? 'ring-1 ring-primary-300' : '']" :aria-label="new Intl.DateTimeFormat('ru-RU', { dateStyle: 'long' }).format(date)" @click="select(date)">
            {{ date.getDate() }}
          </button>
        </span>
      </div>

      <div class="mt-3 flex items-center justify-between border-t border-gray-100 pt-3">
        <button type="button" class="text-sm font-medium text-primary-600 hover:text-primary-700" @click="select(new Date())">Сегодня</button>
        <button type="button" class="text-sm font-medium text-gray-500 hover:text-gray-700" @click="clear">Очистить</button>
      </div>
    </div>
  </div>
</template>
