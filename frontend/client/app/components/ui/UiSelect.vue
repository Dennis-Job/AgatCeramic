<script setup lang="ts">
defineProps<{
  label: string
  options: { value: string; label: string }[]
  disabled?: boolean
}>()
const model = defineModel<string>({ required: true })
const id = useId()
</script>

<template>
  <div class="ui-select">
    <label :for="id" class="ui-field-label">{{ label }}</label>
    <select :id="id" v-model="model" :disabled="disabled">
      <option
        v-for="option in options"
        :key="option.value"
        :value="option.value"
      >
        {{ option.label }}
      </option>
    </select>
  </div>
</template>

<style scoped>
.ui-select {
  display: grid;
  gap: 8px;
  min-width: 0;
}
select {
  width: 100%;
  min-width: 0;
  min-height: 48px;
  border: 1px solid var(--color-line);
  border-radius: 0;
  padding: 12px;
  background: var(--color-bg);
  color: var(--color-ink);
  font: inherit;
  font-size: 14px;
  text-overflow: ellipsis;
}
select:disabled {
  opacity: 0.55;
}
</style>
