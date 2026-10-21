<script setup lang="ts">
import {
  editPhoneInput,
  formatPhoneInput,
  normalizePhoneInput,
  phoneInputCaret,
} from '~/utils/phone'

const props = defineProps<{
  label: string
  type?: 'text' | 'tel' | 'email'
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
const display = computed(() =>
  isPhone.value ? formatPhoneInput(model.value) : model.value,
)

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
  } else model.value = target.value
}

onMounted(validatePhone)
watch([model, () => props.required], validatePhone)
</script>

<template>
  <div class="ui-text-field">
    <label :for="id">{{ label }}</label>
    <input
      :id="id"
      ref="input"
      :value="display"
      :type="type || 'text'"
      :autocomplete="autocomplete"
      :required="required"
      :maxlength="isPhone ? undefined : maxlength"
      :pattern="isPhone ? undefined : pattern"
      :inputmode="isPhone ? 'tel' : undefined"
      :autofocus="autofocus"
      :disabled="disabled"
      :readonly="readonly"
      @beforeinput="beforeInput"
      @paste="paste"
      @input="onInput"
    />
  </div>
</template>

<style scoped>
.ui-text-field {
  display: grid;
  gap: 10px;
  min-width: 0;
}
label {
  font-size: 13px;
}
input {
  width: 100%;
  min-width: 0;
  min-height: 48px;
  border: 1px solid var(--field-border-color, var(--color-line));
  border-radius: 0;
  padding: 12px;
  background: var(--color-bg);
  color: var(--color-ink);
  font-size: 16px;
}
input:disabled {
  opacity: 0.55;
}
</style>
