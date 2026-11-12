<script setup lang="ts">
import {
  editPhoneInput,
  formatPhoneInput,
  normalizePhoneInput,
  phoneInputCaret,
} from '~/utils/phone'

const props = defineProps<{
  label: string
  type?: 'text' | 'tel' | 'email' | 'number' | 'date'
  money?: boolean
  min?: string
  max?: string
  step?: string
  surface?: 'white'
  autocomplete?: string
  required?: boolean
  maxlength?: number
  pattern?: string
  autofocus?: boolean
  disabled?: boolean
  readonly?: boolean
}>()
const model = defineModel<string>({ required: true })
const id = useId()
const input = ref<HTMLInputElement | null>(null)
const isPhone = computed(() => props.type === 'tel')
const editingMoney = ref(false)
function canonicalMoney(value: string) {
  const plain = value.replace(/[\s\u00a0\u202f]/g, '').replace(',', '.')
  if (!/^\d+(?:\.\d{0,2})?$/.test(plain)) return null
  const [integer = '', decimals = ''] = plain.split('.')
  return integer.replace(/^0+(?=\d)/, '') + '.' + decimals.padEnd(2, '0')
}
const display = computed(() => {
  if (isPhone.value) return formatPhoneInput(model.value)
  if (!props.money || editingMoney.value || !model.value) return model.value
  const canonical = canonicalMoney(model.value)
  if (canonical === null) return model.value
  const [integer = '', decimals] = canonical.split('.')
  return integer.replace(/\B(?=(\d{3})+(?!\d))/g, '\u00a0') + ',' + decimals
})
function validateMoney() {
  if (!props.money || !input.value) return
  input.value.setCustomValidity(
    model.value && canonicalMoney(model.value) === null
      ? 'Введите положительную сумму с точностью до копеек.'
      : '',
  )
}
function onBlur() {
  if (!props.money) return
  const canonical = canonicalMoney(model.value)
  if (canonical !== null) model.value = canonical
  editingMoney.value = false
  validateMoney()
}

function validatePhone() {
  if (!isPhone.value || !input.value) return
  const raw = normalizePhoneInput(model.value)
  const empty = raw === ''
  input.value.setCustomValidity(
    empty && props.required
      ? 'Введите номер телефона.'
      : raw === null || (!empty && !/^\+7\d{10}$/.test(raw))
        ? 'Введите 10 цифр номера после +7.'
        : '',
  )
}

function applyEdit(inputType: string, data = '') {
  if (!input.value || props.disabled || props.readonly) return
  const result = editPhoneInput(
    input.value.value,
    input.value.selectionStart ?? 0,
    input.value.selectionEnd ?? 0,
    inputType,
    data,
  )
  model.value = result.value
  input.value.value = result.display
  input.value.setSelectionRange(result.caret, result.caret)
  validatePhone()
}

function beforeInput(event: Event) {
  if (!isPhone.value) return
  const edit = event as InputEvent
  if (
    edit.isComposing ||
    ![
      'insertText',
      'insertFromPaste',
      'insertReplacementText',
      'deleteContentBackward',
      'deleteContentForward',
      'deleteByCut',
    ].includes(edit.inputType)
  )
    return
  event.preventDefault()
  if (edit.inputType === 'insertFromPaste' && edit.data === null) return
  applyEdit(edit.inputType, edit.data ?? '')
}

function paste(event: ClipboardEvent) {
  if (!isPhone.value) return
  event.preventDefault()
  applyEdit('insertFromPaste', event.clipboardData?.getData('text') ?? '')
}

function onInput(event: Event) {
  const target = event.target as HTMLInputElement
  if (props.disabled || props.readonly) {
    target.value = display.value
    return
  }
  if (isPhone.value) {
    const raw = normalizePhoneInput(target.value)
    if (raw === null) target.value = display.value
    else {
      model.value = raw
      target.value = formatPhoneInput(raw)
    }
    const caret = phoneInputCaret(
      target.value,
      (raw?.replace(/\D/g, '').slice(1) ?? '').length,
    )
    target.setSelectionRange(caret, caret)
    validatePhone()
  } else {
    model.value = props.money
      ? target.value.replace(/[\s\u00a0\u202f]/g, '').replace(',', '.')
      : target.value
    validateMoney()
  }
}

onMounted(() => {
  validatePhone()
  validateMoney()
})
watch([model, () => props.required], () => {
  validatePhone()
  validateMoney()
})
</script>

<template>
  <div
    class="ui-text-field"
    :class="{ 'ui-field--white': surface === 'white' }"
  >
    <label :for="id" class="ui-field-label">{{ label }}</label>
    <input
      :id="id"
      ref="input"
      :value="display"
      :type="money ? 'text' : type || 'text'"
      :autocomplete="autocomplete"
      :required="required"
      :maxlength="isPhone ? undefined : maxlength"
      :pattern="isPhone ? undefined : pattern"
      :min="min"
      :max="max"
      :step="step"
      :inputmode="isPhone ? 'tel' : money ? 'decimal' : undefined"
      :autofocus="autofocus"
      :disabled="disabled"
      :readonly="readonly"
      @focus="editingMoney = !!money"
      @blur="onBlur"
      @beforeinput="beforeInput"
      @paste="paste"
      @input="onInput"
    />
  </div>
</template>

<style scoped>
.ui-text-field {
  display: grid;
  gap: 8px;
  min-width: 0;
}
input {
  width: 100%;
  min-width: 0;
  min-height: 48px;
  border: 1px solid var(--field-border-color, var(--color-line));
  border-radius: 0;
  padding: 12px;
  background: var(--field-background, var(--color-bg));
  color: var(--color-ink);
  font-size: 16px;
}
input:disabled {
  opacity: 0.55;
}
</style>
