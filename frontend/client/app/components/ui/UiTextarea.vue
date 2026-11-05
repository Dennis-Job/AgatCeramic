<script setup lang="ts">
defineProps<{
  label: string
  surface?: 'white'
  required?: boolean
  maxlength?: number
  disabled?: boolean
  readonly?: boolean
}>()
const model = defineModel<string>({ required: true })
const id = useId()
</script>

<template>
  <div class="ui-textarea" :class="{ 'ui-field--white': surface === 'white' }">
    <label :for="id" class="ui-field-label">{{ label }}</label>
    <textarea
      :id="id"
      v-model="model"
      :required="required"
      :maxlength="maxlength"
      :disabled="disabled"
      :readonly="readonly"
      rows="5"
    />
  </div>
</template>

<style scoped>
.ui-textarea {
  display: grid;
  gap: 8px;
  min-width: 0;
}
textarea {
  width: 100%;
  min-width: 0;
  resize: vertical;
  border: 1px solid var(--field-border-color, var(--color-line));
  border-radius: 0;
  padding: 12px;
  background: var(--field-background, var(--color-bg));
  color: var(--color-ink);
  font: inherit;
  font-size: 16px;
  line-height: 1.7;
}
textarea:disabled {
  opacity: 0.55;
}
</style>
