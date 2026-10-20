<script setup lang="ts">
import { Search, X } from '@lucide/vue'
import { computed, onMounted, ref, useAttrs, watch } from 'vue'
import {
  commitMoneyInput,
  formatMoneyInput,
  moneyInputCaret,
  normalizeMoneyInput,
} from '../../utils/moneyInput'
import {
  editPhoneInput,
  formatPhoneInput,
  normalizePhoneInput,
  phoneInputCaret,
  phoneNationalDigits,
} from '../../utils/phone'
defineOptions({ inheritAttrs: false })
const props = withDefaults(
  defineProps<{
    modelValue?: string
    type?: string
    searchable?: boolean
    money?: boolean
  }>(),
  { modelValue: '', type: 'text', searchable: false, money: false },
)
const emit = defineEmits<{ 'update:modelValue': [value: string] }>()
const attrs = useAttrs()
const input = ref<HTMLInputElement | null>(null)
const displayValue = computed(() =>
  props.type === 'tel'
    ? formatPhoneInput(props.modelValue)
    : props.money
      ? formatMoneyInput(props.modelValue)
      : props.modelValue,
)
const hasValue = computed(() => props.modelValue.length > 0)
const isDisabled = computed(
  () => attrs.disabled !== undefined && attrs.disabled !== false,
)
const isReadonly = computed(
  () => attrs.readonly !== undefined && attrs.readonly !== false,
)
const containerClass = computed(() => attrs.class)
const inputAttrs = computed(() => {
  const attributes = { ...attrs }
  delete attributes.class
  if (props.money) {
    delete attributes.min
    delete attributes.max
    delete attributes.step
    attributes.inputmode = 'decimal'
  }
  if (props.type === 'tel') {
    delete attributes.maxlength
    attributes.inputmode = 'tel'
  }
  return attributes
})

function validatePhone(value: string): void {
  if (props.type !== 'tel' || !input.value) return
  const raw = normalizePhoneInput(value)
  const required = attrs.required !== undefined && attrs.required !== false
  input.value.setCustomValidity(
    raw === null || (raw && !/^\+7\d{10}$/.test(raw)) || (!raw && required)
      ? 'Введите 10 цифр номера телефона после +7.'
      : '',
  )
}

function applyPhoneEdit(
  target: HTMLInputElement,
  inputType: string,
  data = '',
): void {
  const previous = normalizePhoneInput(target.value) ?? target.value
  const result = editPhoneInput(
    target.value,
    target.selectionStart ?? 0,
    target.selectionEnd ?? 0,
    inputType,
    data,
  )
  target.value = result.display
  target.setSelectionRange(result.caret, result.caret)
  if (result.value !== previous) updateValue(result.value)
  validatePhone(result.value)
}

function handlePaste(event: ClipboardEvent): void {
  if (props.type !== 'tel') return
  event.preventDefault()
  if (isDisabled.value || isReadonly.value || !event.clipboardData) return
  applyPhoneEdit(
    event.target as HTMLInputElement,
    'insertFromPaste',
    event.clipboardData.getData('text'),
  )
}

function validateMoney(value: string): void {
  if (!props.money || !input.value) return
  const raw = normalizeMoneyInput(value)
  let message = ''
  if (raw === null || (raw && !Number.isFinite(Number(raw)))) {
    message = 'Введите денежную сумму.'
  } else if (raw) {
    const amount = Number(raw)
    if (attrs.min !== undefined && amount < Number(attrs.min)) {
      message = `Сумма должна быть не меньше ${formatMoneyInput(String(attrs.min))}.`
    } else if (attrs.max !== undefined && amount > Number(attrs.max)) {
      message = `Сумма должна быть не больше ${formatMoneyInput(String(attrs.max))}.`
    }
  }
  input.value.setCustomValidity(message)
}

function handleInput(event: Event): void {
  const target = event.target as HTMLInputElement
  if (props.type === 'tel') {
    if (isDisabled.value || isReadonly.value) {
      target.value = displayValue.value
      return
    }
    const prefix = target.value.slice(
      0,
      target.selectionStart ?? target.value.length,
    )
    const raw = normalizePhoneInput(target.value)
    if (raw === null) target.value = displayValue.value
    else {
      target.value = formatPhoneInput(raw)
      updateValue(raw)
      validatePhone(raw)
    }
    const caret = phoneInputCaret(
      target.value,
      phoneNationalDigits(prefix).length,
    )
    target.setSelectionRange(caret, caret)
    return
  }
  if (!props.money) return updateValue(target.value)
  if (isDisabled.value || isReadonly.value) return
  const caret = target.selectionStart ?? target.value.length
  const prefix = target.value.slice(0, caret)
  const normalizedPrefix = normalizeMoneyInput(prefix) ?? prefix
  const meaningfulCharacters = (normalizedPrefix.match(/[\d.,]/g) ?? []).length
  const raw = normalizeMoneyInput(target.value)
  if (raw === null) {
    target.value = displayValue.value
  } else {
    target.value = formatMoneyInput(raw)
    updateValue(raw)
    validateMoney(raw)
  }
  const nextCaret = moneyInputCaret(target.value, meaningfulCharacters)
  target.setSelectionRange(nextCaret, nextCaret)
}

function handleBeforeInput(event: Event): void {
  if (props.type === 'tel') {
    const phoneEvent = event as InputEvent
    if (
      ![
        'insertText',
        'insertFromPaste',
        'insertReplacementText',
        'deleteContentBackward',
        'deleteContentForward',
        'deleteByCut',
      ].includes(phoneEvent.inputType)
    )
      return
    event.preventDefault()
    if (isDisabled.value || isReadonly.value) return
    // Paste is handled by the clipboard event, which supplies complete clipboard data.
    if (phoneEvent.inputType === 'insertFromPaste' && phoneEvent.data === null)
      return
    applyPhoneEdit(
      event.target as HTMLInputElement,
      phoneEvent.inputType,
      phoneEvent.data ?? '',
    )
    return
  }
  if (!props.money) return
  const target = event.target as HTMLInputElement
  const start = target.selectionStart ?? 0
  if (start !== target.selectionEnd) return
  const inputType = (event as InputEvent).inputType
  if (
    inputType === 'deleteContentBackward' &&
    /\s/.test(target.value[start - 1] ?? '')
  ) {
    target.setSelectionRange(start - 1, start - 1)
  } else if (
    inputType === 'deleteContentForward' &&
    /\s/.test(target.value[start] ?? '')
  ) {
    target.setSelectionRange(start + 1, start + 1)
  }
}

function commitMoney(): void {
  if (!props.money || !input.value || isDisabled.value || isReadonly.value)
    return
  const raw = commitMoneyInput(input.value.value)
  if (raw === null) return
  input.value.value = formatMoneyInput(raw)
  if (raw !== props.modelValue) updateValue(raw)
  validateMoney(raw)
}

function validate(value: string): void {
  validateMoney(value)
  validatePhone(value)
}
onMounted(() => validate(props.modelValue))
watch(() => props.modelValue, validate)

function updateValue(value: string): void {
  if (!isDisabled.value) emit('update:modelValue', value)
}

function clear(): void {
  if ((props.money || props.type === 'tel') && isReadonly.value) return
  if (!isDisabled.value) {
    emit('update:modelValue', '')
    validateMoney('')
    validatePhone('')
    if (props.type === 'tel' && input.value) input.value.value = '+7'
  }
}
</script>
<template>
  <div
    :class="[
      containerClass,
      isDisabled
        ? 'cursor-not-allowed border-gray-200 bg-gray-50'
        : 'border-gray-300 bg-white admin-control-focus',
    ]"
    class="flex min-h-[var(--admin-control-height-md)] w-full min-w-0 items-center gap-2 rounded-lg border px-3 shadow-input transition"
  >
    <Search
      v-if="searchable"
      :size="17"
      class="shrink-0 text-gray-500"
      aria-hidden="true"
    />
    <input
      ref="input"
      data-composite-control
      v-bind="inputAttrs"
      :value="displayValue"
      :type="money ? 'text' : type"
      class="min-w-0 flex-1 bg-transparent py-1.5 text-sm text-gray-500 outline-none placeholder:text-gray-500 disabled:cursor-not-allowed disabled:text-gray-500"
      @beforeinput="handleBeforeInput"
      @input="handleInput"
      @paste="handlePaste"
      @blur="commitMoney"
      @keydown.enter="commitMoney"
    />
    <button
      v-if="hasValue"
      type="button"
      class="grid h-5 w-5 shrink-0 place-items-center rounded text-gray-500 transition hover:bg-primary-100 hover:text-gray-500 admin-focus disabled:cursor-not-allowed disabled:bg-transparent disabled:text-gray-500 disabled:hover:bg-transparent disabled:hover:text-gray-500"
      aria-label="Очистить поле"
      :disabled="isDisabled || ((money || type === 'tel') && isReadonly)"
      @click="clear"
    >
      <X :size="16" aria-hidden="true" />
    </button>
  </div>
</template>
